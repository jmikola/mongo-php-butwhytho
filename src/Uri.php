<?php

declare(strict_types=1);

namespace MongoDB;

use InvalidArgumentException;
use RuntimeException;
//use Symfony\Component\Stopwatch\Stopwatch;

use function array_shift;
use function assert;
use function count;
use function dns_get_record;
use function explode;
use function in_array;
use function is_int;
use function is_string;
use function printf;
use function rawurldecode;
use function sprintf;
use function str_ends_with;
use function strcspn;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;

use const DNS_SRV;
use const DNS_TXT;

class Uri
{
    private bool $isSrv;
    private string|null $username;
    private string|null $password;
    private array $hosts;
    private array $options;

    public function __construct(string $uri)
    {
        // Parse scheme
        $delimAt = strpos($uri, '://');

        if ($delimAt !== false) {
            $this->parseScheme(substr($uri, 0, $delimAt));
            $afterScheme = substr($uri, $delimAt + 3);
        } else {
            throw new InvalidArgumentException(sprintf('Failed to parse scheme: %s', $uri));
        }

        // Parse userInfo
        $delimAt = strpos($afterScheme, '@');

        if ($delimAt !== false) {
            $this->parseUserInfo(substr($afterScheme, 0, $delimAt));
            $afterUserInfo = substr($afterScheme, $delimAt + 1);
        } else {
            $afterUserInfo = $afterScheme;
        }

        // Parse hostInfo
        $delimAt = strpos($afterUserInfo, '/');

        if ($delimAt !== false) {
            $hostInfo = substr($afterUserInfo, 0, $delimAt);
            $afterHostInfo = substr($afterUserInfo, $delimAt + 1);
        } else {
            $hostInfo = $afterUserInfo;
            $afterHostInfo = null;
        }

        $this->parseHostInfo($hostInfo);

        // Parse authDatabase
        $delimAt = strpos($afterHostInfo, '?');

        if ($delimAt !== false) {
            $this->parseAuthDatabase(substr($afterHostInfo, 0, $delimAt));
            $afterAuthDatabase = substr($afterHostInfo, $delimAt + 1);
        } else {
            $afterAuthDatabase = $afterHostInfo;
        }

        // Resolve SRV records
        if ($this->isSrv) {
            $this->doSrvLookup();
        }

        // Parse options (after SRV/TXT resolution)
        $this->parseOptions($afterAuthDatabase);

        $this->doValidation();
    }

    public function getHosts(): array
    {
        return $this->hosts;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(UriOption $option, mixed $default = null): mixed
    {
        return $this->options[$option->value] ?? $default;
    }

    public function getUsername(): string|null
    {
        return $this->username;
    }

    public function getPassword(): string|null
    {
        return $this->password;
    }

    private function doSrvLookup(): void
    {
        //$stopwatch = new Stopwatch(true);

        if (count($this->hosts) > 1) {
            throw new InvalidArgumentException(sprintf('SRV scheme requires one host but %d were parsed', count($this->hosts)));
        }

        $hostPort = array_shift($this->hosts);
        $host = $hostPort->getHost();
        $port = $hostPort->getPort();

        if ($port !== null) {
            throw new InvalidArgumentException(sprintf('SRV scheme prohibits a port but "%d" was parsed', $port));
        }

        if (count(explode('.', $host)) < 3) {
            throw new InvalidArgumentException(sprintf('SRV lookup requires host with at least three parts: %s', $host));
        }

        // SRV lookup
        $srvHost = '_mongodb._tcp.' . $host;

        //$stopwatch->start('srv_lookup');
        $results = dns_get_record($srvHost, DNS_SRV);
        //$stopwatch->stop('srv_lookup');

        if ($results === false) {
            throw new RuntimeException(sprintf('SRV lookup for "%s" failed', $srvHost));
        }

        if (count($results) < 1) {
            throw new RuntimeException(sprintf('SRV lookup for "%s" returned no results', $srvHost));
        }

        // Extract {domainname} from host
        $domainOffset = strrpos($host, '.', strrpos($host, '.') - strlen($host) - 1);
        $domain = substr($host, $domainOffset);

        // TODO: Enforce srvMaxHosts

        foreach ($results as $result) {
            assert($result['host'] === $srvHost);
            assert(is_string($result['target']));
            assert(is_int($result['port']));

            if (! str_ends_with($result['target'], $domain)) {
                throw new RuntimeException(sprintf('SRV result does not share parent domain "%s": %s', $domain, $result['target']));
            }

            $this->hosts[] = new HostPort($result['target'], $result['port']);
        }

        // TXT lookup
        //$stopwatch->start('txt_lookup');
        $results = dns_get_record($host, DNS_TXT);
        //$stopwatch->stop('txt_lookup');

        if ($results === false) {
            throw new RuntimeException(sprintf('TXT lookup for "%s" failed', $host));
        }

        if (count($results) > 1) {
            throw new RuntimeException(sprintf('Expected one result for TXT lookup but %d were returned', count($results)));
        }

        foreach ($results as $result) {
            assert($result['host'] === $host);
            assert(is_string($result['txt']));

            $this->parseOptionsFromTxtRecord($result['txt']);
        }

        //foreach ($stopwatch->getSectionEvents('__root__') as $event) {
        //    printf("%s\n", $event);
        //}
    }

    private function doValidation(): void
    {
        // https://github.com/mongodb/specifications/blob/master/source/server-discovery-and-monitoring/server-discovery-and-monitoring.rst#allowed-configuration-combinations
        if ($this->options[UriOption::DirectConnection->value] ?? false && count($this->hosts) > 1) {
            throw new InvalidArgumentException('directConnection URI option conflicts with multiple hosts');
        }

        // https://github.com/mongodb/specifications/blob/master/source/load-balancers/load-balancers.rst#uri-validation
        if ($this->options[UriOption::LoadBalanced->value] ?? false) {
            if ($this->options[UriOption::DirectConnection->value] ?? false) {
                throw new InvalidArgumentException('loadBalanced URI option conflicts with directConnection');
            } elseif ($this->options[UriOption::ReplicaSet->value] ?? null !== null) {
                throw new InvalidArgumentException('loadBalanced URI option conflicts with replicaSet');
            } elseif (count($this->hosts) > 1) {
                throw new InvalidArgumentException('loadBalanced URI option conflicts with multiple hosts');
            }
        }
    }

    private function parseAuthDatabase(string $authDatabase): void
    {
        if ($authDatabase === '') {
            return;
        }

        $authDatabase = rawurldecode($authDatabase);

        if (strcspn($authDatabase, '/\\ "$.') !== $authDatabase) {
            throw new InvalidArgumentException(sprintf('Decoded auth database contains invalid characters: %s', $authDatabase));
        }

        $this->authDatabase = $authDatabase;
    }

    private function parseHostInfo(string $hostInfo): void
    {
        foreach (explode(',', $hostInfo) as $hostPort) {
            $this->hosts[] = HostPort::fromHostPortString($hostPort);
        }

        if (count($this->hosts) === 0) {
            throw new InvalidArgumentException('No hosts were parsed');
        }
    }

    private function parseOptions(string $options): void
    {
        foreach (explode('&', $options) as $keyValue) {
            $delimAt = strpos($keyValue, '=');

            if ($delimAt === false) {
                throw new InvalidArgumentException(sprintf('Key/value is missing "=" delimiter: %s', $keyValue));
            }

            $key = rawurldecode(substr($keyValue, 0, $delimAt));
            $value = rawurldecode(substr($keyValue, $delimAt + 1));

            $this->setOption($key, $value);
        }
    }

    private function parseScheme(string $scheme): void
    {
        try {
            $this->isSrv = match ($scheme) {
                'mongodb' => false,
                'mongodb+srv' => true,
            };
        } catch (UnhandledMatchError) {
            throw new InvalidArgumentException(sprintf('Unsupported scheme: %s', $scheme));
        }
    }

    private function parseOptionsFromTxtRecord(string $options): void
    {
        foreach (explode('&', $options) as $keyValue) {
            $delimAt = strpos($keyValue, '=');

            if ($delimAt === false) {
                throw new RuntimeException(sprintf('Key/value is missing "=" delimiter: %s', $keyValue));
            }

            $key = strtolower(substr($keyValue, 0, $delimAt));
            $value = substr($keyValue, $delimAt + 1);

            if (! in_array($key, ['authsource', 'loadbalanced', 'replicaset'])) {
                throw new RuntimeException(sprintf('Option "%s" is not supported in TXT records', $key));
            }

            $this->setOption($key, $value);
        }
    }

    /** @todo Parse Unix domain socket */
    private function parseUserInfo(string $userInfo): void
    {
        [$username, $password] = explode(':', $userInfo, 2) + [1 => null];

        $this->username = rawurldecode($username);

        if (is_string($password)) {
            $this->password = rawurldecode($password);
        }
    }

    private function setOption(string $key, string $value): void
    {
        // TODO: Parse list options and values representing key/value pairs
        $normalizedKey = strtolower($key);

        switch ($normalizedKey) {
            // Boolean options
            case UriOption::DirectConnection:
            case UriOption::LoadBalanced:
                $this->options[$normalizedKey] = match ($value) {
                    'true', '1', 'yes', 'y', 't' => true,
                    'false', '0', '-1', 'no', 'n', 'f' => false,
                };

                // String options
            default:
                $this->options[$normalizedKey] = $value;
        }
    }
}

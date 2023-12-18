<?php

declare(strict_types=1);

namespace MongoDB;

use InvalidArgumentException;
use Stringable;

use function ctype_digit;
use function explode;
use function is_int;
use function is_string;
use function sprintf;

/** @todo Parse IPv6 and Unix domain sockets */
class HostPort implements Stringable
{
    public function __construct(private string $host, private int|null $port)
    {
        if (is_int($port) && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException(sprintf('Port must be between [1..65535]: %s', $port));
        }
    }

    public static function fromHostPortString(string $hostPort): self
    {
        [$host, $port] = explode(':', $hostPort, 2) + [1 => null];

        if (is_string($port)) {
            if (! ctype_digit($port)) {
                throw new InvalidArgumentException(sprintf('Port must be numeric: %s', $port));
            }

            $port = (int) $port;
        }

        return new self($host, $port);
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int|null
    {
        return $this->port;
    }

    public function __toString(): string
    {
        return $this->host . ($this->port !== null ? ':' . $this->port : '');
    }
}

<?php

declare(strict_types=1);

namespace MongoDB;

use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Type;
use MongoDB\BSON\UTCDateTime;

use function strtolower;

/** @see https://github.com/mongodb/specifications/blob/master/source/server-discovery-and-monitoring/server-discovery-and-monitoring.rst#serverdescription */
class ServerDescription
{
    public ServerType $type = ServerType::Unknown;
    public string $address;
    public string|null $error;
    public int|null $roundTripTime;
    public int|null $minRoundTripTime;
    public UTCDateTime|null $lastWriteDate;
    public Type|null $opTime;
    public int $minWireVersion = 0;
    public int $maxWireVersion = 0;
    public string|null $me;
    public array $hosts = [];
    public array $arbiters = [];
    public array $passives = [];
    public array $tags = [];
    public string|null $setName;
    public ObjectId|null $electionId;
    public int|null $setVersion;
    public string|null $primary;
    public int|null $lastUpdateTime;
    public int|null $logicalSessionTimeoutMinutes;
    public Document|null $topologyVersion;
    public bool|null $isCryptd;

    public static function fromLoadBalancedHost(HostPort $host): self
    {
        $sd = new ServerDescription();
        $sd->type = ServerType::LoadBalanced;
        $sd->address = strtolower((string) $host);

        return $sd;
    }

    public static function fromHost(HostPort $host): self
    {
        $sd = new ServerDescription();
        $sd->address = strtolower((string) $host);

        return $sd;
    }
}

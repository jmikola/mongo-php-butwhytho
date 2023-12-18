<?php

declare(strict_types=1);

namespace MongoDB;

use MongoDB\BSON\ObjectId;

use function assert;
use function count;

/** @see https://github.com/mongodb/specifications/blob/master/source/server-discovery-and-monitoring/server-discovery-and-monitoring.rst#topologydescription */
class TopologyDescription
{
    public TopologyType $type = TopologyType::Unknown;
    public string|null $setName;
    public ObjectId|null $maxElectionId;
    public int|null $maxSetVersion;
    public array $servers;
    public bool $isStale;
    public bool $isCompatible;
    public string|null $compatibilityError;
    public int|null $logicalSessionTimeoutMinutes;

    public static function fromUri(Uri $uri): self
    {
        $td = new TopologyDescription();

        $directConnection = $uri->getOption(UriOption::DirectConnection, false);
        $replicaSet = $uri->getOption(UriOption::ReplicaSet);
        $loadBalanced = $uri->getOption(UriOption::LoadBalanced, false);

        if ($loadBalanced) {
            assert(count($uri->getHosts()) === 1);

            $td->type = TopologyType::LoadBalanced;
            $td->isCompatible = true;
            $td->servers = [ServerDescription::fromLoadBalancedHost($uri->getHosts()[0])];

            return $td;
        }

        $td->type = match (true) {
            $directConnection => TopologyType::Single,
            $replicaSet !== null => TopologyType::ReplicaSetNoPrimary,
            default => TopologyType::Unknown,
        };

        if ($replicaSet !== null) {
            $td->setName = $replicaSet;
        }

        foreach ($uri->getHosts() as $host) {
            $td->servers[] = ServerDescription::fromHost($host);
        }
    }
}

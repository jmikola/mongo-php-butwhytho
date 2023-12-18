<?php

declare(strict_types=1);

namespace MongoDB;

/** @see https://github.com/mongodb/specifications/blob/master/source/server-discovery-and-monitoring/server-discovery-and-monitoring.rst#topologytype */
enum TopologyType
{
    case Single;
    case ReplicaSetNoPrimary;
    case ReplicaSetWithPrimary;
    case Sharded;
    case LoadBalanced;
    case Unknown;
}

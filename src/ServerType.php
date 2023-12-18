<?php

declare(strict_types=1);

namespace MongoDB;

/** @see https://github.com/mongodb/specifications/blob/master/source/server-discovery-and-monitoring/server-discovery-and-monitoring.rst#servertype */
enum ServerType
{
    case Standalone;
    case Mongos;
    case PossiblePrimary;
    case RSPrimary;
    case RSSecondary;
    case RSArbiter;
    case RSOther;
    case RSGhost;
    case LoadBalancer;
    case Unknown;
}

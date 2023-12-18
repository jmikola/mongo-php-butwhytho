<?php

declare(strict_types=1);

namespace MongoDB;

enum UriOption: string
{
    case AuthSource = 'authsource';
    case DirectConnection = 'directconnection';
    case LoadBalanced = 'loadbalanced';
    case ReplicaSet = 'replicaset';
}

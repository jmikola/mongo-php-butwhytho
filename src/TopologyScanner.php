<?php

declare(strict_types=1);

namespace MongoDB;

class TopologyScanner
{
    private TopologyDescription $topologyDescription;

    public function __construct(Uri $uri)
    {
        $this->topologyDescription = TopologyDescription::fromUri($uri);
    }
}

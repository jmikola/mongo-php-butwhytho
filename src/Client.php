<?php

declare(strict_types=1);

namespace MongoDB;

class Client
{
    private Uri $uri;

    public function __construct(string $uri)
    {
        $this->uri = new Uri($uri);
    }
}

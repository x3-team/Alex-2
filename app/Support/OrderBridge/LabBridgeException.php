<?php

namespace App\Support\OrderBridge;

use RuntimeException;

class LabBridgeException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}

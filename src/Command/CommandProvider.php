<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [new DumpCommand];
    }
}

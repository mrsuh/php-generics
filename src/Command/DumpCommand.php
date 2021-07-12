<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Command\BaseCommand;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return array(new DumpCommand);
    }
}

class DumpCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('dump-generics');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Executing');
    }
}

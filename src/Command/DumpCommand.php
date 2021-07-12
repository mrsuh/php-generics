<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Autoload\AutoloadGenerator;
use Composer\Command\BaseCommand;
use Composer\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('dump-generics');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $composer            = $this->getComposer();
        $installationManager = $composer->getInstallationManager();
        $localRepo           = $composer->getRepositoryManager()->getLocalRepository();
        $package             = $composer->getPackage();
        $config              = $composer->getConfig();

        $eventDispatcher = $composer->getEventDispatcher();

        $autoloadGenerator = new AutoloadGenerator($eventDispatcher);

        $packageMap = $autoloadGenerator->buildPackageMap($installationManager, $package, $localRepo->getCanonicalPackages());

        $autoloads = $autoloadGenerator->parseAutoloads($packageMap, $package, true);

        $filesystem = new Filesystem();

        var_dump($autoloads);

        $output->writeln('Executing');
    }
}

<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Autoload\ClassLoader;
use Composer\Command\BaseCommand;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Autoload\AutoloadGenerator;
use Mrsuh\PhpGenerics\Compiler\Compiler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

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

        $eventDispatcher = $composer->getAutoloadGenerator();
        $eventDispatcher = $composer->getEventDispatcher();

        $autoloadGenerator = new AutoloadGenerator($eventDispatcher);

        $packageMap = $autoloadGenerator->buildPackageMap($installationManager, $package, $localRepo->getCanonicalPackages());

        $autoloads = $autoloadGenerator->parseAutoloads($packageMap, $package, true);

        $filesystem = new Filesystem();

        $basePath   = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $vendorPath = $filesystem->normalizePath(realpath(realpath($config->get('vendor-dir'))));

        $classLoader = new ClassLoader();
        foreach ($autoloads['psr-4'] as $namespace => $paths) {
            $exportedPaths = [];
            foreach ($paths as $path) {
                $exportedPaths[] = $autoloadGenerator->getPathCode($filesystem, $basePath, $vendorPath, $path);
            }

            $classLoader->setPsr4($namespace, $exportedPaths);
        }

        foreach ($autoloads['psr-0'] as $namespace => $paths) {
            $exportedPaths = [];
            foreach ($paths as $path) {
                $exportedPaths[] = $autoloadGenerator->getPathCode($filesystem, $basePath, $vendorPath, $path);
            }

            $classLoader->set($namespace, $exportedPaths);
        }

        foreach ($autoloads['psr-4'] as $paths) {
            if (count($paths) !== 2) {
                continue;
            }
            $exportedPaths = [];
            foreach ($paths as $path) {
                $pathCode        = $autoloadGenerator->getPathCode($filesystem, $basePath, $vendorPath, $path);
                $pathCode        = str_replace('$baseDir', $basePath, $pathCode);
                $pathCode        = str_replace('$vendorDir', $vendorPath, $pathCode);
                $exportedPaths[] = $pathCode;
            }

            $cacheDir  = $exportedPaths[0];
            $sourceDir = $exportedPaths[1];

            $compiler = new Compiler($classLoader, $cacheDir);

            $finder      = new Finder();
            $sourceFiles = $finder->in($sourceDir)->name('\.php$')->files();
            foreach ($sourceFiles as $sourceFile) {
                $filePath = $sourceFile->getRelativePathname();

                if ($compiler->needToHandle($filePath)) {
                    $output->writeln('Handle: ' . $filePath);
                    $compiler->handle($filePath);
                }
            }
        }

        $output->writeln('Done!');
    }
}

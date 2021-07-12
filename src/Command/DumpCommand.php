<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Command\BaseCommand;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Autoload\AutoloadGenerator;
use Mrsuh\PhpGenerics\Compiler\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\Engine;
use Mrsuh\PhpGenerics\Compiler\Parser;
use Mrsuh\PhpGenerics\Compiler\Printer;
use Mrsuh\PhpGenerics\Compiler\Result;
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
        $this->getIO()->write('<info>Generating generic files</info>');

        $composer  = $this->getComposer();
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        $package   = $composer->getPackage();
        $config    = $composer->getConfig();

        $autoloadGenerator = new AutoloadGenerator($composer->getEventDispatcher());

        $packageMap = $autoloadGenerator->buildPackageMap(
            $composer->getInstallationManager(),
            $package,
            $localRepo->getCanonicalPackages()
        );

        $autoloads = $autoloadGenerator->parseAutoloads($packageMap, $package, true);

        $filesystem = new Filesystem();

        $basePath   = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $vendorPath = $filesystem->normalizePath(realpath(realpath($config->get('vendor-dir'))));

        $classFinder = new ClassFinder($autoloads, $autoloadGenerator, $filesystem, $basePath, $vendorPath);

        foreach ($autoloads['psr-4'] as $paths) {
            if (count($paths) !== 2) {
                continue;
            }
            $exportedPaths = [];
            foreach ($paths as $path) {
                $exportedPaths[] = $autoloadGenerator->getAbsolutePath($filesystem, $basePath, $vendorPath, $path);
            }

            $cacheDir  = $exportedPaths[0];
            $sourceDir = $exportedPaths[1];

            $filesystem->ensureDirectoryExists($cacheDir);

            $engine  = new Engine($classFinder);
            $printer = new Printer();

            $finder      = new Finder();
            $sourceFiles = $finder->in($sourceDir)->name('*.php')->files();
            /** @var Result[] $results */
            $filesCount = 0;
            foreach ($sourceFiles as $sourceFile) {
                $content = file_get_contents($sourceFile->getRealPath());

                if ($engine->needToHandle($content)) {
                    $result = $engine->handle($content);

                    $usageFilePath = Parser::getRelativeDir($result->getUsageClass()->fqn, $classFinder);
                    $filesystem->ensureDirectoryExists(dirname($usageFilePath));
                    file_put_contents($usageFilePath, $printer->printFile($result->getUsageClass()->ast));
                    $filesCount++;

                    if ($this->getIO()->isVerbose()) {
                        $this->getIO()->write(sprintf('<info>%s</info>', $result->getUsageClass()->fqn));
                    }

                    foreach ($result->getGenericClasses() as $genericClass) {
                        $genericFilePath = Parser::getRelativeDir($genericClass->fqn, $classFinder);
                        $filesystem->ensureDirectoryExists(dirname($genericFilePath));
                        file_put_contents($genericFilePath, $printer->printFile($genericClass->ast));
                        $filesCount++;

                        if ($this->getIO()->isVerbose()) {
                            $this->getIO()->write(sprintf('<info>%s</info>', $genericClass->fqn));
                        }
                    }
                }
            }
        }

        $this->getIO()->write('<info>Generated generic files containing ' . $filesCount . ' classes</info>');
    }
}

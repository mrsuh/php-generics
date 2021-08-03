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
        $this
            ->setName('dump-generics')
            ->setDescription('Dumps the concrete class files from generics classes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getIO()->write('<info>Generating concrete classes</info>');

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

        $filesCount = 0;
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

            $filesystem->emptyDirectory($cacheDir);

            $engine  = new Engine($classFinder);
            $printer = new Printer();

            $finder      = new Finder();
            $sourceFiles = $finder->in($sourceDir)->name('*.php')->files();
            /** @var Result[] $results */

            foreach ($sourceFiles as $sourceFile) {
                $content = file_get_contents($sourceFile->getRealPath());

                if ($content === '') {
                    $this->getIO()->error('Can\'t read file ' . $sourceFile->getRealPath());
                    continue;
                }

                if ($engine->needToHandle($content)) {
                    $result = $engine->handle($content);

                    foreach ($result->getConcreteClasses() as $concreteClass) {
                        $concreteFilePath = $cacheDir . DIRECTORY_SEPARATOR . ltrim(Parser::getRelativeFilePath($concreteClass->fqn, $classFinder), DIRECTORY_SEPARATOR);
                        $filesystem->ensureDirectoryExists(dirname($concreteFilePath));
                        file_put_contents($concreteFilePath, $printer->printFile($concreteClass->ast));
                        $filesCount++;

                        if ($this->getIO()->isVerbose()) {
                            $this->getIO()->write(sprintf('  - %s', $concreteClass->fqn));
                        }
                    }
                }
            }
        }

        $this->getIO()->write('<info>Generated files containing ' . $filesCount . ' concrete classes</info>');
    }
}

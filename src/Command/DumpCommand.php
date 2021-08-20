<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Autoload\ClassLoader;
use Composer\Command\BaseCommand;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Autoload\AutoloadGenerator;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\Compiler;
use Mrsuh\PhpGenerics\Compiler\Printer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('dump-generics')
            ->setDescription('Dumps the concrete class files from generics classes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);
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

        $classLoader = self::getClassLoader($autoloads, $autoloadGenerator, $filesystem, $basePath, $vendorPath);
        $classFinder = new ClassFinder($classLoader);

        $printer  = new Printer();
        $compiler = new Compiler($classFinder);

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

            try {
                $result = $compiler->compile($sourceDir);
            } catch (\Exception $exception) {
                $this->getIO()->writeError(sprintf("<bg=red>%s</>", $exception->getMessage()));
                if ($exception->getPrevious() !== null) {
                    $this->getIO()->writeError(sprintf("<bg=red>%s</>", $exception->getPrevious()->getMessage()));
                }
                $this->getIO()->writeError(sprintf("<bg=red>%s</>", $exception->getTraceAsString()));

                return 1;
            }

            foreach ($result->getConcreteClasses() as $concreteClass) {
                $concreteFilePath = $cacheDir . DIRECTORY_SEPARATOR . ltrim($classFinder->getRelativeFilePathByClassFqn($concreteClass->fqn), DIRECTORY_SEPARATOR);
                $filesystem->ensureDirectoryExists(dirname($concreteFilePath));
                file_put_contents($concreteFilePath, $printer->printFile($concreteClass->ast));
                $filesCount++;

                if ($this->getIO()->isVerbose()) {
                    $this->getIO()->write(sprintf('  - %s', $concreteClass->fqn));
                }
            }
        }

        $timeFin = microtime(true);
        $this->getIO()->write(sprintf(
                "<info>Generated %d concrete classes in %.3f seconds, %.3f MB memory used</info>",
                $filesCount,
                $timeFin - $timeStart,
                memory_get_usage(true) / 1024 / 1024)
        );

        return 0;
    }

    private function getClassLoader(array $autoloads, AutoloadGenerator $autoloadGenerator, Filesystem $filesystem, string $basePath, string $vendorPath): ClassLoader
    {
        $classLoader = new ClassLoader();
        foreach ($autoloads['psr-4'] as $namespace => $paths) {
            $exportedPaths = [];
            foreach ($paths as $path) {
                $exportedPaths[] = $autoloadGenerator->getAbsolutePath($filesystem, $basePath, $vendorPath, $path);
            }

            $classLoader->setPsr4($namespace, $exportedPaths);
        }

        foreach ($autoloads['psr-0'] as $namespace => $paths) {
            $exportedPaths = [];
            foreach ($paths as $path) {
                $exportedPaths[] = $autoloadGenerator->getAbsolutePath($filesystem, $basePath, $vendorPath, $path);
            }

            $classLoader->set($namespace, $exportedPaths);
        }

        return $classLoader;
    }
}

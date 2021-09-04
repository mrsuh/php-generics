<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Command\BaseCommand;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\PackageAutoload;
use Mrsuh\PhpGenerics\Compiler\Compiler;
use Mrsuh\PhpGenerics\Compiler\Printer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('dump-generics')
            ->setDescription('Dumps the concrete class files from generics classes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);

        $composer            = $this->getComposer();
        $localRepository     = $composer->getRepositoryManager()->getLocalRepository();
        $localPackage        = $composer->getPackage();
        $installationManager = $composer->getInstallationManager();

        $libraryPackage = $localRepository->findPackage('php-generics/library', '*');
        $this->getIO()->write(sprintf('<info>%s</info> <comment>%s</comment>', $libraryPackage->getName(), $libraryPackage->getPrettyVersion()));

        if (!self::hasPackageCacheDirectory($localPackage)) {
            $this->getIO()->writeError("<bg=red>You must set autoload cache directory in composer.json</>");

            return 1;
        }

        $filesystem = new Filesystem();

        $basePath   = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $vendorPath = $filesystem->normalizePath(realpath(realpath($composer->getConfig()->get('vendor-dir'))));

        $packageAutoloads = self::getPackageAutoloads($localPackage, $basePath, $localRepository, $installationManager);

        $this->getIO()->write('<info>Generating concrete classes</info>');

        $autoloadGenerator = $composer->getAutoloadGenerator();

        $packageMap = $autoloadGenerator->buildPackageMap(
            $installationManager,
            $localPackage,
            $localRepository->getCanonicalPackages()
        );

        $autoloads         = $autoloadGenerator->parseAutoloads($packageMap, $localPackage, true);
        $localPsr4Autoload = self::parsePackagePsr4Autoload($localPackage);

        $classLoader = $autoloadGenerator->createLoader([
            'psr-4' => self::getPsr4AutoloadWithAbsolutePath($autoloads['psr-4'], $localPsr4Autoload, $basePath)
        ], $vendorPath);

        $classFinder = new ClassFinder($classLoader);
        $printer     = new Printer();
        $compiler    = new Compiler($classFinder);

        $emptiedCacheDirectories = [];

        $filesCount = 0;
        foreach ($localPsr4Autoload as $directories) {
            if (!is_array($directories)) {
                continue;
            }

            if (count($directories) < 2) {
                continue;
            }

            $sourceDirectory = $filesystem->normalizePath($basePath . DIRECTORY_SEPARATOR . $directories[1]);

            try {
                $result = $compiler->compile($sourceDirectory);

                foreach ($result->getConcreteClasses() as $concreteClass) {
                    $genericFilePath = $filesystem->normalizePath($classLoader->findFile($concreteClass->genericFqn));
                    if (!$genericFilePath) {
                        throw new \RuntimeException(sprintf('Can\'t find file for class "%s"', $concreteClass->genericFqn));
                    }

                    $packageAutoload = self::findPackageAutoloadByFilePath($packageAutoloads, $genericFilePath);
                    if ($packageAutoload === null) {
                        throw new \RuntimeException(sprintf('Can\'t find package autoload for file "%s"', $genericFilePath));
                    }

                    $cacheDirectory = $packageAutoload->getCacheDirectory();

                    if (array_key_exists($cacheDirectory, $emptiedCacheDirectories)) {
                        $filesystem->emptyDirectory($cacheDirectory);
                        $emptiedCacheDirectories[$cacheDirectory] = true;
                    }

                    $concreteFilePath = rtrim($cacheDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim(PackageAutoload::getRelativeFilePathByClassFqn($packageAutoload, $concreteClass->fqn), DIRECTORY_SEPARATOR);
                    $filesystem->ensureDirectoryExists(dirname($concreteFilePath));
                    if (file_put_contents($concreteFilePath, $printer->printFile($concreteClass->ast)) === false) {
                        throw new \RuntimeException(sprintf('Can\'t write into file "%s"', $concreteFilePath));
                    }
                    $filesCount++;

                    if ($this->getIO()->isVerbose()) {
                        $line = sprintf('  - %s', $concreteClass->fqn);
                        if ($this->getIO()->isVeryVerbose()) {
                            $line .= sprintf(' <comment>%s</comment>', $concreteFilePath);
                        }
                        $this->getIO()->write($line);
                    }
                }

            } catch (\Exception $exception) {
                $this->getIO()->writeError(sprintf("<bg=red>%s</>", $exception->getMessage()));
                if ($exception->getPrevious() !== null) {
                    $this->getIO()->writeError(sprintf("<bg=red>%s</>", $exception->getPrevious()->getMessage()));
                }
                $this->getIO()->writeError(sprintf("<bg=red>%s</>", $exception->getTraceAsString()));

                return 1;
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

    /**
     * @return PackageAutoload[]
     */
    private static function getPackageAutoloads(PackageInterface $localPackage, string $localPackageBasePath, RepositoryInterface $localRepository, InstallationManager $installationManager): array
    {
        $packageDirectories  = [];
        $installedRepository = new InstalledRepository([$localRepository]);
        foreach ($installedRepository->getRepositories() as $repository) {
            foreach ($repository->getPackages() as $package) {
                foreach (self::parsePackagePsr4Autoload($package) as $namespace => $directories) {
                    if (!is_array($directories)) {
                        continue;
                    }

                    if (count($directories) < 2) {
                        continue;
                    }

                    $packageDirectories[] = new PackageAutoload($namespace, $directories[1], $directories[0]);
                }
            }
        }

        foreach (self::parsePackagePsr4Autoload($localPackage) as $namespace => $directories) {
            if (!is_array($directories)) {
                continue;
            }

            if (count($directories) < 2) {
                continue;
            }

            $packageDirectories[] = new PackageAutoload(
                $namespace,
                $localPackageBasePath . '/' . $directories[1],
                $localPackageBasePath . '/' . $directories[0]
            );
        }

        return $packageDirectories;
    }

    private static function getPsr4AutoloadWithAbsolutePath(array $psr4Autoload, array $localPackagePsr4Autoload, string $localPackageBasePath): array
    {
        foreach ($localPackagePsr4Autoload as $localPackageNamespace => $localPackageDirectories) {
            $directories = &$psr4Autoload[$localPackageNamespace];
            foreach ($directories as &$directory) {
                $directory = $localPackageBasePath . '/' . $directory;
            }
        }
        unset($directories);
        unset($directory);

        return $psr4Autoload;
    }

    private static function hasPackageCacheDirectory(PackageInterface $package): bool
    {
        foreach (self::parsePackagePsr4Autoload($package) as $directories) {
            if (!is_array($directories)) {
                continue;
            }
            if (count($directories) < 2) {
                continue;
            }

            return true;
        }

        return false;
    }

    private static function parsePackagePsr4Autoload(PackageInterface $package): array
    {
        $autoload = $package->getAutoload();

        return $autoload['psr-4'] ?? [];
    }

    /**
     * @param PackageAutoload[] $packageDirectories
     */
    public function findPackageAutoloadByFilePath(array $packageDirectories, string $filePath): ?PackageAutoload
    {
        $directories = null;
        $length      = 0;
        foreach ($packageDirectories as $packageDirectory) {

            if (strpos($filePath, $packageDirectory->getSourceDirectory()) === 0 && strlen($packageDirectory->getSourceDirectory()) > $length) {
                $length      = strlen($packageDirectory->getSourceDirectory());
                $directories = $packageDirectory;
                continue;
            }

            if (strpos($filePath, $packageDirectory->getCacheDirectory()) === 0 && strlen($packageDirectory->getCacheDirectory()) > $length) {
                $length      = strlen($packageDirectory->getCacheDirectory());
                $directories = $packageDirectory;
            }
        }

        return $directories;
    }
}

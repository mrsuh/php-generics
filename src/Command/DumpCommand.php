<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Command\BaseCommand;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\Package;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\PackageDirectories;
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

        $genericPackages = self::getGenericsPackages($localPackage, $basePath, $localRepository, $installationManager);

        $this->getIO()->write('<info>Generating concrete classes</info>');

        $autoloadGenerator = $composer->getAutoloadGenerator();

        $packageMap = $autoloadGenerator->buildPackageMap(
            $installationManager,
            $localPackage,
            $localRepository->getCanonicalPackages()
        );

        $autoloads         = $autoloadGenerator->parseAutoloads($packageMap, $localPackage, true);
        $localPsr4Autoload = self::getPackagePsr4Autoload($localPackage);

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

                    $packageDirectories = self::findPackageDirectoriesByFilePath($genericPackages, $genericFilePath);
                    if ($packageDirectories === null) {
                        throw new \RuntimeException(sprintf('Can\'t find package directories for file "%s"', $genericFilePath));
                    }

                    $cacheDirectory = $packageDirectories->cacheDirectory;

                    if (array_key_exists($cacheDirectory, $emptiedCacheDirectories)) {
                        $filesystem->emptyDirectory($cacheDirectory);
                        $emptiedCacheDirectories[$cacheDirectory] = true;
                    }

                    $concreteFilePath = rtrim($cacheDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($package->getRelativeFilePathByClassFqn($concreteClass->fqn), DIRECTORY_SEPARATOR);
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
     * @return Package[]
     */
    private static function getGenericsPackages(PackageInterface $localPackage, string $localPackageBasePath, RepositoryInterface $localRepository, InstallationManager $installationManager): array
    {
        $genericPackages     = [];
        $installedRepository = new InstalledRepository([$localRepository]);
        foreach ($installedRepository->getRepositories() as $repository) {
            foreach ($repository->getPackages() as $package) {
                $packagePsr4Autoload = self::getPackagePsr4Autoload($package);
                $hasCacheDirectory   = false;
                foreach ($packagePsr4Autoload as $directories) {
                    if (!is_array($directories)) {
                        continue;
                    }

                    if (count($directories) < 2) {
                        continue;
                    }

                    $hasCacheDirectory = true;
                    break;
                }

                if ($hasCacheDirectory) {
                    $packagePath = $installationManager->getInstallPath($package);
                    $tmpAutoload = $packagePsr4Autoload;
                    foreach ($packagePsr4Autoload as $localNamespace => $localDirectories) {
                        $tmpAutoload[$localNamespace] = [];
                        foreach ($localDirectories as $directory) {
                            $tmpAutoload[$localNamespace][] = $packagePath . '/' . $directory;
                        }
                    }
                    $genericPackages[] = new Package($tmpAutoload);
                }
            }
        }

        $tmpAutoload         = [];
        $packagePsr4Autoload = self::getPackagePsr4Autoload($localPackage);
        foreach ($packagePsr4Autoload as $localNamespace => $localDirectories) {
            $tmpAutoload[$localNamespace] = [];
            foreach ($localDirectories as $directory) {
                $tmpAutoload[$localNamespace][] = $localPackageBasePath . '/' . $directory;
            }
        }
        $genericPackages[] = new Package($tmpAutoload);

        return $genericPackages;
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
        foreach (self::getPackagePsr4Autoload($package) as $directories) {
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

    private static function getPackagePsr4Autoload(PackageInterface $package): array
    {
        $autoload = $package->getAutoload();

        return $autoload['psr-4'] ?? [];
    }

    /**
     * @param Package[] $packages
     */
    public function findPackageDirectoriesByFilePath(array $packages, string $filePath): ?PackageDirectories
    {
        $foundPackage   = null;
        $filePathLength = 0;
        foreach ($packages as $package) {
            $packageDirectories = $package->getDirectoriesByFilePath($filePath);
            if ($packageDirectories === null) {
                continue;
            }

            $sourceDirectoryLength = strlen($packageDirectories->sourceDirectory);
            if ($sourceDirectoryLength > $filePathLength) {
                $foundPackage   = $packageDirectories;
                $filePathLength = $sourceDirectoryLength;
            }
        }

        return $foundPackage;
    }
}

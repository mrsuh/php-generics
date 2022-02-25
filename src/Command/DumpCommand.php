<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Command\BaseCommand;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\CompilerInterface;
use Mrsuh\PhpGenerics\Compiler\FileIterator;
use Mrsuh\PhpGenerics\Compiler\Monomorphic\Compiler as MonomorphicCompiler;
use Mrsuh\PhpGenerics\Compiler\Printer;
use Mrsuh\PhpGenerics\Compiler\TypeErased\Compiler as TypeErasedCompiler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends BaseCommand
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;

    protected function configure(): void
    {
        $this
            ->setName('dump-generics')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, sprintf('Generics type. Allowed values: %s, %s', CompilerInterface::MONOMORPHIC, CompilerInterface::TYPE_ERASED), CompilerInterface::MONOMORPHIC)
            ->setDescription('Dumps the concrete class files from generics classes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);

        $composer            = $this->getComposer();
        $localRepository     = $composer->getRepositoryManager()->getLocalRepository();
        $localPackage        = $composer->getPackage();
        $installationManager = $composer->getInstallationManager();

        $libraryPackage = $localRepository->findPackage('mrsuh/php-generics', '*');
        $this->getIO()->write(sprintf('<info>%s</info> <comment>%s</comment>', $libraryPackage->getName(), $libraryPackage->getPrettyVersion()));

        if (!self::hasPackageCacheDirectory($localPackage)) {
            $this->getIO()->writeError("<bg=red>You must set autoload cache directory in composer.json</>");

            return self::FAILURE;
        }

        $filesystem = new Filesystem();

        $basePath   = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $vendorPath = $filesystem->normalizePath(realpath(realpath($composer->getConfig()->get('vendor-dir'))));

        $packageAutoloads = self::getPackageAutoloads($localPackage, $basePath, $localRepository, $installationManager);

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

        $type = $input->getOption('type');
        switch ($type) {
            case CompilerInterface::MONOMORPHIC:
                $compiler = new MonomorphicCompiler($classFinder);
                break;
            case CompilerInterface::TYPE_ERASED:
                $compiler = new TypeErasedCompiler($classFinder);
                break;
            default:
                $this->getIO()->writeError("<bg=red>Invalid --type option value</>");
                $this->getIO()->writeError(sprintf(
                    "<bg=red>You can set --type=%s or --type=%s</>",
                    CompilerInterface::MONOMORPHIC,
                    CompilerInterface::TYPE_ERASED
                ));

                return self::FAILURE;
        }

        $this->getIO()->write(sprintf('<info>Generics type: </info><comment>%s</comment>', $type));
        $this->getIO()->write('<info>Generating concrete classes</info>');

        $emptiedCacheDirectories = [];

        $filesCount = 0;
        foreach ($localPsr4Autoload as $directories) {
            if (!is_array($directories)) {
                continue;
            }

            if (count($directories) < 2) {
                continue;
            }

            $sourcePath = $filesystem->normalizePath($basePath . DIRECTORY_SEPARATOR . $directories[1]);

            foreach (FileIterator::iterateAsFilePath($sourcePath) as $filePath) {
                $packageAutoload = self::findPackageAutoloadByFilePath($packageAutoloads, $filePath);
                if ($packageAutoload === null) {
                    throw new \RuntimeException(sprintf('Can\'t find package autoload for file "%s"', $filePath));
                }

                $cachePath = $filesystem->normalizePath($packageAutoload->getCachePath());
                if (!array_key_exists($cachePath, $emptiedCacheDirectories)) {
                    if ($this->getIO()->isDebug()) {
                        $this->getIO()->write(sprintf('Clear cache directory <comment>%s</comment>', self::getRelativePath($filesystem, $basePath, $cachePath)));
                    }
                    $filesystem->emptyDirectory($cachePath);
                    $emptiedCacheDirectories[$cachePath] = true;
                }
            }

            try {
                if ($this->getIO()->isDebug()) {
                    $this->getIO()->write(sprintf('Handle source directory <comment>%s</comment>', self::getRelativePath($filesystem, $basePath, $sourcePath)));
                }

                foreach ($compiler->compile($sourcePath)->getConcreteClasses() as $concreteClass) {
                    $genericFilePath = $filesystem->normalizePath($classLoader->findFile($concreteClass->genericFqn));
                    if (!$genericFilePath) {
                        throw new \RuntimeException(sprintf('Can\'t find file for class "%s"', $concreteClass->genericFqn));
                    }

                    $packageAutoload = self::findPackageAutoloadByFilePath($packageAutoloads, $genericFilePath);
                    if ($packageAutoload === null) {
                        throw new \RuntimeException(sprintf('Can\'t find package autoload for file "%s"', $genericFilePath));
                    }

                    $concreteFilePath = $filesystem->normalizePath($packageAutoload->getCachePath() . DIRECTORY_SEPARATOR . $packageAutoload->getRelativeFilePathByClassFqn($concreteClass->fqn));
                    $filesystem->ensureDirectoryExists(dirname($concreteFilePath));
                    if (file_put_contents($concreteFilePath, $printer->printFile($concreteClass->ast)) === false) {
                        throw new \RuntimeException(sprintf('Can\'t write into file "%s"', $concreteFilePath));
                    }
                    $filesCount++;

                    if ($this->getIO()->isVerbose()) {
                        $line = sprintf('  - %s', $concreteClass->fqn);
                        if ($this->getIO()->isVeryVerbose()) {
                            $line .= sprintf(' <comment>%s</comment>', self::getRelativePath($filesystem, $basePath, $concreteFilePath));
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

                return self::FAILURE;
            }
        }

        $timeFin = microtime(true);
        $this->getIO()->write(sprintf(
                "<info>Generated %d concrete classes in %.3f seconds, %.3f MB memory used</info>",
                $filesCount,
                $timeFin - $timeStart,
                memory_get_usage(true) / 1024 / 1024)
        );

        return self::SUCCESS;
    }

    /**
     * @return PackageAutoload[]
     */
    private static function getPackageAutoloads(PackageInterface $localPackage, string $localPackageBasePath, RepositoryInterface $localRepository, InstallationManager $installationManager): array
    {
        $packageAutoloads    = [];
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

                    $packageAutoloads[] = new PackageAutoload(
                        $namespace,
                        $installationManager->getInstallPath($package),
                        $directories[1],
                        $directories[0]
                    );
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

            $packageAutoloads[] = new PackageAutoload(
                $namespace,
                $localPackageBasePath,
                $directories[1],
                $directories[0]
            );
        }

        return $packageAutoloads;
    }

    private static function getPsr4AutoloadWithAbsolutePath(array $psr4Autoload, array $localPackagePsr4Autoload, string $localPackageBasePath): array
    {
        $autoload = $psr4Autoload;
        foreach ($localPackagePsr4Autoload as $localPackageNamespace => $localPackageDirectories) {
            $directories = &$autoload[$localPackageNamespace];
            foreach ($directories as &$directory) {
                $directory = $localPackageBasePath . DIRECTORY_SEPARATOR . $directory;
            }
        }
        unset($directories);
        unset($directory);

        return $autoload;
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
    public function findPackageAutoloadByFilePath(array $packageAutoloads, string $filePath): ?PackageAutoload
    {
        $autoload = null;
        $length   = 0;
        foreach ($packageAutoloads as $packageAutoload) {

            $sourcePath = $packageAutoload->getSourcePath();
            if (strpos($filePath, $sourcePath) === 0 && strlen($sourcePath) > $length) {
                $length   = strlen($sourcePath);
                $autoload = $packageAutoload;
                continue;
            }

            $cachePath = $packageAutoload->getCachePath();
            if (strpos($filePath, $cachePath) === 0 && strlen($cachePath) > $length) {
                $length   = strlen($cachePath);
                $autoload = $packageAutoload;
            }
        }

        return $autoload;
    }

    private static function getRelativePath(Filesystem $filesystem, string $basePath, string $filePath): string
    {
        return ltrim($filesystem->normalizePath(str_replace($basePath, '', $filePath)), DIRECTORY_SEPARATOR);
    }
}

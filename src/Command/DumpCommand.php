<?php

namespace Mrsuh\PhpGenerics\Command;

use Composer\Command\BaseCommand;
use Composer\Repository\InstalledRepository;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\Package;
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

        $composer     = $this->getComposer();
        $localRepo    = $composer->getRepositoryManager()->getLocalRepository();
        $localPackage = $composer->getPackage();

        $libraryPackage = $localRepo->findPackage('php-generics/library', '*');
        $this->getIO()->write(sprintf('<info>%s</info> <comment>%s</comment>', $libraryPackage->getName(), $libraryPackage->getPrettyVersion()));

        $localConfig = $composer->getConfig();

        $localPsr4Autoload = $localPackage->getAutoload()['psr-4'] ?? [];
        $hasCacheDirectory = false;
        foreach ($localPsr4Autoload as $directories) {
            if (!is_array($directories)) {
                continue;
            }
            if (count($directories) < 2) {
                continue;
            }
            $hasCacheDirectory = true;
            break;
        }
        if (!$hasCacheDirectory) {
            $this->getIO()->writeError("<bg=red>You must set cache directory</>");

            return 1;
        }

        $genericPackages = [];
        $installedRepo   = new InstalledRepository([$composer->getRepositoryManager()->getLocalRepository()]);
        foreach ($installedRepo->getRepositories() as $repository) {
            foreach ($repository->getPackages() as $package) {
                $packageAutoload     = $package->getAutoload();
                $packagePsr4Autoload = $packageAutoload['psr-4'] ?? [];
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
                    $genericPackages[] = new Package(
                        $composer->getInstallationManager()->getInstallPath($package),
                        $packagePsr4Autoload
                    );
                }
            }
        }

        $this->getIO()->write('<info>Generating concrete classes</info>');

        $autoloadGenerator = $composer->getAutoloadGenerator();

        $packageMap = $autoloadGenerator->buildPackageMap(
            $composer->getInstallationManager(),
            $localPackage,
            $localRepo->getCanonicalPackages()
        );

        $autoloads = $autoloadGenerator->parseAutoloads($packageMap, $localPackage, true);

        $filesystem = new Filesystem();

        $basePath   = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $vendorPath = $filesystem->normalizePath(realpath(realpath($localConfig->get('vendor-dir'))));

        $classLoader = $autoloadGenerator->createLoader($autoloads, $vendorPath);
        $classFinder = new ClassFinder($classLoader);

        $printer  = new Printer();
        $compiler = new Compiler($classFinder);

        $emptiedCacheDirectories = [];

        $filesCount = 0;

        foreach ($localPsr4Autoload as $directories) {
            if (!is_array($directories)) {
                continue;
            }

            if (count($directories) < 2) {
                continue;
            }

            $sourceDirectory = $filesystem->normalizePath($basePath . '/' . $directories[1]);
            $cacheDirectory  = $filesystem->normalizePath($basePath . '/' . $directories[0]);
            $filesystem->emptyDirectory($cacheDirectory);

            try {
                $result = $compiler->compile($sourceDirectory);

                foreach ($result->getConcreteClasses() as $concreteClass) {
                    $genericFilePath = $classLoader->findFile($concreteClass->genericFqn);
                    if (!$genericFilePath) {
                        throw new \RuntimeException(sprintf('Can\'t find file for class "%s"', $concreteClass->genericFqn));
                    }

                    $package = self::findPackageByFilePath($genericPackages, $genericFilePath);
                    if ($package === null) {
                        throw new \RuntimeException(sprintf('Can\'t find package for file "%s"', $genericFilePath));
                    }

                    $cacheDirectory = $package->getCacheDirectory($genericFilePath);

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
     * @param Package[] $packages
     */
    public function findPackageByFilePath(array $packages, string $filePath): ?Package
    {
        foreach ($packages as $package) {
            if ($package->hasFile($filePath)) {
                return $package;
            }
        }

        return null;
    }
}

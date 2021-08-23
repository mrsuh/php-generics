<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

use Composer\Autoload\ClassLoader;

class ClassFinder implements ClassFinderInterface
{
    private ClassLoader $classLoader;

    public function __construct(ClassLoader $classLoader)
    {
        $this->classLoader = $classLoader;
    }

    public function getSourceFileContentByClassFqn(string $fqn): string
    {
        $filePath = $this->classLoader->findFile($fqn);
        if (!$filePath) {
            throw new \RuntimeException(sprintf('Can\'t find file for class "%s"', $fqn));
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException(sprintf('File "%s" is not readable', $filePath));
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Can\t read file "%s"', $filePath));
        }

        if (empty($content)) {
            throw new \RuntimeException(sprintf('File "%s" has empty content', $filePath));
        }

        return $content;
    }

    public function isSourceFileExistsByClassFqn(string $fqn): bool
    {
        return !empty($this->classLoader->findFile($fqn));
    }

    public function getCacheRelativeFilePathByClassFqn(string $fqn): string
    {
        $package = $this->findPrefixInfoByClassFqn($fqn);

        return str_replace([$package->getPrefix(), '\\'], ['', DIRECTORY_SEPARATOR], $fqn) . '.php';
    }

    public function getCacheDirectoryByClassFqn(string $fqn): string
    {
        $package = $this->findPrefixInfoByClassFqn($fqn);

        if (count($package->getDirectories()) < 2) {
            throw new \RuntimeException(sprintf('PSR4 package "%s" hasn\'t cache directory', $package->getPrefix()));
        }

        return $package->getDirectories()[0];
    }

    private function findPrefixInfoByClassFqn(string $fqn): Package
    {
        $prefixLength = 0;
        $package      = null;
        $psr4Prefixes = $this->classLoader->getPrefixesPsr4();
        foreach ($psr4Prefixes as $prefix => $directories) {
            if (strpos($fqn, $prefix) === 0 && strlen($prefix) > $prefixLength) {
                $prefixLength = strlen($prefix);
                $package      = new Package($prefix, $directories);
            }
        }

        if ($package === null) {
            throw new \RuntimeException(sprintf('Can\'t find PSR4 package for class "%s"', $fqn));
        }

        return $package;
    }
}

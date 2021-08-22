<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

use Composer\Autoload\ClassLoader;
use Mrsuh\PhpGenerics\Exception\FileEmptyException;
use Mrsuh\PhpGenerics\Exception\FileNotFoundException;
use Mrsuh\PhpGenerics\Exception\FileNotReadableException;

class ClassFinder implements ClassFinderInterface
{
    private ClassLoader $classLoader;

    public function __construct(ClassLoader $classLoader)
    {
        $this->classLoader = $classLoader;
    }

    public function isFileExistsByClassFqn(string $fqn): bool
    {
        return !empty($this->classLoader->findFile($fqn));
    }

    public function getFileContentByClassFqn(string $fqn): string
    {
        $filePath = $this->classLoader->findFile($fqn);
        if (!$filePath) {
            throw new \RuntimeException(sprintf('Can\'t find file for class "%s"', $fqn));
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException(sprintf('Can\'t read file "%s"', $filePath));
        }

        $content = file_get_contents($filePath);
        if (empty($content)) {
            throw new \RuntimeException(sprintf('File "%s" has empty content', $filePath));
        }

        return $content;
    }

    public function getRelativeFilePathByClassFqn(string $fqn): string
    {
        $psr4Prefixes = $this->classLoader->getPrefixesPsr4();
        foreach (array_keys($psr4Prefixes) as $namespace) {
            $fqn = str_replace($namespace, '', $fqn);
        }

        return str_replace('\\', DIRECTORY_SEPARATOR, $fqn) . '.php';
    }

    public function getCacheAbsoluteFilePathByClassFqn(string $fqn): string
    {
        $prefixInfo = $this->findPrefixInfoByClassFqn($fqn);

        return $prefixInfo['directories'][0] . '/' . str_replace($prefixInfo['prefix'], '', $fqn) . '.php';
    }

    private function findPrefixInfoByClassFqn(string $fqn): array
    {
        $prefixLegnth = 0;
        $info         = [];
        $psr4Prefixes = $this->classLoader->getPrefixesPsr4();
        foreach ($psr4Prefixes as $prefix => $directories) {
            if (strpos($fqn, $prefix) === 0 && strlen($prefix) > $prefixLegnth) {
                $prefixLegnth = strlen($prefix);
                $info         = ['prefix' => $prefix, 'directories' => $directories];
            }
        }

        return $info;
    }
}

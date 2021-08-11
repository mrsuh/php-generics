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
}

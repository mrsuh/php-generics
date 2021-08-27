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

    public function getFileContent(string $fqn): string
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

    public function isFileExists(string $fqn): bool
    {
        return !empty($this->classLoader->findFile($fqn));
    }
}

<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

use Composer\Autoload\ClassLoader;
use Mrsuh\PhpGenerics\Exception\ClassNotFoundException;
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

    /**
     * @throws ClassNotFoundException
     * @throws FileNotReadableException
     */
    public function getFileContentByClassFqn(string $fqn): string
    {
        $filePath = $this->classLoader->findFile($fqn);
        if (!$filePath) {
            throw new ClassNotFoundException(sprintf('Can\'t find class file %s', $fqn));
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new FileNotReadableException(sprintf('Can\'t read class %s from file %s', $fqn, $filePath));
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

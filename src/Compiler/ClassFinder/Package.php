<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

class Package
{
    private array $psr4autoload;

    /**
     * @param array<string, array> $psr4autoload
     */
    public function __construct(array $psr4autoload)
    {
        $this->psr4autoload = $psr4autoload;
    }

    public function getDirectoriesByFilePath(string $filePath): ?PackageDirectories
    {
        foreach ($this->psr4autoload as $directories) {

            if (!is_array($directories)) {
                continue;
            }

            if (count($directories) < 2) {
                continue;
            }

            $sourceDirectory = $directories[1];

            if (strpos($filePath, $sourceDirectory) === 0) {
                return new PackageDirectories($directories[1], $directories[0]);
            }
        }

        return null;
    }

    public function getRelativeFilePathByClassFqn(string $fqn): string
    {
        $filePath = '';
        foreach ($this->psr4autoload as $namespace => $directories) {
            if (strpos($fqn, $namespace) !== 0) {// @todo find right namespace
                continue;
            }

            return str_replace([$namespace, '\\'], ['', DIRECTORY_SEPARATOR], $fqn) . '.php';
        }

        return $filePath;
    }
}

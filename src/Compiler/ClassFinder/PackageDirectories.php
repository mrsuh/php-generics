<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

class PackageDirectories
{
    public string  $sourceDirectory;
    public string  $cacheDirectory;
    private string $namespace;

    public function __construct(string $sourceDirectory, string $cacheDirectory, string $namespace)
    {
        $this->sourceDirectory = $sourceDirectory;
        $this->cacheDirectory  = $cacheDirectory;
        $this->namespace       = $namespace;
    }

    public function getRelativeFilePathByClassFqn(string $fqn): string
    {
        return str_replace([$this->namespace, '\\'], ['', DIRECTORY_SEPARATOR], $fqn) . '.php';
    }
}

<?php

namespace Mrsuh\PhpGenerics\Command;

class PackageAutoload
{
    private string $sourceDirectory;
    private string $cacheDirectory;
    private string $namespace;

    public function __construct(string $namespace, string $sourceDirectory, string $cacheDirectory)
    {
        $this->sourceDirectory = $sourceDirectory;
        $this->cacheDirectory  = $cacheDirectory;
        $this->namespace       = $namespace;
    }

    public function getSourceDirectory(): string
    {
        return $this->sourceDirectory;
    }

    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getRelativeFilePathByClassFqn(string $fqn): string
    {
        return str_replace([$this->getNamespace(), '\\'], ['', DIRECTORY_SEPARATOR], $fqn) . '.php';
    }
}

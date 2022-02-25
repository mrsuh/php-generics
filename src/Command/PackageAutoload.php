<?php

namespace Mrsuh\PhpGenerics\Command;

class PackageAutoload
{
    private string $namespace;
    private string $path;
    private string $sourceDirectory;
    private string $cacheDirectory;

    public function __construct(string $namespace, string $path, string $sourceDirectory, string $cacheDirectory)
    {
        $this->namespace       = $namespace;
        $this->path            = $path;
        $this->sourceDirectory = $sourceDirectory;
        $this->cacheDirectory  = $cacheDirectory;
    }

    public function getSourceDirectory(): string
    {
        return $this->sourceDirectory;
    }

    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    public function getSourcePath(): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $this->sourceDirectory;
    }

    public function getCachePath(): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $this->cacheDirectory;
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

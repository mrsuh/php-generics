<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

class PackageDirectories
{
    public string $sourceDirectory;
    public string $cacheDirectory;

    public function __construct(string $sourceDirectory, string $cacheDirectory)
    {
        $this->sourceDirectory = $sourceDirectory;
        $this->cacheDirectory  = $cacheDirectory;
    }
}

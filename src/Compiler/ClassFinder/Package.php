<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

class Package
{
    private string $prefix;
    private array  $directories;

    /**
     * @param string[] $directories
     */
    public function __construct(string $prefix, array $directories)
    {
        $this->prefix      = $prefix;
        $this->directories = $directories;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @return string[]
     */
    public function getDirectories(): array
    {
        return $this->directories;
    }
}

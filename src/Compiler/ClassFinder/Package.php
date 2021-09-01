<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

class Package
{
    private string $directory;
    private array  $psr4autoload;

    /**
     * @param string[] $autoload
     */
    public function __construct(string $directory, array $psr4autoload)
    {
        $this->directory    = $directory;
        $this->psr4autoload = $psr4autoload;
    }

    public function hasFile(string $filePath): int
    {
        foreach ($this->psr4autoload as $directories) {

            if (!is_array($directories)) {
                continue;
            }

            if (count($directories) < 2) {
                continue;
            }

            if (strpos($filePath, $directories[1]) === 0) {
                return strlen($directories[1]);
            }
        }

        return 0;
    }

    public function getCacheDirectory(string $fqn): string
    {
        foreach ($this->psr4autoload as $namespace => $directories) {
            if (strpos($fqn, $namespace) !== 0) {// @todo find right namespace
                continue;
            }

            if (!is_array($directories)) {
                continue;
            }

            if (count($directories) < 2) {
                continue;
            }

            return $directories[0];
        }

        return '';
    }

    public function getRelativeFilePathByClassFqn(string $fqn): string
    {
        $filePath = '';
        foreach ($this->psr4autoload as $namespace => $directories) {
            if (strpos($fqn, $namespace) !== 0) {// @todo find right namespace
                continue;
            }

            $filePath = str_replace([$namespace, '\\'], ['', DIRECTORY_SEPARATOR], $fqn) . '.php';
            break;
        }

        return $filePath;
    }
}

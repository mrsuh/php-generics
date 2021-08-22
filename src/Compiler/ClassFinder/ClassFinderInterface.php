<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

interface ClassFinderInterface
{
    public function getFileContentByClassFqn(string $fqn): string;

    public function isFileExistsByClassFqn(string $fqn): bool;

    public function getCacheRelativeFilePathByClassFqn(string $fqn): string;

    public function getCacheDirectoryByClassFqn(string $fqn): string;
}

<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

interface ClassFinderInterface
{
    public function getSourceFileContentByClassFqn(string $fqn): string;

    public function isSourceFileExistsByClassFqn(string $fqn): bool;

    public function getCacheRelativeFilePathByClassFqn(string $fqn): string;

    public function getCacheDirectoryByClassFqn(string $fqn): string;
}

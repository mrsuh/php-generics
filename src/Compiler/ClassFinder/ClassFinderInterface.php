<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

interface ClassFinderInterface
{
    public function getFileContentByClassFqn(string $fqn): string;

    public function isFileExistsByClassFqn(string $fqn): bool;

    public function getRelativeFilePathByClassFqn(string $fqn): string;

    public function getCacheAbsoluteFilePathByClassFqn(string $fqn): string;
}

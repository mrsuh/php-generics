<?php

namespace Mrsuh\PhpGenerics\Compiler\ClassFinder;

interface ClassFinderInterface
{
    public function getFileContent(string $fqn): string;

    public function isFileExists(string $fqn): bool;
}

<?php

namespace Mrsuh\PhpGenerics\Compiler;

interface ClassFinderInterface
{
    public function getFileContentByClassFqn(string $fqn): string;

    public function isFileExistsByClassFqn(string $fqn): bool;

    public function getPrefixesPsr4(): array;
}

<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Symfony\Component\Finder\Finder;

class FileIterator
{
    public function iterate(string $directory): \Iterator
    {
        $sourceFiles = (new Finder())
            ->in($directory)
            ->name('*.php')
            ->sortByName()
            ->files();

        foreach ($sourceFiles as $sourceFile) {

            $sourceFilePath = $sourceFile->getRealPath();
            if (!is_readable($sourceFilePath)) {
                throw new \RuntimeException(sprintf('File "%s" is not readable', $sourceFilePath));
            }

            $content = file_get_contents($sourceFilePath);
            if ($content === false) {
                throw new \RuntimeException(sprintf('Can\t read file "%s"', $sourceFilePath));
            }

            if (empty($content)) {
                throw new \RuntimeException(sprintf('File "%s" has empty content', $sourceFilePath));
            }

            try {
                $ast = Parser::resolveNames(Parser::parse($content));
            } catch (\Exception $exception) {
                throw new \RuntimeException(sprintf('Can\'t parse file "%s"', $sourceFilePath), $exception->getCode(), $exception);
            }

            yield $ast;
        }
    }
}

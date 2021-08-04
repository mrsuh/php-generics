<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Symfony\Component\Finder\Finder;

class Compiler
{
    private Engine $engine;

    public function __construct(Engine $engine)
    {
        $this->engine = $engine;
    }

    public function compile(string $directory): Result
    {
        $sourceFiles = (new Finder())
            ->in($directory)
            ->name('*.php')
            ->sortByName()
            ->files();

        $result = new Result();
        foreach ($sourceFiles as $sourceFile) {
            $content = file_get_contents($sourceFile->getRealPath());

            if (empty($content)) {
                throw new \RuntimeException('Can\'t read file ' . $sourceFile->getRealPath());
            }

            if (!$this->engine->needToHandle($content)) {
                continue;
            }

            foreach ($this->engine->handle($content)->getConcreteClasses() as $concreteClass) {
                $result->addConcreteClass($concreteClass);
            }
        }

        return $result;
    }
}

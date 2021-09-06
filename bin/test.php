<?php

use Composer\Autoload\ClassLoader;
use Mrsuh\PhpGenerics\Command\PackageAutoload;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\Compiler;
use Mrsuh\PhpGenerics\Compiler\Parser;
use Mrsuh\PhpGenerics\Compiler\Printer;
use Symfony\Component\Finder\Finder;

require __DIR__ . '/../vendor/autoload.php';

$directories = (new Finder())
    ->in(__DIR__ . '/../tests')
    ->depth('== 0')
    ->sortByName()
    ->directories();

$printer = new Printer();

$allTestsSuccess = true;
foreach ($directories as $directory) {
    $success = true;

    $inputDirectory  = $directory->getRealPath() . '/input';
    $outputDirectory = $directory->getRealPath() . '/output';

    $inputFiles  = (new Finder())->in($inputDirectory)->name('*.php')->files();
    $outputFiles = (new Finder())->in($outputDirectory)->name('*.php')->files();

    if ($outputFiles->count() === 0) {
        continue;
    }

    $classLoader = new ClassLoader();
    $classLoader->setPsr4('Test\\', $inputDirectory);
    $classFinder = new ClassFinder($classLoader);
    $package     = new PackageAutoload('Test\\', $inputDirectory, $outputDirectory);

    $compiler = new Compiler($classFinder);

    try {
        $result = $compiler->compile($inputDirectory);
    } catch (\Exception $exception) {
        echo $exception->getMessage() . PHP_EOL;
        echo $exception->getTraceAsString() . PHP_EOL;
        exit(1);
    }

    if ($outputFiles->count() !== count($result->getConcreteClasses())) {
        $success = false;
        continue;
    }

    foreach ($result->getConcreteClasses() as $concreteClass) {
        $concreteFilePath     = $outputDirectory . DIRECTORY_SEPARATOR . ltrim($package->getRelativeFilePathByClassFqn($concreteClass->fqn), DIRECTORY_SEPARATOR);
        $concreteClassContent = file_get_contents($concreteFilePath);
        try {
            $concreteClassAst = Parser::parse($concreteClassContent);
        } catch (\Exception $exception) {
            echo sprintf('Parse file "%s" error: "%s"', $concreteFilePath, $exception->getMessage()) . PHP_EOL;
            echo $exception->getTraceAsString() . PHP_EOL;
            exit(1);
        }

        if ($printer->printFile($concreteClassAst) !== $printer->printFile($concreteClass->ast)) {
            $success = false;
        }
    }

    if (!$success) {
        $allTestsSuccess = false;
    }

    printf("%s - %d concrete classes [%s]\n", $directory->getBasename(), count($result->getConcreteClasses()), ($success ? 'ok' : 'fail'));
}

if ($allTestsSuccess) {
    print("\nAll tests passed successfully!\n");
    exit(0);
} else {
    print("\nThere are some errors\n");
    exit(1);
}

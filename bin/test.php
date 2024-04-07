<?php

use Composer\Autoload\ClassLoader;
use Mrsuh\PhpGenerics\Command\PackageAutoload;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\CompilerInterface;
use Mrsuh\PhpGenerics\Compiler\Monomorphic\Compiler as MonomorphicCompiler;
use Mrsuh\PhpGenerics\Compiler\TypeErased\Compiler as TypeErasedCompiler;
use Symfony\Component\Finder\Finder;
use Mrsuh\PhpGenerics\Compiler\Parser;
use Mrsuh\PhpGenerics\Compiler\Printer;

require __DIR__ . '/../vendor/autoload.php';

$type = $argv[1];

if (!in_array($type, [CompilerInterface::MONOMORPHIC, CompilerInterface::TYPE_ERASED])) {
    echo 'Invalid argument "type"' . PHP_EOL;
    exit(1);
}

$directories = (new Finder())
    ->in(__DIR__ . '/../tests/' . $type)
    ->depth('== 0')
    ->sortByName()
    ->directories();

$printer = new Printer();

$inputDirectory  = 'input';
$outputDirectory = 'output';

$allTestsSuccess = true;
foreach ($directories as $directory) {
    $success = true;

    $inputPath  = $directory->getRealPath() . DIRECTORY_SEPARATOR . $inputDirectory;
    $outputPath = $directory->getRealPath() . DIRECTORY_SEPARATOR . $outputDirectory;

    $inputFiles  = (new Finder())->in($inputPath)->name('*.php')->files();
    $outputFiles = (new Finder())->in($outputPath)->name('*.php')->files();

    if ($outputFiles->count() === 0) {
        continue;
    }

    $classLoader = new ClassLoader();
    $classLoader->setPsr4('Test\\', $inputPath);
    $classFinder = new ClassFinder($classLoader);
    $package     = new PackageAutoload('Test\\', $directory->getRealPath(), $inputDirectory, $outputDirectory);

    switch ($type) {
        case CompilerInterface::MONOMORPHIC:
            $compiler = new MonomorphicCompiler($classFinder);
            break;
        case CompilerInterface::TYPE_ERASED:
            $compiler = new TypeErasedCompiler($classFinder);
            break;
        default:
            echo 'Invalid argument "type"' . PHP_EOL;
            exit(1);
    }

    try {
        $result = $compiler->compile($inputPath);
    } catch (\Exception $exception) {
        echo $exception->getMessage() . PHP_EOL;
        echo $exception->getTraceAsString() . PHP_EOL;
        exit(1);
    }

    if ($outputFiles->count() !== count($result->getConcreteClasses())) {
        $allTestsSuccess = false;
        printf("%s - [skip failed]\n", $directory->getBasename());
        continue;
    }

    foreach ($result->getConcreteClasses() as $concreteClass) {
        $concreteFilePath     = $outputPath . DIRECTORY_SEPARATOR . ltrim($package->getRelativeFilePathByClassFqn($concreteClass->fqn), DIRECTORY_SEPARATOR);
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

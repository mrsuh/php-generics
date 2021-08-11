<?php

use Composer\Autoload\ClassLoader;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\Compiler;
use Mrsuh\PhpGenerics\Compiler\Printer;

require __DIR__ . '/../vendor/autoload.php';

$sourceDir  = $argv[1];
$outputDir  = $argv[2];
$psr4Prefix = $argv[3];

$classLoader = new ClassLoader();
$classLoader->setPsr4($psr4Prefix, $sourceDir);
$classFinder = new ClassFinder($classLoader);
$printer     = new Printer();
$filesystem  = new Filesystem();
$filesystem->ensureDirectoryExists($sourceDir);
$filesystem->removeDirectory($outputDir);
$filesystem->ensureDirectoryExists($outputDir);

$compiler = new Compiler($classFinder);

$result = $compiler->compile($sourceDir);
foreach ($result->getConcreteClasses() as $concreteClass) {
    $concreteFilePath = $outputDir . DIRECTORY_SEPARATOR . ltrim($classFinder->getRelativeFilePathByClassFqn($concreteClass->fqn), DIRECTORY_SEPARATOR);
    $filesystem->ensureDirectoryExists(dirname($concreteFilePath));
    file_put_contents($concreteFilePath, $printer->printFile($concreteClass->ast));

    printf("  - %s\n", $concreteClass->fqn);
}
echo "Generated files containing " . count($result->getConcreteClasses()) . " concrete classes\n";




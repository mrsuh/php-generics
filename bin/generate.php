<?php

use Composer\Autoload\ClassLoader;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Command\PackageAutoload;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\Compiler;
use Mrsuh\PhpGenerics\Compiler\Printer;

require __DIR__ . '/../vendor/autoload.php';

$timeStart = microtime(true);

$sourceDir = $argv[1];
if (empty($sourceDir) || !is_dir($sourceDir)) {
    echo "Source directory doesn't exists\n";
    exit(1);
}

$outputDir = $argv[2];
if (empty($outputDir) || !is_dir($outputDir)) {
    echo "Output directory doesn't exists\n";
    exit(1);
}

$psr4Prefix = $argv[3];
if (empty($psr4Prefix)) {
    echo "Invalid prefix\n";
    exit(1);
}

$classLoader = new ClassLoader();
$classLoader->setPsr4($psr4Prefix, $sourceDir);
$classFinder = new ClassFinder($classLoader);
$printer     = new Printer();
$filesystem  = new Filesystem();
$filesystem->ensureDirectoryExists($sourceDir);
$filesystem->removeDirectory($outputDir);
$filesystem->ensureDirectoryExists($outputDir);

$package = new PackageAutoload('Test\\', $sourceDir, $outputDir);

$compiler = new Compiler($classFinder);

try {
    $result = $compiler->compile($sourceDir);
} catch (\Exception $exception) {
    echo $exception->getMessage() . PHP_EOL;
    echo $exception->getTraceAsString() . PHP_EOL;
    exit(1);
}

foreach ($result->getConcreteClasses() as $concreteClass) {
    $concreteFilePath = $outputDir . DIRECTORY_SEPARATOR . ltrim($package->getRelativeFilePathByClassFqn($concreteClass->fqn), DIRECTORY_SEPARATOR);
    $filesystem->ensureDirectoryExists(dirname($concreteFilePath));
    file_put_contents($concreteFilePath, $printer->printFile($concreteClass->ast));

    printf("  - %s\n", $concreteClass->fqn);
}
$timeFin = microtime(true);

printf("Generated %d concrete classes in %.3f seconds, %.3f MB memory used\n", count($result->getConcreteClasses()), $timeFin - $timeStart, memory_get_usage(true) / 1024 / 1024);


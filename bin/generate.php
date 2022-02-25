<?php

use Composer\Autoload\ClassLoader;
use Composer\Util\Filesystem;
use Mrsuh\PhpGenerics\Command\PackageAutoload;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinder;
use Mrsuh\PhpGenerics\Compiler\CompilerInterface;
use Mrsuh\PhpGenerics\Compiler\Monomorphic\Compiler as MonomorphicCompiler;
use Mrsuh\PhpGenerics\Compiler\Printer;
use Mrsuh\PhpGenerics\Compiler\TypeErased\Compiler as TypeErasedCompiler;

require __DIR__ . '/../vendor/autoload.php';

$type = $argv[1];

if (!in_array($type, [CompilerInterface::MONOMORPHIC, CompilerInterface::TYPE_ERASED])) {
    echo 'Invalid argument "type"' . PHP_EOL;
    exit(1);
}

$psr4Prefix = 'Test\\';

$timeStart = microtime(true);

$basePath = $argv[2];
if (empty($basePath) || !is_dir($basePath)) {
    echo "Base path doesn't exists\n";
    exit(1);
}

$inputPath  = $basePath . DIRECTORY_SEPARATOR . 'input';
$outputPath = $basePath . DIRECTORY_SEPARATOR . 'output';

$classLoader = new ClassLoader();
$classLoader->setPsr4($psr4Prefix, $inputPath);
$classFinder = new ClassFinder($classLoader);
$printer     = new Printer();
$filesystem  = new Filesystem();
$filesystem->ensureDirectoryExists($inputPath);
$filesystem->removeDirectory($outputPath);
$filesystem->ensureDirectoryExists($outputPath);

$package = new PackageAutoload('Test\\', $basePath, 'input', 'output');

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

foreach ($result->getConcreteClasses() as $concreteClass) {
    $concreteFilePath = $outputPath . DIRECTORY_SEPARATOR . ltrim($package->getRelativeFilePathByClassFqn($concreteClass->fqn), DIRECTORY_SEPARATOR);
    $filesystem->ensureDirectoryExists(dirname($concreteFilePath));
    file_put_contents($concreteFilePath, $printer->printFile($concreteClass->ast));

    printf("  - %s\n", $concreteClass->fqn);
}
$timeFin = microtime(true);

printf("Generated %d concrete classes in %.3f seconds, %.3f MB memory used\n", count($result->getConcreteClasses()), $timeFin - $timeStart, memory_get_usage(true) / 1024 / 1024);


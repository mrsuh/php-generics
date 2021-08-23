<?php

namespace Mrsuh\PhpGenerics\Autoload;

use Composer\Util\Filesystem;

class AutoloadGenerator extends \Composer\Autoload\AutoloadGenerator
{
    /**
     * @param string $basePath
     * @param string $vendorPath
     * @param string $path
     */
    public function getPathCode(Filesystem $filesystem, $basePath, $vendorPath, $path): string
    {
        return parent::getPathCode($filesystem, $basePath, $vendorPath, $path);
    }

    /**
     * @param string $basePath
     * @param string $vendorPath
     * @param string $path
     */
    public function getAbsolutePath(Filesystem $filesystem, $basePath, $vendorPath, $path): string
    {
        if (!$filesystem->isAbsolutePath($path)) {
            $path = $basePath . '/' . $path;
        }
        $path = $filesystem->normalizePath($path);

        $baseDir = '';
        if (strpos($path . '/', $vendorPath . '/') === 0) {
            $path    = substr($path, strlen($vendorPath));
            $baseDir = $vendorPath;
        } else {
            $path = $filesystem->normalizePath($filesystem->findShortestPath($basePath, $path, true));
            if (!$filesystem->isAbsolutePath($path)) {
                $baseDir = $basePath;
                $path    = '/' . $path;
            }
        }

        return $baseDir . $path;
    }
}

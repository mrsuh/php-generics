<?php

namespace Mrsuh\PhpGenerics\Autoload;

use Composer\Util\Filesystem;

class AutoloadGenerator extends \Composer\Autoload\AutoloadGenerator
{
    public function getPathCode(Filesystem $filesystem, $basePath, $vendorPath, $path): string
    {
        return parent::getPathCode($filesystem, $basePath, $vendorPath, $path);
    }
}

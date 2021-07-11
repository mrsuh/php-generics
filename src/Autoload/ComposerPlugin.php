<?php

namespace Mrsuh\PhpGenerics\Autoload;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;

    private static $activated = false;

    public function activate(Composer $composer, IOInterface $io): void
    {
        self::$activated = true;
        $this->composer  = $composer;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        self::$activated = false;
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {

    }

    public function updateAutoloadFile(): void
    {
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');

        if (!is_file($autoloadFile = $vendorDir . '/autoload.php')) {
            return;
        }

        $autoloadFileContent = file_get_contents($autoloadFile);

        preg_match('/return\s+(\S+)::getLoader\(\);/', $autoloadFileContent, $match);
        if (!isset($match[1])) {
            return;
        }

        $composerAutoloadInitedClassName = $match[1];

        $templateFilePath    = __DIR__ . '/autoload.php.template';
        $templateFileContent = file_get_contents($templateFilePath);

        $templateFileContent = str_replace('{ComposerAutoloaderInited}', $composerAutoloadInitedClassName, $templateFileContent);

        file_put_contents($autoloadFile, $templateFileContent);
    }

    public static function getSubscribedEvents(): array
    {
        if (!self::$activated) {
            return [];
        }

        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'updateAutoloadFile',
        ];
    }
}

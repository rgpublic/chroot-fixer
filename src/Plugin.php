<?php

namespace Pw6\ChrootFixer;

use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\ScriptEvents;
use Composer\Script\Event;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(\Composer\Composer $composer, \Composer\IO\IOInterface $io)
    {
        // Noting needed here
    }

    public function deactivate(\Composer\Composer $composer, \Composer\IO\IOInterface $io)
    {
        // Nothing needed here
    }

    public function uninstall(\Composer\Composer $composer, \Composer\IO\IOInterface $io)
    {
        // Nothing needed here
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump',
        ];
    }

    public function onPostAutoloadDump(Event $event)
    {
        $io = $event->getIO();
        $io->write("<info>ChrootFixer: normalizing static autoload paths</info>");

        $composer = $event->getComposer();
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $composerDir = $vendorDir . '/composer';

        $files = [
            $composerDir . '/autoload_static.php',
            $composerDir . '/autoload_classmap.php',
            $composerDir . '/autoload_psr4.php',
        ];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $contents = file_get_contents($file);

// Fix Drupal-style broken paths: '/' . '/web/...', '/' . '//web/...'
$contents = preg_replace(
    "#'/'\s*\.\s*'/+([^']+)'#",
    "__DIR__ . '/../../$1'",
    $contents
);

if (basename($file) === 'autoload_static.php') {
	$contents.="require_once(dirname(__FILE__).'/../pw6/chroot-fixer/src/DrushDrupalFinder.php');";
}

            file_put_contents($file, $contents);
            $io->write("<info>ChrootFixer: fixed $file</info>");
        }
    }
}

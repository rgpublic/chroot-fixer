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
        $composer = $event->getComposer();
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir);

        // --- Fix index.php SCRIPT_FILENAME (Drupal 11.4+ / symfony/runtime) ---
        $webRoot = $composer->getPackage()->getExtra()['drupal-scaffold']['locations']['web-root'] ?? 'web';
        $indexFile = $projectRoot . '/' . trim($webRoot, '/') . '/index.php';

        if (file_exists($indexFile)) {
            $indexContents = file_get_contents($indexFile);
            $needle = "require_once 'autoload_runtime.php';";
            if (strpos($indexContents, $needle) !== false && strpos($indexContents, "SCRIPT_FILENAME") === false) {
                $patched = str_replace(
                    $needle,
                    "\$_SERVER['SCRIPT_FILENAME'] = __FILE__;\n{$needle}",
                    $indexContents
                );
                file_put_contents($indexFile, $patched);
                $io->write("<info>ChrootFixer: injected SCRIPT_FILENAME override in {$webRoot}/index.php</info>");
            }
        }

        $io->write("<info>ChrootFixer: normalizing static autoload paths</info>");

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
            // Note: This code is sometimes loaded via eval an __DIR__ is replaced with the fixed plugin path.
            // We don't want that so we obfuscate the __DIR__ constant by concatenation.
            $contents = preg_replace(
                "#'/'\s*\.\s*'/+([^']+)'#",
                "__D"."IR__ . '/../../$1'",
                $contents
            );

            if (basename($file) === 'autoload_static.php') {
                $contents.="if (is_file(dirname(__FILE__).'/../pw6/chroot-fixer/src/DrushDrupalFinder.php')) {\n";
                $contents.="    require_once(dirname(__FILE__).'/../pw6/chroot-fixer/src/DrushDrupalFinder.php');\n";
                $contents.="}\n";
            }

            file_put_contents($file, $contents);
            $io->write("<info>ChrootFixer: fixed ".ltrim($file,'/')."</info>");
        }
    }
}

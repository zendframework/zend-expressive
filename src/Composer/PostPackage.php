<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Composer;

use Composer\Installer\PackageEvent;

/**
 * Events that integrate within Composer workflow, and allow to automatically merge config from middlewares
 *
 * Some middlewares (like Zend\Expressive), require the use of global configuration to be merged, so that components
 * like container can be used.
 *
 * In order to use this feature, you need to add those lines into your `composer.json` file:
 *
 *   "scripts": {
 *     "post-package-install": [
 *       "Zend\\Expressive\\Composer\\PostPackage::install"
 *     ],
 *     "post-package-update": [
 *       "Zend\\Expressive\\Composer\\PostPackage::update"
 *    ]
 *  }
 *
 * On the other hand, compliant third-party packages must make sure that their "extra" parts in `composer.json`
 * include the necessary information. Here is the required format:
 *
 *   "extra": {
 *     "middleware_config": [
 *       {
 *         "path": "config/general.php"
 *       },
 *       {
 *         "path": "config/view.php",
 *         "only_if": "zend-expressive-view"
 *       }
 *     ]
 *   }
 */
class PostPackage
{
    /**
     * @param PackageEvent $event
     */
    public static function install(PackageEvent $event)
    {
        static::doInstall($event, true);
    }

    /**
     * @param PackageEvent $event
     */
    public static function update(PackageEvent $event)
    {
        static::doInstall($event, false);
    }

    private static function doInstall(PackageEvent $event, $overrideConfig)
    {
        $applicationExtra = $event->getComposer()->getPackage()->getExtra();
        $flags            = isset($applicationExtra['zend_expressive_flags'])
            ? $applicationExtra['zend_expressive_flags']
            : [];

        /** @var \Composer\Package\CompletePackage $installedPackage */
        $installedPackage = $event->getOperation()->getPackage();
        $installedExtra   = $installedPackage->getExtra();

        $middlewareConfig = isset($installedExtra['middleware_config']) ? $installedExtra['middleware_config'] : [];

        foreach ($middlewareConfig as $singleConfig) {
            // If the config has an "only_if" key, this means the config must only be merged if this option
            // was passed as part of the consumer
            if (isset($singleConfig['only_if']) && !in_array($singleConfig['only_if'], $flags, true)) {
                continue;
            }

            // Get the path of the file

            $path = // . $singleConfig['path'];

            // Copy the file into consumer application /config path, under a unique key. If overrideConfig is true (which
            // happens during install), then the file is copy-pasted as it. If it's false (which happens during update),
            // we merge the existing file, by making sure that the order is correct so that existing keys are not overriden
        }
    }
}
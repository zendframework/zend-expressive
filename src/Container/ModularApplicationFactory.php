<?php

namespace Zend\Expressive\Container;

use Zend\Config\Factory as ConfigFactory;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory as ExpressiveApplicationFactory;
use Zend\ModuleManager\Listener\ConfigListener;
use Zend\ModuleManager\Listener\ModuleResolverListener;
use Zend\ModuleManager\ModuleEvent;
use Zend\ModuleManager\ModuleManager;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;

class ModularApplicationFactory
{
    /**
     * @param $systemConfig
     * @return array application config
     */
    private function initModules($systemConfig)
    {
        $modules = is_array($systemConfig['modules'])?$systemConfig['modules']:[];
        $manager = new ModuleManager($modules);
        $manager->getEventManager()->attach(ModuleEvent::EVENT_LOAD_MODULE_RESOLVE, new ModuleResolverListener());

        $configListener = new ConfigListener();
        $manager->getEventManager()->attach($configListener);
        $manager->loadModules();
        $moduleConfig = $configListener->getMergedConfig(false);

        if (!isset($systemConfig['module_listener_options'])
            || !isset($systemConfig['module_listener_options']['config_glob_paths'])
        ) {
            return $moduleConfig;
        }

        $additionalConfig = ConfigFactory::fromFiles(
            glob($systemConfig['module_listener_options']['config_glob_paths'], GLOB_BRACE)
        );

        return ArrayUtils::merge($moduleConfig, $additionalConfig);
    }

    private function initServiceManager($applicationConfig)
    {
        $smConfig = new Config(isset($applicationConfig['service_manager'])?$applicationConfig['service_manager']:[]);
        $serviceManager = new ServiceManager($smConfig);
        $serviceManager->setService('Config', $applicationConfig);
        $serviceManager->setAlias('Configuration', 'Config');
        return $serviceManager;
    }

    /**
     * @param array $systemConfig
     * @return Application
     */
    public function create(array $systemConfig)
    {
        $applicationConfig = $this->initModules($systemConfig);
        $serviceManager = $this->initServiceManager($applicationConfig);

        $defaultFactory = new ExpressiveApplicationFactory();
        return $defaultFactory($serviceManager);
    }
}

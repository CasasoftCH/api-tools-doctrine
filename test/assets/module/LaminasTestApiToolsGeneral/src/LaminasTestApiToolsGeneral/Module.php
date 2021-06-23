<?php

declare(strict_types=1);

namespace LaminasTestApiToolsGeneral;

use Laminas\ApiTools\Provider\ApiToolsProviderInterface;
use Laminas\EventManager\EventInterface;
use Laminas\Loader\StandardAutoloader;
use Laminas\ModuleManager\Feature\BootstrapListenerInterface;
use LaminasTestApiToolsGeneral\Listener\EventCatcher;

class Module implements ApiToolsProviderInterface, BootstrapListenerInterface
{
    public function getConfig()
    {
        return include __DIR__ . '/../../config/module.config.php';
    }

    /**
     * @return string[][][]
     * @psalm-return array<string, array<string, array<string, string>>>
     */
    public function getAutoloaderConfig(): array
    {
        return [
            StandardAutoloader::class => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__,
                ],
            ],
        ];
    }

    /**
     * Add the event catcher
     *
     * @return void
     */
    public function onBootstrap(EventInterface $e)
    {
        $application    = $e->getApplication();
        $serviceManager = $application->getServiceManager();
        $eventManager   = $application->getEventManager();
        $sharedEvents   = $eventManager->getSharedManager();

        /** @var EventCatcher $eventCatcher */
        $eventCatcher = $serviceManager->get(EventCatcher::class);
        $eventCatcher->attachShared($sharedEvents);
    }
}

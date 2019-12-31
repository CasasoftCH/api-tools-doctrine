<?php

namespace Laminas\ApiTools\Doctrine\Server\Resource;

use Doctrine\Common\Persistence\ObjectManager;
use DoctrineModule\Stdlib\Hydrator;
use Exception;
use Laminas\ApiTools\Doctrine\Server\Collection\Query;
use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\Hydrator\HydratorInterface;

/**
 * Class AbstractDoctrineResourceFactory
 *
 * @package Laminas\ApiTools\Doctrine\Server\Resource
 */
class DoctrineResourceFactory implements AbstractFactoryInterface
{

    /**
     * Cache of canCreateServiceWithName lookups
     * @var array
     */
    protected $lookupCache = array();

    /**
     * Determine if we can create a service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     *
     * @return bool
     * @throws \Laminas\ServiceManager\Exception\ServiceNotFoundException
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        if (array_key_exists($requestedName, $this->lookupCache)) {
            return $this->lookupCache[$requestedName];
        }

        if (!$serviceLocator->has('Config')) {
            // @codeCoverageIgnoreStart
            return false;
        }
            // @codeCoverageIgnoreEnd

        // Validate object is set
        $config = $serviceLocator->get('Config');

        if (!isset($config['api-tools']['doctrine-connected'])
            || !is_array($config['api-tools']['doctrine-connected'])
            || !isset($config['api-tools']['doctrine-connected'][$requestedName])
        ) {
            $this->lookupCache[$requestedName] = false;

            return false;
        }

        // Validate if class a valid DoctrineResource
        $className = isset($config['class']) ? $config['class'] : $requestedName;
        $className = $this->normalizeClassname($className);
        $reflection = new \ReflectionClass($className);
        if (!$reflection->isSubclassOf('\Laminas\ApiTools\Doctrine\Server\Resource\DoctrineResource')) {
            // @codeCoverageIgnoreStart
            throw new ServiceNotFoundException(
                sprintf(
                    '%s requires that a valid DoctrineResource "class" is specified for listener %s; no service found',
                    __METHOD__,
                    $requestedName
                )
            );
        }
        // @codeCoverageIgnoreEnd

        // Validate object manager
        $config = $config['api-tools']['doctrine-connected'];
        if (!isset($config[$requestedName]) || !isset($config[$requestedName]['object_manager'])) {
            // @codeCoverageIgnoreStart
            throw new ServiceNotFoundException(
                sprintf(
                    '%s requires that a valid "object_manager" is specified for listener %s; no service found',
                    __METHOD__,
                    $requestedName
                )
            );
        }
            // @codeCoverageIgnoreEnd

        $this->lookupCache[$requestedName] = true;

        return true;
    }

    /**
     * Create service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     *
     * @return DoctrineResource
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        $config = $serviceLocator->get('Config');
        $doctrineConnectedConfig = $config['api-tools']['doctrine-connected'][$requestedName];

        foreach ($config['api-tools-rest'] as $restControllerConfig) {
            if ($restControllerConfig['listener'] == $requestedName) {
                $restConfig = $restControllerConfig;
                break;
            }
        }

        $className = isset($doctrineConnectedConfig['class']) ? $doctrineConnectedConfig['class'] : $requestedName;
        $className = $this->normalizeClassname($className);

        $objectManager = $this->loadObjectManager($serviceLocator, $doctrineConnectedConfig);
        $hydrator = $this->loadHydrator($serviceLocator, $doctrineConnectedConfig, $objectManager);
        $queryProviders = $this->loadQueryProviders($serviceLocator, $doctrineConnectedConfig, $objectManager);
        $queryCreateFilter = $this->loadQueryCreateFilter($serviceLocator, $doctrineConnectedConfig, $objectManager);
        $configuredListeners = $this->loadConfiguredListeners($serviceLocator, $doctrineConnectedConfig);

        $listener = new $className();
        $listener->setObjectManager($objectManager);
        $listener->setHydrator($hydrator);
        $listener->setQueryProviders($queryProviders);
        $listener->setQueryCreateFilter($queryCreateFilter);
        $listener->setServiceManager($serviceLocator);
        $listener->setEntityIdentifierName($restConfig['entity_identifier_name']);
        if (count($configuredListeners)) {
            foreach ($configuredListeners as $configuredListener) {
                $listener->getEventManager()->attach($configuredListener);
            }
        }

        return $listener;
    }

    /**
     * @param $className
     *
     * @return string
     */
    protected function normalizeClassname($className)
    {
        return '\\' . ltrim($className, '\\');
    }

    /**
     * @param ServiceLocatorInterface $serviceLocator
     * @param                         $config
     *
     * @return ObjectManager
     * @throws \Laminas\ServiceManager\Exception\ServiceNotCreatedException
     */
    protected function loadObjectManager(ServiceLocatorInterface $serviceLocator, $config)
    {
        if ($serviceLocator->has($config['object_manager'])) {
            $objectManager = $serviceLocator->get($config['object_manager']);
        } else {
            // @codeCoverageIgnoreStart
            throw new ServiceNotCreatedException('The object_manager could not be found.');
        }
        // @codeCoverageIgnoreEnd
        return $objectManager;
    }

    /**
     * @param ServiceLocatorInterface $serviceLocator
     * @param                         $config
     *
     * @return HydratorInterface
     */
    protected function loadHydrator(ServiceLocatorInterface $serviceLocator, $config)
    {
        // @codeCoverageIgnoreStart
        if (!isset($config['hydrator'])) {
            return null;
        }

        if (!$serviceLocator->has('HydratorManager')) {
            return null;
        }

        $hydratorManager = $serviceLocator->get('HydratorManager');
        if (!$hydratorManager->has($config['hydrator'])) {
            return null;
        }
        // @codeCoverageIgnoreEnd
        return $hydratorManager->get($config['hydrator']);
    }

    /**
     * @param ServiceLocatorInterface $serviceLocator
     * @param                         $config
     * @param                         $objectManager
     *
     * @return Laminas\ApiTools\Doctrine\Query\Provider\FetchAll\FetchAllQueryProviderInterface
     * @throws \Laminas\ServiceManager\Exception\ServiceNotCreatedException
     */
    protected function loadQueryCreateFilter(ServiceLocatorInterface $serviceLocator, $config, $objectManager)
    {
        $createFilterManager = $serviceLocator->get('LaminasApiToolsDoctrineQueryCreateFilterManager');
        $filterManagerAlias = (isset($config['query_create_filter'])) ? $config['query_create_filter']: 'default';

        $queryCreateFilter = $createFilterManager->get($filterManagerAlias);

        // Load the oAuth2 server
        $oAuth2Server = false;
        try {
            $oAuth2ServerFactory = $serviceLocator->get('Laminas\ApiTools\OAuth2\Service\OAuth2Server');
            $oAuth2Server = $oAuth2ServerFactory();
            $queryCreateFilter->setOAuth2Server($oAuth2Server);
        } catch (Exception $e) {
            // If no oAuth2 server that's just fine.
        }

        // Set object manager for all query providers
        $queryCreateFilter ->setObjectManager($objectManager);

        return $queryCreateFilter;
    }


    /**
     * @param ServiceLocatorInterface $serviceLocator
     * @param                         $config
     * @param                         $objectManager
     *
     * @return Laminas\ApiTools\Doctrine\Query\Provider\FetchAll\FetchAllQueryProviderInterface
     * @throws \Laminas\ServiceManager\Exception\ServiceNotCreatedException
     */
    protected function loadQueryProviders(ServiceLocatorInterface $serviceLocator, $config, $objectManager)
    {
        $queryProviders = array();
        $queryManager = $serviceLocator->get('LaminasApiToolsDoctrineQueryProviderManager');

        // Load default query provider
        if (class_exists('\\Doctrine\\ORM\\EntityManager')
            && $objectManager instanceof \Doctrine\ORM\EntityManager
        ) {
            $queryProviders['default'] = $queryManager->get('default_orm');
        } elseif (class_exists('\\Doctrine\\ODM\\MongoDB\\DocumentManager')
            && $objectManager instanceof \Doctrine\ODM\MongoDB\DocumentManager
        ) {
            $queryProviders['default'] = $queryManager->get('default_odm');
        } else {
            // @codeCoverageIgnoreStart
            throw new ServiceNotCreatedException('No valid doctrine module is found for objectManager.');
        }
        // @codeCoverageIgnoreEnd

        // Load custom query providers
        if (isset($config['query_providers'])) {
            foreach ($config['query_providers'] as $method => $plugin) {
                $queryProviders[$method] = $queryManager->get($plugin);
            }
        }

        // Load the oAuth2 server
        $oAuth2Server = false;
        try {
            $oAuth2ServerFactory = $serviceLocator->get('Laminas\ApiTools\OAuth2\Service\OAuth2Server');
            $oAuth2Server = $oAuth2ServerFactory();
        } catch (Exception $e) {
            // If no oAuth2 server that's just fine.
        }

        // Set object manager for all query providers
        foreach ($queryProviders as $provider) {
            $provider->setObjectManager($objectManager);
            if ($oAuth2Server) {
                $provider->setOAuth2Server($oAuth2Server);
            }
        }

        return $queryProviders;
    }

    /**
     * @param ServiceLocatorInterface $serviceLocator
     * @param                         $config
     *
     * @return array
     */
    protected function loadConfiguredListeners(ServiceLocatorInterface $serviceLocator, $config)
    {
        if (!isset($config['listeners'])) {
            return array();
        }

        $listeners = array();
        foreach ($config['listeners'] as $listener) {
            $listeners[] = $serviceLocator->get($listener);
        }
        return $listeners;
    }
}

<?php

namespace Laminas\ApiTools\Doctrine\Server\Query\Provider;

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\ResourceEvent;
use OAuth2\Request as OAuth2Request;
use OAuth2\Server as OAuth2Server;

/**
 * Class FetchAllOrm
 *
 * @package Laminas\ApiTools\Doctrine\Server\Query\Provider
 */
class DefaultOrm extends AbstractQueryProvider
{
    /**
     * @param string $entityClass
     * @param array  $parameters
     *
     * @return mixed This will return an ORM or ODM Query\Builder
     */
    public function createQuery(ResourceEvent $event, $entityClass, $parameters)
    {
        $queryBuilder = $this->getObjectManager()->createQueryBuilder();
        $queryBuilder->select('row')
            ->from($entityClass, 'row');

        return $queryBuilder;
    }
}

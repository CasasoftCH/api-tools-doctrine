<?php

declare(strict_types=1);

namespace LaminasTest\ApiTools\Doctrine\Server\Validator;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManager;
use DoctrineModule\Validator\NoObjectExists as NoObjectExistsOrigin;
use Laminas\ApiTools\Doctrine\Server\Validator\NoObjectExists;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

class NoObjectExistsFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy|ServiceManager */
    private $serviceManager;

    /** @var ValidatorPluginManager */
    private $validators;

    /** @var ObjectProphecy|ObjectRepository */
    private $objectRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $config           = include __DIR__ . '/../../../../config/server.config.php';
        $validatorsConfig = $config['validators'];

        $this->objectRepository = $this->prophesize(ObjectRepository::class);
        $this->serviceManager   = $this->prophesize(ServiceManager::class);

        $this->validators = new ValidatorPluginManager($this->serviceManager->reveal(), $validatorsConfig);
    }

    public function testCreate(): void
    {
        $validator = $this->validators->get(
            NoObjectExists::class,
            [
                'object_repository' => $this->objectRepository->reveal(),
                'fields'            => 'foo',
            ]
        );

        $this->assertInstanceOf(NoObjectExistsOrigin::class, $validator);
    }

    public function testCreateWithEntityClassProvided(): void
    {
        $entityManager = $this->prophesize(EntityManager::class);
        $entityManager->getRepository('MyEntity')->willReturn($this->objectRepository->reveal());

        $this->serviceManager->has('MvcTranslator')->willReturn(false);
        $this->serviceManager->get(EntityManager::class)->willReturn($entityManager->reveal());

        $validator = $this->validators->get(
            NoObjectExists::class,
            [
                'entity_class' => 'MyEntity',
                'fields'       => 'foo',
            ]
        );

        $this->assertInstanceOf(NoObjectExistsOrigin::class, $validator);
    }
}

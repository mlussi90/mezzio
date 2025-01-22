<?php

declare(strict_types=1);

namespace MezzioTest\Container;

use Mezzio\Container\Exception\InvalidServiceException;
use Mezzio\Container\ResponseFactoryFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function class_exists;
use function spl_autoload_functions;
use function spl_autoload_register;
use function spl_autoload_unregister;

class ResponseFactoryFactoryWithoutDiactorosTest extends TestCase
{
    private ContainerInterface $container;

    private ResponseFactoryFactory $factory;

    private array $autoloadFunctions = [];

    protected function setUp(): void
    {
        $this->markTestSkipped('De-registering the autoloader breaks PHPUnit since version 10');

        class_exists(InvalidServiceException::class);

        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory   = new ResponseFactoryFactory();

        $this->autoloadFunctions = spl_autoload_functions();
        foreach ($this->autoloadFunctions as $autoloader) {
            spl_autoload_unregister($autoloader);
        }
    }

    private function reloadAutoloaders(): void
    {
        foreach ($this->autoloadFunctions as $autoloader) {
            spl_autoload_register($autoloader);
        }
    }

    public function testFactoryRaisesAnExceptionIfDiactorosIsNotLoaded(): void
    {
        $this->expectException(InvalidServiceException::class);
        $this->expectExceptionMessage('laminas/laminas-diactoros');

        try {
            ($this->factory)($this->container);
        } finally {
            $this->reloadAutoloaders();
        }
    }
}

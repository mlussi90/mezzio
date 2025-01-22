<?php

declare(strict_types=1);

namespace MezzioTest\Container;

use Closure;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\ServerRequestFilter\FilterServerRequestInterface;
use Mezzio\Container\ServerRequestFactoryFactory;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class ServerRequestFactoryFactoryTest extends TestCase
{
    public function testFactoryReturnsCallable(): callable
    {
        $container = $this->createMock(ContainerInterface::class);
        $factory   = new ServerRequestFactoryFactory();

        $generatedFactory = $factory($container);

        $this->assertIsCallable($generatedFactory);

        return $generatedFactory;
    }

    /**
     * Some containers do not allow returning generic PHP callables, and will
     * error when one is returned; one example is Auryn. As such, the factory
     * cannot simply return a callable referencing the
     * ServerRequestFactory::fromGlobals method, but must be decorated as a
     * closure.
     */
    #[Depends('testFactoryReturnsCallable')]
    public function testFactoryIsAClosure(callable $factory): void
    {
        $this->assertNotSame([ServerRequestFactory::class, 'fromGlobals'], $factory);
        $this->assertNotSame(ServerRequestFactory::class . '::fromGlobals', $factory);
        $this->assertInstanceOf(Closure::class, $factory);
    }

    public function testConsumesFilterServerRequestInterfaceServiceWhenPresent(): void
    {
        $request = new ServerRequest();
        $filter  = $this->createMock(FilterServerRequestInterface::class);
        $filter
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->isInstanceOf(ServerRequestInterface::class))
            ->willReturn($request);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->once())
            ->method('has')
            ->with(FilterServerRequestInterface::class)
            ->willReturn(true);
        $container
            ->expects($this->once())
            ->method('get')
            ->with(FilterServerRequestInterface::class)
            ->willReturn($filter);

        $factory          = new ServerRequestFactoryFactory();
        $generatedFactory = $factory($container);

        $this->assertSame($request, $generatedFactory());
    }
}

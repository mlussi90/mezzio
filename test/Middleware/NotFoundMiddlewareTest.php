<?php

/**
 * @see       https://github.com/mezzio/mezzio for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Middleware;

use Mezzio\Handler\NotFoundHandler;
use Mezzio\Middleware\NotFoundMiddleware;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NotFoundMiddlewareTest extends TestCase
{
    /** @var NotFoundHandler|ObjectProphecy */
    private $internal;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var RequestHandlerInterface|ObjectProphecy */
    private $handler;

    public function setUp()
    {
        $this->internal = $this->prophesize(NotFoundHandler::class);
        $this->request  = $this->prophesize(ServerRequestInterface::class);

        $this->handler = $this->prophesize(RequestHandlerInterface::class);
        $this->handler->handle(Argument::type(ServerRequestInterface::class))->shouldNotBeCalled();
    }

    public function testImplementsInteropMiddleware()
    {
        $handler = new NotFoundMiddleware($this->internal->reveal());
        $this->assertInstanceOf(MiddlewareInterface::class, $handler);
    }

    public function testProxiesToInternalHandler()
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $this->internal
            ->handle(Argument::that([$this->request, 'reveal']))
            ->willReturn($response);

        $handler = new NotFoundMiddleware($this->internal->reveal());
        $this->assertEquals($response, $handler->process($this->request->reveal(), $this->handler->reveal()));
    }
}

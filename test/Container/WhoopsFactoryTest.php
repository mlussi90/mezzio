<?php

declare(strict_types=1);

namespace MezzioTest\Container;

use ArrayAccess;
use Mezzio\Container\WhoopsFactory;
use MezzioTest\InMemoryContainer;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Whoops\Util\Misc as WhoopsUtil;

use function method_exists;
use function sprintf;

#[CoversClass(WhoopsFactory::class)]
class WhoopsFactoryTest extends TestCase
{
    private InMemoryContainer $container;

    private WhoopsFactory $factory;

    public function setUp(): void
    {
        $this->container = new InMemoryContainer();
        $this->container->set('Mezzio\WhoopsPageHandler', $this->createMock(PrettyPageHandler::class));

        $this->factory = new WhoopsFactory();
    }

    public function assertWhoopsContainsHandler(string $type, Whoops $whoops, ?string $message = null): void
    {
        $message = $message ?: sprintf('Failed to assert whoops runtime composed handler of type %s', $type);
        $r       = new ReflectionProperty($whoops, 'handlerStack');
        $r->setAccessible(true);
        $stack = $r->getValue($whoops);

        $found = false;
        foreach ($stack as $handler) {
            if ($handler instanceof $type) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, $message);
    }

    public function testReturnsAWhoopsRuntimeWithPageHandlerComposed(): void
    {
        $factory = $this->factory;
        $result  = $factory($this->container);
        $this->assertInstanceOf(Whoops::class, $result);
        $this->assertWhoopsContainsHandler(PrettyPageHandler::class, $result);
    }

    public function testWillInjectJsonResponseHandlerIfConfigurationExpectsIt(): void
    {
        $config = ['whoops' => ['json_exceptions' => ['display' => true]]];
        $this->container->set('config', $config);

        $factory = $this->factory;
        $result  = $factory($this->container);
        $this->assertInstanceOf(Whoops::class, $result);
        $this->assertWhoopsContainsHandler(PrettyPageHandler::class, $result);
        $this->assertWhoopsContainsHandler(JsonResponseHandler::class, $result);
    }

    /**
     * @param bool  $showsTrace
     * @param bool  $isAjaxOnly
     * @param bool  $requestIsAjax
     */
    #[DataProvider('provideConfig')]
    #[Depends('testWillInjectJsonResponseHandlerIfConfigurationExpectsIt')]
    #[BackupGlobals(true)]
    public function testJsonResponseHandlerCanBeConfigured($showsTrace, $isAjaxOnly, $requestIsAjax): void
    {
        // Set for Whoops 2.x json handler detection
        if ($requestIsAjax) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        }

        $config = [
            'whoops' => [
                'json_exceptions' => [
                    'display'    => true,
                    'show_trace' => $showsTrace,
                    'ajax_only'  => $isAjaxOnly,
                ],
            ],
        ];

        $this->container->set('config', $config);

        $factory = $this->factory;
        $whoops  = $factory($this->container);
        $handler = $whoops->popHandler();

        // If ajax only, not ajax request and Whoops 2, it does not inject JsonResponseHandler
        if (
            $isAjaxOnly
            && ! $requestIsAjax
            && method_exists(WhoopsUtil::class, 'isAjaxRequest')
        ) {
            self::assertInstanceOf(PrettyPageHandler::class, $handler);

            // Skip remaining assertions
            return;
        }

        self::assertInstanceOf(JsonResponseHandler::class, $handler);
        self::assertSame($showsTrace, $handler->addTraceToOutput());

        if (method_exists($handler, 'onlyForAjaxRequests')) {
            self::assertSame($isAjaxOnly, $handler->onlyForAjaxRequests());
        }
    }

    /**
     * @return iterable<string, bool[]>
     */
    public static function provideConfig(): iterable
    {
        // @codingStandardsIgnoreStart
        //    test case                        => showsTrace, isAjaxOnly, requestIsAjax
        yield 'Shows trace'                    => [true,      true,       true];
        yield 'Does not show trace'            => [false,     true,       true];

        yield 'Ajax only, request is ajax'     => [true,      true,       true];
        yield 'Ajax only, request is not ajax' => [true,      true,       false];

        yield 'Not ajax only'                  => [true,      false,      false];
        // @codingStandardsIgnoreEnd
    }

    public function testCanHandleConfigWithArrayAccess(): void
    {
        $config = $this->createMock(ArrayAccess::class);
        $this->container->set('config', $config);

        $factory = new WhoopsFactory();
        $factory($this->container);
        $this->expectNotToPerformAssertions();
    }
}

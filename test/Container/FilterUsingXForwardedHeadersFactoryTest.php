<?php

declare(strict_types=1);

namespace MezzioTest\Container;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\ServerRequestFilter\FilterUsingXForwardedHeaders;
use Mezzio\ConfigProvider;
use Mezzio\Container\FilterUsingXForwardedHeadersFactory;
use MezzioTest\InMemoryContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilterUsingXForwardedHeadersFactoryTest extends TestCase
{
    private InMemoryContainer $container;

    public function setUp(): void
    {
        $this->container = new InMemoryContainer();
        $this->container->set('config', []);
    }

    public function generateServerRequest(array $headers, array $server, string $baseUrlString): ServerRequest
    {
        return new ServerRequest($server, [], $baseUrlString, 'GET', 'php://temp', $headers);
    }

    /** @psalm-return iterable<string, array{0: string}> */
    public static function randomIpGenerator(): iterable
    {
        yield 'class-a' => ['10.1.1.1'];
        yield 'class-c' => ['192.168.1.1'];
        yield 'localhost' => ['127.0.0.1'];
        yield 'public' => ['4.4.4.4'];
    }

    #[DataProvider('randomIpGenerator')]
    public function testIfNoConfigPresentFactoryReturnsFilterThatDoesNotTrustAny(string $remoteAddr): void
    {
        $factory = new FilterUsingXForwardedHeadersFactory();
        $filter  = $factory($this->container);
        $request = $this->generateServerRequest(
            [
                'Host'                                     => 'localhost',
                FilterUsingXForwardedHeaders::HEADER_HOST  => 'api.example.com',
                FilterUsingXForwardedHeaders::HEADER_PROTO => 'https',
            ],
            [
                'REMOTE_ADDR' => $remoteAddr,
            ],
            'http://localhost/foo/bar',
        );

        $filteredRequest = $filter($request);
        $this->assertSame($request, $filteredRequest);
    }

    /** @psalm-return iterable<string, array{0: string, 1: array<string, string>}> */
    public static function trustAnyProvider(): iterable
    {
        $headers = [
            FilterUsingXForwardedHeaders::HEADER_HOST  => 'api.example.com',
            FilterUsingXForwardedHeaders::HEADER_PROTO => 'https',
            FilterUsingXForwardedHeaders::HEADER_PORT  => '4443',
        ];

        foreach (self::randomIpGenerator() as $name => $arguments) {
            yield $name => [
                $arguments[0],
                $headers,
            ];
        }
    }

    #[DataProvider('trustAnyProvider')]
    public function testIfWildcardProxyAddressSpecifiedReturnsFilterConfiguredToTrustAny(
        string $remoteAddr,
        array $headers
    ): void {
        $headers['Host'] = 'localhost';
        $this->container->set('config', [
            ConfigProvider::DIACTOROS_CONFIG_KEY => [
                ConfigProvider::DIACTOROS_SERVER_REQUEST_FILTER_CONFIG_KEY => [
                    ConfigProvider::DIACTOROS_X_FORWARDED_FILTER_CONFIG_KEY => [
                        ConfigProvider::DIACTOROS_TRUSTED_PROXIES_CONFIG_KEY => ['*'],
                    ],
                ],
            ],
        ]);

        $factory = new FilterUsingXForwardedHeadersFactory();
        $filter  = $factory($this->container);
        $request = $this->generateServerRequest(
            $headers,
            ['REMOTE_ADDR' => $remoteAddr],
            'http://localhost/foo/bar',
        );

        $filteredRequest = $filter($request);
        $this->assertNotSame($request, $filteredRequest);

        $uri = $filteredRequest->getUri();
        $this->assertSame($headers[FilterUsingXForwardedHeaders::HEADER_HOST], $uri->getHost());
        // Port is always cast to int
        $this->assertSame((int) $headers[FilterUsingXForwardedHeaders::HEADER_PORT], $uri->getPort());
        $this->assertSame($headers[FilterUsingXForwardedHeaders::HEADER_PROTO], $uri->getScheme());
    }

    #[DataProvider('randomIpGenerator')]
    public function testEmptyProxiesListDoesNotTrustXForwardedRequests(string $remoteAddr): void
    {
        $this->container->set('config', [
            ConfigProvider::DIACTOROS_CONFIG_KEY => [
                ConfigProvider::DIACTOROS_SERVER_REQUEST_FILTER_CONFIG_KEY => [
                    ConfigProvider::DIACTOROS_X_FORWARDED_FILTER_CONFIG_KEY => [
                        ConfigProvider::DIACTOROS_TRUSTED_PROXIES_CONFIG_KEY => [],
                        ConfigProvider::DIACTOROS_TRUSTED_HEADERS_CONFIG_KEY => [
                            FilterUsingXForwardedHeaders::HEADER_HOST,
                        ],
                    ],
                ],
            ],
        ]);

        $factory = new FilterUsingXForwardedHeadersFactory();
        $filter  = $factory($this->container);
        $request = $this->generateServerRequest(
            [
                'Host'                                     => 'localhost',
                FilterUsingXForwardedHeaders::HEADER_HOST  => 'api.example.com',
                FilterUsingXForwardedHeaders::HEADER_PROTO => 'https',
            ],
            [
                'REMOTE_ADDR' => $remoteAddr,
            ],
            'http://localhost/foo/bar',
        );

        $filteredRequest = $filter($request);
        $this->assertSame($request, $filteredRequest);
    }

    #[DataProvider('randomIpGenerator')]
    public function testMissingHeadersListTrustsAllXForwardedRequestsForMatchedProxies(string $remoteAddr): void
    {
        $this->container->set('config', [
            ConfigProvider::DIACTOROS_CONFIG_KEY => [
                ConfigProvider::DIACTOROS_SERVER_REQUEST_FILTER_CONFIG_KEY => [
                    ConfigProvider::DIACTOROS_X_FORWARDED_FILTER_CONFIG_KEY => [
                        ConfigProvider::DIACTOROS_TRUSTED_PROXIES_CONFIG_KEY => ['0.0.0.0/0'],
                    ],
                ],
            ],
        ]);

        $factory = new FilterUsingXForwardedHeadersFactory();
        $filter  = $factory($this->container);
        $request = $this->generateServerRequest(
            [
                'Host'                                     => 'localhost',
                FilterUsingXForwardedHeaders::HEADER_HOST  => 'api.example.com',
                FilterUsingXForwardedHeaders::HEADER_PROTO => 'https',
                FilterUsingXForwardedHeaders::HEADER_PORT  => '4443',
            ],
            [
                'REMOTE_ADDR' => $remoteAddr,
            ],
            'http://localhost/foo/bar',
        );

        $filteredRequest = $filter($request);
        $this->assertNotSame($request, $filteredRequest);

        $uri = $filteredRequest->getUri();
        $this->assertSame('api.example.com', $uri->getHost());
        $this->assertSame(4443, $uri->getPort());
        $this->assertSame('https', $uri->getScheme());
    }

    #[DataProvider('randomIpGenerator')]
    public function testEmptyHeadersListTrustsNoRequests(string $remoteAddr): void
    {
        $this->container->set('config', [
            ConfigProvider::DIACTOROS_CONFIG_KEY => [
                ConfigProvider::DIACTOROS_SERVER_REQUEST_FILTER_CONFIG_KEY => [
                    ConfigProvider::DIACTOROS_X_FORWARDED_FILTER_CONFIG_KEY => [
                        ConfigProvider::DIACTOROS_TRUSTED_PROXIES_CONFIG_KEY => ['0.0.0.0/0'],
                        ConfigProvider::DIACTOROS_TRUSTED_HEADERS_CONFIG_KEY => [],
                    ],
                ],
            ],
        ]);

        $factory = new FilterUsingXForwardedHeadersFactory();
        $filter  = $factory($this->container);
        $request = $this->generateServerRequest(
            [
                'Host'                                     => 'localhost',
                FilterUsingXForwardedHeaders::HEADER_HOST  => 'api.example.com',
                FilterUsingXForwardedHeaders::HEADER_PROTO => 'https',
                FilterUsingXForwardedHeaders::HEADER_PORT  => '4443',
            ],
            [
                'REMOTE_ADDR' => $remoteAddr,
            ],
            'http://localhost/foo/bar',
        );

        $filteredRequest = $filter($request);
        $this->assertSame($request, $filteredRequest);
    }

    /**
     * @psalm-return iterable<string, array{
     *     0: bool,
     *     1: array<string, array<string, array<string, mixed>>>,
     *     2: array<string, string>,
     *     3: array<string, string>,
     *     4: string,
     *     5: string
     * }>
     */
    public static function trustedProxiesAndHeaders(): iterable
    {
        yield 'single-proxy-single-header' => [
            false,
            [
                ConfigProvider::DIACTOROS_CONFIG_KEY => [
                    ConfigProvider::DIACTOROS_SERVER_REQUEST_FILTER_CONFIG_KEY => [
                        ConfigProvider::DIACTOROS_X_FORWARDED_FILTER_CONFIG_KEY => [
                            ConfigProvider::DIACTOROS_TRUSTED_PROXIES_CONFIG_KEY => ['192.168.1.1'],
                            ConfigProvider::DIACTOROS_TRUSTED_HEADERS_CONFIG_KEY => [
                                FilterUsingXForwardedHeaders::HEADER_HOST,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'Host'                                     => 'localhost',
                FilterUsingXForwardedHeaders::HEADER_HOST  => 'api.example.com',
                FilterUsingXForwardedHeaders::HEADER_PROTO => 'https',
                FilterUsingXForwardedHeaders::HEADER_PORT  => '4443',
            ],
            ['REMOTE_ADDR' => '192.168.1.1'],
            'http://localhost/foo/bar',
            'http://api.example.com/foo/bar',
        ];

        yield 'single-proxy-multi-header' => [
            false,
            [
                ConfigProvider::DIACTOROS_CONFIG_KEY => [
                    ConfigProvider::DIACTOROS_SERVER_REQUEST_FILTER_CONFIG_KEY => [
                        ConfigProvider::DIACTOROS_X_FORWARDED_FILTER_CONFIG_KEY => [
                            ConfigProvider::DIACTOROS_TRUSTED_PROXIES_CONFIG_KEY => ['192.168.1.1'],
                            ConfigProvider::DIACTOROS_TRUSTED_HEADERS_CONFIG_KEY => [
                                FilterUsingXForwardedHeaders::HEADER_HOST,
                                FilterUsingXForwardedHeaders::HEADER_PROTO,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'Host'                                     => 'localhost',
                FilterUsingXForwardedHeaders::HEADER_HOST  => 'api.example.com',
                FilterUsingXForwardedHeaders::HEADER_PROTO => 'https',
                FilterUsingXForwardedHeaders::HEADER_PORT  => '4443',
            ],
            ['REMOTE_ADDR' => '192.168.1.1'],
            'http://localhost/foo/bar',
            'https://api.example.com/foo/bar',
        ];

        yield 'unmatched-proxy-single-header' => [
            true,
            [
                ConfigProvider::DIACTOROS_CONFIG_KEY => [
                    ConfigProvider::DIACTOROS_SERVER_REQUEST_FILTER_CONFIG_KEY => [
                        ConfigProvider::DIACTOROS_X_FORWARDED_FILTER_CONFIG_KEY => [
                            ConfigProvider::DIACTOROS_TRUSTED_PROXIES_CONFIG_KEY => ['192.168.1.1'],
                            ConfigProvider::DIACTOROS_TRUSTED_HEADERS_CONFIG_KEY => [
                                FilterUsingXForwardedHeaders::HEADER_HOST,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'Host'                                     => 'localhost',
                FilterUsingXForwardedHeaders::HEADER_HOST  => 'api.example.com',
                FilterUsingXForwardedHeaders::HEADER_PROTO => 'https',
                FilterUsingXForwardedHeaders::HEADER_PORT  => '4443',
            ],
            ['REMOTE_ADDR' => '192.168.2.1'],
            'http://localhost/foo/bar',
            'http://localhost/foo/bar',
        ];

        yield 'matches-proxy-from-list-single-header' => [
            false,
            [
                ConfigProvider::DIACTOROS_CONFIG_KEY => [
                    ConfigProvider::DIACTOROS_SERVER_REQUEST_FILTER_CONFIG_KEY => [
                        ConfigProvider::DIACTOROS_X_FORWARDED_FILTER_CONFIG_KEY => [
                            ConfigProvider::DIACTOROS_TRUSTED_PROXIES_CONFIG_KEY => [
                                '192.168.1.0/24',
                                '192.168.2.0/24',
                            ],
                            ConfigProvider::DIACTOROS_TRUSTED_HEADERS_CONFIG_KEY => [
                                FilterUsingXForwardedHeaders::HEADER_HOST,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'Host'                                     => 'localhost',
                FilterUsingXForwardedHeaders::HEADER_HOST  => 'api.example.com',
                FilterUsingXForwardedHeaders::HEADER_PROTO => 'https',
                FilterUsingXForwardedHeaders::HEADER_PORT  => '4443',
            ],
            ['REMOTE_ADDR' => '192.168.2.1'],
            'http://localhost/foo/bar',
            'http://api.example.com/foo/bar',
        ];
    }

    #[DataProvider('trustedProxiesAndHeaders')]
    public function testCombinedProxiesAndHeadersDefineTrust(
        bool $expectUnfiltered,
        array $config,
        array $headers,
        array $server,
        string $baseUriString,
        string $expectedUriString
    ): void {
        $this->container->set('config', $config);

        $factory = new FilterUsingXForwardedHeadersFactory();
        $filter  = $factory($this->container);
        $request = $this->generateServerRequest($headers, $server, $baseUriString);

        $filteredRequest = $filter($request);

        if ($expectUnfiltered) {
            $this->assertSame($request, $filteredRequest);
            return;
        }

        $this->assertNotSame($request, $filteredRequest);
        $this->assertSame($expectedUriString, $filteredRequest->getUri()->__toString());
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace HyperfTest\Testing;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\NormalizerInterface;
use Hyperf\Di\ClosureDefinitionCollectorInterface;
use Hyperf\Di\Container;
use Hyperf\Di\MethodDefinitionCollector;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\Dispatcher\HttpDispatcher;
use Hyperf\ExceptionHandler\ExceptionHandlerDispatcher;
use Hyperf\HttpServer\CoreMiddleware;
use Hyperf\HttpServer\ResponseEmitter;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Router;
use Hyperf\Server\Event;
use Hyperf\Server\Server;
use Hyperf\Testing\Client;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Filesystem\Filesystem;
use Hyperf\Utils\Serializer\SimpleNormalizer;
use Hyperf\Utils\Waiter;
use HyperfTest\Testing\Stub\Exception\Handler\FooExceptionHandler;
use HyperfTest\Testing\Stub\FooController;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ClientTest extends TestCase
{
    public function testClientRequest()
    {
        $container = $this->getContainer();

        $client = new Client($container);

        $data = $client->get('/');

        $this->assertSame(0, $data['code']);
        $this->assertSame('Hello Hyperf!', $data['data']);
    }

    public function testClientReturnCoroutineId()
    {
        $container = $this->getContainer();

        $client = new Client($container);

        $id = Coroutine::id();
        $data = $client->get('/id');

        $this->assertSame(0, $data['code']);
        $this->assertNotEquals($id, $data['data']);
    }

    public function testClientException()
    {
        $container = $this->getContainer();

        $client = new Client($container);

        $data = $client->get('/exception');

        $this->assertSame(500, $data['code']);
        $this->assertSame('Server Error', $data['message']);
    }

    public function testClientGetUri()
    {
        $container = $this->getContainer();

        $client = new Client($container);

        $data = $client->get('/request', [
            'id' => $id = uniqid(),
        ]);

        $this->assertSame($data['uri'], [
            'scheme' => 'http',
            'host' => '127.0.0.1',
            'port' => 9501,
            'path' => '/request',
            'query' => 'id=' . $id,
        ]);

        $this->assertSame($id, $data['params']['id']);
    }

    public function getContainer()
    {
        $container = Mockery::mock(Container::class);

        $container->shouldReceive('get')->with(HttpDispatcher::class)->andReturn(new HttpDispatcher($container));
        $container->shouldReceive('get')->with(ExceptionHandlerDispatcher::class)->andReturn(new ExceptionHandlerDispatcher($container));
        $container->shouldReceive('get')->with(ResponseEmitter::class)->andReturn(new ResponseEmitter());
        $container->shouldReceive('get')->with(DispatcherFactory::class)->andReturn($factory = new DispatcherFactory());
        $container->shouldReceive('get')->with(NormalizerInterface::class)->andReturn(new SimpleNormalizer());
        $container->shouldReceive('get')->with(MethodDefinitionCollectorInterface::class)->andReturn(new MethodDefinitionCollector());
        $container->shouldReceive('has')->with(ClosureDefinitionCollectorInterface::class)->andReturn(false);
        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturn(new Config([
            'exceptions' => [
                'handler' => [
                    'http' => [
                        FooExceptionHandler::class,
                    ],
                ],
            ],
            'server' => [
                'servers' => [
                    [
                        'name' => 'http',
                        'type' => Server::SERVER_HTTP,
                        'host' => '0.0.0.0',
                        'port' => 9501,
                        'sock_type' => SWOOLE_SOCK_TCP,
                        'callbacks' => [
                            Event::ON_REQUEST => [Server::class, 'onRequest'],
                        ],
                    ],
                ],
            ],
        ]));
        $container->shouldReceive('get')->with(Filesystem::class)->andReturn(new Filesystem());
        $container->shouldReceive('get')->with(FooController::class)->andReturn(new FooController());
        $container->shouldReceive('has')->andReturn(true);
        $container->shouldReceive('get')->with(FooExceptionHandler::class)->andReturn(new FooExceptionHandler());
        $container->shouldReceive('make')->with(CoreMiddleware::class, Mockery::any())->andReturnUsing(function ($class, $args) {
            return new CoreMiddleware(...array_values($args));
        });
        $container->shouldReceive('get')->with(Waiter::class)->andReturn(new Waiter());
        ApplicationContext::setContainer($container);

        Router::init($factory);
        Router::get('/', [FooController::class, 'index']);
        Router::get('/exception', [FooController::class, 'exception']);
        Router::get('/id', [FooController::class, 'id']);
        Router::addRoute(['GET', 'POST'], '/request', [FooController::class, 'request']);

        return $container;
    }
}

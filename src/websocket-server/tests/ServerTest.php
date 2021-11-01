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
namespace HyperfTest\WebSocketServer;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Dispatcher\HttpDispatcher;
use Hyperf\ExceptionHandler\ExceptionHandlerDispatcher;
use Hyperf\HttpServer\ResponseEmitter;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Reflection\ClassInvoker;
use Hyperf\Utils\Waiter;
use Hyperf\WebSocketServer\Server;
use HyperfTest\WebSocketServer\Stub\WebSocketStub;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

/**
 * @internal
 * @coversNothing
 */
class ServerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testDeferOnOpenInCoroutineStyleServer()
    {
        $container = Mockery::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);
        $container->shouldReceive('get')->with(WebSocketStub::class)->andReturn(new WebSocketStub());
        $container->shouldReceive('get')->with(Waiter::class)->andReturn(new Waiter());

        $server = new Server(
            $container,
            Mockery::mock(HttpDispatcher::class),
            Mockery::mock(ExceptionHandlerDispatcher::class),
            Mockery::mock(ResponseEmitter::class),
            Mockery::mock(StdoutLoggerInterface::class),
        );

        $server = new ClassInvoker($server);
        $server->deferOnOpen(new SwooleRequest(), WebSocketStub::class, new SwooleResponse());
        $this->assertNotEquals(Coroutine::id(), WebSocketStub::$coroutineId);
        $this->assertFalse(\Swoole\Coroutine::exists(WebSocketStub::$coroutineId));
    }
}

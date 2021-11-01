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
namespace HyperfTest\Amqp;

use Hyperf\Amqp\ConnectionFactory;
use Hyperf\Amqp\Consumer;
use Hyperf\Utils\Coroutine\Concurrent;
use Hyperf\Utils\Exception\ChannelClosedException;
use Hyperf\Utils\Reflection\ClassInvoker;
use HyperfTest\Amqp\Stub\AMQPConnectionStub;
use HyperfTest\Amqp\Stub\ContainerStub;
use HyperfTest\Amqp\Stub\Delay2Consumer;
use HyperfTest\Amqp\Stub\DelayConsumer;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @coversNothing
 */
class ConsumerTest extends TestCase
{
    public function testConsumerConcurrentLimit()
    {
        $container = ContainerStub::getContainer();
        $consumer = new Consumer($container, Mockery::mock(ConnectionFactory::class), Mockery::mock(LoggerInterface::class));
        $ref = new \ReflectionClass($consumer);
        $method = $ref->getMethod('getConcurrent');
        $method->setAccessible(true);
        /** @var Concurrent $concurrent */
        $concurrent = $method->invokeArgs($consumer, ['default']);
        $this->assertSame(10, $concurrent->getLimit());

        /** @var Concurrent $concurrent */
        $concurrent = $method->invokeArgs($consumer, ['co']);
        $this->assertSame(5, $concurrent->getLimit());
    }

    public function testWaitChannel()
    {
        $connection = new AMQPConnectionStub();
        $invoker = new ClassInvoker($connection);
        $chan = $invoker->channelManager->get(1, true);
        $chan->push($id = uniqid());
        $this->assertSame($id, $invoker->wait_channel(1));

        $this->expectException(ChannelClosedException::class);
        $chan->close();
        $invoker->wait_channel(1);
    }

    public function testRewriteDelayMessage()
    {
        $consumer = new DelayConsumer();
        $this->assertSame('x-delayed', (new ClassInvoker($consumer))->getDeadLetterExchange());

        $consumer = new Delay2Consumer();
        $this->assertSame('delayed', (new ClassInvoker($consumer))->getDeadLetterExchange());
    }
}

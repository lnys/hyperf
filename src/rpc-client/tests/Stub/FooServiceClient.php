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
namespace HyperfTest\RpcClient\Stub;

use Hyperf\RpcClient\AbstractServiceClient;
use Psr\Container\ContainerInterface;

class FooServiceClient extends AbstractServiceClient
{
    protected $serviceName = 'FooService';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function createNodes(): array
    {
        return parent::createNodes();
    }
}

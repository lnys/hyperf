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
namespace HyperfTest\Utils\CodeGen;

use Hyperf\Utils\CodeGen\PhpParser;
use HyperfTest\Utils\Stub\Bar;
use HyperfTest\Utils\Stub\UnionTypeFoo;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class PhpParserTest extends TestCase
{
    public function testGetAstFromReflectionParameter()
    {
        $parserFactory = new ParserFactory();
        $parser7 = $parserFactory->create(ParserFactory::ONLY_PHP7);

        $stmts = $parser7->parse(file_get_contents(__DIR__ . '/../Stub/Bar.php'));
        /** @var ClassMethod $classMethod */
        $classMethod = $stmts[1]->stmts[0]->stmts[0];
        $name = $classMethod->getParams()[0];
        $foo = $classMethod->getParams()[1];
        $extra = $classMethod->getParams()[2];
        $bar = new \ReflectionClass(Bar::class);
        $parameters = $bar->getMethod('__construct')->getParameters();
        $parser = new PhpParser();
        $this->assertNodeParam($name, $parser->getNodeFromReflectionParameter($parameters[0]));
        $this->assertNodeParam($foo, $foo2 = $parser->getNodeFromReflectionParameter($parameters[1]));
        $this->assertSame(['', 'HyperfTest', 'Utils', 'Stub', 'Foo'], $foo2->type->parts);
        $this->assertNodeParam($extra, $parser->getNodeFromReflectionParameter($parameters[2]));

        if (PHP_VERSION_ID > 80000) {
            $stmts = $parser7->parse(file_get_contents(__DIR__ . '/../Stub/UnionTypeFoo.php'));
            /** @var ClassMethod $classMethod */
            $classMethod = $stmts[1]->stmts[0]->stmts[0];
            $name = $classMethod->getParams()[0];

            $foo = new \ReflectionClass(UnionTypeFoo::class);
            $parameters = $foo->getMethod('__construct')->getParameters();
            $this->assertNodeParam($name, $parser->getNodeFromReflectionParameter($parameters[0]));
        }
    }

    protected function assertNodeParam(Node\Param $param, Node\Param $param2)
    {
        if ($param->type) {
            $this->assertSame(get_class($param->type), get_class($param2->type));
            if ($param->type instanceof Node\Name) {
                $this->assertSame(get_class($param->type), get_class($param2->type));
            } elseif ($param->type instanceof Node\Identifier) {
                $this->assertSame($param->type->name, $param2->type->name);
            }
        } else {
            $this->assertSame($param->type, $param2->type);
        }
        $this->assertSame($param2->byRef, $param->byRef);
        $this->assertSame($param->variadic, $param2->variadic);
        $this->assertSame($param->var->name, $param2->var->name);
        $this->assertSame(get_class($param->var), get_class($param2->var));
        if ($param->default) {
            $this->assertSame(get_class($param->default), get_class($param2->default));
            if ($param->default instanceof Node\Expr\Array_) {
                $this->assertSame($param->default->items, $param2->default->items);
            } elseif ($param->default instanceof Node\Scalar) {
                $this->assertSame($param->default->value, $param2->default->value);
            }
        } else {
            $this->assertSame($param->default, $param2->default);
        }
    }
}

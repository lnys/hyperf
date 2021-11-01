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
namespace HyperfTest\Utils;

use Hyperf\Utils\Exception\InvalidArgumentException;
use Hyperf\Utils\Str;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class StrTest extends TestCase
{
    public function testSlug()
    {
        $res = Str::slug('hyperf_', '_');

        $this->assertSame('hyperf', $res);

        $arr = [
            '0' => 0,
            '1' => 1,
            'a' => 'a',
        ];

        $this->assertSame([0, 1, 'a' => 'a'], $arr);
        foreach ($arr as $i => $v) {
            $this->assertIsInt($i);
            break;
        }
    }

    public function testMask()
    {
        $res = Str::mask('hyperf');

        $this->assertSame('******', $res);

        $res = Str::mask('hyperf', 3);

        $this->assertSame('hyp***', $res);

        $res = Str::mask('hyperf', 3, 1);

        $this->assertSame('hyp*rf', $res);

        $res = Str::mask('hyperf', 0, 3);

        $this->assertSame('***erf', $res);

        $res = Str::mask('hyperf', 0, 0, '-');

        $this->assertSame('------', $res);

        $res = Str::mask('hyperf', 6, 2);

        $this->assertSame('hyperf', $res);

        $res = Str::mask('hyperf', 7);

        $this->assertSame('hyperf', $res);

        $res = Str::mask('hyperf', 3, 10);

        $this->assertSame('hyp**********', $res);

        $res = Str::mask('hyperf', -3);
        $this->assertSame('***erf', $res);

        $res = Str::mask('hyperf', -3, 1);
        $this->assertSame('hy*erf', $res);

        $res = Str::mask('hyperf', -3, 3);
        $this->assertSame('***erf', $res);

        $res = Str::mask('hyperf', -3, 5);
        $this->assertSame('*****erf', $res);

        $res = Str::mask('你好啊');

        $this->assertSame('***', $res);

        $res = Str::mask('你好世界', 3);

        $this->assertSame('你好世*', $res);

        $res = Str::mask('你好世界', 2, 1);

        $this->assertSame('你好*界', $res);

        $res = Str::mask('你好世界', 0, 3);

        $this->assertSame('***界', $res);

        $res = Str::mask('你好世界', 1, 1);

        $this->assertSame('你*世界', $res);

        $res = Str::mask('你好世界', 0, 0, '-');

        $this->assertSame('----', $res);

        $res = Str::mask('你好世界', 6, 2);

        $this->assertSame('你好世界', $res);

        $res = Str::mask('你好世界', 7);

        $this->assertSame('你好世界', $res);

        $res = Str::mask('你好世界', 3, 10);

        $this->assertSame('你好世**********', $res);

        $res = Str::mask('你好世界', -1);
        $this->assertSame('***界', $res);

        $res = Str::mask('你好世界', -1, 1);
        $this->assertSame('你好*界', $res);

        $res = Str::mask('你好世界', -3, 3);
        $this->assertSame('***好世界', $res);

        $this->expectException(InvalidArgumentException::class);
        Str::mask('hyperf', -1, -1);
    }

    public function testStartsWith()
    {
        $this->assertFalse(Str::startsWith('hyperf.wiki', 'http://'));
        $this->assertFalse(Str::startsWith('hyperf.wiki', ['http://', 'https://']));
        $this->assertTrue(Str::startsWith('http://www.hyperf.io', 'http://'));
        $this->assertTrue(Str::startsWith('https://www.hyperf.io', ['http://', 'https://']));
    }

    public function testStripTags()
    {
        $this->assertSame('beforeafter', Str::stripTags('before<br>after'));
        $this->assertSame('before<br>after', Str::stripTags('before<br>after', '<br>'));
        $this->assertSame('before<br>after', Str::stripTags('<strong>before</strong><br>after', '<br>'));
        $this->assertSame('<strong>before</strong><br>after', Str::stripTags('<strong>before</strong><br>after', '<br><strong>'));

        if (PHP_VERSION_ID >= 70400) {
            $this->assertSame('<strong>before</strong><br>after', Str::stripTags('<strong>before</strong><br>after', ['<br>', '<strong>']));
        }

        if (PHP_VERSION_ID >= 80000) {
            $this->assertSame('beforeafter', Str::stripTags('before<br>after', null));
        }
    }
}

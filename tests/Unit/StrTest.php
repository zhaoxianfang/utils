<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use BadMethodCallException;
use InvalidArgumentException;
use zxf\Utils\Str\Str;
use zxf\Utils\Str\StringHelper;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * 字符串组件测试（StringHelper 静态方法 + Str 流式包装）
 */
class StrTest extends TestCase
{
    /* ---------------- 命名风格转换 ---------------- */

    public function testCaseConversions(): void
    {
        Assert::same('fooBar', StringHelper::camel('foo-bar'));
        Assert::same('FooBar', StringHelper::studly('foo_bar'));
        Assert::same('foo_bar', StringHelper::snake('FooBar'));
        Assert::same('foo-bar', StringHelper::kebab('FooBar'));
        Assert::same('hello-world', StringHelper::slug('Hello World'));
    }

    public function testSnakeAndSlugDoNotWarnOnPregFailure(): void
    {
        // 早期实现未处理 preg_replace 返回 null，会向 mb_strtolower / trim 传 null
        Assert::doesNotThrow(static function () {
            StringHelper::snake('FooBar');
            StringHelper::slug('Hello World');
            StringHelper::squish('  a   b  ');
        });
    }

    /* ---------------- 判断与操作 ---------------- */

    public function testStartsEndsContains(): void
    {
        Assert::true(StringHelper::startsWith('foobar', 'foo'));
        Assert::true(StringHelper::startsWith('foobar', ['x', 'foo']));
        Assert::false(StringHelper::startsWith('foobar', ''));
        Assert::true(StringHelper::endsWith('foobar', 'bar'));
        Assert::true(StringHelper::contains('foobar', 'oob'));
        Assert::true(StringHelper::contains('foobar', ['zzz', 'oob']));
    }

    public function testRemovePrefixSuffix(): void
    {
        Assert::same('bar', StringHelper::removePrefix('foobar', 'foo'));
        Assert::same('foo', StringHelper::removeSuffix('foobar', 'bar'));
        Assert::same('foobar', StringHelper::removePrefix('foobar', 'zzz'));
    }

    public function testMasking(): void
    {
        Assert::same('138****5678', StringHelper::maskMobile('13812345678'));
        Assert::same('ab****@example.com', StringHelper::maskEmail('abcdef@example.com'));
        Assert::same('a@b.cn', StringHelper::maskEmail('a@b.cn'));
        // 起始位置越界时返回原串
        Assert::same('ab', StringHelper::mask('ab', '*', 5, 4));
    }

    public function testTextUtilities(): void
    {
        Assert::same('a b', StringHelper::squish("  a   b  "));
        Assert::same('cba', StringHelper::reverse('abc'));
        // between() 取「起始标记之后」到「结束标记之前」
        Assert::same('ello', StringHelper::between('hello world', 'h', ' '));
        Assert::same('o wo', StringHelper::between('hello world', 'll', 'rld'));
        Assert::same('', StringHelper::between('hello', 'zz', ' '));
        Assert::same('world', StringHelper::after('hello world', 'hello '));
        Assert::same('hello', StringHelper::before('hello world', ' world'));
    }

    public function testLimitAndPad(): void
    {
        Assert::same('abc...', StringHelper::limit('abcdefgh', 3));
        Assert::same('abcdefgh', StringHelper::limit('abcdefgh', 100));
        Assert::doesNotThrow(static fn () => StringHelper::limit('abcdef', -1));
        Assert::same('5   ', StringHelper::pad('5', 4));
    }

    /* ---------------- 相似度（多字节与长文本） ---------------- */

    public function testSimilarityIsCharacterBased(): void
    {
        // 原生 levenshtein() 按字节比较，中文结果会完全错误
        Assert::equals(0.75, StringHelper::similarity('中文测试', '中文测式'));
        Assert::equals(2.0 / 3.0, StringHelper::similarity('abc', 'abd'));
        Assert::same(1.0, StringHelper::similarity('', ''));
        Assert::same(1.0, StringHelper::similarity('abc', 'abc'));
        Assert::same(0.0, StringHelper::similarity('abc', 'xyz'));
    }

    public function testSimilarityHandlesLongText(): void
    {
        // 原生 levenshtein() 对超过 255 字节的参数直接返回 -1
        $a = str_repeat('测试文本', 100);
        $b = str_repeat('测试文本', 99) . '测试又本';

        $score = StringHelper::similarity($a, $b);

        Assert::true($score > 0.9 && $score <= 1.0, '长文本相似度应接近 1，实际 ' . $score);
    }

    public function testLevenshteinDistanceRejectsOversizedInput(): void
    {
        Assert::throws(
            InvalidArgumentException::class,
            static fn () => StringHelper::levenshteinDistance(str_repeat('a', 2001), 'b')
        );
    }

    /* ---------------- random ---------------- */

    public function testRandom(): void
    {
        Assert::same(16, mb_strlen(StringHelper::random()));
        Assert::same(8, mb_strlen(StringHelper::random(8)));
        Assert::same('aaa', StringHelper::random(3, 'a'));
        Assert::matches('/^[a-zA-Z0-9]{10}$/', StringHelper::random(10));
    }

    public function testRandomRejectsNonPositiveLength(): void
    {
        Assert::throws(InvalidArgumentException::class, static fn () => StringHelper::random(0));
        Assert::throws(InvalidArgumentException::class, static fn () => StringHelper::random(-1));
        Assert::throws(InvalidArgumentException::class, static fn () => StringHelper::random(4, ''));
    }

    /* ---------------- Str 流式包装 ---------------- */

    public function testFluentStringChaining(): void
    {
        // 返回字符串的方法会继续返回 Str 实例，可继续链式调用
        Assert::same('fooBar', (string) u_str('foo-bar')->camel());
        Assert::same('fooBarBaz', (string) u_str('foo-bar-baz')->camel());
        Assert::same('hello-world', (string) u_str('Hello World')->slug());
        Assert::same('138****5678', (string) u_str('13812345678')->maskMobile());
        Assert::true(u_str('foo-bar')->camel() instanceof Str);
    }

    public function testFluentStringTerminatesOnNonStringResult(): void
    {
        Assert::true(u_str('abc')->contains('b'));
        Assert::true(u_str('abc')->startsWith('a'));
        Assert::same(3, u_str('abc')->length());
    }

    public function testFluentStringHelpers(): void
    {
        Assert::same(['a', '中'], u_str('a中')->chars());
        Assert::true(u_str()->isEmpty());
        Assert::false(u_str('a')->isEmpty());
        Assert::true(u_str('a')->is('a'));
        Assert::same('abc', u_str('abc')->value());
    }

    public function testFluentStringUnknownMethod(): void
    {
        Assert::throws(BadMethodCallException::class, static fn () => u_str('a')->nope());
    }

    public function testStrStaticForwarding(): void
    {
        Assert::same('fooBar', (string) Str::camel('foo-bar'));
        Assert::same('foo_bar', (string) Str::snake('FooBar'));
        Assert::throws(BadMethodCallException::class, static fn () => Str::camel(123));
    }
}

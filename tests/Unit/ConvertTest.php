<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use InvalidArgumentException;
use JsonException;
use zxf\Utils\Convert\DataSize;
use zxf\Utils\Convert\Json;
use zxf\Utils\Convert\Number;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * Convert 组件测试（DataSize / Number / Json）
 */
class ConvertTest extends TestCase
{
    /* ---------------- DataSize ---------------- */

    public function testDataSizeParse(): void
    {
        Assert::same(2684354560, DataSize::parse('2.5GB')->toBytes());
        Assert::same(1024, DataSize::parse('1KB')->toBytes());
        Assert::same(1048576, DataSize::parse('1024 kb')->toBytes());
        Assert::same(524288, DataSize::parse('.5MB')->toBytes());
        Assert::same(512, DataSize::parse('512')->toBytes());
    }

    public function testDataSizeParseRejectsMalformedInput(): void
    {
        // 旧正则 [\d.]+ 会接受 "1.2.3" 并被 (float) 静默截断为 1.2
        foreach (['1.2.3KB', 'abc', '-1KB', '1EB', '', '1.2.3'] as $invalid) {
            Assert::throws(
                InvalidArgumentException::class,
                static fn () => DataSize::parse($invalid),
                "parse('{$invalid}') 应被拒绝"
            );
        }
    }

    public function testDataSizeConversions(): void
    {
        $size = DataSize::bytes(1536);

        Assert::same(1536, $size->toBytes());
        Assert::equals(1.5, $size->toKb());
        Assert::equals(0.00146484375, $size->toMb(11));
        Assert::equals(2.0, DataSize::parse('2PB')->toPb());
    }

    public function testDataSizeAutoFormat(): void
    {
        Assert::same('1.5 KB', DataSize::format(1536));
        Assert::same('0 B', DataSize::format(0));
        // 字节级不输出小数
        Assert::same('512 B', DataSize::format(512));
        Assert::stringNotContains('.', DataSize::format(512));
        Assert::same('1 MB', DataSize::format(1048576));
    }

    public function testDataSizeArithmetic(): void
    {
        $a = DataSize::bytes(100);
        $b = DataSize::bytes(30);

        Assert::same(130, $a->add($b)->toBytes());
        Assert::same(70, $a->subtract($b)->toBytes());
        // 相减不会为负
        Assert::same(0, $b->subtract($a)->toBytes());
        Assert::true($a->greaterThan($b));
        Assert::true($b->lessThan($a));
        Assert::true($a->equals(DataSize::bytes(100)));
    }

    /* ---------------- Number ---------------- */

    public function testNumberCurrency(): void
    {
        Assert::same('1,234,567.89', Number::currency(1234567.891));
        Assert::same('1.234.567,89', Number::currency(1234567.891, 2, ',', '.'));
    }

    public function testNumberChinese(): void
    {
        Assert::same('壹仟贰佰叁拾肆元伍角陆分', Number::chinese(1234.56));
        Assert::same('零元整', Number::chinese(0));
        Assert::same('负壹佰元伍角', Number::chinese(-100.5));
        Assert::same('壹佰元零伍分', Number::chinese(100.05));
        Assert::same('壹亿元整', Number::chinese(100000000));
    }

    public function testNumberAbbreviate(): void
    {
        Assert::same('1.2M', Number::abbreviate(1234567));
        Assert::same('1.5K', Number::abbreviate(1500));
        Assert::same('-2.5M', Number::abbreviate(-2500000));
        Assert::same('999', Number::abbreviate(999));
    }

    public function testNumberOrdinal(): void
    {
        Assert::same('1st', Number::ordinal(1));
        Assert::same('2nd', Number::ordinal(2));
        Assert::same('3rd', Number::ordinal(3));
        Assert::same('4th', Number::ordinal(4));
        Assert::same('11th', Number::ordinal(11));
        Assert::same('12th', Number::ordinal(12));
        Assert::same('13th', Number::ordinal(13));
        Assert::same('21st', Number::ordinal(21));
        Assert::same('101st', Number::ordinal(101));
    }

    public function testNumberRoundToRejectsZero(): void
    {
        Assert::equals(5.0, Number::roundTo(7, 5));
        Assert::equals(7.0, Number::roundTo(7, 1));

        // 旧实现仅判断 `$multiple === 0`，而 strict_types 下 0.0 === 0 恒为 false，
        // 传 0.0 会绕过校验并触发 DivisionByZeroError
        Assert::throws(InvalidArgumentException::class, static fn () => Number::roundTo(7, 0));
        Assert::throws(InvalidArgumentException::class, static fn () => Number::roundTo(7, 0.0));
    }

    public function testNumberParityAndClamp(): void
    {
        Assert::true(Number::even(4));
        Assert::false(Number::even(5));
        Assert::true(Number::odd(5));
        Assert::same(5, Number::clamp(10, 1, 5));
        Assert::same(1, Number::clamp(-5, 1, 5));
    }

    public function testNumberPercentageAndFileSize(): void
    {
        Assert::same('50%', Number::percentage(0.5));
        Assert::same('50.00%', Number::percentage(0.5, 2));
        Assert::same('1 KB', Number::fileSize(1024));
    }

    /* ---------------- Json ---------------- */

    public function testJsonEncodeDecode(): void
    {
        $encoded = Json::encode(['a' => 1, '中' => '文']);
        Assert::same('{"a":1,"中":"文"}', $encoded);
        Assert::same(['a' => 1], Json::decode('{"a":1}'));
    }

    public function testJsonDecodeThrowsOnInvalid(): void
    {
        Assert::throws(JsonException::class, static fn () => Json::decode('{bad}'));
        Assert::same('D', Json::decodeSafe('{bad}', 'D'));
    }

    public function testJsonValidityChecks(): void
    {
        Assert::true(Json::isValid('{"a":1}'));
        Assert::false(Json::isValid('{bad}'));
        Assert::true(Json::isObject('{"a":1}'));
        Assert::true(Json::isArray('[1,2]'));
    }

    public function testJsonPathOperations(): void
    {
        Assert::same(2, Json::get('{"a":{"b":[1,2,3]}}', 'a.b.1'));
        Assert::same('D', Json::get('{"a":1}', 'x.y', 'D'));
        Assert::same('{"a":1,"b":{"c":2}}', Json::set('{"a":1}', 'b.c', 2));
        Assert::same('{"b":2}', Json::remove('{"a":1,"b":2}', 'a'));
    }

    public function testJsonMergeAndKeys(): void
    {
        Assert::same('{"a":3,"b":2}', Json::merge('{"a":1}', '{"b":2}', '{"a":3}'));
        Assert::same(['a', 'b'], Json::keys('{"a":1,"b":2}'));
        Assert::same([1, 2], Json::values('{"a":1,"b":2}'));
    }

    /**
     * 旧实现直接返回 decode() 的结果，根节点为标量时
     * 会触发 "Return value must be of type array" 的 TypeError
     */
    public function testJsonToArrayHandlesScalarRoot(): void
    {
        Assert::same(['a' => 1], Json::toArray('{"a":1}'));
        Assert::same([1, 2], Json::toArray('[1,2]'));
        Assert::same([5], Json::toArray('5'));
        Assert::same(['str'], Json::toArray('"str"'));

        Assert::throws(JsonException::class, static fn () => Json::toArray('5', false));
    }

    public function testJsonToObjectHandlesArrayRoot(): void
    {
        // decode(assoc:false) 对 JSON 数组仍返回 PHP 数组，需转换后才符合返回类型
        $object = Assert::doesNotThrow(static fn () => Json::toObject('[1,2]'));
        Assert::same(1, $object->{0});

        $nested = Json::toObject('{"a":{"b":1}}');
        Assert::true($nested->a instanceof \stdClass);
        Assert::same(1, $nested->a->b);
    }

    public function testJsonEquals(): void
    {
        Assert::true(Json::equals('{"a":1,"b":2}', '{"b":2,"a":1}'));
        Assert::false(Json::equals('{"a":1}', '{"a":"1"}'));
    }
}

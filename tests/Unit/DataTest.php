<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use InvalidArgumentException;
use TypeError;
use zxf\Utils\Data\BigNumberCalculator;
use zxf\Utils\Data\CountryNumber;
use zxf\Utils\Data\Random;
use zxf\Utils\Security\Hash;
use zxf\Utils\Support\EnumHelper;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * Data 组件测试（BigNumberCalculator / CountryNumber / Random / Hash / EnumHelper）
 */
class DataTest extends TestCase
{
    /* ---------------- BigNumberCalculator ---------------- */

    public function testBigNumberDivision(): void
    {
        Assert::same('5', BigNumberCalculator::divide('10', '2'));
        Assert::same('0', BigNumberCalculator::divide('0', '9'));
        // 默认精度为 0（整除）；小数需显式指定 precision
        Assert::same('0', BigNumberCalculator::divide('1', '2'));
        Assert::same('0.5', BigNumberCalculator::divide('1', '2', 1));
        Assert::same('3.33', BigNumberCalculator::divide('10', '3', 2));
    }

    /**
     * 旧实现用递归方式逐位试商（divMod → divCeil），
     * 除数为 1 或数值极大时会指数级放大直至爆栈。
     */
    public function testBigNumberDivisionDoesNotOverflowStack(): void
    {
        Assert::same('1', BigNumberCalculator::divide('1', '1'));
        Assert::same('9', BigNumberCalculator::divide('9', '1'));

        $huge = str_repeat('9', 60);
        Assert::same($huge, BigNumberCalculator::divide($huge, '1'));
    }

    public function testBigNumberArithmetic(): void
    {
        Assert::same('3', BigNumberCalculator::add('1', '2'));
        Assert::same('1', BigNumberCalculator::subtract('3', '2'));
        Assert::same('6', BigNumberCalculator::multiply('2', '3'));
        // 支持负数结果（不做 0 截断）
        Assert::same('-1', BigNumberCalculator::subtract('2', '3'));
    }

    public function testBigNumberHandlesLargeOperands(): void
    {
        $big = str_repeat('9', 30);

        // 999…9 + 1 应正确进位为 1 后跟 30 个 0
        Assert::same('1' . str_repeat('0', 30), BigNumberCalculator::add($big, '1'));
        Assert::same(str_repeat('9', 29) . '8', BigNumberCalculator::subtract($big, '1'));
        Assert::same('1' . str_repeat('0', 60), BigNumberCalculator::power('10', '60'));
    }

    /* ---------------- CountryNumber ---------------- */

    public function testCountryNumberLookups(): void
    {
        $catalogue = new CountryNumber();

        Assert::contains('China', $catalogue->getCountryNameByNumber('86'));
        Assert::contains('United-States', $catalogue->getCountryNameByNumber('1'));
        // 单区号的国家返回字符串，多区号的国家返回数组
        Assert::same('86', $catalogue->getCountryNumber('China'));
        Assert::same(['13', '14', '15', '16', '17', '18', '19'], $catalogue->getCountryMobilePrefix('China'));
    }

    /**
     * 旧实现直接对 `number` 字段调用 str_contains()，
     * 而该字段在部分条目中是数组，会抛出 TypeError。
     */
    public function testCountryNumberHandlesArrayValuedNumberField(): void
    {
        $catalogue = new CountryNumber();

        Assert::doesNotThrow(
            static fn () => $catalogue->getCountryNameByNumber('1'),
            'number 字段为数组时不应抛 TypeError'
        );
        Assert::doesNotThrow(
            static fn () => $catalogue->getCountryNameByNumber('44'),
            'number 字段为数组时不应抛 TypeError'
        );
        // 空区号应返回空数组而不是抛出
        Assert::same([], $catalogue->getCountryNameByNumber(''));
    }

    public function testCountryNumberBulkAccessors(): void
    {
        $catalogue = new CountryNumber();

        Assert::true(count($catalogue->getAllCountry()) > 100);
        Assert::true(count($catalogue->getAllCountryName()) > 100);
        Assert::true(count($catalogue->getAllCountryNumber()) > 100);
    }

    /* ---------------- Random ---------------- */

    public function testRandomPickMany(): void
    {
        $items = ['a', 'b', 'c', 'd', 'e'];

        $picked = Random::pickMany($items, 3);
        Assert::count(3, $picked);
        Assert::count(3, array_unique($picked));

        // 数量必须落在 [1, count] 区间内，越界显式报错而不是静默截断
        Assert::throws(InvalidArgumentException::class, static fn () => Random::pickMany($items, 0));
        Assert::throws(InvalidArgumentException::class, static fn () => Random::pickMany($items, 10));
        Assert::throws(InvalidArgumentException::class, static fn () => Random::pickMany([], 1));
    }

    /**
     * 旧实现用 unset($arr[$k]) 后 array_values() 重建，
     * 但 unset 会破坏数字键的连续性判断，可能返回重复元素或数量不足。
     */
    public function testRandomPickManyNeverExceedsAvailableItems(): void
    {
        $items = range(1, 4);

        for ($i = 0; $i < 200; $i++) {
            $picked = Random::pickMany($items, 2);
            Assert::count(2, $picked);
            Assert::count(2, array_unique($picked));
        }
    }

    /* ---------------- Hash ---------------- */

    public function testConsistentHashDistribution(): void
    {
        $nodes = ['n1', 'n2', 'n3', 'n4'];
        $distribution = array_fill_keys($nodes, 0);

        for ($i = 0; $i < 4000; $i++) {
            $distribution[Hash::consistent("user:{$i}", $nodes)]++;
        }

        // crc32 实测分布极不均匀（3 节点时约 50%/25%/25%），故改用 md5
        foreach ($distribution as $node => $count) {
            Assert::true(
                abs($count - 1000) < 200,
                "节点 {$node} 分布偏差过大：{$count}（期望约 1000）"
            );
        }
    }

    public function testConsistentHashStability(): void
    {
        $nodes = ['a', 'b', 'c'];

        Assert::same(Hash::consistent('key1', $nodes), Hash::consistent('key1', $nodes));
        Assert::same(
            Hash::consistent('key1', $nodes),
            Hash::consistent('key1', ['c', 'a', 'b']) // 顺序无关
        );
    }

    public function testConsistentHashMinimisesRebalancing(): void
    {
        $before = [];
        for ($i = 0; $i < 500; $i++) {
            $before[$i] = Hash::consistent("k{$i}", ['n1', 'n2', 'n3', 'n4']);
        }

        $moved = 0;
        for ($i = 0; $i < 500; $i++) {
            if (Hash::consistent("k{$i}", ['n1', 'n2', 'n3']) !== $before[$i]) {
                $moved++;
            }
        }

        // 移除 1/4 节点，理论上约有 1/4 的键需要迁移
        Assert::true($moved < 200, "迁移量过大：{$moved}/500（期望约 125）");
    }

    public function testConsistentHashValidation(): void
    {
        Assert::throws(InvalidArgumentException::class, static fn () => Hash::consistent('k', []));
        Assert::throws(InvalidArgumentException::class, static fn () => Hash::consistent('k', [['x']]));
        Assert::same(10, Hash::consistent('k', [10, 20, 30]));
    }

    /* ---------------- EnumHelper ---------------- */

    public function testEnumHelperWithBackedEnum(): void
    {
        Assert::same(['H', 'S'], EnumHelper::names(SuitFixture::class));
        Assert::same(['h', 's'], EnumHelper::values(SuitFixture::class));
        Assert::same(['H' => 'h', 'S' => 's'], EnumHelper::toArray(SuitFixture::class));
        Assert::same(['h' => 'H', 's' => 'S'], EnumHelper::flip(SuitFixture::class));
        Assert::same('H', EnumHelper::tryFrom(SuitFixture::class, 'h')?->name);
        Assert::null(EnumHelper::tryFrom(SuitFixture::class, 'zz'));
        Assert::true(EnumHelper::hasValue(SuitFixture::class, 'h'));
        Assert::true(EnumHelper::hasName(SuitFixture::class, 'H'));
    }

    /**
     * 纯枚举（UnitEnum）没有 ->value，旧实现会抛 TypeError
     */
    public function testEnumHelperWithPureEnum(): void
    {
        Assert::doesNotThrow(static fn () => EnumHelper::values(PureFixture::class));
        Assert::same(['A', 'B'], EnumHelper::values(PureFixture::class));
        Assert::same(['A' => 'A', 'B' => 'B'], EnumHelper::toArray(PureFixture::class));
        Assert::null(EnumHelper::tryFrom(PureFixture::class, 'A'));
        Assert::false(EnumHelper::hasValue(PureFixture::class, 'A'));
        Assert::same('A', EnumHelper::tryFromName(PureFixture::class, 'A')?->name);
    }

    public function testEnumHelperRejectsNonEnum(): void
    {
        // 旧实现会抛 "Call to undefined method ::cases()"
        Assert::throws(
            InvalidArgumentException::class,
            static fn () => EnumHelper::names(\stdClass::class)
        );
        Assert::false(EnumHelper::isEnum(\stdClass::class));
        Assert::true(EnumHelper::isEnum(SuitFixture::class));
    }

    public function testEnumHelperRandom(): void
    {
        $case = EnumHelper::random(SuitFixture::class);
        Assert::contains($case->value, ['h', 's']);
    }
}

enum SuitFixture: string
{
    case H = 'h';
    case S = 's';
}

enum PureFixture
{
    case A;
    case B;
}

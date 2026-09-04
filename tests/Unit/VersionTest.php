<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use InvalidArgumentException;
use zxf\Utils\Data\Version;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * SemVer 版本号测试
 */
class VersionTest extends TestCase
{
    public function testParse(): void
    {
        Assert::same('1.2.3', (string) Version::of('1.2.3'));
        Assert::same('1.2.3', (string) Version::of('v1.2.3'));
        Assert::same('1.2.3-alpha.1+build.5', (string) Version::of('1.2.3-alpha.1+build.5'));
    }

    public function testRejectsInvalidVersions(): void
    {
        foreach (['', 'abc', '1', '1.2', '1.2.3.4', '1.2.x'] as $invalid) {
            Assert::throws(InvalidArgumentException::class, static fn () => Version::of($invalid), "of('{$invalid}') 应被拒绝");
        }
    }

    public function testCompare(): void
    {
        Assert::same(-1, Version::compare('1.0.0', '1.0.1'));
        Assert::same(0, Version::compare('1.0.0', '1.0.0'));
        Assert::same(1, Version::compare('2.0.0', '1.9.9'));
    }

    public function testComparePrereleaseFollowsSemver(): void
    {
        // 预发布版本低于同版本正式版
        Assert::same(-1, Version::compare('1.0.0-alpha', '1.0.0'));
        Assert::same(-1, Version::compare('1.0.0-alpha', '1.0.0-beta'));
        Assert::same(-1, Version::compare('1.0.0-alpha.1', '1.0.0-alpha.2'));
        Assert::same(-1, Version::compare('1.0.0-rc.1', '1.0.0'));
        Assert::same(1, Version::compare('1.0.0', '1.0.0-0'));
    }

    public function testBuildMetadataIsIgnoredInComparison(): void
    {
        Assert::same(0, Version::compare('1.0.0+build1', '1.0.0+build2'));
    }

    public function testComparisonHelpers(): void
    {
        Assert::true(Version::gt('2.0.0', '1.0.0'));
        Assert::true(Version::gte('1.0.0', '1.0.0'));
        Assert::true(Version::lt('1.0.0', '2.0.0'));
        Assert::true(Version::lte('1.0.0', '1.0.0'));
        Assert::true(Version::eq('1.0.0', '1.0.0'));
    }

    /**
     * 约束表达式（Composer / npm 语义）
     *
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    private function constraintCases(): array
    {
        return [
            // 且 / 或
            ['1.2.3', '>=1.0.0 <2.0.0', true],
            ['2.5.0', '>=1.0.0 <2.0.0', false],
            ['1.5.0', '1.2.0 || >=1.4.0', true],
            ['1.3.0', '1.2.0 || >=1.4.0', false],
            ['1.4.0', '1.2.0 || >=1.4.0', true],
            ['3.0.0', '^1.0.0 || ^2.0.0 || >=3.0.0', true],
            ['1.2.3', '>=1.0,<2.0', true],
            ['2.5.0', '>=1.0,<2.0', false],
            // 插入符
            ['1.2.3', '^1.0.0', true],
            ['2.0.0', '^1.0.0', false],
            ['0.2.0', '^0.2.0', true],
            ['0.3.0', '^0.2.0', false],
            // 波浪号
            ['1.2.9', '~1.2.0', true],
            ['1.3.0', '~1.2.0', false],
            // 范围
            ['1.5.0', '1.2.0 - 1.6.0', true],
            ['1.7.0', '1.2.0 - 1.6.0', false],
            // 通配符
            ['1.2.3', '1.2.x', true],
            ['1.3.0', '1.2.x', false],
            ['1.2.3', '*', true],
            // 不等
            ['2.5.0', '>=2.0.0 <3.0.0 !=2.4.0', true],
            ['2.4.0', '>=2.0.0 <3.0.0 !=2.4.0', false],
            ['1.0.0', '!=1.0.0', false],
            ['1.0.1', '!=1.0.0', true],
            ['1.0.0', '<>1.0.0', false],
            // 省略段的部分版本号
            ['1.2.3', '>=1.0', true],
            ['1.2.3', '<2.0', true],
            ['2.0.0', '<2.0', false],
            ['1.5.0', '~1.2', true],
            ['2.0.0', '~1.2', false],
            ['1.9.9', '~1', true],
            ['2.0.0', '~1', false],
            ['0.3.5', '^0.3', true],
            ['0.4.0', '^0.3', false],
            ['1.5.0', '^1.2', true],
            ['2.0.0', '^1.2', false],
            ['0.0.3', '^0.0.3', true],
            ['0.0.4', '^0.0.3', false],
            // 裸的部分版本号按 npm 语义视为通配范围
            ['1.2.3', '1.2', true],
            ['1.3.0', '1.2', false],
            ['1.9.9', '1', true],
            ['2.0.0', '1', false],
            // 精确匹配（完整三段式）
            ['1.2.3', '1.2.3', true],
            ['1.2.4', '1.2.3', false],
        ];
    }

    public function testSatisfiesConstraints(): void
    {
        foreach ($this->constraintCases() as [$version, $constraint, $expected]) {
            Assert::same(
                $expected,
                Version::of($version)->satisfies($constraint),
                "satisfies({$version}, '{$constraint}')"
            );
        }
    }

    public function testSatisfiesEmptyConstraint(): void
    {
        Assert::true(Version::of('9.9.9')->satisfies(''));
        Assert::true(Version::of('9.9.9')->satisfies('*'));
    }

    public function testIncrement(): void
    {
        Assert::same('2.0.0', (string) Version::of('1.2.3')->increment('major'));
        Assert::same('1.3.0', (string) Version::of('1.2.3')->increment('minor'));
        Assert::same('1.2.4', (string) Version::of('1.2.3')->increment('patch'));
        Assert::same('1.2.4', (string) Version::of('1.2.3')->increment());
    }

    public function testIncrementRejectsUnknownType(): void
    {
        Assert::throws(
            InvalidArgumentException::class,
            static fn () => Version::of('1.2.3')->increment('unknown')
        );
    }

    public function testIncrementDropsPrereleaseSuffix(): void
    {
        Assert::same('1.2.4', (string) Version::of('1.2.3-beta.1')->increment('patch'));
    }

    public function testAcceptsSelfInstanceInComparison(): void
    {
        Assert::same(0, Version::compare(Version::of('1.0.0'), Version::of('1.0.0')));
    }
}

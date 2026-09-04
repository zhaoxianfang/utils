<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use InvalidArgumentException;
use zxf\Utils\Net\Ip;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * IP 工具测试
 */
class IpTest extends TestCase
{
    public function testIsV4(): void
    {
        Assert::true(Ip::isV4('192.168.1.1'));
        Assert::true(Ip::isV4('255.255.255.255'));
        Assert::false(Ip::isV4('256.1.1.1'));
        Assert::false(Ip::isV4('192.168.1'));
        Assert::false(Ip::isV4('::1'));
    }

    public function testConversion(): void
    {
        Assert::same(3232235777, Ip::v4ToLong('192.168.1.1'));
        Assert::same('192.168.1.1', Ip::longToV4(3232235777));
        Assert::same(4294967295, Ip::v4ToLong('255.255.255.255'));
        Assert::same('255.255.255.255', Ip::longToV4(4294967295));
    }

    /**
     * rangeToCidr 必须产出「最小且精确」的 CIDR 列表
     *
     * 旧实现从 /32 起向大块试探、命中即停，导致任何区间都被拆成 /32，
     * 且 0.0.0.0~255.255.255.255 会生成 42 亿条记录而耗尽内存。
     */
    public function testRangeToCidrProducesMinimalSet(): void
    {
        Assert::same(['192.168.1.0/24'], Ip::rangeToCidr('192.168.1.0', '192.168.1.255'));
        Assert::same(['10.0.0.0/8'], Ip::rangeToCidr('10.0.0.0', '10.255.255.255'));
        Assert::same(['0.0.0.0/0'], Ip::rangeToCidr('0.0.0.0', '255.255.255.255'));
        Assert::same(['1.2.3.4/32'], Ip::rangeToCidr('1.2.3.4', '1.2.3.4'));
    }

    public function testRangeToCidrHandlesUnalignedBoundaries(): void
    {
        Assert::same(
            ['192.168.1.1/32', '192.168.1.2/31', '192.168.1.4/31'],
            Ip::rangeToCidr('192.168.1.1', '192.168.1.5')
        );
    }

    /**
     * 生成的 CIDR 覆盖区间必须与原始区间完全一致（不越界、不遗漏）
     */
    public function testRangeToCidrCoversExactlyTheRequestedRange(): void
    {
        $ranges = [
            ['192.168.1.1', '192.168.1.200'],
            ['10.0.0.7', '10.0.3.9'],
            ['172.16.0.1', '172.31.255.255'],
            ['0.0.0.0', '255.255.255.255'],
            ['8.8.8.8', '8.8.8.8'],
        ];

        foreach ($ranges as [$start, $end]) {
            $cidrs = Ip::rangeToCidr($start, $end);
            $startLong = Ip::v4ToLong($start);
            $endLong = Ip::v4ToLong($end);

            $cursor = $startLong;
            foreach ($cidrs as $cidr) {
                [$network, $prefix] = explode('/', $cidr);
                $prefix = (int) $prefix;

                // 每一块都必须与当前游标对齐，否则会覆盖到区间之外的地址
                Assert::same(
                    Ip::v4ToLong($network),
                    $cursor,
                    "{$start}~{$end}: CIDR {$cidr} 未与游标对齐（存在空隙或重叠）"
                );

                $cursor += 1 << (32 - $prefix);
            }

            // 最后一块的下一个地址应恰好是「结束地址 + 1」
            Assert::same($endLong + 1, $cursor, "{$start}~{$end}: CIDR 覆盖范围与原始区间不一致");
        }
    }

    public function testRangeToCidrRejectsInvalidInput(): void
    {
        Assert::throws(
            InvalidArgumentException::class,
            static fn () => Ip::rangeToCidr('not-an-ip', '192.168.1.1')
        );
        Assert::throws(
            InvalidArgumentException::class,
            static fn () => Ip::rangeToCidr('192.168.1.10', '192.168.1.1')
        );
    }

    public function testRangeRespectsSizeLimit(): void
    {
        $ips = Ip::range('192.168.1.1', '192.168.1.5');
        Assert::count(5, $ips);
        Assert::same('192.168.1.1', $ips[0]);

        Assert::throws(
            InvalidArgumentException::class,
            static fn () => Ip::range('10.0.0.0', '10.255.255.255')
        );
    }
}

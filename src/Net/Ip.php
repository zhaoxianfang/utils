<?php

declare(strict_types=1);

namespace zxf\Utils\Net;

use InvalidArgumentException;
use RuntimeException;

/**
 * IP地址工具类
 * 支持IPv4/IPv6验证、子网计算、IP范围检查、ABC类地址判断、回环/组播/链路本地地址识别、
 * CIDR汇总、反向DNS解析、端口检测、本机网络信息获取等功能
 *
 * @package Net
 * @version 2.0.0
 * @license MIT
 * @created 2026-04-23
 * @updated 2026-04-23
 */
class Ip
{
    // ========== 基础验证 ==========

    /**
     * 验证IPv4地址格式
     *
     * 采用 filter_var 的 FILTER_FLAG_IPV4 规则，只接受标准点分十进制，
     * 前导零、超范围段、带端口或掩码的写法均判为非法。
     *
     * @param string $ip 待验证的 IP 字符串
     * @return bool 是合法 IPv4 地址返回 true，否则返回 false
     */
    public static function isV4(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * 验证IPv6地址格式
     *
     * 采用 filter_var 的 FILTER_FLAG_IPV6 规则，支持 "::" 压缩写法与
     * 嵌入 IPv4 的写法，不接受带方括号或端口的形式。
     *
     * @param string $ip 待验证的 IP 字符串
     * @return bool 是合法 IPv6 地址返回 true，否则返回 false
     */
    public static function isV6(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * 验证IP地址（v4或v6）
     *
     * 只要符合 IPv4 或 IPv6 任一格式即通过，不做私有/保留段等语义判断。
     *
     * @param string $ip 待验证的 IP 字符串
     * @return bool 是合法 IP 地址返回 true，否则返回 false
     */
    public static function isValid(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP);
    }

    // ========== 地址类型判断 ==========

    /**
     * 是否为回环地址（Loopback）
     * IPv4: 127.0.0.0/8  IPv6: ::1/128
     *
     * IPv4 只按 "127." 字面前缀判断；IPv6 仅识别 ::1 与其全展开写法，
     * 非 IP 字符串一律返回 false。
     *
     * @param string $ip 待判断的 IP 字符串
     * @return bool 是回环地址返回 true，否则返回 false
     */
    public static function isLoopback(string $ip): bool
    {
        if (self::isV4($ip)) {
            return str_starts_with($ip, '127.');
        }
        return self::isV6($ip) && ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1');
    }

    /**
     * 是否为私有/内网IP
     * IPv4私有网段：10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
     *
     * 实现上取「既不属于私有段也不属于保留段」结果的反面，因此保留段
     * （如 0.0.0.0/8、169.254.0.0/16）同样会返回 true；非 IP 字符串
     * 一律返回 false。
     *
     * @param string $ip 待判断的 IP 字符串
     * @return bool 判定为私有/保留地址返回 true，否则返回 false
     */
    public static function isPrivate(string $ip): bool
    {
        // 非法的 IP 字符串不应被判为私有地址（filter_var 失败会返回 false，
        // 若直接取反会误报为 true），此处先做格式校验。
        if (! self::isValid($ip)) {
            return false;
        }

        return !(bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * 是否为保留IP（含私有地址）
     *
     * 依据 FILTER_FLAG_NO_RES_RANGE 的反面判断，覆盖 0.0.0.0/8、
     * 169.254.0.0/16、组播与 240.0.0.0/4 等段；非 IP 字符串一律返回 false。
     *
     * @param string $ip 待判断的 IP 字符串
     * @return bool 属于保留地址段返回 true，否则返回 false
     */
    public static function isReserved(string $ip): bool
    {
        if (! self::isValid($ip)) {
            return false;
        }

        return !(bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * 是否为公网可路由IP
     *
     * 需同时满足：格式合法、不属于私有段、不属于保留段。因此回环、
     * 链路本地、组播等地址都会被判为否。
     *
     * @param string $ip 待判断的 IP 字符串
     * @return bool 是公网可路由地址返回 true，否则返回 false
     */
    public static function isPublic(string $ip): bool
    {
        return self::isValid($ip) && !self::isPrivate($ip) && !self::isReserved($ip);
    }

    /**
     * 是否为组播地址（Multicast）
     * IPv4: 224.0.0.0/4  IPv6: ff00::/8
     *
     * IPv4 取首段是否落在 224-239；IPv6 只按首两个字符 "ff" 做前缀匹配，
     * 未做前缀长度校验。
     *
     * @param string $ip 待判断的 IP 字符串
     * @return bool 是组播地址返回 true，否则返回 false
     */
    public static function isMulticast(string $ip): bool
    {
        if (self::isV4($ip)) {
            $first = (int) explode('.', $ip)[0];
            return $first >= 224 && $first <= 239;
        }
        return self::isV6($ip) && str_starts_with(strtolower($ip), 'ff');
    }

    /**
     * 是否为链路本地地址（Link-Local）
     * IPv4: 169.254.0.0/16  IPv6: fe80::/10
     *
     * IPv6 通过解析二进制首两字节判断（0xFE 且次字节高 2 位为 10），
     * 可正确覆盖 fe80-febf 区间；解析失败返回 false。
     *
     * @param string $ip 待判断的 IP 字符串
     * @return bool 是链路本地地址返回 true，否则返回 false
     */
    public static function isLinkLocal(string $ip): bool
    {
        if (self::isV4($ip)) {
            return str_starts_with($ip, '169.254.');
        }
        if (self::isV6($ip)) {
            // IPv6 链路本地前缀为 fe80::/10（即 fe80 ~ febf），
            // 不能只判断 'fe8'（会漏掉 fe9/fea/feb）。
            // 通过解析二进制地址前缀精确判断：首字节为 0xFE 且次字节高 2 位为 10。
            $bin = @inet_pton($ip);
            if ($bin === false || strlen($bin) < 2) {
                return false;
            }
            return ord($bin[0]) === 0xFE && (ord($bin[1]) & 0xC0) === 0x80;
        }
        return false;
    }

    /**
     * 是否为广播地址
     * 主机位全1的IPv4地址，或255.255.255.255
     *
     * 只处理 IPv4：255.255.255.255 恒为 true；未传 $mask 时其余地址
     * 一律返回 false；传入掩码则按「主机位是否全 1」判断。
     *
     * @param string $ip 待判断的 IPv4 地址
     * @param string|null $mask 子网掩码（点分十进制），为 null 时只能识别受限广播地址
     * @return bool 是广播地址返回 true，否则返回 false
     */
    public static function isBroadcast(string $ip, ?string $mask = null): bool
    {
        if (!self::isV4($ip)) return false;
        if ($ip === '255.255.255.255') return true;
        if ($mask === null) return false;
        $ipLong = self::v4ToLong($ip);
        $maskLong = self::v4ToLong($mask);
        $hostBits = (~$maskLong) & 0xFFFFFFFF;
        return ($ipLong & $hostBits) === $hostBits;
    }

    // ========== ABC类地址判断 ==========

    /**
     * 获取IPv4地址类别（A/B/C/D/E）
     * A: 1.0.0.0 - 126.255.255.255
     * B: 128.0.0.0 - 191.255.255.255
     * C: 192.0.0.0 - 223.255.255.255
     * D: 224.0.0.0 - 239.255.255.255 (组播)
     * E: 240.0.0.0 - 255.255.255.255 (保留/实验)
     *
     * 仅按首段数值区间归类，不校验其余三段；非 IPv4 或落在 0.x、127.x
     * 等未覆盖区间时返回 'unknown'。
     *
     * @param string $ip 待判断的 IPv4 地址
     * @return string 类别字符 A/B/C/D/E，无法归类时返回 'unknown'
     */
    public static function getClass(string $ip): string
    {
        if (!self::isV4($ip)) return 'unknown';
        $first = (int) explode('.', $ip)[0];
        return match (true) {
            $first >= 1 && $first <= 126   => 'A',
            $first >= 128 && $first <= 191 => 'B',
            $first >= 192 && $first <= 223 => 'C',
            $first >= 224 && $first <= 239 => 'D',
            $first >= 240 && $first <= 255 => 'E',
            default => 'unknown',
        };
    }

    /**
     * 是否为A类地址
     *
     * 等价于 getClass($ip) === 'A'，即首段落在 1-126。
     *
     * @param string $ip 待判断的 IPv4 地址
     * @return bool 是 A 类地址返回 true，否则返回 false
     */
    public static function isClassA(string $ip): bool
    {
        return self::getClass($ip) === 'A';
    }

    /**
     * 是否为B类地址
     *
     * 等价于 getClass($ip) === 'B'，即首段落在 128-191。
     *
     * @param string $ip 待判断的 IPv4 地址
     * @return bool 是 B 类地址返回 true，否则返回 false
     */
    public static function isClassB(string $ip): bool
    {
        return self::getClass($ip) === 'B';
    }

    /**
     * 是否为C类地址
     *
     * 等价于 getClass($ip) === 'C'，即首段落在 192-223。
     *
     * @param string $ip 待判断的 IPv4 地址
     * @return bool 是 C 类地址返回 true，否则返回 false
     */
    public static function isClassC(string $ip): bool
    {
        return self::getClass($ip) === 'C';
    }

    /**
     * 获取默认子网掩码
     *
     * 按传统有类网络给出掩码：A 类 255.0.0.0、B 类 255.255.0.0、
     * C 类 255.255.255.0；D/E 类及非法地址返回 null。
     *
     * @param string $ip IPv4 地址
     * @return string|null 点分十进制掩码，无对应默认掩码时为 null
     */
    public static function getDefaultMask(string $ip): ?string
    {
        return match (self::getClass($ip)) {
            'A' => '255.0.0.0',
            'B' => '255.255.0.0',
            'C' => '255.255.255.0',
            default => null,
        };
    }

    /**
     * 获取默认CIDR前缀
     *
     * 与 getDefaultMask() 对应：A 类 8、B 类 16、C 类 24；
     * D/E 类及非法地址返回 null。
     *
     * @param string $ip IPv4 地址
     * @return int|null 前缀长度，无对应默认值时返回 null
     */
    public static function getDefaultPrefix(string $ip): ?int
    {
        return match (self::getClass($ip)) {
            'A' => 8,
            'B' => 16,
            'C' => 24,
            default => null,
        };
    }

    // ========== 地址转换 ==========

    /**
     * IPv4转无符号长整型
     *
     * 通过 sprintf('%u') 转成无符号表示再转 int，保证在 32 位与 64 位
     * 环境下都能表示 0-4294967295 的完整取值范围。
     *
     * @param string $ip 合法的 IPv4 地址
     * @return int 对应的无符号整数
     * @throws InvalidArgumentException 传入非 IPv4 地址时抛出
     */
    public static function v4ToLong(string $ip): int
    {
        if (!self::isV4($ip)) {
            throw new InvalidArgumentException('无效的IPv4地址: ' . $ip);
        }
        return (int) sprintf('%u', ip2long($ip));
    }

    /**
     * 长整型转IPv4
     *
     * 直接把整数交给 long2ip()，超出 32 位无符号范围时 PHP 的行为依平台
     * 而异，调用方应保证入参来自 v4ToLong() 的结果。
     *
     * @param int $long 0-4294967295 之间的整数
     * @return string 点分十进制 IPv4 地址
     */
    public static function longToV4(int $long): string
    {
        return long2ip($long);
    }

    /**
     * IPv4转二进制字符串（32位）
     *
     * 左侧补 0 到固定 32 位，便于按位比较或做子网可视化。
     *
     * @param string $ip 合法的 IPv4 地址
     * @return string 32 个字符的 0/1 串
     * @throws InvalidArgumentException 传入非 IPv4 地址时抛出
     */
    public static function v4ToBin(string $ip): string
    {
        return str_pad(decbin(self::v4ToLong($ip)), 32, '0', STR_PAD_LEFT);
    }

    /**
     * 压缩IPv6（RFC 5952 标准压缩）
     *
     * 借助 inet_pton/inet_ntop 完成压缩，连续 0 组会折叠为 "::"，
     * 十六进制字母统一为小写。
     *
     * @param string $ip 合法的 IPv6 地址
     * @return string 压缩后的 IPv6 表示
     * @throws InvalidArgumentException 传入非 IPv6 地址时抛出
     */
    public static function compressV6(string $ip): string
    {
        if (!self::isV6($ip)) {
            throw new InvalidArgumentException('无效的IPv6地址: ' . $ip);
        }
        return inet_ntop(inet_pton($ip));
    }

    /**
     * 展开IPv6（补全8组4位十六进制）
     *
     * 输出不带 "::" 的完整写法，每组 4 位、共 8 组，便于逐组读取。
     *
     * @param string $ip 合法的 IPv6 地址
     * @return string 展开后的完整 IPv6 表示
     * @throws InvalidArgumentException 传入非 IPv6 地址时抛出
     */
    public static function expandV6(string $ip): string
    {
        if (!self::isV6($ip)) {
            throw new InvalidArgumentException('无效的IPv6地址: ' . $ip);
        }
        $hex = unpack('H*hex', inet_pton($ip));
        return implode(':', str_split($hex['hex'], 4));
    }

    // ========== CIDR 与 子网计算 ==========

    /**
     * 解析CIDR，获取网段详细信息
     *
     * 返回网络地址、广播地址、掩码、反掩码、前缀长度、地址类别、可用主机数
     * 及首末可用地址。前缀 31/32 时没有独立网络号与广播号，first/last 直接
     * 取网络地址与广播地址；hosts 为广播减网络再减 1，最小取 0。
     *
     * @param string $cidr 形如 192.168.1.0/24 的 IPv4 CIDR
     * @return array{network: string, broadcast: string, mask: string, wildcard: string, prefix: int, class: string, hosts: int, first: string, last: string} 网段信息
     * @throws InvalidArgumentException 缺少 "/"、地址非法或前缀越界时抛出
     */
    public static function cidrInfo(string $cidr): array
    {
        if (!str_contains($cidr, '/')) {
            throw new InvalidArgumentException('CIDR格式错误，应为如 192.168.1.0/24');
        }
        [$ip, $prefix] = explode('/', $cidr, 2);
        $prefix = (int) $prefix;
        if (!self::isV4($ip) || $prefix < 0 || $prefix > 32) {
            throw new InvalidArgumentException('无效的CIDR: ' . $cidr);
        }
        $ipLong = self::v4ToLong($ip);
        // 使用无符号方式计算掩码并强制截断为 32 位，避免 32 位环境下 `-1 <<` 溢出，
        // 同时在 64 位环境下与原实现结果一致。
        $mask = $prefix === 0 ? 0 : ((0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF);
        $network = $ipLong & $mask;
        $broadcast = $network | (~$mask & 0xFFFFFFFF);
        $class = self::getClass($ip);
        return [
            'network'    => self::longToV4($network),
            'broadcast'  => self::longToV4($broadcast),
            'mask'       => self::longToV4($mask),
            'wildcard'   => self::longToV4(~$mask & 0xFFFFFFFF),
            'prefix'     => $prefix,
            'class'      => $class,
            'hosts'      => max(0, $broadcast - $network - 1),
            'first'      => $prefix >= 31 ? self::longToV4($network) : self::longToV4($network + 1),
            'last'       => $prefix >= 31 ? self::longToV4($broadcast) : self::longToV4($broadcast - 1),
        ];
    }

    /**
     * IP是否在指定CIDR网段内
     *
     * 仅支持 IPv4。任一参数非法（非 IPv4、缺 "/"、前缀越界）时不抛异常，
     * 一律返回 false。
     *
     * @param string $ip 待判断的 IPv4 地址
     * @param string $cidr 形如 192.168.1.0/24 的网段
     * @return bool 命中该网段返回 true，否则返回 false
     */
    public static function inCidr(string $ip, string $cidr): bool
    {
        if (!self::isV4($ip) || !str_contains($cidr, '/')) {
            return false;
        }
        [$net, $prefix] = explode('/', $cidr, 2);
        $prefix = (int) $prefix;
        if (!self::isV4($net) || $prefix < 0 || $prefix > 32) {
            return false;
        }
        $mask = $prefix === 0 ? 0 : ((0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF);
        return (self::v4ToLong($ip) & $mask) === (self::v4ToLong($net) & $mask);
    }

    /**
     * IP是否在指定范围内（含边界）
     *
     * 仅支持 IPv4，按无符号整数比较大小；三个参数任一非法即返回 false，
     * 且当 $start 大于 $end 时同样返回 false（不抛异常）。
     *
     * @param string $ip 待判断的 IPv4 地址
     * @param string $start 范围起始地址（含）
     * @param string $end 范围结束地址（含）
     * @return bool 落在闭区间内返回 true，否则返回 false
     */
    public static function inRange(string $ip, string $start, string $end): bool
    {
        if (!self::isV4($ip) || !self::isV4($start) || !self::isV4($end)) {
            return false;
        }
        $ipLong = self::v4ToLong($ip);
        return $ipLong >= self::v4ToLong($start) && $ipLong <= self::v4ToLong($end);
    }

    /**
     * 计算给定IP和掩码的网络地址
     *
     * 按位与得到网络号，结果与主机位取值无关。
     *
     * @param string $ip IPv4 地址
     * @param string $mask 子网掩码（点分十进制）
     * @return string 网络地址
     * @throws InvalidArgumentException IP 或掩码非法时抛出
     */
    public static function getNetworkAddress(string $ip, string $mask): string
    {
        if (!self::isV4($ip) || !self::isV4($mask)) {
            throw new InvalidArgumentException('无效的IP或掩码');
        }
        return self::longToV4(self::v4ToLong($ip) & self::v4ToLong($mask));
    }

    /**
     * 计算给定IP和掩码的广播地址
     *
     * 把主机位全部置 1，结果即该子网的广播地址。
     *
     * @param string $ip IPv4 地址
     * @param string $mask 子网掩码（点分十进制）
     * @return string 广播地址
     * @throws InvalidArgumentException IP 或掩码非法时抛出
     */
    public static function getBroadcastAddress(string $ip, string $mask): string
    {
        if (!self::isV4($ip) || !self::isV4($mask)) {
            throw new InvalidArgumentException('无效的IP或掩码');
        }
        return self::longToV4(self::v4ToLong($ip) | (~self::v4ToLong($mask) & 0xFFFFFFFF));
    }

    /**
     * 获取反掩码（Wildcard Mask）
     *
     * 对掩码按位取反并截断为 32 位，常用于 ACL 通配符写法。
     *
     * @param string $mask 子网掩码（点分十进制）
     * @return string 反掩码
     * @throws InvalidArgumentException 掩码非法时抛出
     */
    public static function getWildcardMask(string $mask): string
    {
        if (!self::isV4($mask)) {
            throw new InvalidArgumentException('无效的掩码');
        }
        return self::longToV4(~self::v4ToLong($mask) & 0xFFFFFFFF);
    }

    /**
     * 将IP范围转换为最小CIDR列表（CIDR聚合）
     *
     * 每次迭代取「既对齐到块边界、又不超出结束地址」的最大块，块大小用
     * 前缀长度表示，直至覆盖整个区间。结果块数与被覆盖地址数的二进制
     * 位数同阶，不会因大范围而爆炸式增长。
     *
     * @param string $start 起始 IPv4 地址（含）
     * @param string $end 结束 IPv4 地址（含）
     * @return array<int, string> CIDR 字符串列表，按地址升序
     * @throws InvalidArgumentException 地址非法或起始大于结束时抛出
     */
    public static function rangeToCidr(string $start, string $end): array
    {
        if (!self::isV4($start) || !self::isV4($end)) {
            throw new InvalidArgumentException('无效的IPv4地址');
        }
        $startLong = self::v4ToLong($start);
        $endLong = self::v4ToLong($end);
        if ($startLong > $endLong) {
            throw new InvalidArgumentException('起始IP不能大于结束IP');
        }

        $cidrs = [];
        $cursor = $startLong;

        // 每次迭代都取「能覆盖当前起始地址的最大块」，块大小受两个约束：
        //  1. 对齐约束：起始地址必须落在块边界上，由地址末尾连续 0 的个数决定；
        //  2. 容量约束：块不能超出结束地址，由剩余地址数量决定。
        // 前缀长度（0~32）越小块越大，因此取两个约束的较大值。
        //
        // 早期实现从 /32 开始向小前缀试探、命中即停，结果永远返回 /32，
        // 且遇到 0.0.0.0~255.255.255.255 这种大范围会生成数十亿条记录而耗尽内存。
        while ($cursor <= $endLong) {
            // 约束一：对齐
            $maxByAlign = 0; // 起始地址为 0.0.0.0 时，可对齐到任意大小的块
            if ($cursor !== 0) {
                $lowbit = $cursor & -$cursor; // 最低位的 1，其位序即末尾连续 0 的个数
                $bits = 0;
                for ($tmp = $lowbit; $tmp > 1; $tmp >>= 1) {
                    $bits++;
                }
                $maxByAlign = 32 - $bits;
            }

            // 约束二：容量
            $bits = 0;
            for ($tmp = $endLong - $cursor + 1; $tmp > 1; $tmp >>= 1) {
                $bits++;
            }
            $maxBySize = 32 - $bits;

            $prefix = max($maxByAlign, $maxBySize);

            $cidrs[] = self::longToV4($cursor) . '/' . $prefix;

            // 前进一个块。$prefix 为 0 时块大小为 2^32，必然越过结束地址，
            // 循环随之终止（64 位整数下不会溢出）。
            $cursor += 1 << (32 - $prefix);
        }

        return $cidrs;
    }

    /**
     * 获取IP地址段内所有地址列表
     * 注意：大范围会消耗大量内存，建议使用 rangeToCidr()
     *
     * 区间为闭区间，含起始与结束地址；单次最多枚举 65536 个地址，
     * 超出即抛异常而非静默截断。
     *
     * @param string $start 起始 IPv4 地址（含）
     * @param string $end 结束 IPv4 地址（含）
     * @return array<int, string> 点分十进制地址列表，按升序
     * @throws InvalidArgumentException 地址非法、起始大于结束或范围超过 65536 个地址时抛出
     */
    public static function range(string $start, string $end): array
    {
        if (!self::isV4($start) || !self::isV4($end)) {
            throw new InvalidArgumentException('无效的IPv4地址');
        }
        $s = self::v4ToLong($start);
        $e = self::v4ToLong($end);
        if ($s > $e) {
            throw new InvalidArgumentException('起始IP不能大于结束IP');
        }
        if ($e - $s > 65535) {
            throw new InvalidArgumentException('范围过大（超过65536个地址），请使用 rangeToCidr()');
        }
        $ips = [];
        for ($i = $s; $i <= $e; $i++) {
            $ips[] = self::longToV4($i);
        }
        return $ips;
    }

    // ========== DNS 与 网络诊断 ==========

    /**
     * 正向解析：域名解析为IP地址列表
     *
     * 同时查询 A 与 AAAA 记录并去重，重新索引后返回；只保留记录中的
     * ip/ipv6 字段，因此返回的是纯地址数组。
     *
     * @param string $hostname 待解析的域名
     * @return array<int, string> IP 地址数组（IPv4 与 IPv6 混合，已去重）
     * @throws RuntimeException DNS 查询失败时抛出
     */
    public static function resolve(string $hostname): array
    {
        $records = dns_get_record($hostname, DNS_A | DNS_AAAA);
        if ($records === false) {
            throw new RuntimeException('DNS解析失败: ' . $hostname);
        }
        $ips = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) $ips[] = $record['ip'];
            if (isset($record['ipv6'])) $ips[] = $record['ipv6'];
        }
        return array_values(array_unique($ips));
    }

    /**
     * 反向解析：IP解析为域名
     *
     * 结果与入参相同（即无 PTR 记录、退化为原地址）时视为失败，返回 null；
     * 解析依赖本机 DNS 配置，可能阻塞到超时。
     *
     * @param string $ip 待反解的 IP 地址
     * @return string|null 解析到的域名；无法解析时为 null
     * @throws InvalidArgumentException IP 格式非法时抛出
     */
    public static function getHostname(string $ip): ?string
    {
        if (!self::isValid($ip)) {
            throw new InvalidArgumentException('无效的IP地址');
        }
        $result = gethostbyaddr($ip);
        return $result !== false && $result !== $ip ? $result : null;
    }

    /**
     * 检测TCP端口是否开放
     *
     * 通过 fsockopen 尝试建立连接，成功即视为开放并立即关闭连接；连接
     * 失败只返回 false，不返回具体错误原因。
     *
     * @param string $ip 目标 IP 地址
     * @param int $port 目标端口（1-65535）
     * @param int $timeout 超时时间（秒），默认3
     * @return bool 端口可连接返回 true，否则返回 false
     * @throws InvalidArgumentException IP 非法或端口越界时抛出
     */
    public static function isPortOpen(string $ip, int $port, int $timeout = 3): bool
    {
        if (!self::isValid($ip) || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException('无效的IP或端口');
        }
        $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }

    /**
     * 获取客户端真实IP（支持CDN/代理转发头）
     *
     * 安全模型：仅当直连来源 REMOTE_ADDR 命中 $trustedProxies 可信代理白名单时，
     * 才采纳 X-Forwarded-For 等转发头；否则直接返回直连 IP，避免客户端伪造 IP。
     *
     * @param array $trustedProxies 可信代理 IP 白名单（如负载均衡/反代的内网地址），为空则不信任任何转发头
     * @return string|null
     */
    public static function getClientIp(array $trustedProxies = []): ?string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

        // 非可信代理直连：直接返回直连 IP，拒绝任何伪造的转发头
        if ($remoteAddr === null || ! in_array($remoteAddr, $trustedProxies, true)) {
            return $remoteAddr;
        }

        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];
        foreach ($headers as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }
            $ips = explode(',', $_SERVER[$header]);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (self::isValid($ip)) {
                    return $ip;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * 获取本机所有网卡IP地址
     * @return array ['eth0' => ['192.168.1.2', ...], ...]
     */
    public static function getLocalIps(): array
    {
        $os = PHP_OS_FAMILY;
        $interfaces = [];

        if ($os === 'Windows') {
            $output = shell_exec('ipconfig /all');
            if ($output) {
                preg_match_all('/IPv4.*?[:：]\s+(\d+\.\d+\.\d+\.\d+)/', $output, $matches);
                $interfaces['default'] = $matches[1] ?? [];
            }
        } else {
            $output = shell_exec('ip -4 -o addr show 2>/dev/null || ifconfig -a 2>/dev/null');
            if ($output) {
                preg_match_all('/(\S+).*?inet\s+(\d+\.\d+\.\d+\.\d+)/', $output, $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $interfaces[$m[1]][] = $m[2];
                }
            }
        }

        // 兜底方案
        if (empty($interfaces)) {
            $hostname = gethostname();
            if ($hostname) {
                $ips = gethostbyname($hostname);
                if ($ips && $ips !== $hostname) {
                    $interfaces['default'] = array_filter(explode(',', $ips));
                }
            }
        }

        return $interfaces;
    }

    /**
     * 获取本机默认IP（非回环、非链路本地）
     *
     * 遍历 getLocalIps() 的结果，返回第一个满足条件的 IPv4 地址；
     * 找不到时返回 null。
     *
     * @return string|null 默认 IPv4 地址，无可用网卡时为 null
     */
    public static function getLocalIp(): ?string
    {
        $interfaces = self::getLocalIps();
        foreach ($interfaces as $ips) {
            foreach ($ips as $ip) {
                if (self::isV4($ip) && !self::isLoopback($ip) && !self::isLinkLocal($ip)) {
                    return $ip;
                }
            }
        }
        return null;
    }

    /**
     * 获取指定网卡的MAC地址
     *
     * 依赖系统命令（Windows 用 getmac，类 Unix 用 ip link show）解析输出；
     * $interface 为空时取第一块网卡。命令不可用或解析失败返回 null，
     * 结果统一转为小写并以冒号分隔。
     *
     * @param string $interface 网卡名（如 eth0），空字符串表示不限网卡
     * @return string|null 形如 aa:bb:cc:dd:ee:ff 的 MAC 地址；获取失败时为 null
     */
    public static function getMacAddress(string $interface = ''): ?string
    {
        $os = PHP_OS_FAMILY;
        $mac = null;

        if ($os === 'Windows') {
            $output = shell_exec('getmac /v /fo csv 2>nul');
            if ($output && preg_match('/"([^"]{17})"/', $output, $m)) {
                $mac = $m[1];
            }
        } else {
            $cmd = $interface !== '' ? 'ip link show ' . escapeshellarg($interface) . ' 2>/dev/null' : "ip link show 2>/dev/null | grep ether";
            $output = shell_exec($cmd);
            if ($output && preg_match('/ether\s+([0-9a-f:]{17})/', $output, $m)) {
                $mac = $m[1];
            }
        }

        return $mac ? strtolower(str_replace('-', ':', $mac)) : null;
    }

    // ========== 综合信息 ==========

    /**
     * 获取IP综合信息
     *
     * 汇总版本、合法性、回环/私有/公网/组播/链路本地等判定结果；IPv4 额外
     * 附带类别、整数、二进制与默认掩码，IPv6 额外附带压缩与展开表示。
     *
     * @param string $ip 待分析的 IP 地址
     * @return array<string, mixed> 综合信息数组，键名随地址版本不同而增减
     * @throws InvalidArgumentException IP 格式非法时抛出
     */
    public static function info(string $ip): array
    {
        if (!self::isValid($ip)) {
            throw new InvalidArgumentException('无效的IP地址');
        }

        $info = [
            'ip'          => $ip,
            'version'     => self::isV4($ip) ? 4 : 6,
            'is_valid'    => true,
            'is_loopback' => self::isLoopback($ip),
            'is_private'  => self::isPrivate($ip),
            'is_public'   => self::isPublic($ip),
            'is_multicast' => self::isMulticast($ip),
            'is_link_local' => self::isLinkLocal($ip),
        ];

        if (self::isV4($ip)) {
            $info['class'] = self::getClass($ip);
            $info['long'] = self::v4ToLong($ip);
            $info['binary'] = self::v4ToBin($ip);
            $info['default_mask'] = self::getDefaultMask($ip);
        }

        if (self::isV6($ip)) {
            $info['compressed'] = self::compressV6($ip);
            $info['expanded'] = self::expandV6($ip);
        }

        return $info;
    }

    // ========== 随机生成 ==========

    /**
     * 生成随机IPv4地址
     *
     * 四段均用 random_int 取值，首末段避开 0 与 255；结果不保证是公网
     * 地址，需要公网地址请用 randomPublicV4()。
     *
     * @return string 随机生成的 IPv4 地址
     */
    public static function randomV4(): string
    {
        return implode('.', [random_int(1, 254), random_int(0, 255), random_int(0, 255), random_int(1, 254)]);
    }

    /**
     * 在指定CIDR内生成随机IPv4地址
     *
     * 以 cidrInfo() 给出的首末可用地址为上下界取随机值，因此在 /31、/32
     * 等特殊网段下也能返回有效地址。
     *
     * @param string $cidr 形如 192.168.1.0/24 的 IPv4 CIDR
     * @return string 该网段内的随机 IPv4 地址
     * @throws InvalidArgumentException CIDR 格式非法时抛出
     */
    public static function randomInCidr(string $cidr): string
    {
        $info = self::cidrInfo($cidr);
        $first = self::v4ToLong($info['first']);
        $last = self::v4ToLong($info['last']);
        return self::longToV4(random_int($first, $last));
    }

    /**
     * 生成随机公网IPv4地址
     *
     * 反复调用 randomV4() 直到结果通过 isPublic() 判定，理论上存在循环，
     * 实际命中率较高。仅保证格式上属于公网段，不保证地址真实可达。
     *
     * @return string 随机生成的公网 IPv4 地址
     */
    public static function randomPublicV4(): string
    {
        do {
            $ip = self::randomV4();
        } while (!self::isPublic($ip));
        return $ip;
    }
}

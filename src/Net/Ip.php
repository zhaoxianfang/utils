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
     */
    public static function isV4(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * 验证IPv6地址格式
     */
    public static function isV6(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * 验证IP地址（v4或v6）
     */
    public static function isValid(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP);
    }

    // ========== 地址类型判断 ==========

    /**
     * 是否为回环地址（Loopback）
     * IPv4: 127.0.0.0/8  IPv6: ::1/128
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
     */
    public static function isPrivate(string $ip): bool
    {
        return !(bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * 是否为保留IP（含私有地址）
     */
    public static function isReserved(string $ip): bool
    {
        return !(bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * 是否为公网可路由IP
     */
    public static function isPublic(string $ip): bool
    {
        return self::isValid($ip) && !self::isPrivate($ip) && !self::isReserved($ip);
    }

    /**
     * 是否为组播地址（Multicast）
     * IPv4: 224.0.0.0/4  IPv6: ff00::/8
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
     */
    public static function isLinkLocal(string $ip): bool
    {
        if (self::isV4($ip)) {
            return str_starts_with($ip, '169.254.');
        }
        return self::isV6($ip) && str_starts_with(strtolower($ip), 'fe8');
    }

    /**
     * 是否为广播地址
     * 主机位全1的IPv4地址，或255.255.255.255
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

    /** 是否为A类地址 */
    public static function isClassA(string $ip): bool
    {
        return self::getClass($ip) === 'A';
    }

    /** 是否为B类地址 */
    public static function isClassB(string $ip): bool
    {
        return self::getClass($ip) === 'B';
    }

    /** 是否为C类地址 */
    public static function isClassC(string $ip): bool
    {
        return self::getClass($ip) === 'C';
    }

    /** 获取默认子网掩码 */
    public static function getDefaultMask(string $ip): ?string
    {
        return match (self::getClass($ip)) {
            'A' => '255.0.0.0',
            'B' => '255.255.0.0',
            'C' => '255.255.255.0',
            default => null,
        };
    }

    /** 获取默认CIDR前缀 */
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
     */
    public static function longToV4(int $long): string
    {
        return long2ip($long);
    }

    /**
     * IPv4转二进制字符串（32位）
     */
    public static function v4ToBin(string $ip): string
    {
        return str_pad(decbin(self::v4ToLong($ip)), 32, '0', STR_PAD_LEFT);
    }

    /**
     * 压缩IPv6（RFC 5952 标准压缩）
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
        $mask = -1 << (32 - $prefix);
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
     */
    public static function inCidr(string $ip, string $cidr): bool
    {
        if (!self::isV4($ip)) return false;
        [$net, $prefix] = explode('/', $cidr, 2);
        $mask = -1 << (32 - (int) $prefix);
        return (self::v4ToLong($ip) & $mask) === (self::v4ToLong($net) & $mask);
    }

    /**
     * IP是否在指定范围内（含边界）
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
        while ($startLong <= $endLong) {
            // 找到最大的CIDR块
            $maxSize = 32;
            while ($maxSize > 0) {
                $mask = 0xFFFFFFFF << (32 - $maxSize);
                $maskedStart = $startLong & $mask;
                $blockEnd = $maskedStart + pow(2, 32 - $maxSize) - 1;
                if ($maskedStart === $startLong && $blockEnd <= $endLong) {
                    break;
                }
                $maxSize--;
            }
            $cidrs[] = self::longToV4($startLong) . '/' . $maxSize;
            $startLong += (int) pow(2, 32 - $maxSize);
        }
        return $cidrs;
    }

    /**
     * 获取IP地址段内所有地址列表
     * 注意：大范围会消耗大量内存，建议使用 rangeToCidr()
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
     * @return array IP地址数组
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
     * @param int $timeout 超时时间（秒），默认3
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
     * 获取客户端真实IP（支持CDN代理头）
     * 优先级：CF-Connecting-IP > X-Forwarded-For > X-Real-IP > REMOTE_ADDR
     */
    public static function getClientIp(): ?string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];
        foreach ($headers as $header) {
            if (empty($_SERVER[$header])) continue;
            $ips = explode(',', $_SERVER[$header]);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (self::isValid($ip) && !self::isPrivate($ip)) {
                    return $ip;
                }
                if (self::isValid($ip)) {
                    return $ip;
                }
            }
        }
        return null;
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
     */
    public static function getLocalIp(): ?string
    {
        $interfaces = self::getLocalIps();
        foreach ($interfaces as $name => $ips) {
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
            $cmd = $interface ? "ip link show {$interface} 2>/dev/null" : "ip link show 2>/dev/null | grep ether";
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
     */
    public static function randomV4(): string
    {
        return implode('.', [random_int(1, 254), random_int(0, 255), random_int(0, 255), random_int(1, 254)]);
    }

    /**
     * 在指定CIDR内生成随机IPv4地址
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
     */
    public static function randomPublicV4(): string
    {
        do {
            $ip = self::randomV4();
        } while (!self::isPublic($ip));
        return $ip;
    }
}

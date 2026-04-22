<?php

declare(strict_types=1);

namespace zxf\Utils\Net;

use InvalidArgumentException;
use RuntimeException;

/**
 * 域名工具类
 * 提供域名格式验证、IDN 中文域名处理、Punycode 转换、DNS 记录查询、WHOIS 基础查询等功能
 *
 * @package Net
 * @version 1.0.0
 * @license MIT
 */
class Domain
{
    /**
     * 验证域名格式（支持 IDN 中文域名）
     *
     * 先将中文域名转换为 Punycode 后再进行正则校验
     *
     * @param string $domain 域名，如 "example.com" 或 "中国.cn"
     * @return bool 格式有效返回 true
     */
    public static function isValid(string $domain): bool
    {
        // 先转换为Punycode以统一验证
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        $check = $ascii !== false ? $ascii : $domain;
        return (bool) preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])$/', $check);
    }

    /**
     * 验证是否为顶级域名（TLD），即不含点号
     *
     * @param string $domain 域名
     * @return bool 是顶级域名返回 true
     */
    public static function isTld(string $domain): bool
    {
        return !str_contains($domain, '.');
    }

    /**
     * 验证是否为子域名（包含至少两个点号）
     *
     * @param string $domain 域名
     * @return bool 是子域名返回 true
     */
    public static function isSubdomain(string $domain): bool
    {
        return substr_count($domain, '.') > 1;
    }

    /**
     * 获取域名的顶级域名（TLD）
     *
     * 支持识别常见的双部分 TLD，如 co.uk、com.cn 等
     *
     * @param string $domain 域名，如 "www.sub.example.co.uk"
     * @return string 顶级域名，如 "co.uk" 或 "com"
     */
    public static function getTld(string $domain): string
    {
        $parts = explode('.', strtolower($domain));
        // 常见双后缀TLD列表
        $doubleTlds = ['co.uk', 'com.cn', 'net.cn', 'org.cn', 'gov.cn', 'co.jp', 'com.au', 'co.nz', 'com.tw', 'com.hk'];
        $lastTwo = implode('.', array_slice($parts, -2));
        if (in_array($lastTwo, $doubleTlds, true)) {
            return $lastTwo;
        }
        return end($parts);
    }

    /**
     * 获取注册域名（二级域名，即主域名）
     *
     * @param string $domain 域名，如 "www.sub.example.com"
     * @return string 注册域名，如 "example.com"
     */
    public static function getRegisteredDomain(string $domain): string
    {
        $tld = self::getTld($domain);
        $domain = strtolower($domain);
        $tldPos = strrpos($domain, '.' . $tld);
        if ($tldPos === false) return $domain;
        $before = substr($domain, 0, $tldPos);
        $lastDot = strrpos($before, '.');
        $sld = $lastDot !== false ? substr($before, $lastDot + 1) : $before;
        return $sld . '.' . $tld;
    }

    /**
     * 获取子域名部分（不含注册域名）
     *
     * @param string $domain 域名，如 "www.sub.example.com"
     * @return string|null 子域名部分，如 "www.sub"；若无子域名返回 null
     */
    public static function getSubdomain(string $domain): ?string
    {
        $reg = self::getRegisteredDomain($domain);
        if ($domain === $reg) return null;
        $sub = substr($domain, 0, -(strlen($reg) + 1));
        return $sub !== false && $sub !== '' ? $sub : null;
    }

    /**
     * 将 Unicode 域名（含中文）转换为 Punycode ASCII 编码
     *
     * @param string $domain Unicode 域名，如 "中国.cn"
     * @return string Punycode 域名，如 "xn--fiqs8s.cn"
     */
    public static function toAscii(string $domain): string
    {
        $result = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        return $result !== false ? $result : $domain;
    }

    /**
     * 将 Punycode 域名转换为 Unicode 域名（显示中文等）
     *
     * @param string $domain Punycode 域名，如 "xn--fiqs8s.cn"
     * @return string Unicode 域名，如 "中国.cn"
     */
    public static function toUnicode(string $domain): string
    {
        $result = idn_to_utf8($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        return $result !== false ? $result : $domain;
    }

    /**
     * 解析域名的 A/AAAA 记录，获取 IP 地址列表
     *
     * @param string $domain 域名
     * @return string[] IPv4/IPv6 地址列表
     * @throws InvalidArgumentException 当域名格式无效时抛出
     * @throws RuntimeException 当 DNS 解析失败时抛出
     */
    public static function resolve(string $domain): array
    {
        if (!self::isValid($domain)) {
            throw new InvalidArgumentException('无效的域名: ' . $domain);
        }
        $records = dns_get_record($domain, DNS_A | DNS_AAAA);
        if ($records === false) {
            throw new RuntimeException('DNS解析失败: ' . $domain);
        }
        $ips = [];
        foreach ($records as $r) {
            if (isset($r['ip'])) $ips[] = $r['ip'];
            if (isset($r['ipv6'])) $ips[] = $r['ipv6'];
        }
        return array_values(array_unique($ips));
    }

    /**
     * 获取域名的 MX（邮件交换）记录
     *
     * @param string $domain 域名
     * @return array 按优先级排序的 MX 记录数组，格式为 [['host' => 'mx.example.com', 'pri' => 10], ...]
     * @throws InvalidArgumentException 当域名格式无效时抛出
     */
    public static function getMxRecords(string $domain): array
    {
        if (!self::isValid($domain)) {
            throw new InvalidArgumentException('无效的域名');
        }
        $records = dns_get_record($domain, DNS_MX);
        if ($records === false) return [];
        $mx = [];
        foreach ($records as $r) {
            if (isset($r['target'])) {
                $mx[] = ['host' => $r['target'], 'pri' => $r['pri'] ?? 0];
            }
        }
        usort($mx, fn($a, $b) => $a['pri'] <=> $b['pri']);
        return $mx;
    }

    /**
     * 获取域名的 TXT 记录
     *
     * @param string $domain 域名
     * @return string[] TXT 记录文本数组
     * @throws InvalidArgumentException 当域名格式无效时抛出
     */
    public static function getTxtRecords(string $domain): array
    {
        if (!self::isValid($domain)) {
            throw new InvalidArgumentException('无效的域名');
        }
        $records = dns_get_record($domain, DNS_TXT);
        if ($records === false) return [];
        return array_column($records, 'txt');
    }

    /**
     * 获取域名的 NS（域名服务器）记录
     *
     * @param string $domain 域名
     * @return string[] NS 服务器域名数组
     * @throws InvalidArgumentException 当域名格式无效时抛出
     */
    public static function getNsRecords(string $domain): array
    {
        if (!self::isValid($domain)) {
            throw new InvalidArgumentException('无效的域名');
        }
        $records = dns_get_record($domain, DNS_NS);
        if ($records === false) return [];
        return array_column($records, 'target');
    }

    /**
     * 获取域名的 SOA（授权起始）记录
     *
     * @param string $domain 域名
     * @return array|null SOA 记录数组，包含 mname、rname、serial 等字段；无记录返回 null
     * @throws InvalidArgumentException 当域名格式无效时抛出
     */
    public static function getSoaRecord(string $domain): ?array
    {
        if (!self::isValid($domain)) {
            throw new InvalidArgumentException('无效的域名');
        }
        $records = dns_get_record($domain, DNS_SOA);
        if (empty($records)) return null;
        return [
            'mname'   => $records[0]['mname'] ?? null,
            'rname'   => $records[0]['rname'] ?? null,
            'serial'  => $records[0]['serial'] ?? null,
            'refresh' => $records[0]['refresh'] ?? null,
            'retry'   => $records[0]['retry'] ?? null,
            'expire'  => $records[0]['expire'] ?? null,
            'minimum' => $records[0]['minimum-ttl'] ?? null,
        ];
    }

    /**
     * 检查域名是否已注册（通过查询 NS 记录判断）
     *
     * @param string $domain 域名
     * @return bool 已注册返回 true，未注册或查询失败返回 false
     */
    public static function isRegistered(string $domain): bool
    {
        return !empty(self::getNsRecords($domain));
    }

    /**
     * 获取域名的 WHOIS 信息（基础查询，依赖系统 whois 命令）
     *
     * @param string $domain 域名
     * @return string|null WHOIS 原始文本；命令不可用或无结果返回 null
     */
    public static function whois(string $domain): ?string
    {
        $domain = escapeshellarg($domain);
        $result = shell_exec("whois {$domain} 2>/dev/null");
        return $result !== null && trim($result) !== '' ? $result : null;
    }

    /**
     * 生成随机域名
     *
     * @param string $tld    顶级域名，默认 "com"
     * @param int    $length 随机名称长度，默认 8
     * @return string 随机域名，如 "a3k9m2p7.com"
     */
    public static function random(string $tld = 'com', int $length = 8): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $name = '';
        $charLen = strlen($chars);
        for ($i = 0; $i < $length; $i++) {
            $name .= $chars[random_int(0, $charLen - 1)];
        }
        return $name . '.' . $tld;
    }
}

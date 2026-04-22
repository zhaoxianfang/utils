<?php

declare(strict_types=1);

namespace zxf\Utils\Crypto;

use InvalidArgumentException;
use RuntimeException;

/**
 * 安全 Base64 编解码类
 * 提供标准 Base64、URL 安全 Base64（RFC 4648）、无填充 Base64 的编解码功能
 * 支持文件 Base64 转换和 Data URI 处理
 *
 * @package Crypto
 * @version 1.0.0
 * @license MIT
 */
class Base64
{
    /** @var array<string,string> URL 安全替换映射（标准 → URL安全） */
    private const URL_SAFE_REPLACEMENTS = ['+' => '-', '/' => '_'];

    /** @var array<string,string> URL 安全反向映射（URL安全 → 标准） */
    private const URL_SAFE_REVERSE = ['-' => '+', '_' => '/'];

    /**
     * 对二进制数据进行标准 Base64 编码
     *
     * @param string $data 原始二进制数据
     * @return string Base64 编码字符串（含填充符 =）
     */
    public static function encode(string $data): string
    {
        return base64_encode($data);
    }

    /**
     * 对标准 Base64 字符串进行解码
     *
     * @param string $data   Base64 编码字符串
     * @param bool   $strict 是否严格模式（拒绝非 Base64 字符），默认 true
     * @return string|false 解码后的二进制数据，失败返回 false
     */
    public static function decode(string $data, bool $strict = true): string|false
    {
        return base64_decode($data, $strict);
    }

    /**
     * 对二进制数据进行 URL 安全 Base64 编码（RFC 4648）
     *
     * 将 "+" 替换为 "-", "/" 替换为 "_"，可选择是否保留填充符 "="
     *
     * @param string $data    原始二进制数据
     * @param bool   $padding 是否保留填充符，默认 false（推荐用于 URL 参数）
     * @return string URL 安全 Base64 字符串
     */
    public static function urlSafeEncode(string $data, bool $padding = false): string
    {
        $encoded = base64_encode($data);
        $encoded = strtr($encoded, self::URL_SAFE_REPLACEMENTS);
        if (!$padding) {
            $encoded = rtrim($encoded, '=');
        }
        return $encoded;
    }

    /**
     * 对 URL 安全 Base64 字符串进行解码
     *
     * 自动将 "-" 还原为 "+", "_" 还原为 "/"，并补全填充符
     *
     * @param string $data   URL 安全 Base64 字符串
     * @param bool   $strict 是否严格模式，默认 true
     * @return string|false 解码后的二进制数据，失败返回 false
     */
    public static function urlSafeDecode(string $data, bool $strict = true): string|false
    {
        $data = strtr($data, self::URL_SAFE_REVERSE);
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode($data, $strict);
    }

    /**
     * 对二进制数据进行无填充标准 Base64 编码
     *
     * @param string $data 原始二进制数据
     * @return string 无填充符的 Base64 字符串
     */
    public static function encodeNoPadding(string $data): string
    {
        return rtrim(base64_encode($data), '=');
    }

    /**
     * 对无填充 Base64 字符串进行解码
     *
     * @param string $data   无填充 Base64 字符串
     * @param bool   $strict 是否严格模式，默认 true
     * @return string|false 解码后的二进制数据，失败返回 false
     */
    public static function decodeNoPadding(string $data, bool $strict = true): string|false
    {
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode($data, $strict);
    }

    /**
     * 将文件内容编码为 Base64 字符串（支持大文件分块读取）
     *
     * @param string $filePath  文件路径
     * @param int    $chunkSize 每次读取的块大小（字节），默认 1MB
     * @return string Base64 编码后的文件内容
     * @throws InvalidArgumentException 当文件不存在时抛出
     * @throws RuntimeException 当文件读取失败时抛出
     */
    public static function encodeFile(string $filePath, int $chunkSize = 1048576): string
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("文件不存在: {$filePath}");
        }
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("无法打开文件: {$filePath}");
        }
        $encoded = '';
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException("读取文件失败");
                }
                $encoded .= base64_encode($chunk);
            }
        } finally {
            fclose($handle);
        }
        return $encoded;
    }

    /**
     * 将 Base64 字符串解码并写入文件
     *
     * @param string $data     Base64 编码字符串
     * @param string $filePath 目标文件路径（目录不存在时自动创建）
     * @param bool   $strict   是否严格模式，默认 true
     * @return int 写入文件的字节数
     * @throws InvalidArgumentException 当 Base64 解码失败时抛出
     * @throws RuntimeException 当文件写入失败时抛出
     */
    public static function decodeToFile(string $data, string $filePath, bool $strict = true): int
    {
        $decoded = base64_decode($data, $strict);
        if ($decoded === false) {
            throw new InvalidArgumentException('Base64解码失败');
        }
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $bytes = file_put_contents($filePath, $decoded, LOCK_EX);
        if ($bytes === false) {
            throw new RuntimeException("写入文件失败: {$filePath}");
        }
        return $bytes;
    }

    /**
     * 将二进制数据封装为 Data URI 字符串
     *
     * @param string $data     原始二进制数据
     * @param string $mimeType MIME 类型，默认 application/octet-stream
     * @return string Data URI 字符串，如 "data:image/png;base64,iVBORw0..."
     */
    public static function toDataUri(string $data, string $mimeType = 'application/octet-stream'): string
    {
        return "data:{$mimeType};base64," . base64_encode($data);
    }

    /**
     * 从 Data URI 字符串中解析出 MIME 类型和二进制数据
     *
     * @param string $dataUri Data URI 字符串
     * @return array{mime:string,data:string} 包含 mime 类型和 data 数据的数组
     * @throws InvalidArgumentException 当 Data URI 格式无效或解码失败时抛出
     */
    public static function fromDataUri(string $dataUri): array
    {
        if (!str_starts_with($dataUri, 'data:')) {
            throw new InvalidArgumentException('无效的Data URI');
        }
        $content = substr($dataUri, 5);
        $commaPos = strpos($content, ',');
        if ($commaPos === false) {
            throw new InvalidArgumentException('无效的Data URI');
        }
        $meta = substr($content, 0, $commaPos);
        $data = substr($content, $commaPos + 1);
        $isBase64 = str_ends_with($meta, ';base64');
        if ($isBase64) {
            $meta = substr($meta, 0, -7);
            $decoded = base64_decode($data, true);
            if ($decoded === false) {
                throw new InvalidArgumentException('Base64解码失败');
            }
            $data = $decoded;
        } else {
            $data = rawurldecode($data);
        }
        return ['mime' => $meta ?: 'text/plain', 'data' => $data];
    }

    /**
     * 验证字符串是否为有效的 Base64 编码
     *
     * @param string $data   待验证的字符串
     * @param bool   $strict 是否进行字符集和长度严格校验，默认 true
     * @return bool 有效返回 true，无效返回 false
     */
    public static function isValid(string $data, bool $strict = true): bool
    {
        if ($strict) {
            if (!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $data)) {
                return false;
            }
            if (strlen($data) % 4 !== 0) {
                return false;
            }
        }
        return base64_decode($data, true) !== false;
    }

    /**
     * 根据原始数据长度计算 Base64 编码后的长度
     *
     * @param int  $originalLength 原始数据字节长度
     * @param bool $padding        是否包含填充符，默认 true
     * @return int Base64 编码后的字符串长度
     */
    public static function encodedLength(int $originalLength, bool $padding = true): int
    {
        $length = (int) ceil($originalLength / 3) * 4;
        if (!$padding) {
            $r = $originalLength % 3;
            $length -= $r === 1 ? 2 : ($r === 2 ? 1 : 0);
        }
        return $length;
    }

    /**
     * 根据 Base64 编码字符串长度估算原始数据长度（不含填充符）
     *
     * @param int $encodedLength Base64 编码字符串长度
     * @return int 原始数据最大可能字节长度（实际可能少1-2字节）
     */
    public static function decodedLength(int $encodedLength): int
    {
        return (int) floor($encodedLength / 4) * 3;
    }
}

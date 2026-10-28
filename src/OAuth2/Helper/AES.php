<?php

declare(strict_types=1);

namespace zxf\Utils\OAuth2\Helper;

use InvalidArgumentException;

/**
 * Class AES.
 *
 * 轻量 AES-CBC 加解密封装，密钥长度决定算法（16/24/32 字节 → 128/192/256 位）。
 * 主要用于解密第三方平台（如微信小程序）下发的加密数据。
 *
 * @author overtrue <i@overtrue.me>
 *
 * @package zxf\Utils\OAuth2\Helper
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * ```php
 * $key = '0123456789abcdef'; // 16 字节
 * $iv  = '0123456789abcdef'; // 16 字节
 * $cipher = AES::encrypt('hello', $key, $iv);
 * $plain  = AES::decrypt($cipher, $key, $iv); // 'hello'
 * ```
 */
class AES
{
    /**
     * AES-CBC 加密。
     *
     * @param  string  $text    待加密明文
     * @param  string  $key     密钥，长度必须为 16 / 24 / 32 字节
     * @param  string  $iv      初始化向量，必须为 16 字节
     * @param  int     $option  openssl 选项，默认 OPENSSL_RAW_DATA（返回原始二进制而非 base64）
     * @return string 加密后的密文
     *
     * @throws InvalidArgumentException 密钥或 IV 长度非法、或 openssl 加密失败时抛出
     *
     * @example
     * ```php
     * $cipher = AES::encrypt('hello', '0123456789abcdef', '0123456789abcdef');
     * ```
     */
    public static function encrypt(string $text, string $key, string $iv, int $option = OPENSSL_RAW_DATA): string
    {
        self::validateKey($key);
        self::validateIv($iv);

        $cipherText = openssl_encrypt($text, self::getMode($key), $key, $option, $iv);

        // openssl_encrypt() 在算法与 IV 不匹配等情况下返回 false。
        // 原实现直接 return，会让 false 撞上「返回类型 string」而抛出难以定位的 TypeError，
        // 这里统一转换为含义明确的异常。
        if ($cipherText === false) {
            throw new InvalidArgumentException('AES 加密失败，请检查密钥长度与 IV 是否为 16 字节。');
        }

        return $cipherText;
    }

    /**
     * AES-CBC 解密。
     *
     * @param  string       $cipherText  待解密密文
     * @param  string       $key         密钥，长度必须为 16 / 24 / 32 字节
     * @param  string       $iv          初始化向量，必须为 16 字节
     * @param  int          $option      openssl 选项，需与加密时一致
     * @param  string|null  $method      显式指定算法（如 'aes-128-cbc'）；为 null 时按密钥长度推导
     * @return string 解密后的明文
     *
     * @throws InvalidArgumentException 密钥或 IV 长度非法、或 openssl 解密失败时抛出
     *
     * @example
     * ```php
     * $plain = AES::decrypt($cipher, '0123456789abcdef', '0123456789abcdef');
     * ```
     */
    public static function decrypt(string $cipherText, string $key, string $iv, int $option = OPENSSL_RAW_DATA, $method = null): string
    {
        self::validateKey($key);
        self::validateIv($iv);

        $text = openssl_decrypt($cipherText, $method ?: self::getMode($key), $key, $option, $iv);

        // 同 encrypt()：把 openssl 的 false 转为明确异常，避免 TypeError。
        if ($text === false) {
            throw new InvalidArgumentException('AES 解密失败，请检查密钥、IV 与算法是否匹配。');
        }

        return $text;
    }

    /**
     * 根据密钥长度推导 AES-CBC 算法名称。
     *
     * @param  string  $key  密钥（16 / 24 / 32 字节分别对应 128 / 192 / 256 位）
     * @return string 算法标识，形如 'aes-128-cbc'
     *
     * @example
     * ```php
     * AES::getMode('0123456789abcdef'); // 'aes-128-cbc'
     * ```
     */
    public static function getMode($key)
    {
        return 'aes-'.(8 * strlen($key)).'-cbc';
    }

    /**
     * 校验密钥长度是否合法。
     *
     * @param  string  $key  待校验密钥
     * @return void
     *
     * @throws InvalidArgumentException 当密钥长度不是 16 / 24 / 32 字节时抛出
     */
    public static function validateKey(string $key)
    {
        if (!in_array(strlen($key), [16, 24, 32], true)) {
            throw new InvalidArgumentException(sprintf('Key length must be 16, 24, or 32 bytes; got key len (%s).', strlen($key)));
        }
    }

    /**
     * 校验初始化向量长度是否合法。
     *
     * AES-CBC 的 IV 固定为 16 字节；空字符串被允许（交由 openssl 处理）。
     *
     * @param  string  $iv  待校验的初始化向量
     * @return void
     *
     * @throws InvalidArgumentException 当 IV 非空且长度不为 16 字节时抛出
     */
    public static function validateIv(string $iv)
    {
        if (!empty($iv) && 16 !== strlen($iv)) {
            throw new InvalidArgumentException('IV length must be 16 bytes.');
        }
    }
}

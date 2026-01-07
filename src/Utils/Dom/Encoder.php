<?php

declare(strict_types=1);

namespace zxf\Utils\Dom;

/**
 * 编码器类
 * 
 * 提供各种编码和转义功能
 * 支持 HTML 实体编码、URL 编码、JSON 编码等
 * 
 * 特性：
 * - PHP 8.2+ 类型系统
 * - 纯静态方法
 * - 完整的编码/解码 API
 * 
 * @package zxf\Utils\Dom
 */
class Encoder
{
    /**
     * HTML 实体编码
     * 
     * @param  string  $string  要编码的字符串
     * @param  int  $flags  编码标志
     * @param  string|null  $encoding  字符编码
     * @param  bool  $doubleEncode  是否双重编码
     * @return string
     */
    public static function html(
        string $string,
        int $flags = ENT_QUOTES | ENT_HTML5,
        ?string $encoding = 'UTF-8',
        bool $doubleEncode = true
    ): string {
        return htmlspecialchars($string, $flags, $encoding, $doubleEncode);
    }

    /**
     * 转换为 HTML 实体（仅转义非ASCII字符）
     * 
     * @param  string  $string  要转换的字符串
     * @param  string  $encoding  字符编码
     * @return string
     */
    public static function convertToHtmlEntities(string $string, string $encoding = 'UTF-8'): string
    {
        return mb_encode_numericentity($string, [0x80, 0x10FFFF, 0, ~0], $encoding);
    }

    /**
     * HTML 实体解码（别名方法）
     * 
     * @param  string  $string  要解码的字符串
     * @param  int  $flags  解码标志
     * @param  string|null  $encoding  字符编码
     * @return string
     */
    public static function htmlEntitiesDecode(
        string $string,
        int $flags = ENT_QUOTES | ENT_HTML5,
        ?string $encoding = 'UTF-8'
    ): string {
        return htmlspecialchars_decode($string, $flags);
    }

    /**
     * 转义 HTML 特殊字符
     * 
     * @param  string  $string  要转义的字符串
     * @return string
     */
    public static function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * 检测字符串编码
     * 
     * @param  string  $string  要检测的字符串
     * @return string|false
     */
    public static function detectEncoding(string $string): string|false
    {
        return mb_detect_encoding($string, ['UTF-8', 'GBK', 'GB2312', 'BIG5'], true);
    }

    /**
     * HTML 实体解码
     * 
     * @param  string  $string  要解码的字符串
     * @param  int  $flags  解码标志
     * @param  string|null  $encoding  字符编码
     * @return string
     */
    public static function htmlDecode(
        string $string,
        int $flags = ENT_QUOTES | ENT_HTML5,
        ?string $encoding = 'UTF-8'
    ): string {
        return htmlspecialchars_decode($string, $flags);
    }

    /**
     * URL 编码
     * 
     * @param  string  $string  要编码的字符串
     * @return string
     */
    public static function url(string $string): string
    {
        return rawurlencode($string);
    }

    /**
     * URL 解码
     * 
     * @param  string  $string  要解码的字符串
     * @return string
     */
    public static function urlDecode(string $string): string
    {
        return rawurldecode($string);
    }

    /**
     * Base64 编码
     * 
     * @param  string  $string  要编码的字符串
     * @return string
     */
    public static function base64(string $string): string
    {
        return base64_encode($string);
    }

    /**
     * Base64 解码
     * 
     * @param  string  $string  要解码的字符串
     * @return string|false
     */
    public static function base64Decode(string $string): string|false
    {
        return base64_decode($string, true);
    }

    /**
     * JSON 编码
     * 
     * @param  mixed  $data  要编码的数据
     * @param  int  $flags  编码标志
     * @param  int  $depth  最大深度
     * @return string|false
     */
    public static function json(mixed $data, int $flags = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT, int $depth = 512): string|false
    {
        return json_encode($data, $flags, $depth);
    }

    /**
     * JSON 解码
     * 
     * @param  string  $json  JSON 字符串
     * @param  bool  $associative  是否返回关联数组
     * @param  int  $depth  最大深度
     * @return mixed
     */
    public static function jsonDecode(string $json, bool $associative = true, int $depth = 512): mixed
    {
        return json_decode($json, $associative, $depth);
    }

    /**
     * JavaScript 字符串编码
     * 
     * @param  string  $string  要编码的字符串
     * @return string
     */
    public static function js(string $string): string
    {
        $escaped = addcslashes($string, "\\'\"\n\r\t\0\040");
        return $escaped;
    }

    /**
     * CSS 字符串编码
     * 
     * @param  string  $string  要编码的字符串
     * @return string
     */
    public static function css(string $string): string
    {
        // 转义 CSS 特殊字符
        $escaped = preg_replace_callback(
            '/[^a-zA-Z0-9]/',
            fn($match) => sprintf('\\%X ', ord($match[0])),
            $string
        );
        return $escaped;
    }

    /**
     * 清理 HTML 标签
     * 
     * @param  string  $string  要清理的字符串
     * @param  array<string>  $allowedTags  允许的标签
     * @return string
     */
    public static function stripTags(string $string, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            return strip_tags($string);
        }
        return strip_tags($string, implode('', $allowedTags));
    }

    /**
     * 转义正则表达式特殊字符
     * 
     * @param  string  $string  要转义的字符串
     * @return string
     */
    public static function regex(string $string): string
    {
        return preg_quote($string, '/');
    }

    /**
     * 转义 SQL 字符串（MySQL）
     * 
     * @param  string  $string  要转义的字符串
     * @return string
     */
    public static function sql(string $string): string
    {
        return addslashes($string);
    }

    /**
     * 十六进制编码
     * 
     * @param  string  $string  要编码的字符串
     * @return string
     */
    public static function hex(string $string): string
    {
        return bin2hex($string);
    }

    /**
     * 十六进制解码
     * 
     * @param  string  $hex  十六进制字符串
     * @return string|false
     */
    public static function hexDecode(string $hex): string|false
    {
        return hex2bin($hex);
    }
}

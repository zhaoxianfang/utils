<?php

declare(strict_types=1);

namespace zxf\Utils\Env;

use InvalidArgumentException;
use RuntimeException;

/**
 * 环境变量工具类
 * 提供 .env 文件加载、类型自动转换、默认值、必填校验等功能
 * 支持字符串、整数、浮点、布尔、数组、JSON 等类型的自动转换
 *
 * @package Env
 * @version 1.0.0
 * @license MIT
 */
class Env
{
    /** @var array<string,string> 已加载的环境变量缓存 */
    private static array $variables = [];

    /** @var bool 是否已从文件加载过环境变量 */
    private static bool $loaded = false;

    /**
     * 从 .env 文件加载环境变量到内存和系统环境
     *
     * 支持注释（#）、空行、双引号/单引号字符串、多行值
     *
     * @param string $path      .env 文件路径
     * @param bool   $overwrite 是否覆盖已存在的系统环境变量，默认 false
     * @return array<string,string> 加载的变量键值对
     * @throws RuntimeException 当文件不存在或无法读取时抛出
     */
    public static function load(string $path = __DIR__ . '/../../.env', bool $overwrite = false): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException('.env 文件不存在: ' . $path);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $loaded = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // 跳过注释和空行
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // 解析 KEY=VALUE
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // 去除引号
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
                $value = str_replace('\\"', '"', $value);
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            }

            self::$variables[$key] = $value;

            if ($overwrite || getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }

            $loaded[$key] = $value;
        }

        self::$loaded = true;
        return $loaded;
    }

    /**
     * 获取环境变量（支持类型自动转换）
     *
     * 查找优先级：已加载缓存 > putenv() > $_ENV > $_SERVER
     *
     * @param string $key     变量名
     * @param mixed  $default 变量不存在时的默认值
     * @param string $type    目标类型：string, int, float, bool, array, json
     * @return mixed 转换后的值；变量不存在且未指定默认值时返回 null
     */
    public static function get(string $key, mixed $default = null, string $type = 'string'): mixed
    {
        $value = self::$variables[$key] ?? getenv($key) ?? $_ENV[$key] ?? $_SERVER[$key] ?? null;

        if ($value === null || $value === false) {
            return $default;
        }

        return self::cast($value, $type);
    }

    /**
     * 获取字符串类型的环境变量
     *
     * @param string $key     变量名
     * @param string $default 默认值
     * @return string 字符串值
     */
    public static function string(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default, 'string');
    }

    /**
     * 获取整数类型的环境变量
     *
     * @param string $key     变量名
     * @param int    $default 默认值
     * @return int 整数值
     */
    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default, 'int');
    }

    /**
     * 获取浮点数类型的环境变量
     *
     * @param string $key     变量名
     * @param float  $default 默认值
     * @return float 浮点数值
     */
    public static function float(string $key, float $default = 0.0): float
    {
        return (float) self::get($key, $default, 'float');
    }

    /**
     * 获取布尔类型的环境变量
     *
     * 真值：true, 1, yes, on
     * 假值：false, 0, no, off, 空字符串
     *
     * @param string $key     变量名
     * @param bool   $default 默认值
     * @return bool 布尔值
     */
    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) self::get($key, $default, 'bool');
    }

    /**
     * 获取数组类型的环境变量（按逗号分隔）
     *
     * @param string $key     变量名
     * @param array  $default 默认值
     * @return array 字符串数组
     */
    public static function array(string $key, array $default = []): array
    {
        return (array) self::get($key, $default, 'array');
    }

    /**
     * 获取 JSON 类型的环境变量（自动解码）
     *
     * @param string $key     变量名
     * @param mixed  $default 默认值
     * @return mixed JSON 解码后的值；解码失败返回原始字符串
     */
    public static function json(string $key, mixed $default = null): mixed
    {
        return self::get($key, $default, 'json');
    }

    /**
     * 获取必填环境变量，不存在则抛出异常
     *
     * @param string $key  变量名
     * @param string $type 目标类型，默认 string
     * @return mixed 转换后的值
     * @throws InvalidArgumentException 当变量不存在或为空字符串时抛出
     */
    public static function required(string $key, string $type = 'string'): mixed
    {
        $value = self::get($key, null, $type);
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("缺少必要的环境变量: {$key}");
        }
        return $value;
    }

    /**
     * 设置环境变量（同时更新内存缓存、putenv、$_ENV 和 $_SERVER）
     *
     * @param string $key   变量名
     * @param mixed  $value 变量值（将被强制转换为字符串）
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $str = (string) $value;
        self::$variables[$key] = $str;
        putenv("{$key}={$str}");
        $_ENV[$key] = $str;
        $_SERVER[$key] = $str;
    }

    /**
     * 判断环境变量是否存在（含空字符串视为存在）
     *
     * @param string $key 变量名
     * @return bool 存在返回 true
     */
    public static function has(string $key): bool
    {
        return self::get($key, $this ?? null) !== null;
    }

    /**
     * 获取所有已加载的环境变量
     *
     * @return array<string,string> 环境变量键值对数组
     */
    public static function all(): array
    {
        return self::$variables;
    }

    /**
     * 清空已加载的环境变量缓存（不影响系统环境变量）
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$variables = [];
        self::$loaded = false;
    }

    /**
     * 将字符串值转换为指定类型
     *
     * @param string $value 原始字符串值
     * @param string $type  目标类型：string, int, float, bool, array, json
     * @return mixed 转换后的值
     */
    private static function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer'     => (int) $value,
            'float', 'double'    => (float) $value,
            'bool', 'boolean'    => self::toBool($value),
            'array'              => array_map('trim', explode(',', $value)),
            'json'               => json_decode($value, true) ?? $value,
            default              => $value,
        };
    }

    /**
     * 将字符串转换为布尔值
     *
     * @param string $value 原始字符串
     * @return bool 转换后的布尔值
     */
    private static function toBool(string $value): bool
    {
        return match (strtolower($value)) {
            'true', '1', 'yes', 'on'  => true,
            'false', '0', 'no', 'off', '' => false,
            default => (bool) $value,
        };
    }
}

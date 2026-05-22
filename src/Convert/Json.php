<?php

/**
 * ============================================================================
 * JSON 处理工具类
 * ============================================================================
 *
 * 提供 JSON 编码、解码、美化、路径查询、合并、验证等功能。
 * 比原生 json_* 函数更友好，支持链式操作和详细错误处理。
 *
 * @package     zxf\Utils\Convert
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Convert;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use zxf\Utils\Support\Arr;

final class Json
{
    /**
     * 将值编码为 JSON 字符串
     *
     * @throws JsonException 编码失败时抛出
     */
    public static function encode(mixed $value, int $flags = 0, int $depth = 512): string
    {
        $flags |= JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;
        return json_encode($value, $flags, $depth);
    }

    /**
     * 将值编码为格式化的 JSON 字符串
     *
     * @throws JsonException 编码失败时抛出
     */
    public static function pretty(mixed $value, int $flags = 0, int $depth = 512): string
    {
        return self::encode($value, $flags | JSON_PRETTY_PRINT, $depth);
    }

    /**
     * 解码 JSON 字符串
     *
     * @throws JsonException 解码失败时抛出
     */
    public static function decode(string $json, bool $associative = true, int $depth = 512, int $flags = 0): mixed
    {
        return json_decode($json, $associative, $depth, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * 安全解码 JSON 字符串，失败时返回默认值
     */
    public static function decodeSafe(string $json, mixed $default = null, bool $associative = true): mixed
    {
        try {
            return json_decode($json, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $default;
        }
    }

    /**
     * 判断字符串是否为有效 JSON
     */
    public static function isValid(string $string): bool
    {
        if (trim($string) === '') {
            return false;
        }
        try {
            json_decode($string, true, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * 判断字符串是否为 JSON 数组
     */
    public static function isArray(string $string): bool
    {
        $decoded = self::decodeSafe($string);
        return is_array($decoded) && array_is_list($decoded);
    }

    /**
     * 判断字符串是否为 JSON 对象
     */
    public static function isObject(string $string): bool
    {
        $decoded = self::decodeSafe($string);
        return is_array($decoded) && ! array_is_list($decoded);
    }

    /**
     * 通过路径表达式从 JSON 字符串或数组中获取值
     *
     * @param array|string $data JSON 字符串或已解码的数组
     * @param string $path
     * @param mixed|null $default
     * @return mixed
     * @throws JsonException
     */
    public static function get(array|string $data, string $path, mixed $default = null): mixed
    {
        $array = is_string($data) ? self::decode($data) : $data;
        if (! is_array($array)) {
            return $default;
        }
        return Arr::get($array, $path, $default);
    }

    /**
     * 通过路径表达式设置 JSON 中的值，返回新 JSON 字符串
     */
    public static function set(string $json, string $path, mixed $value): string
    {
        $array = self::decode($json);
        Arr::set($array, $path, $value);
        return self::encode($array);
    }

    /**
     * 合并多个 JSON 字符串（递归合并）
     */
    public static function merge(string ...$jsons): string
    {
        $arrays = array_map(static fn ($json) => self::decode($json), $jsons);
        return self::encode(Arr::mergeRecursive(...$arrays));
    }

    /**
     * 从数组或对象中提取指定路径的值列表
     */
    public static function pluck(array|string $data, string $path): array
    {
        $array = is_string($data) ? self::decode($data) : $data;
        if (! is_array($array)) {
            return [];
        }

        $results = [];
        foreach ($array as $item) {
            $results[] = Arr::get($item, $path);
        }
        return $results;
    }

    /**
     * 将 JSON 字符串转为 PHP 数组，确保所有对象都被转为数组
     */
    public static function toArray(string $json): array
    {
        return self::decode($json, true);
    }

    /**
     * 将 JSON 字符串转为 stdClass 对象
     */
    public static function toObject(string $json): object
    {
        return self::decode($json, false);
    }

    /**
     * 比较两个 JSON 字符串是否等价（忽略键顺序和空白）
     */
    public static function equals(string $a, string $b): bool
    {
        try {
            $decodedA = self::decode($a);
            $decodedB = self::decode($b);
            return Arr::sortRecursive((array) $decodedA)
                == Arr::sortRecursive((array) $decodedB);
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * 获取 JSON 字符串中的键列表（顶层）
     */
    public static function keys(string $json): array
    {
        $decoded = self::decodeSafe($json, []);
        return is_array($decoded) ? array_keys($decoded) : [];
    }

    /**
     * 获取 JSON 字符串中的值列表（顶层）
     */
    public static function values(string $json): array
    {
        $decoded = self::decodeSafe($json, []);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * 删除 JSON 中指定路径的值，返回新 JSON 字符串
     */
    public static function remove(string $json, string $path): string
    {
        $array = self::decode($json);
        $keys = explode('.', $path);
        $target = &$array;

        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (! isset($target[$key]) || ! is_array($target[$key])) {
                return $json;
            }
            $target = &$target[$key];
        }

        unset($target[array_shift($keys) ?? '']);
        return self::encode($array);
    }

    /**
     * 将文件内容读取并解码为数组
     *
     * @throws RuntimeException|JsonException 文件不存在或解码失败时抛出
     */
    public static function fromFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("JSON 文件不存在: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("无法读取 JSON 文件: {$path}");
        }
        return self::decode($content);
    }

    /**
     * 将数组编码并写入文件
     *
     * @throws RuntimeException|JsonException 写入失败时抛出
     */
    public static function toFile(string $path, mixed $value, int $flags = 0): void
    {
        $json = self::pretty($value, $flags);
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            throw new RuntimeException("无法创建目录: {$dir}");
        }
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException("无法写入 JSON 文件: {$path}");
        }
    }
}

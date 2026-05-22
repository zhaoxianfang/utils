<?php

/**
 * ============================================================================
 * 数组工具类
 * ============================================================================
 *
 * 提供静态数组操作的便捷方法，与 DataArray 不同，Arr 专注于
 * 轻量级的单次操作，不维护实例状态。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use InvalidArgumentException;

final class Arr
{
    /**
     * 使用点符号从嵌套数组中获取值
     */
    public static function get(array $array, int|string|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $array;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (! str_contains((string) $key, '.')) {
            return $default;
        }

        foreach (explode('.', (string) $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * 使用点符号设置嵌套数组中的值
     */
    public static function set(array &$array, int|string|null $key, mixed $value): array
    {
        if ($key === null) {
            return $array = $value;
        }

        $keys = explode('.', (string) $key);
        foreach ($keys as $i => $segment) {
            if (count($keys) === 1) {
                break;
            }
            unset($keys[$i]);

            if (! isset($array[$segment]) || ! is_array($array[$segment])) {
                $array[$segment] = [];
            }

            $array = &$array[$segment];
        }

        $array[array_shift($keys) ?? ''] = $value;

        return $array;
    }

    /**
     * 判断数组是否关联数组（非索引数组）
     */
    public static function isAssoc(array $array): bool
    {
        return [] === $array || array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * 将数组按键排序（支持递归）
     */
    public static function sortRecursive(array $array, int $options = SORT_REGULAR): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::sortRecursive($value, $options);
            }
        }

        if (self::isAssoc($array)) {
            ksort($array, $options);
        } else {
            sort($array, $options);
        }

        return $array;
    }

    /**
     * 从数组中随机抽取指定数量的元素（安全随机）
     */
    public static function random(array $array, int $number = 1): mixed
    {
        $count = count($array);
        if ($number > $count) {
            throw new InvalidArgumentException("随机抽取数量 [{$number}] 超出数组大小 [{$count}]");
        }

        if ($number === 1) {
            return $array[array_rand($array)];
        }

        $keys = array_rand($array, $number);
        return array_map(static fn ($key) => $array[$key], is_array($keys) ? $keys : [$keys]);
    }

    /**
     * 按给定键的值对数组进行分组
     */
    public static function groupBy(array $array, int|string|callable $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $groupKey = is_callable($key) ? $key($item) : u_data_get($item, $key);
            $result[$groupKey] ??= [];
            $result[$groupKey][] = $item;
        }
        return $result;
    }

    /**
     * 计算数组中每个值的出现次数
     */
    public static function countValues(array $array, int|string|callable|null $key = null): array
    {
        if ($key === null) {
            return array_count_values($array);
        }

        $counts = [];
        foreach ($array as $item) {
            $value = is_callable($key) ? $key($item) : u_data_get($item, $key);
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * 将二维数组中指定列提取为一维数组
     */
    public static function pluck(array $array, int|string $value, int|string|null $key = null): array
    {
        $results = [];
        foreach ($array as $item) {
            $itemValue = u_data_get($item, $value);
            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $results[u_data_get($item, $key)] = $itemValue;
            }
        }
        return $results;
    }

    /**
     * 将数组扁平化为一维数组，键使用分隔符连接
     */
    public static function dot(iterable $array, string $prepend = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && [] !== $value) {
                $results = array_merge($results, self::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }
        return $results;
    }

    /**
     * 将点符号键的数组还原为嵌套数组
     */
    public static function undot(array $array): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            self::set($results, $key, $value);
        }
        return $results;
    }

    /**
     * 仅保留数组中指定的键
     */
    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * 排除数组中指定的键
     */
    public static function except(array $array, array|string $keys): array
    {
        return array_diff_key($array, array_flip((array) $keys));
    }

    /**
     * 递归合并数组（与 array_merge_recursive 不同，不会将相同键的值转为数组）
     */
    public static function mergeRecursive(array ...$arrays): array
    {
        $result = array_shift($arrays) ?? [];
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                    $result[$key] = self::mergeRecursive($result[$key], $value);
                } else {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * 对数组的每个元素应用回调
     */
    public static function mapWithKeys(array $array, callable $callback): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            [$mappedKey, $mappedValue] = $callback($value, $key);
            $result[$mappedKey] = $mappedValue;
        }
        return $result;
    }

    /**
     * 按回调结果对数组进行唯一化
     */
    public static function unique(array $array, int|string|callable|null $key = null): array
    {
        if ($key === null) {
            return array_unique($array, SORT_REGULAR);
        }

        $exists = [];
        return array_filter($array, function ($item) use ($key, &$exists) {
            $value = is_callable($key) ? $key($item) : u_data_get($item, $key);
            if (in_array($value, $exists, true)) {
                return false;
            }
            $exists[] = $value;
            return true;
        });
    }

    /**
     * 将数组切分为指定大小的块
     */
    public static function chunk(array $array, int $size): array
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Chunk size must be greater than 0');
        }
        return array_chunk($array, $size);
    }

    /**
     * 将数组中指定键的值提取并转为键值对映射
     */
    public static function mapToDictionary(array $array, callable $callback): array
    {
        $dictionary = [];
        foreach ($array as $key => $value) {
            [$newKey, $newValue] = $callback($value, $key);
            $dictionary[$newKey] ??= [];
            $dictionary[$newKey][] = $newValue;
        }
        return $dictionary;
    }

    /**
     * 将数组交叉组合（类似 Python zip）
     */
    public static function zip(array ...$arrays): array
    {
        return array_map(null, ...$arrays);
    }

    /**
     * 计算数组的笛卡尔积
     */
    public static function crossJoin(array ...$arrays): array
    {
        $results = [[]];
        foreach ($arrays as $index => $array) {
            $append = [];
            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;
                    $append[] = $product;
                }
            }
            $results = $append;
        }
        return $results;
    }
}

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
 *
 * @example
 * use zxf\Utils\Support\Arr;
 *
 * $data = ['user' => ['name' => 'Tom', 'age' => 18]];
 * echo Arr::get($data, 'user.name');        // 'Tom'
 * Arr::set($data, 'user.email', 'a@b.com'); // 写入嵌套键
 * $flat = Arr::dot($data);                  // 点符号扁平化
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use InvalidArgumentException;

/**
 * 数组工具类
 *
 * 提供静态数组操作的便捷方法，专注于轻量级的单次操作，不维护实例状态。
 * 涵盖点符号嵌套读写、分组/去重、扁平化与展开、集合运算（笛卡尔积、zip）、
 * 数组合并与映射等常见场景，是 DataArray 之外的高频数组处理助手。
 */
final class Arr
{
    /**
     * 使用点符号从嵌套数组中获取值
     *
     * 支持 'a.b.c' 形式的点符号路径逐层取值；键中本身含 '.' 时
     * 会优先按整键（`array_key_exists`）匹配，匹配不到再尝试按点拆分。
     *
     * @param array $array 待读取的源数组
     * @param int|string|null $key 点符号路径，为 null 时直接返回整个数组
     * @param mixed $default 路径不存在时返回的默认值
     * @return mixed 路径对应的值，不存在时返回 $default
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
     *
     * 通过引用逐级创建中间数组，最终把值写入最深层。
     * 传入 null 键时，要求值本身为数组并整体替换原数组。
     *
     * @param array $array 目标数组（通过引用修改）
     * @param int|string|null $key 点符号路径；为 null 表示整体替换为 $value
     * @param mixed $value 要写入的值
     * @return array 写入完成后的（子）数组
     * @throws InvalidArgumentException 当键为 null 但 $value 不是数组时抛出
     */
    public static function set(array &$array, int|string|null $key, mixed $value): array
    {
        if ($key === null) {
            if (! is_array($value)) {
                throw new InvalidArgumentException('使用 null 键设置值时，值必须是数组');
            }
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
     * 判断数组是否为关联数组（非从 0 开始连续索引的数组）
     *
     * 空数组视为关联数组；通过判断键序列是否等于 range(0, n-1) 来区分。
     *
     * @param array $array 待判断的数组
     * @return bool 是关联数组返回 true，否则返回 false
     */
    public static function isAssoc(array $array): bool
    {
        return [] === $array || array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * 递归对数组排序（关联数组按 key 排序，索引数组按 value 排序）
     *
     * 先递归处理每个子数组，再依据是否关联数组分别使用 ksort / sort。
     *
     * @param array $array 待排序的数组（按引用递归排序）
     * @param int $options 排序选项，同 ksort/sort 的 $flags 参数
     * @return array 排序后的数组
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
     * 从数组中随机抽取指定数量的元素（基于 array_rand 的安全随机）
     *
     * 底层使用 array_rand 获取随机键，避免自写 rand 导致分布不均。
     *
     * @param array $array 源数组
     * @param int $number 抽取数量，默认 1
     * @return mixed 抽取 1 个时返回该元素，多个时返回包含元素的数组
     * @throws InvalidArgumentException 当抽取数量大于数组长度时抛出
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
     * 按给定键（或回调）的值对数组进行分组
     *
     * 分组依据为键名（借助全局函数 u_data_get 取值）或回调返回值；
     * 同一分组键的元素聚合到同一子数组中。
     *
     * @param array $array 待分组的数组
     * @param int|string|callable $key 用作分组依据的键名，或接收元素并返回分组键的回调
     * @return array 以分组键为索引、元素数组为值的二维数组
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
     * 统计数组中每个值的出现次数
     *
     * 不传 $key 时直接统计数组自身的值；传入 $key 则按元素内指定字段统计。
     *
     * @param array $array 源数组
     * @param int|string|callable|null $key 统计依据的字段名/键名或回调，null 表示统计元素本身
     * @return array 值 => 出现次数的映射
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
     * 从二维数组中提取指定列，可选作为键的另一列
     *
     * @param array $array 源数组
     * @param int|string $value 作为值的字段名/键名
     * @param int|string|null $key 作为键的字段名/键名，null 时使用连续数字索引
     * @return array 提取后的一维数组
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
     * 将多维数组扁平化为一维，键名用点符号拼接
     *
     * @param iterable $array 待扁平化的数组
     * @param string $prepend 前缀（递归时用于拼接父级键）
     * @return array 点符号键 => 值的扁平数组
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
     * 将使用点符号键的扁平数组还原为嵌套数组
     *
     * 依赖 {@see Arr::set()} 按点符号逐级创建中间结构。
     *
     * @param array $array 点符号键的扁平数组
     * @return array 还原后的嵌套数组
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
     *
     * @param array $array 源数组
     * @param array|string $keys 要保留的键，可为数组或单个键字符串
     * @return array 仅含指定键的新数组
     */
    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * 排除数组中指定的键
     *
     * @param array $array 源数组
     * @param array|string $keys 要排除的键，可为数组或单个键字符串
     * @return array 剔除指定键后的新数组
     */
    public static function except(array $array, array|string $keys): array
    {
        return array_diff_key($array, array_flip((array) $keys));
    }

    /**
     * 递归合并多个数组（同键值均为数组时递归合并，否则后者覆盖前者）
     *
     * 与 array_merge_recursive 不同：后者会把相同字符串键的值合并为数组，
     * 本方法在同值均为数组时才合并，否则直接覆盖，行为更可控。
     *
     * @param array ...$arrays 待合并的数组，按顺序后者覆盖前者
     * @return array 合并后的数组
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
     * 对数组每个元素应用回调，并用回调返回的 [键, 值] 构建新数组
     *
     * 回调需返回 [$mappedKey, $mappedValue] 二元组，用于自定义键名。
     *
     * @param array $array 源数组
     * @param callable $callback 接收 (值, 键)，需返回 [新键, 新值]
     * @return array 重构后的数组
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
     * 按回调结果（或字段）对数组去重
     *
     * 为避免 O(n^2) 的逐项比较，按去重键计算 Hash 后做 O(1) 判重。
     * 对象用 spl_object_hash、数组用序列化 md5、标量用「类型:值」作为哈希键。
     *
     * @param array $array 源数组
     * @param int|string|callable|null $key 去重依据的字段名/键名或回调，null 表示按元素本身
     * @return array 去重后的数组（保留首次出现顺序）
     */
    public static function unique(array $array, int|string|callable|null $key = null): array
    {
        if ($key === null) {
            return array_unique($array, SORT_REGULAR);
        }

        $exists = [];
        return array_filter($array, function ($item) use ($key, &$exists) {
            $value = is_callable($key) ? $key($item) : u_data_get($item, $key);
            // 用哈希键去重，将 O(n^2) 的 in_array 比较降为 O(1)
            if (is_object($value)) {
                $hash = 'obj:' . spl_object_hash($value);
            } elseif (is_array($value)) {
                $hash = 'arr:' . md5(serialize($value));
            } else {
                $hash = get_debug_type($value) . ':' . $value;
            }
            if (isset($exists[$hash])) {
                return false;
            }
            $exists[$hash] = true;
            return true;
        });
    }

    /**
     * 将数组切分为指定大小的块
     *
     * @param array $array 源数组
     * @param int $size 每块大小，必须大于 0
     * @return array 切分后的块数组
     * @throws InvalidArgumentException 当 size 小于等于 0 时抛出
     */
    public static function chunk(array $array, int $size): array
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Chunk size must be greater than 0');
        }
        return array_chunk($array, $size);
    }

    /**
     * 对数组每个元素应用回调，按 [新键, 新值] 构建字典（同键自动聚合为数组）
     *
     * @param array $array 源数组
     * @param callable $callback 接收 (值, 键)，需返回 [新键, 新值]
     * @return array 以新键为索引、值数组为字典结构
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
     * 将多个数组按位置交叉组合（类似 Python 的 zip）
     *
     * 以最长数组长度为准，较短数组在对应位置以 null 补齐。
     *
     * @param array ...$arrays 待交叉的多个数组
     * @return array 位置对应的元组数组（每个元素为各数组同位置值的数组）
     */
    public static function zip(array ...$arrays): array
    {
        return array_map(null, ...$arrays);
    }

    /**
     * 计算多个数组的笛卡尔积（所有可能组合）
     *
     * 从空组合开始，逐数组将当前结果与下一数组各项拼接，
     * 最终得到全部组合。
     *
     * @param array ...$arrays 待求积的多个数组
     * @return array 所有组合的数组（每个组合为按输入顺序排列的子数组）
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

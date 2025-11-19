<?php

namespace zxf\Util\Array;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use RuntimeException;
use InvalidArgumentException;

/**
 * 数组对象工具类
 * 提供数组的面向对象操作方式，支持点分隔符访问、通配符查找、链式操作等高级功能
 *
 * @example
 * $data = new DataArray(['user' => ['name' => 'John']]);
 * echo $data->get('user.name'); // John
 * echo $data['user.name']; // John (通过数组访问)
 * echo $data->get('list.*.name'); // 通配符查找
 *
 * @see https://weisifang.com/docs/doc/6_203 DataArray使用文档
 */
class DataArray implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * 存储数据的数组
     * @var array
     */
    private array $data;

    /**
     * 是否只读模式
     * @var bool
     */
    private bool $readOnly;

    /**
     * 构造函数
     *
     * @param array $data 初始数组数据
     * @param bool $readOnly 是否只读模式，默认可写
     */
    public function __construct(array $data = [], bool $readOnly = false)
    {
        $this->data = $data;
        $this->readOnly = $readOnly;
    }

    /**
     * 创建只读实例
     *
     * @param array $data 初始数组数据
     * @return static 返回只读的DataArray实例
     */
    public static function readOnly(array $data = []): static
    {
        return new static($data, true);
    }

    /**
     * 从JSON字符串创建实例
     *
     * @param string $json JSON字符串
     * @param bool $associative 是否返回关联数组，默认true
     * @return static 返回DataArray实例
     * @throws InvalidArgumentException JSON解析失败时抛出异常
     */
    public static function fromJson(string $json, bool $associative = true): static
    {
        $data = json_decode($json, $associative);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('JSON解析错误: ' . json_last_error_msg());
        }

        // 确保返回的是数组
        $result = $associative ? (array)$data : get_object_vars($data);
        return new static($result ?: []);
    }

    /**
     * 从对象创建实例
     *
     * @param object $object 源对象
     * @return static 返回DataArray实例
     */
    public static function fromObject(object $object): static
    {
        return new static(get_object_vars($object));
    }

    /**
     * 从查询字符串创建实例
     *
     * @param string $queryString 查询字符串（如：name=John&age=30）
     * @return static 返回DataArray实例
     */
    public static function fromQueryString(string $queryString): static
    {
        parse_str($queryString, $data);
        return new static($data ?: []);
    }

    /*****************************************************************
     * ArrayAccess 接口实现
     *****************************************************************/

    /**
     * 检查偏移量是否存在
     *
     * @param mixed $offset 要检查的偏移量
     * @return bool 如果偏移量存在返回true，否则返回false
     */
    public function offsetExists(mixed $offset): bool
    {
        // 支持点分隔符的偏移量检查
        if (is_string($offset) && str_contains($offset, '.')) {
            return $this->has($offset);
        }
        return isset($this->data[$offset]);
    }

    /**
     * 获取偏移量的值
     *
     * @param mixed $offset 要获取的偏移量
     * @return mixed 偏移量对应的值，不存在返回null
     */
    public function offsetGet(mixed $offset): mixed
    {
        // 支持点分隔符的偏移量获取
        if (is_string($offset) && str_contains($offset, '.')) {
            return $this->get($offset);
        }
        return $this->data[$offset] ?? null;
    }

    /**
     * 设置偏移量的值
     *
     * @param mixed $offset 要设置的偏移量
     * @param mixed $value 要设置的值
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        // 支持点分隔符的偏移量设置
        if (is_string($offset) && str_contains($offset, '.')) {
            $this->set($offset, $value);
        } else {
            if (is_null($offset)) {
                $this->data[] = $value;
            } else {
                $this->data[$offset] = $value;
            }
        }
    }

    /**
     * 删除偏移量
     *
     * @param mixed $offset 要删除的偏移量
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        // 支持点分隔符的偏移量删除
        if (is_string($offset) && str_contains($offset, '.')) {
            $this->delete($offset);
        } else {
            unset($this->data[$offset]);
        }
    }

    /*****************************************************************
     * 魔术方法
     *****************************************************************/

    /**
     * 魔术方法获取属性
     *
     * @param string $name 属性名
     * @return mixed 属性值，如果是数组则返回新的DataArray实例
     */
    public function __get(string $name): mixed
    {
        $value = $this->get($name);

        // 如果是数组，返回新的DataArray实例以支持链式对象访问
        if (is_array($value)) {
            return new static($value, $this->readOnly);
        }

        return $value;
    }

    /**
     * 魔术方法设置属性
     *
     * @param string $name 属性名
     * @param mixed $value 属性值
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }
        $this->set($name, $value);
    }

    /**
     * 魔术方法检查属性是否存在
     *
     * @param string $name 属性名
     * @return bool 属性是否存在
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * 魔术方法删除属性
     *
     * @param string $name 属性名
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function __unset(string $name): void
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }
        $this->delete($name);
    }

    /**
     * 转换为字符串
     *
     * @return string JSON格式的字符串表示
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /*****************************************************************
     * 基本数组操作
     *****************************************************************/

    /**
     * 获取包含所有键的数组
     *
     * @return array 包含所有键的数组
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * 获取包含所有值的数组
     *
     * @return array 包含所有值的数组
     */
    public function values(): array
    {
        return array_values($this->data);
    }

    /**
     * 递归获取所有键（包含嵌套结构的点分隔键）
     *
     * @return array 包含所有层级键名的数组
     */
    public function keysRecursive(): array
    {
        return $this->getKeysRecursive($this->data);
    }

    /**
     * 递归获取键的辅助方法
     *
     * @param array $array 要处理的数组
     * @param string $prefix 当前键前缀
     * @return array 递归获取的所有键
     */
    private function getKeysRecursive(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : (string)$key;
            $keys[] = $fullKey;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->getKeysRecursive($value, $fullKey));
            }
        }
        return $keys;
    }

    /**
     * 反向遍历数组对象
     *
     * @return Generator 返回生成器用于反向遍历
     */
    public function reverse(): Generator
    {
        $keys = array_reverse(array_keys($this->data));
        foreach ($keys as $key) {
            yield $key => $this->data[$key];
        }
    }

    /*****************************************************************
     * Countable 接口实现
     *****************************************************************/

    /**
     * 获取数组元素数量
     *
     * @return int 数组元素的数量
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * 递归计数（包含所有嵌套元素）
     *
     * @return int 所有层级元素的总数
     */
    public function countRecursive(): int
    {
        return count($this->data, COUNT_RECURSIVE);
    }

    /*****************************************************************
     * JsonSerializable 接口实现和序列化方法
     *****************************************************************/

    /**
     * 序列化为JSON数据
     *
     * @return array 用于JSON序列化的数组
     */
    public function jsonSerialize(): array
    {
        return $this->toArrayRecursive();
    }

    /**
     * 转换为JSON字符串
     *
     * @param int $flags JSON编码选项
     * @return string JSON格式的字符串
     */
    public function toJson(int $flags = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT): string
    {
        $result = json_encode($this->jsonSerialize(), $flags);
        return $result === false ? '[]' : $result;
    }

    /**
     * 获取所有数据（保持DataArray对象）
     *
     * @return array 原始数组数据
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * 递归转换为数组（将所有层级的DataArray对象转换为普通数组）
     *
     * @return array 完全转换为原生数组的数据
     */
    public function toArrayRecursive(): array
    {
        return $this->convertToArrayRecursive($this->data);
    }

    /**
     * 递归转换的辅助方法
     *
     * @param mixed $data 要转换的数据
     * @return array 转换后的数组
     */
    private function convertToArrayRecursive(mixed $data): array
    {
        // 如果是DataArray实例，递归转换其数据
        if ($data instanceof self) {
            return $this->convertToArrayRecursive($data->toArray());
        }

        // 如果是数组，递归处理每个元素
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->convertToArrayRecursive($value);
            }
            return $result;
        }

        // 对于非数组的值，如果是可遍历对象，也进行处理
        if ($data instanceof Traversable) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->convertToArrayRecursive($value);
            }
            return $result;
        }

        // 对于标量值或对象，直接返回（在数组上下文中包装）
        return [$data];
    }

    /**
     * 转换为查询字符串
     *
     * @param string $prefix 参数前缀
     * @return string URL查询字符串
     */
    public function toQueryString(string $prefix = ''): string
    {
        return http_build_query($this->toArrayRecursive(), $prefix);
    }

    /*****************************************************************
     * 核心数据访问方法
     *****************************************************************/

    /**
     * 检查数组是否为空
     *
     * @return bool 数组为空返回true，否则返回false
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * 增强的get方法，支持点分隔符和通配符查找
     *
     * @param mixed $key 要获取的键，支持点分隔符和通配符
     * @param mixed $defaultValue 键不存在时返回的默认值
     * @return mixed 键对应的值或默认值
     */
    public function get(mixed $key, mixed $defaultValue = null): mixed
    {
        if (empty($key)) {
            return $this->toArray();
        }

        $key = (string)$key;

        // 通配符查找
        if (str_contains($key, '*') || str_contains($key, '**')) {
            return $this->getWithWildcard($key, $defaultValue);
        }

        // 点分隔符查找
        if (str_contains($key, '.')) {
            return $this->getWithDotNotation($key, $defaultValue);
        }

        // 普通查找
        return $this->data[$key] ?? $defaultValue;
    }

    /**
     * 获取多个键的值
     *
     * @param array $keys 要获取的键名数组
     * @param mixed $defaultValue 键不存在时返回的默认值
     * @return array 键值对数组
     */
    public function getMultiple(array $keys, mixed $defaultValue = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $defaultValue);
        }
        return $result;
    }

    /**
     * 使用点分隔符查找数组值
     *
     * @param string $key 点分隔的键路径
     * @param mixed $defaultValue 默认值
     * @return mixed 找到的值或默认值
     */
    private function getWithDotNotation(string $key, mixed $defaultValue = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $k) {
            if ((!is_array($value) && !$value instanceof ArrayAccess) || !isset($value[$k])) {
                return $defaultValue;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 使用通配符查找数组值
     *
     * @param string $pattern 包含通配符的键路径
     * @param mixed $defaultValue 默认值
     * @return mixed 找到的值或默认值
     */
    private function getWithWildcard(string $pattern, mixed $defaultValue = null): mixed
    {
        $segments = explode('.', $pattern);
        $results = $this->wildcardSearchRecursive($this->data, $segments, 0);

        return empty($results) ? $defaultValue : (count($results) === 1 ? $results[0] : $results);
    }

    /**
     * 递归搜索匹配通配符的值
     *
     * @param mixed $data 当前搜索的数据
     * @param array $segments 路径分段数组
     * @param int $index 当前处理的段索引
     * @return array 匹配的结果数组
     */
    private function wildcardSearchRecursive(mixed $data, array $segments, int $index): array
    {
        $results = [];

        // 如果已经处理完所有分段，返回当前数据
        if ($index >= count($segments)) {
            return is_array($data) ? [$data] : [$data];
        }

        $currentSegment = $segments[$index];

        // 如果不是数组或可遍历对象，无法继续搜索
        if (!is_array($data) && !$data instanceof Traversable) {
            return [];
        }

        // 处理单级通配符 (*)
        if ($currentSegment === '*') {
            foreach ($data as $value) {
                $results = array_merge($results, $this->wildcardSearchRecursive($value, $segments, $index + 1));
            }
        }
        // 处理多级通配符 (**)
        elseif ($currentSegment === '**') {
            // 先尝试跳过当前通配符继续搜索
            $results = array_merge($results, $this->wildcardSearchRecursive($data, $segments, $index + 1));

            // 然后在当前层级继续搜索
            foreach ($data as $value) {
                if (is_array($value) || $value instanceof Traversable) {
                    $results = array_merge($results, $this->wildcardSearchRecursive($value, $segments, $index));
                }
            }
        }
        // 普通键名精确匹配
        else {
            if (($data instanceof ArrayAccess && $data->offsetExists($currentSegment)) ||
                (is_array($data) && array_key_exists($currentSegment, $data))) {
                $value = $data[$currentSegment];
                $results = array_merge($results, $this->wildcardSearchRecursive($value, $segments, $index + 1));
            }
        }

        // 过滤掉null值，确保返回数组
        return array_values(array_filter($results, fn($item) => $item !== null));
    }

    /**
     * 设置值，支持点分隔符（链式操作）
     *
     * @param mixed $key 键名
     * @param mixed $value 值
     * @return static 返回当前实例以支持链式操作
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function set(mixed $key, mixed $value): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        if (is_string($key) && str_contains($key, '.')) {
            $this->setWithDotNotation($key, $value);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * 使用点分隔符设置值
     *
     * @param string $key 点分隔的键路径
     * @param mixed $value 要设置的值
     */
    private function setWithDotNotation(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$this->data;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }

    /**
     * 检查键是否存在，支持点分隔符
     *
     * @param mixed $key 要检查的键
     * @return bool 键存在返回true，否则返回false
     */
    public function has(mixed $key): bool
    {
        if (empty($key)) {
            return false;
        }

        $key = (string)$key;

        if (str_contains($key, '.')) {
            return $this->hasWithDotNotation($key);
        }

        return array_key_exists($key, $this->data);
    }

    /**
     * 使用点分隔符检查键是否存在
     *
     * @param string $key 点分隔的键路径
     * @return bool 路径存在返回true，否则返回false
     */
    private function hasWithDotNotation(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * 删除指定键，支持点分隔符（链式操作）
     *
     * @param mixed $key 要删除的键
     * @return static 返回当前实例以支持链式操作
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function delete(mixed $key): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        if (is_string($key) && str_contains($key, '.')) {
            $this->deleteWithDotNotation($key);
        } else {
            unset($this->data[$key]);
        }

        return $this;
    }

    /**
     * 批量删除多个键
     *
     * @param array $keys 要删除的键名数组
     * @return static 返回当前实例以支持链式操作
     */
    public function deleteMultiple(array $keys): static
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return $this;
    }

    /**
     * 使用点分隔符删除键
     *
     * @param string $key 点分隔的键路径
     */
    private function deleteWithDotNotation(string $key): void
    {
        $keys = explode('.', $key);
        $current = &$this->data;

        foreach ($keys as $i => $k) {
            if (!isset($current[$k])) {
                return;
            }

            if ($i === count($keys) - 1) {
                unset($current[$k]);
            } else {
                if (!is_array($current[$k])) {
                    return;
                }
                $current = &$current[$k];
            }
        }
    }

    /*****************************************************************
     * 数组合并操作
     *****************************************************************/

    /**
     * 合并另一个数组到当前数组对象中（链式操作）
     *
     * @param array|DataArray $array 要合并的数组或DataArray实例
     * @return static 返回当前实例以支持链式操作
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function merge(array|DataArray $array): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        // 如果是DataArray实例，获取其数组数据
        $mergeData = $array instanceof self ? $array->toArray() : $array;

        $this->data = array_merge($this->data, $mergeData);
        return $this;
    }

    /**
     * 递归合并另一个数组到当前数组对象中（链式操作）
     *
     * @param array|DataArray $array 要合并的数组或DataArray实例
     * @return static 返回当前实例以支持链式操作
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function mergeRecursive(array|DataArray $array): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        // 如果是DataArray实例，获取其数组数据
        $mergeData = $array instanceof self ? $array->toArray() : $array;

        $this->data = $this->arrayMergeRecursive($this->data, $mergeData);
        return $this;
    }

    /**
     * 递归合并数组的辅助方法
     *
     * @param array $array1 第一个数组
     * @param array $array2 第二个数组
     * @return array 合并后的数组
     */
    private function arrayMergeRecursive(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = $this->arrayMergeRecursive($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }

    /*****************************************************************
     * 搜索和查找方法
     *****************************************************************/

    /**
     * 在数组中搜索给定的值，并返回第一个匹配的键
     *    确保返回正确的键名或false，支持点分隔符路径搜索
     *
     * @param mixed $value 要搜索的值
     * @param bool $strict 是否使用严格比较
     * @return string|int|false 找到返回键名，否则返回false
     */
    public function search(mixed $value, bool $strict = true): string|int|false
    {
        // 先在顶层搜索
        $result = array_search($value, $this->data, $strict);
        if ($result !== false) {
            return $result;
        }

        // 如果顶层没找到，递归搜索所有层级
        $paths = $this->recursiveValueSearch($this->data, $value, $strict);
        return empty($paths) ? false : $paths[0];
    }

    /**
     * 递归搜索给定的值，返回所有匹配的路径
     * 增强：支持返回完整路径和值信息
     *
     * @param mixed $value 要搜索的值
     * @param bool $strict 是否使用严格比较
     * @param bool $includeValue 是否在结果中包含值
     * @return array 包含所有匹配路径的数组
     */
    public function searchValueRecursive(mixed $value, bool $strict = true, bool $includeValue = false): array
    {
        return $this->recursiveValueSearch($this->data, $value, $strict, '', $includeValue);
    }

    /**
     * 递归搜索值的辅助方法 - 增强功能
     *
     * @param array $array 要搜索的数组
     * @param mixed $value 要搜索的值
     * @param bool $strict 是否严格比较
     * @param string $path 当前搜索路径
     * @param bool $includeValue 是否包含值
     * @return array 匹配的路径数组
     */
    private function recursiveValueSearch(array $array, mixed $value, bool $strict = true, string $path = '', bool $includeValue = false): array
    {
        $results = [];
        foreach ($array as $key => $val) {
            $currentPath = $path ? $path . '.' . $key : (string)$key;

            // 检查当前值是否匹配
            $isMatch = $strict ? $val === $value : $val == $value;
            if ($isMatch) {
                if ($includeValue) {
                    $results[] = [
                        'path' => $currentPath,
                        'value' => $val,
                        'key' => $key
                    ];
                } else {
                    $results[] = $currentPath;
                }
            }

            // 递归搜索子数组
            if (is_array($val)) {
                $nestedResults = $this->recursiveValueSearch($val, $value, $strict, $currentPath, $includeValue);
                $results = array_merge($results, $nestedResults);
            }

            // 如果是DataArray实例，也递归搜索
            if ($val instanceof self) {
                $nestedResults = $this->recursiveValueSearch($val->toArray(), $value, $strict, $currentPath, $includeValue);
                $results = array_merge($results, $nestedResults);
            }
        }
        return $results;
    }

    /**
     * 搜索键名（支持通配符）
     *
     * @param string $pattern 要搜索的键名模式，支持通配符 * 和 ?
     * @return array 匹配的键名数组
     */
    public function searchKey(string $pattern): array
    {
        $allKeys = $this->keysRecursive();
        $matches = [];

        // 将通配符模式转换为正则表达式
        $regexPattern = '/^' . str_replace(
                ['*', '?', '.'],
                ['.*', '.', '\.'],
                $pattern
            ) . '$/';

        foreach ($allKeys as $key) {
            if (preg_match($regexPattern, $key)) {
                $matches[] = $key;
            }
        }

        return $matches;
    }

    /**
     * 使用回调函数搜索数组元素
     *
     * @param callable $callback 搜索回调函数，返回true表示匹配
     * @param bool $includePath 是否包含路径信息
     * @return array 匹配的元素数组
     */
    public function searchByCallback(callable $callback, bool $includePath = false): array
    {
        return $this->recursiveCallbackSearch($this->data, $callback, $includePath);
    }

    /**
     * 递归回调搜索的辅助方法
     *
     * @param array $array 要搜索的数组
     * @param callable $callback 回调函数
     * @param bool $includePath 是否包含路径
     * @param string $path 当前路径
     * @return array 匹配结果
     */
    private function recursiveCallbackSearch(array $array, callable $callback, bool $includePath = false, string $path = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            $currentPath = $path ? $path . '.' . $key : (string)$key;

            // 检查当前元素是否匹配
            if ($callback($value, $key, $currentPath)) {
                if ($includePath) {
                    $results[] = [
                        'path' => $currentPath,
                        'key' => $key,
                        'value' => $value
                    ];
                } else {
                    $results[] = $value;
                }
            }

            // 递归搜索子数组
            if (is_array($value)) {
                $nestedResults = $this->recursiveCallbackSearch($value, $callback, $includePath, $currentPath);
                $results = array_merge($results, $nestedResults);
            }

            // 如果是DataArray实例，也递归搜索
            if ($value instanceof self) {
                $nestedResults = $this->recursiveCallbackSearch($value->toArray(), $callback, $includePath, $currentPath);
                $results = array_merge($results, $nestedResults);
            }
        }
        return $results;
    }

    /**
     * 搜索并返回第一个匹配的元素
     *
     * @param mixed $value 要搜索的值
     * @param bool $strict 是否严格比较
     * @param bool $includePath 是否包含路径信息
     * @return mixed 第一个匹配的元素，未找到返回null
     */
    public function searchFirst(mixed $value, bool $strict = true, bool $includePath = false): mixed
    {
        $results = $this->searchValueRecursive($value, $strict, $includePath);
        return empty($results) ? null : $results[0];
    }

    /**
     * 搜索并统计匹配数量
     *
     * @param mixed $value 要搜索的值
     * @param bool $strict 是否严格比较
     * @return int 匹配的数量
     */
    public function searchCount(mixed $value, bool $strict = true): int
    {
        $results = $this->searchValueRecursive($value, $strict);
        return count($results);
    }

    /*****************************************************************
     * 排序方法（全部支持链式操作）
     *****************************************************************/

    /**
     * 对数组按值进行排序
     *
     * @param int $flags 排序标志
     * @return static 返回当前实例以支持链式操作
     */
    public function sort(int $flags = SORT_REGULAR): static
    {
        asort($this->data, $flags);
        return $this;
    }

    /**
     * 对数组按值进行逆序排序
     *
     * @param int $flags 排序标志
     * @return static 返回当前实例以支持链式操作
     */
    public function rsort(int $flags = SORT_REGULAR): static
    {
        arsort($this->data, $flags);
        return $this;
    }

    /**
     * 对数组按键名进行排序
     *
     * @param int $flags 排序标志
     * @return static 返回当前实例以支持链式操作
     */
    public function ksort(int $flags = SORT_REGULAR): static
    {
        ksort($this->data, $flags);
        return $this;
    }

    /**
     * 对数组按键名进行逆序排序
     *
     * @param int $flags 排序标志
     * @return static 返回当前实例以支持链式操作
     */
    public function krsort(int $flags = SORT_REGULAR): static
    {
        krsort($this->data, $flags);
        return $this;
    }

    /**
     * 使用自定义回调函数对数组排序
     *
     * @param callable $callback 比较回调函数
     * @return static 返回当前实例以支持链式操作
     */
    public function usort(callable $callback): static
    {
        usort($this->data, $callback);
        return $this;
    }

    /**
     * 使用自定义回调函数对数组按值排序并保持索引关联
     *
     * @param callable $callback 比较回调函数
     * @return static 返回当前实例以支持链式操作
     */
    public function uasort(callable $callback): static
    {
        uasort($this->data, $callback);
        return $this;
    }

    /**
     * 使用自定义回调函数对数组按键名排序
     *
     * @param callable $callback 比较回调函数
     * @return static 返回当前实例以支持链式操作
     */
    public function uksort(callable $callback): static
    {
        uksort($this->data, $callback);
        return $this;
    }

    /*****************************************************************
     * 数组转换和操作（返回新实例）
     *****************************************************************/

    /**
     * 返回数组的一个片段（返回新实例）
     *
     * @param int $offset 开始的偏移量
     * @param int|null $length 返回的元素个数
     * @param bool $preserveKeys 是否保留键名
     * @return static 新的DataArray实例
     */
    public function slice(int $offset, ?int $length = null, bool $preserveKeys = true): static
    {
        return new static(array_slice($this->data, $offset, $length, $preserveKeys));
    }

    /**
     * 使用回调函数对数组的每个元素进行操作（返回新实例）
     *
     * @param callable $callback 回调函数
     * @return static 新的DataArray实例
     */
    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->data));
    }

    /**
     * 使用回调函数过滤数组中的元素（返回新实例）
     *
     * @param callable|null $callback 回调函数，为空时过滤空值
     * @param int $mode 过滤模式
     * @return static 新的DataArray实例
     */
    public function filter(callable $callback = null, int $mode = 0): static
    {
        $callback = $callback ?: fn($value) => (bool)$value;
        $filtered = array_filter($this->data, $callback, $mode);
        return new static($filtered);
    }

    /**
     * 使用回调函数迭代缩小数组到单一值
     *
     * @param callable $callback 回调函数
     * @param mixed $initial 初始值
     * @return mixed 缩减后的结果
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->data, $callback, $initial);
    }

    /**
     * 设置多个值（链式操作）
     *
     * @param array $values 键值对数组
     * @return static 返回当前实例以支持链式操作
     */
    public function add(array $values): static
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * 数组分块（返回新实例）
     *
     * @param int $size 每块的大小
     * @param bool $preserveKeys 是否保留键名
     * @return static 新的DataArray实例
     */
    public function chunk(int $size, bool $preserveKeys = false): static
    {
        return new static(array_chunk($this->data, $size, $preserveKeys));
    }

    /**
     * 数组去重（返回新实例）
     *
     * @param int $flags 比较标志
     * @return static 新的DataArray实例
     */
    public function unique(int $flags = SORT_STRING): static
    {
        // 安全地去重，处理可能的数据类型问题
        $uniqueData = [];
        foreach ($this->data as $key => $value) {
            $serialized = serialize($value);
            if (!in_array($serialized, $uniqueData, true)) {
                $uniqueData[$serialized] = $value;
            }
        }
        return new static(array_values($uniqueData));
    }

    /**
     * 数组键值交换（返回新实例）
     *
     * @return static 新的DataArray实例
     */
    public function flip(): static
    {
        // 只翻转可以作为键的值（字符串和整数）
        $flippableData = array_filter($this->data, function($value) {
            return is_string($value) || is_int($value);
        });

        $flipped = [];
        foreach ($flippableData as $key => $value) {
            $flipped[$value] = $key;
        }
        return new static($flipped);
    }

    /**
     * 获取数组的列（返回新实例）
     *
     * @param mixed $columnKey 要获取的列键名
     * @param mixed $indexKey 作为索引的列键名
     * @return static 新的DataArray实例
     */
    public function column(mixed $columnKey, mixed $indexKey = null): static
    {
        $result = array_column($this->data, $columnKey, $indexKey);
        return new static($result ?: []);
    }

    /**
     * 使用回调函数对数组进行分组（返回新实例）
     *
     * @param callable $callback 分组回调函数
     * @return static 新的DataArray实例
     */
    public function groupBy(callable $callback): static
    {
        $grouped = [];
        foreach ($this->data as $key => $value) {
            $groupKey = $callback($value, $key);
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [];
            }
            $grouped[$groupKey][] = $value;
        }
        return new static($grouped);
    }

    /*****************************************************************
     * 栈和队列操作
     *****************************************************************/

    /**
     * 在数组末尾添加一个或多个元素（链式操作）
     *
     * @param mixed ...$values 要添加的值
     * @return static 返回当前实例以支持链式操作
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function push(mixed ...$values): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        foreach ($values as $value) {
            $this->data[] = $value;
        }
        return $this;
    }

    /**
     * 清空数组（链式操作）
     *
     * @return static 返回当前实例以支持链式操作
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function clear(): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        $this->data = [];
        return $this;
    }

    /**
     * 获取第一个元素
     *
     * @return mixed 第一个元素的值
     */
    public function first(): mixed
    {
        return $this->data ? reset($this->data) : null;
    }

    /**
     * 获取最后一个元素
     *
     * @return mixed 最后一个元素的值
     */
    public function last(): mixed
    {
        return $this->data ? end($this->data) : null;
    }

    /**
     * 弹出最后一个元素
     *
     * @return mixed 弹出的元素值
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function pop(): mixed
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        return array_pop($this->data);
    }

    /**
     * 在数组开头插入一个或多个元素（链式操作）
     *
     * @param mixed ...$values 要插入的值
     * @return static 返回当前实例以支持链式操作
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function unshift(mixed ...$values): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        foreach (array_reverse($values) as $value) {
            array_unshift($this->data, $value);
        }
        return $this;
    }

    /**
     * 弹出第一个元素
     *
     * @return mixed 弹出的元素值
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function shift(): mixed
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        return array_shift($this->data);
    }

    /*****************************************************************
     * 高级功能和工具方法
     *****************************************************************/

    /**
     * 检查是否只读模式
     *
     * @return bool 只读返回true，否则返回false
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * 创建副本（保持只读状态）
     *
     * @return static 新的DataArray实例
     */
    public function copy(): static
    {
        return new static($this->data, $this->readOnly);
    }

    /**
     * 创建可变副本（强制可写）
     *
     * @return static 新的可写DataArray实例
     */
    public function mutable(): static
    {
        return new static($this->data, false);
    }

    /**
     * 遍历数组并对每个元素执行回调（链式操作）
     *
     * @param callable $callback 回调函数
     * @return static 返回当前实例以支持链式操作
     */
    public function each(callable $callback): static
    {
        foreach ($this->data as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }
        return $this;
    }

    /**
     * 当条件满足时执行回调（链式操作）
     *
     * @param mixed $condition 条件
     * @param callable $callback 条件为真时执行的回调
     * @param callable|null $default 条件为假时执行的回调
     * @return static 返回当前实例以支持链式操作
     */
    public function when(mixed $condition, callable $callback, callable $default = null): static
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($default) {
            $default($this, $condition);
        }

        return $this;
    }

    /**
     * 除非条件满足时执行回调（链式操作）
     *
     * @param mixed $condition 条件
     * @param callable $callback 条件为假时执行的回调
     * @param callable|null $default 条件为真时执行的回调
     * @return static 返回当前实例以支持链式操作
     */
    public function unless(mixed $condition, callable $callback, callable $default = null): static
    {
        return $this->when(!$condition, $callback, $default);
    }

    /**
     * 管道操作，将当前实例传递给回调函数
     *
     * @param callable $callback 回调函数
     * @return mixed 回调函数的返回值
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * 随机获取一个或多个元素
     *
     * @param int $num 要获取的元素数量
     * @return mixed 单个元素或元素数组
     */
    public function random(int $num = 1): mixed
    {
        if (empty($this->data)) {
            return $num === 1 ? null : [];
        }

        if ($num === 1) {
            $key = array_rand($this->data, 1);
            return $this->data[$key];
        }

        $keys = array_rand($this->data, min($num, count($this->data)));
        $result = [];
        foreach ((array)$keys as $key) {
            $result[$key] = $this->data[$key];
        }
        return $result;
    }

    /*****************************************************************
     * 调试方法
     *****************************************************************/

    /**
     * 输出并终止脚本（调试用）
     */
    public function dd(): void
    {
        var_dump($this->toArrayRecursive());
        exit(1);
    }

    /**
     * 输出不终止脚本（调试用，链式操作）
     *
     * @return static 返回当前实例以支持链式操作
     */
    public function dump(): static
    {
        var_dump($this->toArrayRecursive());
        return $this;
    }

    /**
     * 获取迭代器
     *
     * @return ArrayIterator 数组迭代器
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }
}

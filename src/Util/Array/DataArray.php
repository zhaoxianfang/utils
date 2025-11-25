<?php

namespace zxf\Util\Array;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Exception;
use Generator;
use IteratorAggregate;
use JsonSerializable;
use SimpleXMLElement;
use Traversable;
use RuntimeException;
use InvalidArgumentException;
use Stringable;
use Serializable;
use RecursiveIteratorIterator;

/**
 * 增强版数组对象工具类
 * 提供数组的面向对象操作方式，支持点分隔符访问、通配符查找、链式操作等高级功能
 * 支持继承和方法重写，提供更丰富的数组操作功能
 * 实现 PHP 8.2+ 多种接口，提供完整的数据处理能力
 *
 * @example
 * $data = new DataArray(['user' => ['name' => 'John']]);
 * echo $data->get('user.name'); // John
 * echo $data['user.name']; // John (通过数组访问)
 * echo $data->get('list.*.name'); // 通配符查找
 *
 * @see https://weisifang.com/docs/doc/6_203 DataArray使用文档
 */
class DataArray implements
    ArrayAccess,
    Countable,
    IteratorAggregate,
    JsonSerializable,
    Stringable,
    Serializable
{
    /**
     * 存储数据的数组
     * @var array
     */
    protected array $data;

    /**
     * 是否只读模式
     * @var bool
     */
    protected bool $readOnly;

    /**
     * 通配符搜索深度限制
     * @var int
     */
    protected int $wildcardSearchDepth = 50;

    /**
     * 默认分隔符
     * @var string
     */
    protected string $delimiter = '.';

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
     * @param int $depth 递归深度
     * @param int $flags JSON解码选项
     * @return static 返回DataArray实例
     * @throws InvalidArgumentException JSON解析失败时抛出异常
     */
    public static function fromJson(string $json, bool $associative = true, int $depth = 512, int $flags = 0): static
    {
        $data = json_decode($json, $associative, $depth, $flags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('JSON解析错误: ' . json_last_error_msg());
        }

        // 确保返回的是数组
        $result = $associative ? (array)$data : (array)get_object_vars($data);
        return new static($result ?: []);
    }

    /**
     * 从对象创建实例
     *
     * @param object $object 源对象
     * @param bool $recursive 是否递归转换对象属性
     * @return static 返回DataArray实例
     */
    public static function fromObject(object $object, bool $recursive = false): static
    {
        $data = get_object_vars($object);

        if ($recursive) {
            $data = static::convertObjectToArrayRecursive($data);
        }

        return new static($data);
    }

    /**
     * 递归将对象转换为数组
     *
     * @param mixed $data 要转换的数据
     * @return mixed 转换后的数据
     */
    protected static function convertObjectToArrayRecursive(mixed $data): mixed
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = static::convertObjectToArrayRecursive($value);
            }
        }

        return $data;
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

    /**
     * 从CSV字符串创建实例
     *
     * @param string $csv CSV字符串
     * @param string $delimiter 分隔符
     * @param string $enclosure 包围符
     * @param string $escape 转义符
     * @param bool $hasHeaders 是否包含标题行
     * @return static 返回DataArray实例
     */
    public static function fromCsv(
        string $csv,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $hasHeaders = true
    ): static {
        $lines = str_getcsv($csv, "\n");
        $data = [];

        if (empty($lines)) {
            return new static([]);
        }

        $headers = [];
        $startIndex = 0;

        if ($hasHeaders && !empty($lines[0])) {
            $firstLine = str_getcsv($lines[0], $delimiter, $enclosure, $escape);
            $headers = array_map('trim', $firstLine);
            $startIndex = 1;
        }

        for ($i = $startIndex; $i < count($lines); $i++) {
            if (empty(trim($lines[$i]))) {
                continue;
            }

            $row = str_getcsv($lines[$i], $delimiter, $enclosure, $escape);
            $row = array_map('trim', $row);

            if ($hasHeaders && !empty($headers) && count($headers) === count($row)) {
                $data[] = array_combine($headers, $row);
            } else {
                $data[] = $row;
            }
        }

        return new static($data);
    }

    /**
     * 从XML字符串创建实例
     *
     * @param string $xml XML字符串
     * @param bool $associative 是否返回关联数组
     * @return static 返回DataArray实例
     * @throws InvalidArgumentException XML解析失败时抛出异常
     */
    public static function fromXml(string $xml, bool $associative = true): static
    {
        $backup = libxml_use_internal_errors(true);
        $data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($backup);

        if ($data === false) {
            throw new InvalidArgumentException('XML解析错误');
        }

        $json = json_encode($data);
        if ($json === false) {
            throw new InvalidArgumentException('XML转换JSON失败');
        }

        return static::fromJson($json, $associative);
    }

    /**
     * 从YAML字符串创建实例（需要yaml扩展）
     *
     * @param string $yaml YAML字符串
     * @return static 返回DataArray实例
     * @throws InvalidArgumentException YAML解析失败时抛出异常
     */
    public static function fromYaml(string $yaml): static
    {
        if (!function_exists('yaml_parse')) {
            throw new RuntimeException('YAML扩展未安装');
        }

        $data = yaml_parse($yaml);
        if ($data === false) {
            throw new InvalidArgumentException('YAML解析错误');
        }

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
        if (is_string($offset) && str_contains($offset, $this->delimiter)) {
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
        if (is_string($offset) && str_contains($offset, $this->delimiter)) {
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
        if (is_string($offset) && str_contains($offset, $this->delimiter)) {
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
        if (is_string($offset) && str_contains($offset, $this->delimiter)) {
            $this->delete($offset);
        } else {
            unset($this->data[$offset]);
        }
    }

    /*****************************************************************
     * IteratorAggregate 接口实现
     *****************************************************************/

    /**
     * 获取迭代器
     *
     * @return Traversable 数组迭代器
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /*****************************************************************
     * Serializable 接口实现
     *****************************************************************/

    /**
     * 序列化对象
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize([
            'data' => $this->data,
            'readOnly' => $this->readOnly,
            'delimiter' => $this->delimiter,
            'wildcardSearchDepth' => $this->wildcardSearchDepth
        ]);
    }

    /**
     * 反序列化对象
     *
     * @param string $data
     */
    public function unserialize(string $data): void
    {
        $unserialized = unserialize($data);

        $this->data = $unserialized['data'] ?? [];
        $this->readOnly = $unserialized['readOnly'] ?? false;
        $this->delimiter = $unserialized['delimiter'] ?? '.';
        $this->wildcardSearchDepth = $unserialized['wildcardSearchDepth'] ?? 50;
    }

    /**
     * PHP 8.1+ 序列化魔术方法
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'data' => $this->data,
            'readOnly' => $this->readOnly,
            'delimiter' => $this->delimiter,
            'wildcardSearchDepth' => $this->wildcardSearchDepth
        ];
    }

    /**
     * PHP 8.1+ 反序列化魔术方法
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->data = $data['data'] ?? [];
        $this->readOnly = $data['readOnly'] ?? false;
        $this->delimiter = $data['delimiter'] ?? '.';
        $this->wildcardSearchDepth = $data['wildcardSearchDepth'] ?? 50;
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

    /**
     * 魔术方法调用，支持动态方法
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        // 支持动态的 where{Field} 方法
        if (str_starts_with($name, 'where')) {
            $field = lcfirst(substr($name, 5));
            $value = $arguments[0] ?? null;
            $operator = $arguments[1] ?? '=';

            return $this->where($field, $operator, $value);
        }

        // 支持动态的 orderBy{Field} 方法
        if (str_starts_with($name, 'orderBy')) {
            $field = lcfirst(substr($name, 7));
            $direction = $arguments[0] ?? 'asc';

            return $this->orderBy($field, $direction);
        }

        throw new RuntimeException("方法 {$name} 不存在");
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
    protected function getKeysRecursive(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix . $this->delimiter . $key : (string)$key;
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
     * @return mixed 转换后的数据
     */
    protected function convertToArrayRecursive(mixed $data): mixed
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

        // 对于可遍历对象，也进行处理
        if ($data instanceof Traversable) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->convertToArrayRecursive($value);
            }
            return $result;
        }

        // 对于标量值或对象，直接返回
        return $data;
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

    /**
     * 转换为CSV字符串
     *
     * @param string $delimiter 分隔符
     * @param string $enclosure 包围符
     * @return string CSV格式的字符串
     */
    public function toCsv(string $delimiter = ',', string $enclosure = '"'): string
    {
        $data = $this->toArrayRecursive();

        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // 写入标题行
        $firstRow = reset($data);
        if (is_array($firstRow)) {
            fputcsv($output, array_keys($firstRow), $delimiter, $enclosure);
        }

        // 写入数据行
        foreach ($data as $row) {
            if (is_array($row)) {
                fputcsv($output, $row, $delimiter, $enclosure);
            } else {
                fputcsv($output, [$row], $delimiter, $enclosure);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * 转换为XML字符串
     *
     * @param string $rootNode 根节点名称
     * @param string $itemNode 项目节点名称
     * @return string XML格式的字符串
     * @throws Exception
     */
    public function toXml(string $rootNode = 'root', string $itemNode = 'item'): string
    {
        $data = $this->toArrayRecursive();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $rootNode . '/>');

        $this->arrayToXml($data, $xml, $itemNode);

        return $xml->asXML();
    }

    /**
     * 数组转换为XML的辅助方法
     *
     * @param array $data 数组数据
     * @param SimpleXMLElement $xml XML对象
     * @param string $itemNode 项目节点名称
     */
    protected function arrayToXml(array $data, SimpleXMLElement $xml, string $itemNode): void
    {
        foreach ($data as $key => $value) {
            // 处理数字键名
            if (is_numeric($key)) {
                $key = $itemNode;
            }

            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode, $itemNode);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
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
     * 检查数组是否不为空
     *
     * @return bool 数组不为空返回true，否则返回false
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
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
        if (str_contains($key, $this->delimiter)) {
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
    protected function getWithDotNotation(string $key, mixed $defaultValue = null): mixed
    {
        $keys = explode($this->delimiter, $key);
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
    protected function getWithWildcard(string $pattern, mixed $defaultValue = null): mixed
    {
        $segments = explode($this->delimiter, $pattern);
        $results = $this->wildcardSearchRecursive($this->data, $segments, 0);

        return empty($results) ? $defaultValue : (count($results) === 1 ? $results[0] : $results);
    }

    /**
     * 递归搜索匹配通配符的值
     *
     * @param mixed $data 当前搜索的数据
     * @param array $segments 路径分段数组
     * @param int $index 当前处理的段索引
     * @param int $depth 当前搜索深度
     * @return array 匹配的结果数组
     */
    protected function wildcardSearchRecursive(mixed $data, array $segments, int $index, int $depth = 0): array
    {
        // 防止无限递归
        if ($depth > $this->wildcardSearchDepth) {
            return [];
        }

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
                $results = array_merge($results, $this->wildcardSearchRecursive($value, $segments, $index + 1, $depth + 1));
            }
        }
        // 处理多级通配符 (**)
        elseif ($currentSegment === '**') {
            // 先尝试跳过当前通配符继续搜索
            $results = array_merge($results, $this->wildcardSearchRecursive($data, $segments, $index + 1, $depth + 1));

            // 然后在当前层级继续搜索
            foreach ($data as $value) {
                if (is_array($value) || $value instanceof Traversable) {
                    $results = array_merge($results, $this->wildcardSearchRecursive($value, $segments, $index, $depth + 1));
                }
            }
        }
        // 普通键名精确匹配
        else {
            if (($data instanceof ArrayAccess && $data->offsetExists($currentSegment)) ||
                (is_array($data) && array_key_exists($currentSegment, $data))) {
                $value = $data[$currentSegment];
                $results = array_merge($results, $this->wildcardSearchRecursive($value, $segments, $index + 1, $depth + 1));
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

        if (is_string($key) && str_contains($key, $this->delimiter)) {
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
    protected function setWithDotNotation(string $key, mixed $value): void
    {
        $keys = explode($this->delimiter, $key);
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

        if (str_contains($key, $this->delimiter)) {
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
    protected function hasWithDotNotation(string $key): bool
    {
        $keys = explode($this->delimiter, $key);
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

        if (is_string($key) && str_contains($key, $this->delimiter)) {
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
    protected function deleteWithDotNotation(string $key): void
    {
        $keys = explode($this->delimiter, $key);
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
     * @param array|self ...$arrays 要合并的数组或DataArray实例
     * @return static 返回当前实例以支持链式操作
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function merge(array|self ...$arrays): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        foreach ($arrays as $array) {
            // 如果是DataArray实例，获取其数组数据
            $mergeData = $array instanceof self ? $array->toArray() : $array;
            $this->data = array_merge($this->data, $mergeData);
        }

        return $this;
    }

    /**
     * 递归合并另一个数组到当前数组对象中（链式操作）
     *
     * @param array|self ...$arrays 要合并的数组或DataArray实例
     * @return static 返回当前实例以支持链式操作
     * @throws RuntimeException 只读模式下尝试修改时抛出异常
     */
    public function mergeRecursive(array|self ...$arrays): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        foreach ($arrays as $array) {
            // 如果是DataArray实例，获取其数组数据
            $mergeData = $array instanceof self ? $array->toArray() : $array;
            $this->data = $this->arrayMergeRecursive($this->data, $mergeData);
        }

        return $this;
    }

    /**
     * 递归合并数组的辅助方法
     *
     * @param array $array1 第一个数组
     * @param array $array2 第二个数组
     * @return array 合并后的数组
     */
    protected function arrayMergeRecursive(array $array1, array $array2): array
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
    protected function recursiveValueSearch(array $array, mixed $value, bool $strict = true, string $path = '', bool $includeValue = false): array
    {
        $results = [];
        foreach ($array as $key => $val) {
            $currentPath = $path ? $path . $this->delimiter . $key : (string)$key;

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
    protected function recursiveCallbackSearch(array $array, callable $callback, bool $includePath = false, string $path = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            $currentPath = $path ? $path . $this->delimiter . $key : (string)$key;

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

    /**
     * 自然排序（不区分大小写）
     *
     * @return static 返回当前实例以支持链式操作
     */
    public function natSort(): static
    {
        natsort($this->data);
        return $this;
    }

    /**
     * 自然排序（不区分大小写）
     *
     * @return static 返回当前实例以支持链式操作
     */
    public function natCaseSort(): static
    {
        natcasesort($this->data);
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
                $uniqueData[$serialized] = $key;
            }
        }

        $result = [];
        foreach ($uniqueData as $key) {
            $result[$key] = $this->data[$key];
        }

        return new static($result);
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
     * 使用回调函数或字符串对数组进行分组（返回新实例）
     *
     * @param string|callable $groupBy 分组键或回调函数
     * @param bool $preserveKeys 是否保留原始键
     * @return static 新的DataArray实例
     */
    public function groupBy(string|callable $groupBy, bool $preserveKeys = false): static
    {
        $grouped = [];

        foreach ($this->data as $key => $value) {
            if (is_callable($groupBy)) {
                $groupKey = $groupBy($value, $key);
            } else {
                $groupKey = $this->getValueFromItem($value, $groupBy);
            }

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [];
            }

            if ($preserveKeys) {
                $grouped[$groupKey][$key] = $value;
            } else {
                $grouped[$groupKey][] = $value;
            }
        }

        return new static($grouped);
    }

    /**
     * 从数组项中获取值
     *
     * @param mixed $item 数组项
     * @param string $key 键名
     * @return mixed
     */
    protected function getValueFromItem(mixed $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        } elseif (is_object($item)) {
            return $item->$key ?? ($item->{$key} ?? null);
        }

        return null;
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
     * 新增功能：条件过滤和查询
     *****************************************************************/

    /**
     * 根据条件过滤数组
     *
     * @param string|callable $key 要比较的键或回调函数
     * @param mixed $operator 操作符或值
     * @param mixed $value 比较的值
     * @return static 新的DataArray实例
     */
    public function where(string|callable $key, mixed $operator = null, mixed $value = null): static
    {
        // 如果第一个参数是回调函数
        if (is_callable($key)) {
            return $this->filter($key);
        }

        // 处理两个参数的情况（键和值，使用等号比较）
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $filtered = array_filter($this->data, function($item) use ($key, $operator, $value) {
            $itemValue = $this->getValueFromItem($item, $key);

            return $this->compareValues($itemValue, $operator, $value);
        });

        return new static(array_values($filtered));
    }

    /**
     * 比较值
     *
     * @param mixed $itemValue
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function compareValues(mixed $itemValue, string $operator, mixed $value): bool
    {
        return match($operator) {
            '=', '==' => $itemValue == $value,
            '===' => $itemValue === $value,
            '!=', '<>' => $itemValue != $value,
            '!==' => $itemValue !== $value,
            '>' => $itemValue > $value,
            '>=' => $itemValue >= $value,
            '<' => $itemValue < $value,
            '<=' => $itemValue <= $value,
            'in' => in_array($itemValue, (array)$value, true),
            'not_in' => !in_array($itemValue, (array)$value, true),
            'between' => (is_array($value) && count($value) >= 2 && $itemValue >= $value[0] && $itemValue <= $value[1]),
            'not_between' => (is_array($value) && count($value) >= 2 && ($itemValue < $value[0] || $itemValue > $value[1])),
            'like' => str_contains((string)$itemValue, (string)$value),
            'not_like' => !str_contains((string)$itemValue, (string)$value),
            'starts_with' => str_starts_with((string)$itemValue, (string)$value),
            'ends_with' => str_ends_with((string)$itemValue, (string)$value),
            'regex' => (bool)preg_match($value, (string)$itemValue),
            'not_regex' => !preg_match($value, (string)$itemValue),
            'null' => is_null($itemValue),
            'not_null' => !is_null($itemValue),
            'empty' => empty($itemValue),
            'not_empty' => !empty($itemValue),
            'true' => $itemValue === true,
            'false' => $itemValue === false,
            default => false
        };
    }

    /**
     * 根据多个条件过滤数组
     *
     * @param array $conditions 条件数组
     * @param string $boolean 逻辑关系：and 或 or
     * @return static 新的DataArray实例
     */
    public function whereMultiple(array $conditions, string $boolean = 'and'): static
    {
        $filtered = array_filter($this->data, function($item) use ($conditions, $boolean) {
            $results = [];

            foreach ($conditions as $condition) {
                if (is_callable($condition)) {
                    $results[] = $condition($item);
                    continue;
                }

                $key = $condition[0];
                $operator = count($condition) === 3 ? $condition[1] : '=';
                $value = count($condition) === 3 ? $condition[2] : $condition[1];

                $itemValue = $this->getValueFromItem($item, $key);
                $results[] = $this->compareValues($itemValue, $operator, $value);
            }

            return $boolean === 'and'
                ? !in_array(false, $results, true)
                : in_array(true, $results, true);
        });

        return new static(array_values($filtered));
    }

    /**
     * 使用OR逻辑连接多个条件
     *
     * @param array $conditions 条件数组
     * @return static 新的DataArray实例
     */
    public function orWhere(array $conditions): static
    {
        return $this->whereMultiple($conditions, 'or');
    }

    /**
     * 根据键值对过滤数组
     *
     * @param array $pairs 键值对数组
     * @return static 新的DataArray实例
     */
    public function wherePairs(array $pairs): static
    {
        $conditions = [];
        foreach ($pairs as $key => $value) {
            $conditions[] = [$key, '=', $value];
        }

        return $this->whereMultiple($conditions);
    }

    /**
     * 根据键存在性过滤数组
     *
     * @param string|array $keys 要检查的键
     * @param bool $exists 是否要求存在
     * @return static 新的DataArray实例
     */
    public function whereKeyExists(string|array $keys, bool $exists = true): static
    {
        $keys = (array)$keys;

        return $this->filter(function($item) use ($keys, $exists) {
            if (!is_array($item) && !$item instanceof ArrayAccess) {
                return false;
            }

            foreach ($keys as $key) {
                $hasKey = array_key_exists($key, $item);
                if ($exists && !$hasKey) {
                    return false;
                }
                if (!$exists && $hasKey) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * 排序并获取前N个元素
     *
     * @param int $count 要获取的元素数量
     * @param callable|null $callback 排序回调函数
     * @return static 新的DataArray实例
     */
    public function top(int $count, callable $callback = null): static
    {
        $sorted = $callback ? $this->usort($callback) : $this->sort();
        return $sorted->slice(0, $count);
    }

    /**
     * 对数组进行分页
     *
     * @param int $page 页码
     * @param int $perPage 每页数量
     * @return static 新的DataArray实例
     */
    public function forPage(int $page, int $perPage): static
    {
        $offset = max(0, ($page - 1) * $perPage);
        return $this->slice($offset, $perPage);
    }

    /**
     * 根据字段排序
     *
     * @param string $field 排序字段
     * @param string $direction 排序方向：asc 或 desc
     * @return static 新的DataArray实例
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $sorted = $this->sortBy($field);

        if ($direction === 'desc') {
            $sorted = $sorted->reverse();
        }

        return $sorted;
    }

    /**
     * 根据字段排序（返回新实例）
     *
     * @param string $field 排序字段
     * @return static 新的DataArray实例
     */
    public function sortBy(string $field): static
    {
        $data = $this->data;

        usort($data, function($a, $b) use ($field) {
            $aValue = $this->getValueFromItem($a, $field);
            $bValue = $this->getValueFromItem($b, $field);

            if ($aValue == $bValue) {
                return 0;
            }

            return ($aValue < $bValue) ? -1 : 1;
        });

        return new static($data);
    }

    /*****************************************************************
     * 新增功能：统计和计算
     *****************************************************************/

    /**
     * 计算数组值的总和
     *
     * @param callable|string|null $callback 回调函数或字段名用于提取数值
     * @return float 总和
     */
    public function sum(callable|string $callback = null): float
    {
        if ($callback) {
            if (is_string($callback)) {
                return array_sum(array_map(fn($item) => $this->getValueFromItem($item, $callback) ?? 0, $this->data));
            }
            return array_sum(array_map($callback, $this->data));
        }

        return array_sum($this->data);
    }

    /**
     * 计算数组值的平均值
     *
     * @param callable|string|null $callback 回调函数或字段名用于提取数值
     * @return float 平均值
     */
    public function avg(callable|string $callback = null): float
    {
        $count = count($this->data);
        if ($count === 0) {
            return 0.0;
        }

        return $this->sum($callback) / $count;
    }

    /**
     * 获取最大值
     *
     * @param callable|string|null $callback 回调函数或字段名用于提取数值
     * @return mixed 最大值
     */
    public function max(callable|string $callback = null): mixed
    {
        if ($callback) {
            if (is_string($callback)) {
                $values = array_map(fn($item) => $this->getValueFromItem($item, $callback), $this->data);
            } else {
                $values = array_map($callback, $this->data);
            }
            $values = array_filter($values, fn($v) => $v !== null);
            return empty($values) ? null : max($values);
        }

        return empty($this->data) ? null : max($this->data);
    }

    /**
     * 获取最小值
     *
     * @param callable|string|null $callback 回调函数或字段名用于提取数值
     * @return mixed 最小值
     */
    public function min(callable|string $callback = null): mixed
    {
        if ($callback) {
            if (is_string($callback)) {
                $values = array_map(fn($item) => $this->getValueFromItem($item, $callback), $this->data);
            } else {
                $values = array_map($callback, $this->data);
            }
            $values = array_filter($values, fn($v) => $v !== null);
            return empty($values) ? null : min($values);
        }

        return empty($this->data) ? null : min($this->data);
    }

    /**
     * 统计值出现的频率
     *
     * @param callable|string|null $callback 回调函数或字段名用于提取数值
     * @return static 新的DataArray实例，包含值和出现次数
     */
    public function frequency(callable|string $callback = null): static
    {
        if ($callback) {
            if (is_string($callback)) {
                $values = array_map(fn($item) => $this->getValueFromItem($item, $callback), $this->data);
            } else {
                $values = array_map($callback, $this->data);
            }
        } else {
            $values = $this->data;
        }

        $frequency = array_count_values(array_filter($values, fn($v) => $v !== null));
        return new static($frequency);
    }

    /**
     * 计算中位数
     *
     * @param callable|string|null $callback 回调函数或字段名用于提取数值
     * @return float 中位数
     */
    public function median(callable|string $callback = null): float
    {
        $values = $this->getNumericValues($callback);

        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = (int)floor(($count - 1) / 2);

        if ($count % 2) {
            return (float)$values[$middle];
        } else {
            return (float)(($values[$middle] + $values[$middle + 1]) / 2);
        }
    }

    /**
     * 计算众数
     *
     * @param callable|string|null $callback 回调函数或字段名用于提取数值
     * @return array 众数数组
     */
    public function mode(callable|string $callback = null): array
    {
        $frequency = $this->frequency($callback)->toArray();

        if (empty($frequency)) {
            return [];
        }

        $maxCount = max($frequency);
        return array_keys(array_filter($frequency, fn($count) => $count === $maxCount));
    }

    /**
     * 计算标准差
     *
     * @param callable|string|null $callback 回调函数或字段名用于提取数值
     * @return float 标准差
     */
    public function stddev(callable|string $callback = null): float
    {
        $values = $this->getNumericValues($callback);

        if (empty($values)) {
            return 0.0;
        }

        $mean = $this->avg($callback);
        $sum = 0.0;

        foreach ($values as $value) {
            $sum += pow($value - $mean, 2);
        }

        return (float)sqrt($sum / count($values));
    }

    /**
     * 获取数值数组
     *
     * @param callable|string|null $callback 回调函数或字段名
     * @return array 数值数组
     */
    protected function getNumericValues(callable|string $callback = null): array
    {
        if ($callback) {
            if (is_string($callback)) {
                $values = array_map(fn($item) => $this->getValueFromItem($item, $callback), $this->data);
            } else {
                $values = array_map($callback, $this->data);
            }
        } else {
            $values = $this->data;
        }

        return array_filter($values, 'is_numeric');
    }

    /*****************************************************************
     * 新增功能：数组结构操作
     *****************************************************************/

    /**
     * 将多维数组展平为一维数组
     *
     * @param string $delimiter 键名分隔符
     * @return static 新的DataArray实例
     */
    public function flatten(string $delimiter = null): static
    {
        $delimiter = $delimiter ?: $this->delimiter;
        $result = [];
        $this->flattenRecursive($this->data, $result, '', $delimiter);
        return new static($result);
    }

    /**
     * 递归展平数组
     *
     * @param array $array 要展平的数组
     * @param array $result 结果数组
     * @param string $prefix 当前前缀
     * @param string $delimiter 分隔符
     */
    protected function flattenRecursive(array $array, array &$result, string $prefix, string $delimiter): void
    {
        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . $delimiter . $key : (string)$key;

            if (is_array($value)) {
                $this->flattenRecursive($value, $result, $newKey, $delimiter);
            } else {
                $result[$newKey] = $value;
            }
        }
    }

    /**
     * 将扁平数组转换为嵌套数组
     *
     * @param string $delimiter 键名分隔符
     * @return static 新的DataArray实例
     */
    public function unflatten(string $delimiter = null): static
    {
        $delimiter = $delimiter ?: $this->delimiter;
        $result = [];

        foreach ($this->data as $key => $value) {
            $keys = explode($delimiter, (string)$key);
            $current = &$result;

            foreach ($keys as $k) {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }

            $current = $value;
        }

        return new static($result);
    }

    /**
     * 提取指定键的值
     *
     * @param string|array $keys 要提取的键
     * @return static 新的DataArray实例
     */
    public function only(string|array $keys): static
    {
        $keys = (array)$keys;
        $result = [];

        foreach ($keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->get($key);
            }
        }

        return new static($result);
    }

    /**
     * 排除指定键的值
     *
     * @param string|array $keys 要排除的键
     * @return static 新的DataArray实例
     */
    public function except(string|array $keys): static
    {
        $keys = (array)$keys;
        $result = $this->data;

        foreach ($keys as $key) {
            if (str_contains($key, $this->delimiter)) {
                $this->deleteFromArray($result, $key);
            } else {
                unset($result[$key]);
            }
        }

        return new static($result);
    }

    /**
     * 从数组中删除指定键
     *
     * @param array $array 目标数组
     * @param string $key 要删除的键
     */
    protected function deleteFromArray(array &$array, string $key): void
    {
        $keys = explode($this->delimiter, $key);
        $current = &$array;

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

    /**
     * 合并数组值
     *
     * @return static 新的DataArray实例
     */
    public function collapse(): static
    {
        $result = [];

        foreach ($this->data as $values) {
            if (is_array($values)) {
                $result = array_merge($result, $values);
            }
        }

        return new static($result);
    }

    /**
     * 交叉连接数组
     *
     * @param array ...$arrays 要交叉连接的数组
     * @return static 新的DataArray实例
     */
    public function crossJoin(array ...$arrays): static
    {
        $results = [[]];

        foreach ($arrays as $index => $array) {
            $append = [];

            foreach ($results as $result) {
                foreach ($array as $item) {
                    $append[] = array_merge($result, [$item]);
                }
            }

            $results = $append;
        }

        return new static($results);
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

    /*****************************************************************
     * 新增功能：配置和扩展
     *****************************************************************/

    /**
     * 设置通配符搜索深度限制
     *
     * @param int $depth 深度限制
     * @return static 返回当前实例以支持链式操作
     */
    public function setWildcardSearchDepth(int $depth): static
    {
        $this->wildcardSearchDepth = max(1, $depth);
        return $this;
    }

    /**
     * 获取通配符搜索深度限制
     *
     * @return int 深度限制
     */
    public function getWildcardSearchDepth(): int
    {
        return $this->wildcardSearchDepth;
    }

    /**
     * 设置分隔符
     *
     * @param string $delimiter 分隔符
     * @return static 返回当前实例以支持链式操作
     */
    public function setDelimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * 获取分隔符
     *
     * @return string 分隔符
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * 静态方法创建实例（语法糖）
     *
     * @param array $data 初始数据
     * @return static
     */
    public static function make(array $data = []): static
    {
        return new static($data);
    }

    /**
     * 检查是否包含所有指定的键
     *
     * @param array $keys 要检查的键名数组
     * @return bool 包含所有键返回true，否则返回false
     */
    public function containsAll(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->has($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检查是否包含任意一个指定的键
     *
     * @param array $keys 要检查的键名数组
     * @return bool 包含任意一个键返回true，否则返回false
     */
    public function containsAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取数组的深度
     *
     * @return int 数组的最大深度
     */
    public function depth(): int
    {
        return $this->calculateDepth($this->data);
    }

    /**
     * 计算数组深度
     *
     * @param array $array 要计算的数组
     * @param int $currentDepth 当前深度
     * @return int 最大深度
     */
    protected function calculateDepth(array $array, int $currentDepth = 1): int
    {
        $maxDepth = $currentDepth;

        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->calculateDepth($value, $currentDepth + 1);
                $maxDepth = max($maxDepth, $depth);
            }
        }

        return $maxDepth;
    }

    /**
     * 将数组划分为两个数组：一个满足条件，另一个不满足条件
     *
     * @param callable $callback 条件回调函数
     * @return array [满足条件的数组, 不满足条件的数组]
     */
    public function partition(callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($this->data as $key => $value) {
            if ($callback($value, $key)) {
                $passed[$key] = $value;
            } else {
                $failed[$key] = $value;
            }
        }

        return [new static($passed), new static($failed)];
    }

    /**
     * 对数组进行随机排序
     *
     * @return static 返回当前实例以支持链式操作
     */
    public function shuffle(): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        shuffle($this->data);
        return $this;
    }

    /**
     * 填充数组到指定长度
     *
     * @param int $size 目标大小
     * @param mixed $value 填充值
     * @return static 返回当前实例以支持链式操作
     */
    public function pad(int $size, mixed $value): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        $this->data = array_pad($this->data, $size, $value);
        return $this;
    }

    /**
     * 使用指定值填充数组
     *
     * @param mixed $value 填充值
     * @param int $count 填充数量
     * @return static 返回当前实例以支持链式操作
     */
    public function fill(mixed $value, int $count): static
    {
        if ($this->readOnly) {
            throw new RuntimeException('不能修改只读的DataArray');
        }

        $this->data = array_fill(0, $count, $value);
        return $this;
    }

    /**
     * 创建指定范围的数组
     *
     * @param mixed $start 起始值
     * @param mixed $end 结束值
     * @param int|float $step 步长
     * @return static 新的DataArray实例
     */
    public static function range(mixed $start, mixed $end, int|float $step = 1): static
    {
        if (is_numeric($start) && is_numeric($end) && is_numeric($step)) {
            $data = range($start, $end, $step);
        } else {
            $data = [];
            $current = $start;

            while ($current <= $end) {
                $data[] = $current;
                $current += $step;
            }
        }

        return new static($data);
    }

    /**
     * 使用键和值创建数组
     *
     * @param array $keys 键数组
     * @param array $values 值数组
     * @return static 新的DataArray实例
     */
    public static function combine(array $keys, array $values): static
    {
        $data = array_combine($keys, $values);
        return new static($data ?: []);
    }

    /**
     * 将数组转换为集合样式（返回新实例）
     *
     * @return static 新的DataArray实例
     */
    public function toCollection(): static
    {
        // 将关联数组转换为数值数组的集合
        $collection = [];
        foreach ($this->data as $key => $value) {
            if (is_array($value)) {
                $collection[] = new static($value);
            } else {
                $collection[] = $value;
            }
        }
        return new static($collection);
    }

    /**
     * 对数组进行笛卡尔积计算
     *
     * @param array ...$arrays 要计算笛卡尔积的数组
     * @return static 新的DataArray实例
     */
    public function cartesian(array ...$arrays): static
    {
        $input = array_merge([$this->data], $arrays);
        $result = [[]];

        foreach ($input as $arr) {
            $append = [];

            foreach ($result as $product) {
                foreach ($arr as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }

            $result = $append;
        }

        return new static($result);
    }

    /**
     * 对数组进行插值操作
     *
     * @param callable $callback 插值回调函数
     * @param int $step 插值步长
     * @return static 新的DataArray实例
     */
    public function interpolate(callable $callback, int $step = 1): static
    {
        $result = [];
        $count = count($this->data);

        for ($i = 0; $i < $count - 1; $i += $step) {
            $current = $this->data[$i];
            $next = $this->data[$i + 1] ?? $current;

            $result[] = $current;

            // 在当前位置和下一个位置之间插入值
            for ($j = 1; $j < $step; $j++) {
                $ratio = $j / $step;
                $interpolated = $callback($current, $next, $ratio);
                $result[] = $interpolated;
            }
        }

        // 添加最后一个元素
        if ($count > 0) {
            $result[] = $this->data[$count - 1];
        }

        return new static($result);
    }

    /**
     * 对数组进行窗口操作
     *
     * @param int $size 窗口大小
     * @param callable $callback 窗口回调函数
     * @return static 新的DataArray实例
     */
    public function window(int $size, callable $callback): static
    {
        $result = [];
        $count = count($this->data);

        for ($i = 0; $i <= $count - $size; $i++) {
            $window = array_slice($this->data, $i, $size);
            $result[] = $callback($window, $i);
        }

        return new static($result);
    }

    /**
     * 对数组进行滑动窗口操作
     *
     * @param int $size 窗口大小
     * @param int $step 滑动步长
     * @param callable $callback 窗口回调函数
     * @return static 新的DataArray实例
     */
    public function slidingWindow(int $size, int $step, callable $callback): static
    {
        $result = [];
        $count = count($this->data);

        for ($i = 0; $i <= $count - $size; $i += $step) {
            $window = array_slice($this->data, $i, $size);
            $result[] = $callback($window, $i);
        }

        return new static($result);
    }

    /**
     * 检查数组是否为列表（连续数字索引从0开始）
     *
     * @return bool
     */
    public function isList(): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($this->data);
        }

        $i = 0;
        foreach ($this->data as $k => $v) {
            if ($k !== $i++) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检查数组是否为关联数组
     *
     * @return bool
     */
    public function isAssoc(): bool
    {
        return !$this->isList();
    }

    /**
     * 使用递归迭代器遍历所有元素
     *
     * @return Generator
     */
    public function recursive(): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new \RecursiveArrayIterator($this->data),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * 获取所有叶子节点（没有子元素的元素）
     *
     * @return static
     */
    public function leaves(): static
    {
        $leaves = [];

        foreach ($this->recursive() as $key => $value) {
            if (!is_array($value) && !$value instanceof Traversable) {
                $leaves[$key] = $value;
            }
        }

        return new static($leaves);
    }

    /**
     * 实现 PHP 8.2 的随机获取键
     *
     * @return int|string|null
     */
    public function firstKey(): int|string|null
    {
        return array_key_first($this->data);
    }

    /**
     * 实现 PHP 8.2 的随机获取键
     *
     * @return int|string|null
     */
    public function lastKey(): int|string|null
    {
        return array_key_last($this->data);
    }

    /**
     * 获取所有值的类型统计
     *
     * @return static
     */
    public function typeFrequency(): static
    {
        $types = [];

        foreach ($this->data as $value) {
            $type = gettype($value);
            if (!isset($types[$type])) {
                $types[$type] = 0;
            }
            $types[$type]++;
        }

        return new static($types);
    }

    /**
     * 使用指定算法计算数组的哈希值
     *
     * @param string $algo 哈希算法
     * @return string
     */
    public function hash(string $algo = 'md5'): string
    {
        return hash($algo, serialize($this->data));
    }

    /**
     * 比较两个数组的差异
     *
     * @param array|self $array 要比较的数组
     * @return static 差异数组
     */
    public function diff(array|self $array): static
    {
        $compare = $array instanceof self ? $array->toArray() : $array;
        return new static(array_diff($this->data, $compare));
    }

    /**
     * 比较两个数组的键差异
     *
     * @param array|self $array 要比较的数组
     * @return static 键差异数组
     */
    public function diffKeys(array|self $array): static
    {
        $compare = $array instanceof self ? $array->toArray() : $array;
        return new static(array_diff_key($this->data, $compare));
    }

    /**
     * 比较两个数组的键值差异
     *
     * @param array|self $array 要比较的数组
     * @return static 键值差异数组
     */
    public function diffAssoc(array|self $array): static
    {
        $compare = $array instanceof self ? $array->toArray() : $array;
        return new static(array_diff_assoc($this->data, $compare));
    }

    /**
     * 获取两个数组的交集
     *
     * @param array|self $array 要比较的数组
     * @return static 交集数组
     */
    public function intersect(array|self $array): static
    {
        $compare = $array instanceof self ? $array->toArray() : $array;
        return new static(array_intersect($this->data, $compare));
    }

    /**
     * 获取两个数组的键交集
     *
     * @param array|self $array 要比较的数组
     * @return static 键交集数组
     */
    public function intersectKeys(array|self $array): static
    {
        $compare = $array instanceof self ? $array->toArray() : $array;
        return new static(array_intersect_key($this->data, $compare));
    }

    /**
     * 获取两个数组的键值交集
     *
     * @param array|self $array 要比较的数组
     * @return static 键值交集数组
     */
    public function intersectAssoc(array|self $array): static
    {
        $compare = $array instanceof self ? $array->toArray() : $array;
        return new static(array_intersect_assoc($this->data, $compare));
    }

    /**
     * 创建递归迭代器
     *
     * @return \RecursiveArrayIterator
     */
    public function getRecursiveIterator(): \RecursiveArrayIterator
    {
        return new \RecursiveArrayIterator($this->data);
    }

    /**
     * 创建可搜索的迭代器
     *
     * @return \ArrayIterator
     */
    public function getSeekableIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * 对数组进行多字段排序
     *
     * @param array $fields 排序字段和方向数组 ['field1' => 'asc', 'field2' => 'desc']
     * @return static 返回当前实例以支持链式操作
     */
    public function multiSort(array $fields): static
    {
        $params = [];
        foreach ($fields as $field => $direction) {
            $params[] = $this->column($field)->toArray();
            $params[] = $direction === 'desc' ? SORT_DESC : SORT_ASC;
        }
        $params[] = &$this->data;

        array_multisort(...$params);
        return $this;
    }

    /**
     * 对数组进行分组统计
     *
     * @param string|callable $groupField 分组字段或回调函数
     * @param string|callable $statField 统计字段或回调函数
     * @param string $statType 统计类型：count, sum, avg, max, min
     * @return static 新的DataArray实例
     */
    public function groupStat(
        string|callable $groupField,
        string|callable $statField,
        string $statType = 'count'
    ): static {
        $grouped = $this->groupBy($groupField);
        $result = [];

        foreach ($grouped->toArray() as $groupKey => $items) {
            $dataArray = new static($items);

            $result[$groupKey] = match($statType) {
                'count' => $dataArray->count(),
                'sum' => $dataArray->sum($statField),
                'avg' => $dataArray->avg($statField),
                'max' => $dataArray->max($statField),
                'min' => $dataArray->min($statField),
                default => $dataArray->count()
            };
        }

        return new static($result);
    }

    /**
     * 对数组进行分桶操作
     *
     * @param string|callable $field 分桶字段或回调函数
     * @param array $buckets 桶定义 [['min' => 0, 'max' => 10], ['min' => 11, 'max' => 20]]
     * @param string $defaultBucket 默认桶名称
     * @return static 新的DataArray实例
     */
    public function bucket(
        string|callable $field,
        array $buckets,
        string $defaultBucket = 'other'
    ): static {
        $result = [];

        foreach ($buckets as $bucketName => $range) {
            $result[$bucketName] = [];
        }
        $result[$defaultBucket] = [];

        foreach ($this->data as $item) {
            $value = is_callable($field) ? $field($item) : $this->getValueFromItem($item, $field);
            $placed = false;

            foreach ($buckets as $bucketName => $range) {
                if ($value >= $range['min'] && $value <= $range['max']) {
                    $result[$bucketName][] = $item;
                    $placed = true;
                    break;
                }
            }

            if (!$placed) {
                $result[$defaultBucket][] = $item;
            }
        }

        return new static($result);
    }

    /**
     * 对数组进行采样
     *
     * @param int $sampleSize 样本大小
     * @param bool $preserveKeys 是否保留键
     * @return static 新的DataArray实例
     */
    public function sample(int $sampleSize, bool $preserveKeys = false): static
    {
        if ($sampleSize >= count($this->data)) {
            return $this->copy();
        }

        $keys = array_rand($this->data, $sampleSize);
        $result = [];

        foreach ((array)$keys as $key) {
            if ($preserveKeys) {
                $result[$key] = $this->data[$key];
            } else {
                $result[] = $this->data[$key];
            }
        }

        return new static($result);
    }

    /**
     * 对数组进行加权随机选择
     *
     * @param string|callable $weightField 权重字段或回调函数
     * @param int $count 选择数量
     * @return static 新的DataArray实例
     */
    public function weightedRandom(string|callable $weightField, int $count = 1): static
    {
        $weights = [];
        $totalWeight = 0;

        foreach ($this->data as $key => $item) {
            $weight = is_callable($weightField) ? $weightField($item) : $this->getValueFromItem($item, $weightField);
            $weight = max(0, (float)$weight);
            $weights[$key] = $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return $this->sample($count);
        }

        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $random = mt_rand() / mt_getrandmax() * $totalWeight;
            $current = 0;

            foreach ($weights as $key => $weight) {
                $current += $weight;
                if ($random <= $current) {
                    $result[] = $this->data[$key];
                    break;
                }
            }
        }

        return new static($result);
    }

    /**
     * 对数组进行移动平均计算
     *
     * @param string|callable $field 计算字段或回调函数
     * @param int $window 窗口大小
     * @return static 新的DataArray实例
     */
    public function movingAverage(string|callable $field, int $window = 3): static
    {
        $values = [];
        foreach ($this->data as $item) {
            $value = is_callable($field) ? $field($item) : $this->getValueFromItem($item, $field);
            if (is_numeric($value)) {
                $values[] = (float)$value;
            }
        }

        $result = [];
        $count = count($values);

        for ($i = 0; $i <= $count - $window; $i++) {
            $windowValues = array_slice($values, $i, $window);
            $result[] = array_sum($windowValues) / $window;
        }

        return new static($result);
    }

    /**
     * 对数组进行累积计算
     *
     * @param string|callable $field 计算字段或回调函数
     * @param callable $callback 累积回调函数
     * @param mixed $initial 初始值
     * @return static 新的DataArray实例
     */
    public function cumulative(
        string|callable $field,
        callable $callback,
        mixed $initial = 0
    ): static {
        $values = [];
        foreach ($this->data as $item) {
            $value = is_callable($field) ? $field($item) : $this->getValueFromItem($item, $field);
            $values[] = $value;
        }

        $result = [];
        $accumulator = $initial;

        foreach ($values as $value) {
            $accumulator = $callback($accumulator, $value);
            $result[] = $accumulator;
        }

        return new static($result);
    }

    /**
     * 创建数据透视表
     *
     * @param string|callable $rowField 行字段
     * @param string|callable $columnField 列字段
     * @param string|callable $valueField 值字段
     * @param string $aggregate 聚合函数：count, sum, avg, max, min
     * @return static 新的DataArray实例
     */
    public function pivot(
        string|callable $rowField,
        string|callable $columnField,
        string|callable $valueField,
        string $aggregate = 'count'
    ): static {
        $rows = [];
        $columns = [];

        // 收集所有行和列的唯一值
        foreach ($this->data as $item) {
            $rowValue = is_callable($rowField) ? $rowField($item) : $this->getValueFromItem($item, $rowField);
            $colValue = is_callable($columnField) ? $columnField($item) : $this->getValueFromItem($item, $columnField);

            if (!in_array($rowValue, $rows, true)) {
                $rows[] = $rowValue;
            }
            if (!in_array($colValue, $columns, true)) {
                $columns[] = $colValue;
            }
        }

        // 初始化结果数组
        $result = [];
        foreach ($rows as $row) {
            $result[$row] = array_fill_keys($columns, 0);
        }

        // 填充数据
        foreach ($this->data as $item) {
            $rowValue = is_callable($rowField) ? $rowField($item) : $this->getValueFromItem($item, $rowField);
            $colValue = is_callable($columnField) ? $columnField($item) : $this->getValueFromItem($item, $columnField);
            $value = is_callable($valueField) ? $valueField($item) : $this->getValueFromItem($item, $valueField);

            if ($aggregate === 'count') {
                $result[$rowValue][$colValue]++;
            } else {
                $result[$rowValue][$colValue] += $value;
            }
        }

        // 如果是平均值，需要额外处理
        if ($aggregate === 'avg') {
            $counts = [];
            foreach ($rows as $row) {
                $counts[$row] = array_fill_keys($columns, 0);
            }

            foreach ($this->data as $item) {
                $rowValue = is_callable($rowField) ? $rowField($item) : $this->getValueFromItem($item, $rowField);
                $colValue = is_callable($columnField) ? $columnField($item) : $this->getValueFromItem($item, $columnField);
                $counts[$rowValue][$colValue]++;
            }

            foreach ($rows as $row) {
                foreach ($columns as $col) {
                    if ($counts[$row][$col] > 0) {
                        $result[$row][$col] /= $counts[$row][$col];
                    }
                }
            }
        }

        return new static($result);
    }

    /**
     * 对数组进行数据验证
     *
     * @param callable $validator 验证回调函数
     * @return array [有效数据, 无效数据]
     */
    public function validate(callable $validator): array
    {
        $valid = [];
        $invalid = [];

        foreach ($this->data as $key => $value) {
            if ($validator($value, $key)) {
                $valid[$key] = $value;
            } else {
                $invalid[$key] = $value;
            }
        }

        return [new static($valid), new static($invalid)];
    }

    /**
     * 对数组进行数据转换
     *
     * @param callable $transformer 转换回调函数
     * @return static 新的DataArray实例
     */
    public function transform(callable $transformer): static
    {
        $result = [];

        foreach ($this->data as $key => $value) {
            $result[$key] = $transformer($value, $key);
        }

        return new static($result);
    }

    /**
     * 创建数据管道
     *
     * @param callable ...$callbacks 回调函数序列
     * @return static 新的DataArray实例
     */
    public function pipeThrough(callable ...$callbacks): static
    {
        $result = $this;

        foreach ($callbacks as $callback) {
            $result = $callback($result);
        }

        return $result instanceof self ? $result : new static($result);
    }

    /**
     * 创建数据流处理
     *
     * @param array $operations 操作序列
     * @return static 新的DataArray实例
     */
    public function stream(array $operations): static
    {
        $result = $this;

        foreach ($operations as $operation) {
            if (is_callable($operation)) {
                $result = $operation($result);
            } elseif (is_array($operation) && isset($operation[0])) {
                $method = $operation[0];
                $args = $operation[1] ?? [];
                $result = $result->$method(...$args);
            }
        }

        return $result instanceof self ? $result : new static($result);
    }
}

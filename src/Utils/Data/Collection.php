<?php

namespace zxf\Utils\Data;

use zxf\Utils\Array\DataArray;

/**
 * 增强型集合操作类
 * 继承DataArray并提供更强大的集合操作功能
 * 支持链式操作、条件筛选、数据转换等高级功能
 *
 * @example
 * $collection = new Collection([['name' => 'John', 'age' => 25], ['name' => 'Jane', 'age' => 30]]);
 * $result = $collection->where('age', '>', 25)->pluck('name')->toArray(); // ['Jane']
 *
 * @see https://weisifang.com/docs/doc/6_203 DataArray使用文档
 */
class Collection extends DataArray
{
    /**
     * 创建一个新的Collection实例
     *
     * @param array $data 初始数据
     * @param bool $readOnly 是否只读
     * @return static
     */
    public static function make(array $data = [], bool $readOnly = false): static
    {
        return new static($data, $readOnly);
    }

    /**
     * 从迭代器创建Collection
     *
     * @param iterable $iterable 可迭代对象
     * @return static
     */
    public static function fromIterable(iterable $iterable): static
    {
        $data = [];
        foreach ($iterable as $key => $value) {
            $data[$key] = $value;
        }
        return new static($data);
    }

    /**
     * 创建指定数量的集合
     *
     * @param int $times 数量
     * @param callable|null $callback 回调函数
     * @return static
     */
    public static function times(int $times, callable $callback = null): static
    {
        if ($times < 1) {
            return new static([]);
        }

        $data = range(1, $times);

        if ($callback) {
            $data = array_map($callback, $data);
        }

        return new static($data);
    }

    /**
     * 从集合中提取字段值
     *
     * @param string|array $value 字段名
     * @param string|null $key 键字段名
     * @return static
     */
    public function pluck(string|array $value, string $key = null): static
    {
        $results = [];

        foreach ($this->data as $item) {
            $itemValue = $this->getValueFromItem($item, $value);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = $this->getValueFromItem($item, $key);
                $results[$itemKey] = $itemValue;
            }
        }

        return new static($results);
    }
}
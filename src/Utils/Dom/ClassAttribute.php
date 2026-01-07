<?php

declare(strict_types=1);

namespace zxf\Utils\Dom;

use InvalidArgumentException;

/**
 * 类属性管理类
 * 
 * 提供对元素 class 属性的便捷操作方法
 * 支持添加、删除、检查、切换类名等操作
 * 
 * 特性：
 * - 流畅的链式调用
 * - PHP 8.2+ 类型系统
 * - 只读属性访问
 * - 完整的类名操作 API
 * 
 * @example
 * $element = $document->first('div');
 * $element->classes()->add('active');
 * $element->classes()->remove('inactive');
 * if ($element->classes()->contains('active')) {
 *     echo '元素包含 active 类';
 * }
 * 
 * @package zxf\Utils\Dom
 */
class ClassAttribute
{
    /**
     * 关联的元素对象
     */
    protected Element $element;

    /**
     * 构造函数
     * 
     * @param  Element  $element  元素对象
     */
    public function __construct(Element $element)
    {
        $this->element = $element;
    }

    /**
     * 获取所有类名
     *
     * @return array<int, string> 类名数组
     */
    public function all(): array
    {
        $classString = $this->element->getAttribute('class') ?? '';

        if (trim($classString) === '') {
            return [];
        }

        return array_values(array_filter(
            array_unique(explode(' ', trim($classString))),
            fn($class) => trim($class) !== ''
        ));
    }

    /**
     * 获取所有类名（别名方法）
     *
     * @return array<int, string> 类名数组
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * 添加类名
     *
     * @param  string  ...$classNames  类名列表
     * @return self
     */
    public function add(string ...$classNames): self
    {
        $currentClasses = $this->all();

        foreach ($classNames as $className) {
            $className = trim($className);
            if ($className !== '' && ! in_array($className, $currentClasses, true)) {
                $currentClasses[] = $className;
            }
        }

        $this->element->setAttribute('class', implode(' ', $currentClasses));

        return $this;
    }

    /**
     * 添加多个类名（数组形式）
     *
     * @param  array<int, string>  $classNames  类名数组
     * @return self
     */
    public function addMultiple(array $classNames): self
    {
        return $this->add(...$classNames);
    }

    /**
     * 移除类名
     *
     * @param  string  ...$classNames  类名列表
     * @return self
     */
    public function remove(string ...$classNames): self
    {
        $currentClasses = $this->all();

        foreach ($classNames as $className) {
            $key = array_search(trim($className), $currentClasses, true);
            if ($key !== false) {
                unset($currentClasses[$key]);
            }
        }

        $this->element->setAttribute('class', implode(' ', array_values($currentClasses)));

        return $this;
    }

    /**
     * 移除多个类名（数组形式）
     *
     * @param  array<int, string>  $classNames  类名数组
     * @return self
     */
    public function removeMultiple(array $classNames): self
    {
        return $this->remove(...$classNames);
    }

    /**
     * 切换类名
     * 
     * @param  string  $className  类名
     * @return self
     */
    public function toggle(string $className): self
    {
        $className = trim($className);
        $currentClasses = $this->all();
        $key = array_search($className, $currentClasses, true);

        if ($key !== false) {
            unset($currentClasses[$key]);
        } else {
            $currentClasses[] = $className;
        }

        $this->element->setAttribute('class', implode(' ', array_values($currentClasses)));

        return $this;
    }

    /**
     * 检查是否包含指定类名
     * 
     * @param  string  $className  类名
     * @return bool
     */
    public function contains(string $className): bool
    {
        return in_array(trim($className), $this->all(), true);
    }

    /**
     * 设置类名（替换所有）
     * 
     * @param  string  ...$classNames  新的类名列表
     * @return self
     */
    public function set(string ...$classNames): self
    {
        $classNames = array_values(array_filter(
            array_unique(array_map('trim', $classNames)),
            fn($class) => $class !== ''
        ));

        $this->element->setAttribute('class', implode(' ', $classNames));

        return $this;
    }

    /**
     * 清空所有类名
     * 
     * @return self
     */
    public function clear(): self
    {
        $this->element->removeAttribute('class');
        return $this;
    }

    /**
     * 检查是否有任何类名
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this->all()) === 0;
    }

    /**
     * 获取类名数量
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * 替换类名
     * 
     * @param  string  $oldClass  旧类名
     * @param  string  $newClass  新类名
     * @return self
     */
    public function replace(string $oldClass, string $newClass): self
    {
        $this->remove($oldClass);
        $this->add($newClass);
        return $this;
    }

    /**
     * 检查是否包含所有指定类名
     * 
     * @param  string  ...$classNames  类名列表
     * @return bool
     */
    public function containsAll(string ...$classNames): bool
    {
        foreach ($classNames as $className) {
            if (! $this->contains($className)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检查是否包含任意指定类名
     * 
     * @param  string  ...$classNames  类名列表
     * @return bool
     */
    public function containsAny(string ...$classNames): bool
    {
        foreach ($classNames as $className) {
            if ($this->contains($className)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 魔术方法：获取类名
     * 
     * @param  string  $name  属性名（应为 'all'）
     * @return array<int, string>|null
     */
    public function __get(string $name): ?array
    {
        return match ($name) {
            'all' => $this->all(),
            default => null,
        };
    }

    /**
     * 魔术方法：转换为字符串
     * 
     * @return string
     */
    public function __toString(): string
    {
        return implode(' ', $this->all());
    }

    /**
     * 可数接口：获取类名数量
     * 
     * @return int
     */
    public function __invoke(): int
    {
        return $this->count();
    }
}

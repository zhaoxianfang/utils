<?php

declare(strict_types=1);

namespace zxf\Utils\Dom\Attributes;

use zxf\Utils\Dom\Element;

/**
 * 样式属性管理类
 * 
 * 提供对元素 style 属性的便捷操作方法
 * 支持设置、获取、删除样式等操作
 * 
 * 特性：
 * - 流畅的链式调用
 * - PHP 8.2+ 类型系统
 * - 完整的 CSS 样式操作 API
 * 
 * @example
 * $element = $document->first('div');
 * $element->style()->set('color', 'red');
 * $element->style()->set('background-color', '#fff');
 * $color = $element->style()->get('color');
 * 
 * @package zxf\Utils\Dom
 */
class StyleAttribute
{
    /**
     * 关联的元素对象
     */
    protected Element $element;

    /**
     * 样式属性名到 CSS 属性名的映射（驼峰命名转短横线命名）
     * 
     * @var array<string, string>
     */
    protected array $propertyMap = [
        'backgroundColor' => 'background-color',
        'borderColor' => 'border-color',
        'borderRadius' => 'border-radius',
        'borderWidth' => 'border-width',
        'boxShadow' => 'box-shadow',
        'fontSize' => 'font-size',
        'fontFamily' => 'font-family',
        'fontWeight' => 'font-weight',
        'letterSpacing' => 'letter-spacing',
        'lineHeight' => 'line-height',
        'marginLeft' => 'margin-left',
        'marginRight' => 'margin-right',
        'marginTop' => 'margin-top',
        'marginBottom' => 'margin-bottom',
        'paddingLeft' => 'padding-left',
        'paddingRight' => 'padding-right',
        'paddingTop' => 'padding-top',
        'paddingBottom' => 'padding-bottom',
        'textAlign' => 'text-align',
        'textDecoration' => 'text-decoration',
        'textTransform' => 'text-transform',
        'whiteSpace' => 'white-space',
        'zIndex' => 'z-index',
        'overflowX' => 'overflow-x',
        'overflowY' => 'overflow-y',
        'minWidth' => 'min-width',
        'minHeight' => 'min-height',
        'maxWidth' => 'max-width',
        'maxHeight' => 'max-height',
    ];

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
     * 获取所有样式
     * 
     * @return array<string, string> 样式数组
     */
    public function all(): array
    {
        $styleString = $this->element->getAttribute('style') ?? '';
        
        if (trim($styleString) === '') {
            return [];
        }

        $styles = [];
        foreach (explode(';', trim($styleString)) as $rule) {
            $rule = trim($rule);
            if ($rule !== '') {
                [$property, $value] = array_map('trim', explode(':', $rule, 2));
                if (isset($value)) {
                    $styles[$property] = $value;
                }
            }
        }

        return $styles;
    }

    /**
     * 设置样式
     *
     * @param  string|array<string, string|null>  $name  样式名或样式数组
     * @param  string|null  $value  样式值（当 $name 为字符串时使用）
     * @return self
     */
    public function set(string|array $name, ?string $value = null): self
    {
        if (is_array($name)) {
            foreach ($name as $key => $val) {
                $this->set($key, $val);
            }
            return $this;
        }

        $property = $this->normalizePropertyName($name);
        $currentStyles = $this->all();

        if ($value === null) {
            unset($currentStyles[$property]);
        } else {
            $currentStyles[$property] = $value;
        }

        $this->element->setAttribute('style', $this->stylesToString($currentStyles));

        return $this;
    }

    /**
     * 批量设置样式（别名方法）
     *
     * @param  array<string, string|null>  $styles  样式数组
     * @return self
     */
    public function setMultiple(array $styles): self
    {
        return $this->set($styles);
    }

    /**
     * 获取所有样式（别名方法）
     *
     * @return array<string, string> 样式数组
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * 获取样式值
     * 
     * @param  string  $name  样式名
     * @return string|null
     */
    public function get(string $name): ?string
    {
        $property = $this->normalizePropertyName($name);
        $styles = $this->all();
        return $styles[$property] ?? null;
    }

    /**
     * 移除样式
     * 
     * @param  string  ...$names  样式名列表
     * @return self
     */
    public function remove(string ...$names): self
    {
        foreach ($names as $name) {
            $property = $this->normalizePropertyName($name);
            $styles = $this->all();
            unset($styles[$property]);
            $this->element->setAttribute('style', $this->stylesToString($styles));
        }

        return $this;
    }

    /**
     * 清空所有样式
     * 
     * @return self
     */
    public function clear(): self
    {
        $this->element->removeAttribute('style');
        return $this;
    }

    /**
     * 检查是否存在指定样式
     * 
     * @param  string  $name  样式名
     * @return bool
     */
    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /**
     * 切换样式
     * 
     * @param  string  $name  样式名
     * @param  string|null  $value1  第一个样式值
     * @param  string|null  $value2  第二个样式值
     * @return self
     */
    public function toggle(string $name, ?string $value1 = null, ?string $value2 = null): self
    {
        $currentValue = $this->get($name);
        
        if ($currentValue === null) {
            if ($value1 !== null) {
                $this->set($name, $value1);
            }
        } elseif ($value2 !== null && $currentValue === $value1) {
            $this->set($name, $value2);
        } elseif ($value1 !== null) {
            $this->set($name, $value1);
        } else {
            $this->remove($name);
        }

        return $this;
    }

    /**
     * 获取样式数量
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * 检查是否有任何样式
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * 增加样式值
     * 
     * @param  string  $name  样式名
     * @param  string  $value  要增加的值
     * @return self
     */
    public function add(string $name, string $value): self
    {
        $currentValue = $this->get($name) ?? '';
        $newValue = trim($currentValue . ' ' . $value);
        $this->set($name, $newValue);
        return $this;
    }

    /**
     * 减少样式值
     * 
     * @param  string  $name  样式名
     * @param  string  $value  要减少的值
     * @return self
     */
    public function subtract(string $name, string $value): self
    {
        $currentValue = $this->get($name) ?? '';
        $newValue = str_replace($value, '', $currentValue);
        $newValue = preg_replace('/\s+/', ' ', trim($newValue));
        $this->set($name, $newValue);
        return $this;
    }

    /**
     * 标准化样式属性名
     * 
     * @param  string  $name  样式名
     * @return string 标准化后的样式名
     */
    protected function normalizePropertyName(string $name): string
    {
        // 检查是否是驼峰命名
        if (preg_match('/[A-Z]/', $name)) {
            return $this->propertyMap[$name] ?? $name;
        }

        return $name;
    }

    /**
     * 将样式数组转换为字符串
     * 
     * @param  array<string, string>  $styles  样式数组
     * @return string
     */
    protected function stylesToString(array $styles): string
    {
        $parts = [];
        foreach ($styles as $property => $value) {
            $parts[] = sprintf('%s: %s', $property, $value);
        }
        return implode('; ', $parts);
    }

    /**
     * 魔术方法：获取样式
     * 
     * @param  string  $name  样式名
     * @return string|null
     */
    public function __get(string $name): ?string
    {
        return $this->get($name);
    }

    /**
     * 魔术方法：设置样式
     * 
     * @param  string  $name  样式名
     * @param  string|null  $value  样式值
     * @return void
     */
    public function __set(string $name, ?string $value): void
    {
        $this->set($name, $value);
    }

    /**
     * 魔术方法：检查样式是否存在
     * 
     * @param  string  $name  样式名
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * 魔术方法：删除样式
     * 
     * @param  string  $name  样式名
     * @return void
     */
    public function __unset(string $name): void
    {
        $this->remove($name);
    }

    /**
     * 魔术方法：转换为字符串
     * 
     * @return string
     */
    public function __toString(): string
    {
        $styles = $this->all();
        return $this->stylesToString($styles);
    }

    /**
     * 可数接口：获取样式数量
     * 
     * @return int
     */
    public function __invoke(): int
    {
        return $this->count();
    }
}

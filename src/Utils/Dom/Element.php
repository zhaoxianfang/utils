<?php

declare(strict_types=1);

namespace zxf\Utils\Dom;

use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use zxf\Utils\Dom\Exceptions\InvalidSelectorException;
use zxf\Utils\Dom\ClassAttribute;
use zxf\Utils\Dom\StyleAttribute;

class Element extends Node
{
    /**
     * 类属性管理对象
     * 
     * @var ClassAttribute|null
     */
    protected ?ClassAttribute $classAttribute = null;

    /**
     * 样式属性管理对象
     * 
     * @var StyleAttribute|null
     */
    protected ?StyleAttribute $styleAttribute = null;

    /**
     * @param  DOMElement|DOMText|DOMComment|DOMCdataSection|string  $tagName  The tag name of an element
     * @param  string|int|float|null  $value  The value of an element
     * @param  array  $attributes  The attributes of an element
     */
    public function __construct($tagName, $value = null, array $attributes = [])
    {
        if (is_string($tagName)) {
            $document = new DOMDocument('1.0', 'UTF-8');

            $node = $document->createElement($tagName);

            $this->setNode($node);
        } else {
            $this->setNode($tagName);
        }

        if ($value !== null) {
            $this->setValue($value);
        }

        foreach ($attributes as $attrName => $attrValue) {
            $this->setAttribute($attrName, $attrValue);
        }
    }

    /**
     * Creates a new element.
     *
     * @param  DOMNode|string  $name  The tag name of an element
     * @param  string|int|float|null  $value  The value of an element
     * @param  array  $attributes  The attributes of an element
     */
    public static function create($name, $value = null, array $attributes = []): self
    {
        return new Element($name, $value, $attributes);
    }

    /**
     * Creates a new element node by CSS selector.
     *
     *
     *
     * @throws InvalidSelectorException
     */
    public static function createBySelector(string $selector, ?string $value = null, array $attributes = []): self
    {
        return Document::create()->createElementBySelector($selector, $value, $attributes);
    }

    public function tagName(): string
    {
        return $this->node->tagName;
    }

    /**
     * Checks that the node matches selector.
     *
     * @param  string  $selector  CSS selector
     * @param  string|bool  $typeOrStrict  选择器类型或严格模式标志
     *
     * @throws InvalidSelectorException if the selector is invalid
     * @throws InvalidArgumentException if the tag name is not a string
     * @throws RuntimeException if the tag name is not specified in strict mode
     */
    public function matches(string $selector, string|bool $typeOrStrict = false): bool
    {
        // 兼容旧版 API：如果第二个参数是布尔值，则视为 strict 模式
        $type = Query::TYPE_CSS;
        $strict = false;
        
        if (is_bool($typeOrStrict)) {
            $strict = $typeOrStrict;
        } else {
            $type = $typeOrStrict;
        }
        if (! $this->node instanceof DOMElement) {
            return false;
        }

        if ($selector === '*') {
            return true;
        }

        if (! $strict) {
            $innerHtml = $this->html();
            $html = "<root>$innerHtml</root>";

            $selector = 'root > '.trim($selector);

            $document = new Document;

            $document->loadHtml($html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);

            return $document->has($selector);
        }

        $segments = Query::parseSelector($selector);
        $segment = $segments[0] ?? [];

        if (! array_key_exists('tag', $segment)) {
            throw new RuntimeException(sprintf('Tag name must be specified in %s', $selector));
        }

        if ($segment['tag'] !== $this->tagName() && $segment['tag'] !== '*') {
            return false;
        }

        $segmentId = $segment['id'] ?? null;

        if ($segmentId !== $this->getAttribute('id')) {
            return false;
        }

        $classes = $this->hasAttribute('class') ? explode(' ', trim($this->getAttribute('class'))) : [];

        $segmentClasses = $segment['classes'] ?? [];

        $diff1 = array_diff($segmentClasses, $classes);
        $diff2 = array_diff($classes, $segmentClasses);

        if (count($diff1) > 0 || count($diff2) > 0) {
            return false;
        }

        $attributes = $this->attributes();

        unset($attributes['id'], $attributes['class']);

        $segmentAttrs = $segment['attributes'] ?? [];

        $diff1 = array_diff_assoc($segments['attributes'], $attributes);
        $diff2 = array_diff_assoc($attributes, $segments['attributes']);

        // if the attributes are not equal
        if (count($diff1) > 0 || count($diff2) > 0) {
            return false;
        }

        return true;
    }

    /**
     * 获取子元素
     * 
     * @return array<int, Element> 子元素数组
     */
    public function children(): array
    {
        $result = [];
        $childNodes = $this->node->childNodes;
        
        foreach ($childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $result[] = new Element($childNode);
            }
        }
        
        return $result;
    }

    /**
     * 获取父元素
     *
     * @return Element|null 父元素，如果没有则返回 null
     */
    public function parent(): ?Element
    {
        $parentNode = $this->node->parentNode;

        if ($parentNode === null || !($parentNode instanceof DOMElement)) {
            return null;
        }

        return new Element($parentNode);
    }

    /**
     * 获取所属文档
     *
     * @return Document|null 文档对象
     */
    public function ownerDocument(): ?Document
    {
        $domDocument = $this->node->ownerDocument;
        if ($domDocument === null) {
            return null;
        }

        return Document::getFromDomDocument($domDocument);
    }

    /**
     * 获取第一个子元素
     *
     * @return Element|null 第一个子元素
     */
    public function firstChild(): ?Element
    {
        $firstChild = $this->node->firstChild;
        while ($firstChild !== null && !($firstChild instanceof DOMElement)) {
            $firstChild = $firstChild->nextSibling;
        }

        return $firstChild !== null ? new Element($firstChild) : null;
    }

    /**
     * 获取最后一个子元素
     *
     * @return Element|null 最后一个子元素
     */
    public function lastChild(): ?Element
    {
        $lastChild = $this->node->lastChild;
        while ($lastChild !== null && !($lastChild instanceof DOMElement)) {
            $lastChild = $lastChild->previousSibling;
        }

        return $lastChild !== null ? new Element($lastChild) : null;
    }

    /**
     * 获取下一个兄弟元素
     *
     * @return Element|null 下一个兄弟元素
     */
    public function nextSibling(): ?Element
    {
        $sibling = $this->node->nextSibling;
        while ($sibling !== null && !($sibling instanceof DOMElement)) {
            $sibling = $sibling->nextSibling;
        }

        return $sibling !== null ? new Element($sibling) : null;
    }

    /**
     * 获取前一个兄弟元素
     *
     * @return Element|null 前一个兄弟元素
     */
    public function previousSibling(): ?Element
    {
        $sibling = $this->node->previousSibling;
        while ($sibling !== null && !($sibling instanceof DOMElement)) {
            $sibling = $sibling->previousSibling;
        }

        return $sibling !== null ? new Element($sibling) : null;
    }

    /**
     * 获取所有兄弟元素
     *
     * @return array<int, Element> 兄弟元素数组
     */
    public function siblings(): array
    {
        $result = [];
        $sibling = $this->node->previousSibling;

        // 向前查找
        while ($sibling !== null) {
            if ($sibling instanceof DOMElement) {
                array_unshift($result, new Element($sibling));
            }
            $sibling = $sibling->previousSibling;
        }

        // 向后查找
        $sibling = $this->node->nextSibling;
        while ($sibling !== null) {
            if ($sibling instanceof DOMElement) {
                $result[] = new Element($sibling);
            }
            $sibling = $sibling->nextSibling;
        }

        return $result;
    }

    /**
     * Determine if an attribute exists on the element.
     *
     * @param  string  $name  The name of an attribute
     */
    public function hasAttribute(string $name): bool
    {
        return $this->node->hasAttribute($name);
    }

    /**
     * Set an attribute on the element.
     *
     * @param  string  $name  The name of an attribute
     * @param  string|int|float  $value  The value of an attribute
     */
    public function setAttribute(string $name, $value): Element
    {
        if (is_numeric($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('%s expects parameter 2 to be string or null, %s given.', __METHOD__, (is_object($value) ? get_class($value) : gettype($value))));
        }

        $this->node->setAttribute($name, $value);

        return $this;
    }

    /**
     * Access to the element's attributes.
     *
     * @param  string  $name  The name of an attribute
     * @param  string|null  $default  The value returned if the attribute doesn't exist
     * @return string|null The value of an attribute or null if attribute doesn't exist
     */
    public function getAttribute(string $name, ?string $default = null): ?string
    {
        if ($this->hasAttribute($name)) {
            return $this->node->getAttribute($name);
        }

        return $default;
    }

    /**
     * Unset an attribute on the element.
     *
     * @param  string  $name  The name of an attribute
     */
    public function removeAttribute(string $name): self
    {
        $this->node->removeAttribute($name);

        return $this;
    }

    /**
     * 移除属性（别名方法）
     *
     * @param  string  $name  属性名
     * @return self
     */
    public function removeAttr(string $name): self
    {
        return $this->removeAttribute($name);
    }

    /**
     * Unset all attributes of the element.
     *
     * @param  string[]  $preserved
     */
    public function removeAllAttributes(array $preserved = []): self
    {
        if (! $this->node instanceof DOMElement) {
            return $this;
        }

        foreach ($this->attributes() as $name => $value) {
            if (in_array($name, $preserved, true)) {
                continue;
            }

            $this->node->removeAttribute($name);
        }

        return $this;
    }

    /**
     * Alias for getAttribute and setAttribute methods.
     *
     * @param  string  $name  The name of an attribute
     * @param  string|null  $value  The value that will be returned an attribute doesn't exist
     * @return string|null|Element
     */
    public function attr(string $name, ?string $value = null)
    {
        if ($value === null) {
            return $this->getAttribute($name);
        }

        return $this->setAttribute($name, $value);
    }

    /**
     * Returns the node attributes or null, if it is not DOMElement.
     *
     * @param  string[]  $names
     */
    public function attributes(?array $names = null): ?array
    {
        if (! $this->node instanceof DOMElement) {
            return null;
        }

        if ($names === null) {
            $result = [];

            foreach ($this->node->attributes as $name => $attribute) {
                $result[$name] = $attribute->value;
            }

            return $result;
        }

        $result = [];

        foreach ($this->node->attributes as $name => $attribute) {
            if (in_array($name, $names, true)) {
                $result[$name] = $attribute->value;
            }
        }

        return $result;
    }

    /**
     * 获取类属性管理对象
     * 
     * 提供便捷的类名操作方法
     * 
     * @return ClassAttribute 类属性管理对象
     * 
     * @throws LogicException 当节点不是元素节点时抛出异常
     */
    public function classes(): ClassAttribute
    {
        if ($this->classAttribute !== null) {
            return $this->classAttribute;
        }

        if (! $this->isElementNode()) {
            throw new LogicException('类属性仅适用于元素节点。');
        }

        $this->classAttribute = new ClassAttribute($this);

        return $this->classAttribute;
    }

    /**
     * 获取样式属性管理对象
     *
     * 提供便捷的样式操作方法
     *
     * @return StyleAttribute 样式属性管理对象
     *
     * @throws LogicException 当节点不是元素节点时抛出异常
     */
    public function style(): StyleAttribute
    {
        if ($this->styleAttribute !== null) {
            return $this->styleAttribute;
        }

        if (! $this->isElementNode()) {
            throw new LogicException('样式属性仅适用于元素节点。');
        }

        $this->styleAttribute = new StyleAttribute($this);

        return $this->styleAttribute;
    }

    /**
     * 设置样式（便捷方法）
     *
     * @param  string  $name  样式名
     * @param  string|null  $value  样式值
     * @return self
     */
    public function css(string $name, ?string $value = null): self|string|null
    {
        if ($value === null) {
            // 获取样式值
            return $this->style()->get($name);
        }
        // 设置样式值
        $this->style()->set($name, $value);
        return $this;
    }

    /**
     * 获取或设置HTML内容（便捷方法）
     *
     * @param  string|null  $html  HTML内容，如果为null则获取
     * @return self|string  设置时返回self，获取时返回HTML字符串
     */
    public function setHtml(?string $html = null): self|string
    {
        if ($html === null) {
            // 获取HTML内容
            return $this->html();
        }
        // 设置HTML内容
        $this->setInnerHtml($html);
        return $this;
    }

    /**
     * 获取类属性管理对象（别名方法）
     *
     * @return ClassAttribute 类属性管理对象
     */
    public function class(): ClassAttribute
    {
        return $this->classes();
    }

    /**
     * 添加类名（便捷方法）
     *
     * @param  string  ...$classNames  类名列表
     * @return self
     */
    public function addClass(string ...$classNames): self
    {
        $this->classes()->add(...$classNames);
        return $this;
    }

    /**
     * 移除类名（便捷方法）
     *
     * @param  string  ...$classNames  类名列表
     * @return self
     */
    public function removeClass(string ...$classNames): self
    {
        $this->classes()->remove(...$classNames);
        return $this;
    }

    /**
     * 切换类名（便捷方法）
     *
     * @param  string  $className  类名
     * @return self
     */
    public function toggleClass(string $className): self
    {
        $this->classes()->toggle($className);
        return $this;
    }

    /**
     * 检查类是否存在（便捷方法）
     *
     * @param  string  $className  类名
     * @return bool
     */
    public function hasClass(string $className): bool
    {
        return $this->classes()->contains($className);
    }

    /**
     * 获取元素ID
     *
     * @return string|null
     */
    public function id(): ?string
    {
        return $this->getAttribute('id');
    }

    /**
     * 设置元素ID
     *
     * @param  string  $id  ID值
     * @return self
     */
    public function setId(string $id): self
    {
        return $this->setAttribute('id', $id);
    }

    /**
     * 查找后代元素
     *
     * @param  string  $selector  选择器
     * @param  string  $type  选择器类型
     * @return array<int, Element> 匹配的元素数组
     */
    public function find(string $selector, string $type = Query::TYPE_CSS): array
    {
        $document = $this->ownerDocument();
        if ($document === null) {
            return [];
        }

        return $document->find($selector, $type, $this->node);
    }

    /**
     * 查找第一个匹配的后代元素
     *
     * @param  string  $selector  选择器
     * @param  string  $type  选择器类型
     * @return Element|null 第一个匹配的元素
     */
    public function first(string $selector, string $type = Query::TYPE_CSS): ?Element
    {
        $document = $this->ownerDocument();
        if ($document === null) {
            return null;
        }

        return $document->first($selector, $type, $this->node);
    }

    /**
     * 使用 XPath 查找后代元素
     *
     * @param  string  $xpathExpression  XPath 表达式
     * @return array<int, Element> 匹配的元素数组
     */
    public function xpath(string $xpathExpression): array
    {
        $document = $this->ownerDocument();
        if ($document === null) {
            return [];
        }

        // 直接调用Document的xpath方法
        return $document->xpath($xpathExpression);
        // 注意：这将返回整个文档的结果，不是相对于当前元素
        // 如果需要相对查找，需要在XPath中使用 . 开头的表达式
    }

    /**
     * 使用正则表达式查找后代元素
     *
     * @param  string  $pattern  正则表达式模式
     * @param  string|null  $attribute  要匹配的属性名（如果提供则匹配属性值）
     * @return array<int, Element> 匹配的元素数组
     */
    public function regex(string $pattern, ?string $attribute = null): array
    {
        $document = $this->ownerDocument();
        if ($document === null) {
            return [];
        }

        return $document->findByRegex($pattern, $this->node, $attribute);
    }

    /**
     * 查找包含指定文本的后代元素
     *
     * @param  string  $text  要查找的文本
     * @param  string  $selector  CSS选择器（可选）
     * @return array<int, Element> 匹配的元素数组
     */
    public function findByText(string $text, string $selector = '*'): array
    {
        $elements = $this->find($selector);
        $result = [];
        
        foreach ($elements as $element) {
            if ($element instanceof Element && str_contains($element->text(), $text)) {
                $result[] = $element;
            }
        }
        
        return $result;
    }

    /**
     * 查找包含指定文本的后代元素（不区分大小写）
     *
     * @param  string  $text  要查找的文本
     * @param  string  $selector  CSS选择器（可选）
     * @return array<int, Element> 匹配的元素数组
     */
    public function findByTextIgnoreCase(string $text, string $selector = '*'): array
    {
        $elements = $this->find($selector);
        $result = [];
        $lowerText = strtolower($text);
        
        foreach ($elements as $element) {
            if (is_string($element)) {
                continue;
            }
            if ($element instanceof Element && str_contains(strtolower($element->text()), $lowerText)) {
                $result[] = $element;
            }
        }
        
        return $result;
    }

    /**
     * 查找具有指定属性的后代元素
     *
     * @param  string  $attribute  属性名
     * @param  string|null  $value  属性值（如果为null则只检查属性存在）
     * @param  string  $selector  CSS选择器（可选）
     * @return array<int, Element> 匹配的元素数组
     */
    public function findByAttribute(string $attribute, ?string $value = null, string $selector = '*'): array
    {
        $elements = $this->find($selector);
        $result = [];
        
        foreach ($elements as $element) {
            if (!($element instanceof Element)) {
                continue;
            }
            
            if ($value === null) {
                if ($element->hasAttribute($attribute)) {
                    $result[] = $element;
                }
            } else {
                if ($element->getAttribute($attribute) === $value) {
                    $result[] = $element;
                }
            }
        }
        
        return $result;
    }

    /**
     * 查找直接子元素
     *
     * @param  string  $selector  CSS选择器
     * @return array<int, Element> 匹配的子元素数组
     */
    public function findChildren(string $selector = '*'): array
    {
        $children = $this->children();
        $result = [];
        
        foreach ($children as $child) {
            if ($child instanceof Element && $child->matches($selector)) {
                $result[] = $child;
            }
        }
        
        return $result;
    }

    /**
     * 查找第一个直接子元素
     *
     * @param  string  $selector  CSS选择器
     * @return Element|null 第一个匹配的子元素
     */
    public function findFirstChild(string $selector = '*'): ?Element
    {
        $children = $this->findChildren($selector);
        return $children[0] ?? null;
    }

    /**
     * 移除样式
     *
     * @param  string  ...$names  样式名列表
     * @return StyleAttribute
     */
    public function removeStyle(string ...$names): StyleAttribute
    {
        return $this->style()->remove(...$names);
    }

    /**
     * 检查样式是否存在
     *
     * @param  string  $name  样式名
     * @return bool
     */
    public function hasStyle(string $name): bool
    {
        return $this->style()->has($name);
    }


    /**
     * Dynamically set an attribute on the element.
     *
     * @param  string  $name  The name of an attribute
     * @param  string|int|float  $value  The value of an attribute
     * @return Element
     */
    public function __set(string $name, $value)
    {
        return $this->setAttribute($name, $value);
    }

    /**
     * Dynamically access the element's attributes.
     *
     * @param  string  $name  The name of an attribute
     */
    public function __get(string $name): ?string
    {
        return $this->getAttribute($name);
    }

    /**
     * Determine if an attribute exists on the element.
     *
     * @param  string  $name  The attribute name
     */
    public function __isset(string $name): bool
    {
        return $this->hasAttribute($name);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $name  The name of an attribute
     */
    public function __unset(string $name)
    {
        $this->removeAttribute($name);
    }
}

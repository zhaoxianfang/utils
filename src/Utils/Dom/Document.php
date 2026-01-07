<?php

declare(strict_types=1);

namespace zxf\Utils\Dom;

use DOMDocument;
use DOMDocumentFragment;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;
use RuntimeException;
use zxf\Utils\Dom\Exceptions\InvalidSelectorException;

/**
 * DOM 文档类
 * 
 * 提供对 HTML/XML 文档的便捷操作方法
 * 支持创建文档、查找元素、修改内容等
 * 
 * 特性：
 * - 强大的 CSS 和 XPath 选择器支持
 * - 流畅的链式调用
 * - PHP 8.2+ 类型系统
 * - 完整的 DOM 操作 API
 * - 支持伪元素选择器（::text, ::attr）
 * 
 * @example
 * $doc = new Document('<div class="item">Hello</div>');
 * $elements = $doc->find('.item');
 * $element = $doc->first('div');
 * $text = $doc->text('.item::text');
 * 
 * @package zxf\Utils\Dom
 */
class Document
{
    /**
     * HTML 文档类型
     */
    public const TYPE_HTML = 'html';

    /**
     * XML 文档类型
     */
    public const TYPE_XML = 'xml';

    /**
     * DOM 文档对象
     */
    protected DOMDocument $document;

    /**
     * 文档编码
     */
    protected string $encoding = 'UTF-8';

    /**
     * 文档类型
     */
    protected string $type = self::TYPE_HTML;

    /**
     * 文档实例映射（用于从 DOMDocument 获取 Document）
     * 
     * @var array<int, self>
     */
    protected static array $instances = [];

    /**
     * 构造函数
     * 
     * @param  DOMDocument|string|null  $string  DOM 文档对象或 HTML/XML 字符串
     * @param  bool  $isFile  是否从文件加载
     * @param  string  $encoding  文档编码
     * @param  string  $type  文档类型（HTML 或 XML）
     */
    public function __construct(
        DOMDocument|string|null $string = null,
        bool $isFile = false,
        string $encoding = 'UTF-8',
        string $type = self::TYPE_HTML
    ) {
        $this->encoding = $encoding;
        $this->type = strtolower($type);

        if ($string instanceof DOMDocument) {
            $this->document = $string;
        } else {
            $this->document = new DOMDocument('1.0', $this->encoding);
            $this->document->preserveWhiteSpace = false;

            if ($string !== null) {
                $this->load($string, $isFile);
            }
        }

        // 注册实例
        spl_object_hash($this->document);
        self::$instances[spl_object_hash($this->document)] = $this;
    }

    /**
     * 创建新文档实例
     * 
     * @param  DOMDocument|string|null  $string  DOM 文档对象或 HTML/XML 字符串
     * @param  bool  $isFile  是否从文件加载
     * @param  string  $encoding  文档编码
     * @param  string  $type  文档类型（HTML 或 XML）
     * @return self
     */
    public static function create(
        DOMDocument|string|null $string = null,
        bool $isFile = false,
        string $encoding = 'UTF-8',
        string $type = self::TYPE_HTML
    ): self {
        return new self($string, $isFile, $encoding, $type);
    }

    /**
     * 从 DOMDocument 获取 Document 实例
     * 
     * @param  DOMDocument  $domDocument  DOM 文档对象
     * @return self|null
     */
    public static function getFromDomDocument(DOMDocument $domDocument): ?self
    {
        $hash = spl_object_hash($domDocument);
        return self::$instances[$hash] ?? null;
    }

    /**
     * 加载 HTML/XML 内容
     * 
     * @param  string  $string  HTML/XML 字符串或文件路径
     * @param  bool  $isFile  是否从文件加载
     * @param  string|null  $type  文档类型（默认使用构造时的类型）
     * @return self
     * 
     * @throws RuntimeException 当加载失败时抛出
     */
    public function load(string $string, bool $isFile = false, ?string $type = null): self
    {
        $type = strtolower($type ?? $this->type);

        // 禁用错误输出，使用异常处理
        libxml_use_internal_errors(true);

        $loaded = false;

        if ($isFile) {
            if (! file_exists($string)) {
                throw new RuntimeException(sprintf('文件不存在: %s', $string));
            }
            $loaded = $type === self::TYPE_XML
                ? $this->document->load($string)
                : $this->document->loadHTMLFile($string);
        } else {
            if ($type === self::TYPE_XML) {
                $loaded = $this->document->loadXML($string);
            } else {
                // 对于 HTML，需要正确处理 UTF-8 编码
                // 如果字符串是 UTF-8，需要在 XML 声明中指定编码
                if (!preg_match('/encoding=/i', $string) && mb_detect_encoding($string, 'UTF-8', true) === 'UTF-8') {
                    $string = '<?xml encoding="' . $this->encoding . '" ?>' . $string;
                }
                $loaded = $this->document->loadHTML($string);
            }
        }

        if (! $loaded) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new RuntimeException(sprintf('文档加载失败: %s', $errors[0]->message ?? '未知错误'));
        }

        libxml_clear_errors();

        return $this;
    }

    /**
     * 保存文档到文件
     * 
     * @param  string  $filename  文件名
     * @return self
     * 
     * @throws RuntimeException 当保存失败时抛出
     */
    public function save(string $filename): self
    {
        $result = $this->type === self::TYPE_XML
            ? $this->document->save($filename)
            : $this->document->saveHTMLFile($filename);

        if ($result === false) {
            throw new RuntimeException(sprintf('文档保存失败: %s', $filename));
        }

        return $this;
    }

    /**
     * 获取文档内容
     *
     * @return string HTML/XML 内容
     */
    public function toString(): string
    {
        return $this->type === self::TYPE_XML
            ? $this->document->saveXML()
            : $this->document->saveHTML();
    }

    /**
     * 加载 HTML 内容（便捷方法）
     *
     * @param  string  $html  HTML 字符串
     * @return self
     */
    public function loadHtml(string $html): self
    {
        return $this->load($html, false, self::TYPE_HTML);
    }

    /**
     * 加载 XML 内容（便捷方法）
     *
     * @param  string  $xml  XML 字符串
     * @return self
     */
    public function loadXml(string $xml): self
    {
        return $this->load($xml, false, self::TYPE_XML);
    }

    /**
     * 检查是否有匹配的元素
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $type  选择器类型
     * @return bool
     */
    public function has(string $expression, string $type = Query::TYPE_CSS): bool
    {
        return count($this->find($expression, $type)) > 0;
    }

    /**
     * 获取匹配元素的数量
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $type  选择器类型
     * @return int
     */
    public function count(string $expression, string $type = Query::TYPE_CSS): int
    {
        return count($this->find($expression, $type));
    }

    /**
     * 获取文档的 DOMDocument 对象
     * 
     * @return DOMDocument
     */
    public function getDocument(): DOMDocument
    {
        return $this->document;
    }

    /**
     * 获取文档类型
     * 
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 获取文档编码
     *
     * @return string
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * 设置元素属性
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $name  属性名
     * @param  string|null  $value  属性值
     * @return self
     */
    public function setAttr(string $expression, string $name, ?string $value = null): self
    {
        if ($value === null) {
            return $this;
        }

        $element = $this->first($expression);

        if ($element !== null) {
            $element->setAttribute($name, $value);
        }

        return $this;
    }

    /**
     * 设置元素属性（便捷方法 - 3参数版本）
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $name  属性名
     * @param  string|null  $value  属性值
     * @param  string  $type  选择器类型
     * @return self
     */
    public function set(string $expression, string $name, ?string $value = null, string $type = Query::TYPE_CSS): self
    {
        if ($value === null) {
            return $this;
        }

        return $this->setAttr($expression, $name, $value);
    }

    /**
     * 获取/设置元素属性
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $name  属性名（如果提供则为设置操作）
     * @param  string|null  $value  属性值（提供此值则为设置操作）
     * @param  string  $type  选择器类型
     * @return self|string|null
     */
    public function attr(string $expression, string $name, ?string $value = null, string $type = Query::TYPE_CSS)
    {
        if ($value !== null) {
            return $this->setAttr($expression, $name, $value);
        }

        $element = $this->first($expression, $type);
        return $element?->getAttribute($name);
    }

    /**
     * 添加类名
     *
     * @param  string  $expression  选择器表达式
     * @param  string  ...$classNames  类名列表
     * @return self
     */
    public function addClass(string $expression, string ...$classNames): self
    {
        $elements = $this->find($expression);
        foreach ($elements as $element) {
            if ($element instanceof Element) {
                $element->classes()->add(...$classNames);
            }
        }
        return $this;
    }

    /**
     * 移除类名
     *
     * @param  string  $expression  选择器表达式
     * @param  string  ...$classNames  类名列表
     * @return self
     */
    public function removeClass(string $expression, string ...$classNames): self
    {
        $elements = $this->find($expression);
        foreach ($elements as $element) {
            if ($element instanceof Element) {
                $element->classes()->remove(...$classNames);
            }
        }
        return $this;
    }

    /**
     * 切换类名
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $className  类名
     * @return self
     */
    public function toggleClass(string $expression, string $className): self
    {
        $elements = $this->find($expression);
        foreach ($elements as $element) {
            if ($element instanceof Element) {
                $element->classes()->toggle($className);
            }
        }
        return $this;
    }

    /**
     * 检查类是否存在
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $className  类名
     * @return bool
     */
    public function hasClass(string $expression, string $className): bool
    {
        $element = $this->first($expression);
        return $element !== null && $element instanceof Element && $element->classes()->contains($className);
    }

    /**
     * 删除属性
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $name  属性名
     * @return self
     */
    public function removeAttr(string $expression, string $name): self
    {
        $elements = $this->find($expression);
        foreach ($elements as $element) {
            if ($element instanceof Element) {
                $element->removeAttribute($name);
            }
        }
        return $this;
    }

    /**
     * 设置内容
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $content  内容
     * @return self
     */
    public function setContent(string $expression, string $content): self
    {
        $element = $this->first($expression);
        if ($element !== null) {
            $element->html($content);
        }
        return $this;
    }


    /**
     * 设置是否保留空白
     * 
     * @param  bool  $preserve  是否保留空白
     * @return self
     */
    public function preserveWhiteSpace(bool $preserve = true): self
    {
        $this->document->preserveWhiteSpace = $preserve;
        return $this;
    }

    /**
     * 格式化输出
     * 
     * @param  bool  $format  是否格式化
     * @return self
     */
    public function format(bool $format = true): self
    {
        $this->document->formatOutput = $format;
        return $this;
    }

    /**
     * 查找匹配选择器的元素
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $type  选择器类型（CSS 或 XPath）
     * @param  DOMElement|null  $contextNode  上下文节点
     * @return array<int, Element|string> 匹配的元素数组或文本/属性值数组
     *
     * @throws InvalidSelectorException 当选择器无效时抛出
     * @throws InvalidArgumentException 当上下文节点无效时抛出
     * @throws RuntimeException 当查询失败时抛出
     */
    public function find(
        string $expression,
        string $type = Query::TYPE_CSS,
        ?DOMElement $contextNode = null
    ): array {
        // 处理 ::text 伪元素
        if (str_ends_with($expression, '::text')) {
            $cleanSelector = substr($expression, 0, -6); // ::text 是 6 个字符
            $elements = $this->doFind($cleanSelector, $type, true, $contextNode);
            return array_map(fn($el) => $el->text(), $elements);
        }

        // 处理 ::attr(name) 伪元素
        if (preg_match('/::attr\\(([^)]+)\\)$/', $expression, $matches)) {
            $attrName = trim($matches[1], '"\'');
            $cleanSelector = substr($expression, 0, -strlen($matches[0]));
            $elements = $this->doFind($cleanSelector, $type, true, $contextNode);
            return array_map(fn($el) => $el->getAttribute($attrName), $elements);
        }

        return $this->doFind($expression, $type, true, $contextNode);
    }

    /**
     * 获取第一个匹配的元素
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $type  选择器类型（CSS 或 XPath）
     * @param  DOMElement|null  $contextNode  上下文节点
     * @return Element|null 第一个匹配的元素或 null
     */
    public function first(
        string $expression,
        string $type = Query::TYPE_CSS,
        ?DOMElement $contextNode = null
    ): ?Element {
        $elements = $this->doFind($expression, $type, true, $contextNode);
        return $elements[0] ?? null;
    }

    /**
     * 获取匹配元素的文本内容
     * 
     * @param  string  $expression  选择器表达式（支持 ::text 伪元素）
     * @param  string  $type  选择器类型
     * @return string|null 文本内容
     */
    public function text(string $expression, string $type = Query::TYPE_CSS): ?string
    {
        // 检查 ::text 伪元素
        if (str_ends_with($expression, '::text')) {
            $cleanSelector = substr($expression, 0, -6); // ::text 是 6 个字符
            $element = $this->first($cleanSelector, $type);
            return $element?->text() ?? null;
        }

        // 检查 ::attr(name) 伪元素
        if (preg_match('/::attr\\(([^)]+)\\)$/', $expression, $matches)) {
            $attrName = trim($matches[1], '"\'');
            $cleanSelector = substr($expression, 0, -strlen($matches[0]));
            $element = $this->first($cleanSelector, $type);
            return $element?->getAttribute($attrName) ?? null;
        }

        $element = $this->first($expression, $type);
        return $element?->text() ?? null;
    }

    /**
     * 获取匹配元素的 HTML 内容
     *
     * @param  string|null  $expression  选择器表达式（如果为 null 则返回整个文档的 HTML）
     * @param  string  $type  选择器类型
     * @return string|null HTML 内容
     */
    public function html(?string $expression = null, string $type = Query::TYPE_CSS): ?string
    {
        if ($expression === null) {
            return $this->document->saveHTML();
        }
        $element = $this->first($expression, $type);
        return $element?->html() ?? null;
    }

    /**
     * 获取 XML 文档内容
     *
     * @return string|false XML 内容
     */
    public function xml(): string|false
    {
        return $this->document->saveXML();
    }

    /**
     * 获取匹配元素的属性值
     * @param  string  $expression  选择器表达式
     * @param  string  $type  选择器类型
     * @param  bool  $wrapNode  是否包装节点为 Element 对象
     * @param  DOMElement|null  $contextNode  上下文节点
     * @return array<int, Element|DOMNode>
     * 
     * @throws InvalidSelectorException 当选择器无效时抛出
     * @throws InvalidArgumentException 当上下文节点无效时抛出
     * @throws RuntimeException 当查询失败时抛出
     */
    private function doFind(
        string $expression,
        string $type,
        bool $wrapNode,
        ?DOMElement $contextNode
    ): array {
        // 编译选择器
        $compiledExpression = Query::compile($expression, $type);

        // 验证上下文节点
        if ($contextNode !== null) {
            if (! $contextNode instanceof DOMElement) {
                throw new InvalidArgumentException(
                    sprintf(
                        '上下文节点必须是 DOMElement 实例，%s 给定。',
                        is_object($contextNode) ? get_class($contextNode) : gettype($contextNode)
                    )
                );
            }

            if ($type === Query::TYPE_CSS) {
                $compiledExpression = '.' . $compiledExpression;
            }
        }

        // 执行 XPath 查询
        $xpath = $this->createXpath();
        $nodeList = $xpath->query($compiledExpression, $contextNode);

        if ($nodeList === false) {
            throw new RuntimeException('XPath 查询失败。');
        }

        // 转换结果
        $result = [];
        foreach ($nodeList as $node) {
            $result[] = $wrapNode ? $this->wrapNode($node) : $node;
        }

        return $result;
    }

    /**
     * 创建 XPath 对象
     * 
     * @return \DOMXPath
     */
    protected function createXpath(): \DOMXPath
    {
        return new \DOMXPath($this->document);
    }

    /**
     * 包装 DOM 节点为 Element 对象
     * 
     * @param  DOMNode  $node  DOM 节点
     * @return Element
     */
    public function wrapNode(DOMNode $node): Element
    {
        return new Element($node);
    }

    /**
     * 创建元素
     * 
     * @param  string  $tagName  标签名
     * @param  string|null  $content  内容
     * @param  array<string, string>  $attributes  属性数组
     * @return Element
     */
    public function createElement(
        string $tagName,
        ?string $content = null,
        array $attributes = []
    ): Element {
        $element = new Element($tagName);

        if ($content !== null) {
            $element->text($content);
        }

        if (! empty($attributes)) {
            foreach ($attributes as $attrName => $attrValue) {
                $element->setAttribute($attrName, $attrValue);
            }
        }

        return $element;
    }

    /**
     * 创建文本节点
     * 
     * @param  string  $text  文本内容
     * @return DOMText
     */
    public function createTextNode(string $text): DOMText
    {
        return $this->document->createTextNode($text);
    }

    /**
     * 创建文档片段
     * 
     * @return DocumentFragment
     */
    public function createDocumentFragment(): DocumentFragment
    {
        $fragment = new DocumentFragment($this->document->createDocumentFragment());
        return $fragment;
    }

    /**
     * 创建文档片段（别名方法）
     * 
     * @return DocumentFragment
     */
    public function createFragment(): DocumentFragment
    {
        return $this->createDocumentFragment();
    }

    /**
     * 获取根元素
     * 
     * @return Element|null
     */
    public function root(): ?Element
    {
        return $this->document->documentElement 
            ? $this->wrapNode($this->document->documentElement) 
            : null;
    }

    /**
     * 获取 head 元素
     * 
     * @return Element|null
     */
    public function head(): ?Element
    {
        return $this->first('head');
    }

    /**
     * 获取 body 元素
     * 
     * @return Element|null
     */
    public function body(): ?Element
    {
        return $this->first('body');
    }

    /**
     * 获取 title 元素文本
     * 
     * @return string|null
     */
    public function title(): ?string
    {
        $titleElement = $this->first('title');
        return $titleElement?->text();
    }

    /**
     * 设置标题
     * 
     * @param  string  $title  标题文本
     * @return self
     */
    public function setTitle(string $title): self
    {
        $titleElement = $this->first('title');
        
        if ($titleElement === null) {
            $head = $this->head() ?? $this->createElement('head');
            $this->root()?->prepend($head);
            $titleElement = $this->createElement('title');
            $head->append($titleElement);
        }

        $titleElement->text($title);

        return $this;
    }

    /**
     * 获取所有链接
     * 
     * @return array<int, array{href: string|null, text: string|null}> 链接数组
     */
    public function links(): array
    {
        $links = [];
        $elements = $this->find('a');

        foreach ($elements as $element) {
            $links[] = [
                'href' => $element->getAttribute('href'),
                'text' => $element->text(),
            ];
        }

        return $links;
    }

    /**
     * 获取所有图片
     * 
     * @return array<int, array{src: string|null, alt: string|null}> 图片数组
     */
    public function images(): array
    {
        $images = [];
        $elements = $this->find('img');

        foreach ($elements as $element) {
            $images[] = [
                'src' => $element->getAttribute('src'),
                'alt' => $element->getAttribute('alt'),
            ];
        }

        return $images;
    }

    /**
     * 获取所有表单元素
     * 
     * @return array<int, Element> 表单元素数组
     */
    public function forms(): array
    {
        return $this->find('form');
    }

    /**
     * 获取所有输入元素
     * 
     * @return array<int, Element> 输入元素数组
     */
    public function inputs(): array
    {
        return $this->find('input, textarea, select');
    }

    /**
     * 包装并插入节点
     * 
     * @param  Node|DOMNode  $node  要插入的节点
     * @param  DOMNode  $referenceNode  参考节点
     * @return Element
     */
    public function wrapAndInsertBefore(Node|DOMNode $node, DOMNode $referenceNode): Element
    {
        $wrappedNode = $node instanceof Node ? $node->getNode() : $node;
        $referenceNode->parentNode->insertBefore($wrappedNode, $referenceNode);
        return $this->wrapNode($wrappedNode);
    }

    /**
     * 包装并追加节点
     * 
     * @param  Node|DOMNode  $node  要追加的节点
     * @param  DOMNode  $parentNode  父节点
     * @return Element
     */
    public function wrapAndAppend(Node|DOMNode $node, DOMNode $parentNode): Element
    {
        $wrappedNode = $node instanceof Node ? $node->getNode() : $node;
        $parentNode->appendChild($wrappedNode);
        return $this->wrapNode($wrappedNode);
    }

    /**
     * 清空文档内容
     * 
     * @return self
     */
    public function clear(): self
    {
        while ($this->document->firstChild !== null) {
            $this->document->removeChild($this->document->firstChild);
        }

        return $this;
    }

    /**
     * 通过 CSS 选择器创建元素
     * 
     * @param  string  $selector  CSS 选择器
     * @param  string|null  $content  元素内容
     * @param  array<string, string>  $attributes  额外属性
     * @return Element
     * 
     * @throws InvalidSelectorException 当选择器无效时抛出
     */
    public function createElementBySelector(
        string $selector,
        ?string $content = null,
        array $attributes = []
    ): Element {
        $segments = Query::parseSelector($selector);
        $segment = $segments[0] ?? ['tag' => 'div', 'id' => '', 'classes' => [], 'attributes' => []];
        
        $element = $this->createElement(
            $segment['tag'] ?? 'div',
            $content
        );
        
        // 设置 ID
        if (!empty($segment['id'])) {
            $element->attr('id', $segment['id']);
        }
        
        // 设置类名
        if (!empty($segment['classes'])) {
            foreach ($segment['classes'] as $class) {
                $element->classes()->add($class);
            }
        }
        
        // 设置属性
        if (!empty($segment['attributes'])) {
            foreach ($segment['attributes'] as $attr) {
                $element->setAttribute($attr['name'], $attr['value'] ?? '');
            }
        }
        
        // 设置额外属性
        foreach ($attributes as $name => $value) {
            $element->setAttribute($name, $value);
        }
        
        return $element;
    }

    /**
     * 查找文本内容（支持 ::text 伪元素）
     * 
     * @param  string  $expression  选择器表达式
     * @param  string  $type  选择器类型
     * @return array<int, string> 文本数组
     */
    public function texts(string $expression, string $type = Query::TYPE_CSS): array
    {
        if (!str_ends_with($expression, '::text')) {
            $expression .= '::text';
        }
        return $this->find($expression, $type);
    }

    /**
     * 查找属性值（支持 ::attr(name) 伪元素）
     *
     * @param  string  $expression  选择器表达式
     * @param  string  $attrName  属性名
     * @param  string  $type  选择器类型
     * @return array<int, string|null> 属性值数组
     */
    public function attrs(string $expression, string $attrName, string $type = Query::TYPE_CSS): array
    {
        if (!str_ends_with($expression, "::attr({$attrName})") && !str_ends_with($expression, '::attr(')) {
            $expression .= "::attr({$attrName})";
        }
        return $this->find($expression, $type);
    }

    /**
     * 魔术方法：调用查找方法
     * 
     * @param  string  $method  方法名
     * @param  array<mixed>  $arguments  参数数组
     * @return mixed
     * 
     * @throws BadMethodCallException 当方法不存在时抛出
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (str_starts_with($method, 'find') || str_starts_with($method, 'first')) {
            $expression = $arguments[0] ?? '';
            $type = $arguments[1] ?? Query::TYPE_CSS;
            $contextNode = $arguments[2] ?? null;

            if (str_starts_with($method, 'first')) {
                return $this->first($expression, $type, $contextNode);
            }
            return $this->find($expression, $type, $contextNode);
        }

        throw new \BadMethodCallException(sprintf('方法 "%s" 不存在。', $method));
    }

    /**
     * 魔术方法：转换为字符串
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}

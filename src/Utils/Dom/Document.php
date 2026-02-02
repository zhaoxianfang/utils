<?php

declare(strict_types=1);

namespace zxf\Utils\Dom;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;
use RuntimeException;
use zxf\Utils\Dom\Exceptions\InvalidSelectorException;
use zxf\Utils\Dom\Selectors\Query;
use zxf\Utils\Dom\Fragments\DocumentFragment;

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
     * @param  bool  $isFile  是否从文件加载（支持本地文件和远程 URL）
     * @param  string  $encoding  文档编码
     * @param  string  $type  文档类型（HTML 或 XML）
     *
     * @example
     * // 从字符串加载
     * $doc = new Document('<div>Content</div>');
     *
     * // 从本地文件加载
     * $doc = new Document('/path/to/file.html', true);
     *
     * // 从远程 URL 加载
     * $doc = new Document('https://example.com', true);
     *
     * // 加载 XML
     * $doc = new Document('<?xml version="1.0"?><root/>', false, 'UTF-8', Document::TYPE_XML);
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

        // 注册实例到静态映射
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
     * @param  bool  $isFile  是否从文件加载（支持本地文件和 HTTP/HTTPS URL）
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
            // 检查是否为 HTTP/HTTPS URL
            if ($this->isRemoteUrl($string)) {
                $content = $this->fetchRemoteContent($string);
                if ($content === false) {
                    throw new RuntimeException(sprintf('无法获取远程内容: %s', $string));
                }
                $loaded = $type === self::TYPE_XML
                    ? $this->document->loadXML($content)
                    : $this->document->loadHTML($content);
            } elseif (file_exists($string)) {
                // 本地文件
                $loaded = $type === self::TYPE_XML
                    ? $this->document->load($string)
                    : $this->document->loadHTMLFile($string);
            } else {
                throw new RuntimeException(sprintf('文件不存在: %s', $string));
            }
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
            $errorMsg = !empty($errors) ? $errors[0]->message : '未知错误';
            throw new RuntimeException(sprintf('文档加载失败: %s', $errorMsg));
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

        return $this->setAttr($expression, $name, $value, $type);
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
        if ($element !== null && method_exists($element, 'setHtml')) {
            $element->setHtml($content);
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
     * @param  string  $type  选择器类型（CSS、XPath 或 Regex）
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
        // 处理正则表达式选择器
        if (strcasecmp($type, Query::TYPE_REGEX) === 0) {
            return $this->findByRegex($expression, $contextNode);
        }

        // 处理 XPath 表达式中的 /text() 函数（直接文本节点）
        // 例如：//div[@class="content"]/text()
        if (strcasecmp($type, Query::TYPE_XPATH) === 0 && str_ends_with($expression, '/text()')) {
            $baseExpression = substr($expression, 0, -7); // 移除 '/text()' (7个字符)
            return $this->doFindTextNodes($baseExpression, $contextNode);
        }

        // 处理 XPath 表达式中的 text() 函数（所有文本节点）
        // 例如：//div[@class="content"]//text()
        if (strcasecmp($type, Query::TYPE_XPATH) === 0 && str_contains($expression, '//text()')) {
            return $this->doFind($expression, $type, false, $contextNode);
        }

        // 处理 ::text 伪元素（CSS方式获取元素文本）
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
     * 使用 XPath 查询元素
     *
     * 支持完整的 XPath 1.0 语法，包括：
     * - 路径表达式：/（绝对路径）、//（相对路径）、..（父节点）
     * - 轴：child、descendant、parent、ancestor、following-sibling、preceding-sibling
     * - 函数：text()、comment()、normalize-space()、contains()、starts-with()、ends-with()
     * - 节点测试：node()、text()、comment()、element()
     * - 布尔函数：true()、false()、not()、and、or
     * - 数值函数：position()、last()、count()、sum()、number()、string-length()
     * - 字符串函数：concat()、substring()、translate()
     *
     * @param  string  $xpathExpression  XPath 表达式
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * // 基本路径
     * $elements = $doc->xpath('//div[@class="container"]');
     * $elements = $doc->xpath('//a[contains(@href, "example.com")]');
     *
     * // 索引和位置
     * $elements = $doc->xpath('(//div[@class="item"])[1]');
     * $elements = $doc->xpath('//li[position() > 3]');
     *
     * // 绝对路径（全路径）
     * $elements = $doc->xpath('/html/body/div[3]/div[1]/div/div[1]/span');
     *
     * // 文本节点
     * $elements = $doc->xpath('//body/div[3]/div[1]/div/div[1]/text()');
     *
     * // 组合条件
     * $elements = $doc->xpath('//div[contains(@class, "item") and @data-id="123"]');
     */
    public function xpath(string $xpathExpression): array
    {
        return $this->doFind($xpathExpression, Query::TYPE_XPATH, true, null);
    }

    /**
     * 使用正则表达式查找元素
     *
     * 此方法使用正则表达式匹配元素的文本内容、HTML内容或属性值
     * 支持匹配：
     * - 文本内容: 通过元素的 textContent 匹配
     * - HTML内容: 通过元素的 innerHTML 匹配
     * - 属性值: 通过元素属性匹配
     *
     * 正则表达式选择器优势：
     * 1. 灵活匹配：支持复杂的模式匹配
     * 2. 文本提取：直接匹配文本内容，无需预先知道结构
     * 3. 属性过滤：基于属性值的正则匹配
     * 4. 多数据匹配：支持提取多个匹配结果
     *
     * @param  string  $pattern  正则表达式模式
     * @param  DOMElement|null  $contextNode  上下文节点（如果提供则从此节点开始搜索）
     * @param  string|null  $attribute  属性名（如果提供则匹配属性值）
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * // 查找文本包含 "2026" 的元素
     * $elements = $doc->regex('/2026/');
     *
     * // 查找文本包含日期格式的元素
     * $elements = $doc->regex('/\d{4}-\d{2}-\d{2}/');
     *
     * // 查找 href 包含 "gov.cn" 的链接
     * $elements = $doc->regex('/gov\.cn/', null, 'href');
     *
     * // 查找邮箱地址
     * $elements = $doc->regex('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/');
     *
     * // 查找电话号码
     * $elements = $doc->regex('/\d{3}-\d{4}-\d{4}/');
     *
     * // 在指定上下文中搜索
     * $context = $doc->first('div.container');
     * $elements = $doc->regex('/test/', $context);
     */
    public function regex(string $pattern, ?DOMElement $contextNode = null, ?string $attribute = null): array
    {
        return $this->findByRegex($pattern, $contextNode, $attribute);
    }

    /**
     * 使用正则表达式查找元素并提取所有匹配的文本
     *
     * 此方法使用正则表达式匹配元素的文本内容，并提取所有匹配的文本。
     * 支持分组捕获和多数据提取。
     *
     * @param  string  $pattern  正则表达式模式（支持分组捕获）
     * @param  DOMElement|null  $contextNode  上下文节点
     * @param  string|null  $attribute  属性名（如果提供则匹配属性值）
     * @return array<int, string|array> 匹配的文本数组或分组数组
     *
     * @example
     * // 提取所有日期
     * $matches = $doc->regexMatch('/\d{4}-\d{2}-\d{2}/');
     * // 返回: ['2026-01-01', '2026-01-02', ...]
     *
     * // 提取分组数据（姓名和年龄）
     * $matches = $doc->regexMatch('/(\w+)\s*[:：]\s*(\d+)/');
     * // 返回: [['张三', '30'], ['李四', '25'], ...]
     *
     * // 提取属性中的URL
     * $matches = $doc->regexMatch('/https?:\/\/[^"\']+/i', null, 'href');
     */
    public function regexMatch(string $pattern, ?DOMElement $contextNode = null, ?string $attribute = null): array
    {
        $result = [];

        // 验证正则表达式
        $errorReporting = error_reporting(0);
        @preg_match($pattern, '');
        error_reporting($errorReporting);

        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new RuntimeException(sprintf('无效的正则表达式: "%s"。错误代码: %d', $pattern, preg_last_error()));
        }

        // 获取所有元素节点
        $xpath = $this->createXpath();
        $contextPath = $contextNode !== null ? '.' : '//';
        $nodeList = $xpath->query($contextPath . '//*');

        if ($nodeList === false) {
            return $result;
        }

        foreach ($nodeList as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            // 获取匹配内容
            $content = $attribute !== null && $node->hasAttribute($attribute)
                ? $node->getAttribute($attribute)
                : ($node->textContent ?? '');

            if ($content === '') {
                continue;
            }

            // 执行匹配，提取所有结果
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER) > 0) {
                foreach ($matches as $match) {
                    // 如果有分组，返回分组数组；否则返回完整匹配
                    if (count($match) > 1) {
                        // 提取所有分组（排除完整匹配）
                        $groups = array_slice($match, 1);
                        $result[] = array_values(array_filter($groups, fn($g) => $g !== ''));
                    } else {
                        $result[] = $match[0];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 使用正则表达式查找元素并提取所有匹配的文本（带元素信息）
     *
     * 此方法与 regexMatch 类似，但返回包含元素信息的详细结果。
     * 返回格式：[['element' => Element, 'matches' => string[]], ...]
     *
     * @param  string  $pattern  正则表达式模式
     * @param  DOMElement|null  $contextNode  上下文节点
     * @param  string|null  $attribute  属性名（如果提供则匹配属性值）
     * @return array<int, array{element: Element, matches: array<string>}> 匹配结果数组
     *
     * @example
     * // 提取日期并获取所在元素
     * $results = $doc->regexMatchWithElement('/\d{4}-\d{2}-\d{2}/');
     * foreach ($results as $result) {
     *     echo "日期: {$result['matches'][0]}, 元素: {$result['element']->tagName()}";
     * }
     *
     * // 提取链接并获取href
     * $results = $doc->regexMatchWithElement('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i');
     */
    public function regexMatchWithElement(string $pattern, ?DOMElement $contextNode = null, ?string $attribute = null): array
    {
        $result = [];

        // 验证正则表达式
        $errorReporting = error_reporting(0);
        @preg_match($pattern, '');
        error_reporting($errorReporting);

        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new RuntimeException(sprintf('无效的正则表达式: "%s"。错误代码: %d', $pattern, preg_last_error()));
        }

        // 获取所有元素节点
        $xpath = $this->createXpath();
        $contextPath = $contextNode !== null ? '.' : '//';
        $nodeList = $xpath->query($contextPath . '//*');

        if ($nodeList === false) {
            return $result;
        }

        foreach ($nodeList as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            // 获取匹配内容
            $content = $attribute !== null && $node->hasAttribute($attribute)
                ? $node->getAttribute($attribute)
                : ($node->textContent ?? '');

            if ($content === '') {
                continue;
            }

            // 执行匹配，提取所有结果
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER) > 0) {
                foreach ($matches as $match) {
                    // 如果有分组，返回分组数组；否则返回完整匹配
                    $matchData = count($match) > 1
                        ? array_values(array_filter(array_slice($match, 1), fn($g) => $g !== ''))
                        : [$match[0]];

                    $result[] = [
                        'element' => $this->wrapNode($node),
                        'matches' => $matchData,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * 使用多个正则表达式同时查找元素（多列数据匹配）
     *
     * 此方法支持同时使用多个正则表达式模式进行匹配，返回每个模式的匹配结果。
     * 适用于需要从不同位置提取不同类型数据的场景。
     *
     * @param  array<string, string>  $patterns  正则表达式数组，格式：['name1' => 'pattern1', 'name2' => 'pattern2']
     * @param  DOMElement|null  $contextNode  上下文节点
     * @param  string|null  $attribute  属性名（如果提供则匹配属性值）
     * @return array<string, array<int, string>> 按模式名称索引的匹配结果数组
     *
     * @example
     * // 同时提取日期、邮箱和电话
     * $results = $doc->regexMulti([
     *     'dates' => '/\d{4}-\d{2}-\d{2}/',
     *     'emails' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
     *     'phones' => '/\d{3}-\d{4}-\d{4}/'
     * ]);
     * // 返回: ['dates' => ['2026-01-01', ...], 'emails' => ['test@example.com', ...], 'phones' => ['123-4567-8901', ...]]
     *
     * // 从特定元素的多列属性中提取数据
     * $results = $doc->regexMulti([
     *     'ids' => '/\d+/',
     *     'codes' => '/[A-Z]\d{3}/'
     * ], null, 'data-id');
     */
    public function regexMulti(array $patterns, ?DOMElement $contextNode = null, ?string $attribute = null): array
    {
        $result = [];

        // 对每个模式执行匹配
        foreach ($patterns as $name => $pattern) {
            $result[$name] = $this->regexMatch($pattern, $contextNode, $attribute);
        }

        return $result;
    }

    /**
     * 使用正则表达式提取表格数据
     *
     * 此方法使用正则表达式从HTML中提取表格数据，支持多表格匹配。
     * 返回结构化的表格数据，包含表头(thead)和表体(tbody)。
     *
     * 重要特性：
     * - 自动处理HTML中的换行、制表符等空白字符
     * - 支持匹配多个表格
     * - 返回标准化的表格数据结构
     * - 支持自定义提取选项
     *
     * @param  string  $pattern  正则表达式模式，用于匹配表格
     * @param  array<string, mixed>  $options  提取选项
     *                                     - 'trimText': 是否修剪空白字符，默认true
     *                                     - 'removeEmpty': 是否移除空行，默认true
     *                                     - 'cellSelector': 单元格选择器，默认'td, th'
     *                                     - 'rowSelector': 行选择器，默认'tr'
     *                                     - 'includeHeader': 是否包含表头，默认true
     *                                     - 'headerRow': 表头行索引，默认0
     *                                     - 'skipRows': 跳过的行数，默认0
     * @return array<int, array<string, array<int, array<string>>>> 表格数据数组
     *                                                         [
     *                                                             0 => [
     *                                                                 'thead' => ['姓名', '性别', ...],
     *                                                                 'tbody' => [['张三', '男', ...], ...]
     *                                                             ],
     *                                                             1 => [...] // 第二个表格
     *                                                         ]
     *
     * @throws RuntimeException 当正则表达式无效时抛出
     *
     * @example
     * // 提取所有表格
     * $tables = $doc->regexExtractTable('/<table[^>]*>.*?<\/table>/is');
     *
     * // 提取特定class的表格
     * $tables = $doc->regexExtractTable('/<table[^>]*class="data-table"[^>]*>.*?<\/table>/is');
     *
     * // 提取带自定义选项
     * $tables = $doc->regexExtractTable('/<table[^>]*>.*?<\/table>/is', [
     *     'trimText' => true,
     *     'removeEmpty' => true,
     *     'includeHeader' => true
     * ]);
     */
    public function regexExtractTable(string $pattern, array $options = []): array
    {
        // 合并默认选项
        $defaultOptions = [
            'trimText' => true,
            'removeEmpty' => true,
            'cellSelector' => 'td, th',
            'rowSelector' => 'tr',
            'includeHeader' => true,
            'headerRow' => 0,
            'skipRows' => 0
        ];
        $options = array_merge($defaultOptions, $options);

        // 验证正则表达式
        $errorReporting = error_reporting(0);
        @preg_match($pattern, '');
        error_reporting($errorReporting);

        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new RuntimeException(sprintf('无效的正则表达式: "%s"。错误代码: %d', $pattern, preg_last_error()));
        }

        // 获取HTML内容
        $html = $this->html();

        // 匹配所有表格
        if (preg_match_all($pattern, $html, $tableMatches, PREG_PATTERN_ORDER) === false) {
            return [];
        }

        $result = [];

        // 处理每个匹配的表格
        foreach ($tableMatches[0] as $tableHtml) {
            // 提取表格数据
            $tableData = $this->extractTableDataFromHtml($tableHtml, $options);
            
            if (!empty($tableData['thead']) || !empty($tableData['tbody'])) {
                $result[] = $tableData;
            }
        }

        return $result;
    }

    /**
     * 从HTML字符串提取表格数据
     *
     * @param  string  $html  HTML字符串
     * @param  array<string, mixed>  $options  提取选项
     * @return array<string, array<int, array<string>>> 表格数据
     */
    protected function extractTableDataFromHtml(string $html, array $options): array
    {
        $result = [
            'thead' => [],
            'tbody' => []
        ];

        // 创建临时DOM文档
        $tempDoc = new Document($html);
        $table = $tempDoc->first('table');

        if ($table === null) {
            return $result;
        }

        // 提取表头
        if ($options['includeHeader']) {
            $thead = $table->first('thead');
            if ($thead !== null) {
                $theadRows = $thead->find($options['rowSelector']);
                if (!empty($theadRows)) {
                    $headerRowIndex = min($options['headerRow'], count($theadRows) - 1);
                    $headerRow = $theadRows[$headerRowIndex];
                    
                    $cells = $headerRow->find($options['cellSelector']);
                    foreach ($cells as $cell) {
                        $text = $this->cleanCellText($cell->text(), $options['trimText']);
                        if ($options['removeEmpty'] && empty($text)) {
                            continue;
                        }
                        $result['thead'][] = $text;
                    }
                }
            } else {
                // 如果没有 thead，尝试从第一行获取
                $allRows = $table->find($options['rowSelector']);
                if (!empty($allRows)) {
                    $headerRow = $allRows[0];
                    $cells = $headerRow->find($options['cellSelector']);
                    foreach ($cells as $cell) {
                        $text = $this->cleanCellText($cell->text(), $options['trimText']);
                        if ($options['removeEmpty'] && empty($text)) {
                            continue;
                        }
                        $result['thead'][] = $text;
                    }
                }
            }
        }

        // 提取表体
        $tbodies = $table->find('tbody');
        if (!empty($tbodies)) {
            $tbodyStartIndex = 0;
            foreach ($tbodies as $tbody) {
                $tbodyRows = $tbody->find($options['rowSelector']);
                foreach ($tbodyRows as $row) {
                    $effectiveRowIndex = $tbodyStartIndex;
                    if ($effectiveRowIndex < $options['skipRows']) {
                        $tbodyStartIndex++;
                        continue;
                    }

                    $rowData = [];
                    $cells = $row->find($options['cellSelector']);
                    foreach ($cells as $cell) {
                        $text = $this->cleanCellText($cell->text(), $options['trimText']);
                        $rowData[] = $text;
                    }

                    // 检查是否为空行
                    if ($options['removeEmpty']) {
                        $isEmpty = true;
                        foreach ($rowData as $value) {
                            if (!empty(trim($value))) {
                                $isEmpty = false;
                                break;
                            }
                        }
                        if (!$isEmpty) {
                            $result['tbody'][] = $rowData;
                        }
                    } else {
                        $result['tbody'][] = $rowData;
                    }

                    $tbodyStartIndex++;
                }
            }
        } else {
            // 如果没有 tbody，从所有行提取（排除表头）
            $allRows = $table->find($options['rowSelector']);
            $theadRows = $table->first('thead')?->find($options['rowSelector']) ?? [];
            $tbodyStartIndex = count($theadRows);
            
            foreach ($allRows as $index => $row) {
                // 跳过 thead 的行
                if ($index < $tbodyStartIndex) {
                    continue;
                }

                $effectiveRowIndex = $index - $tbodyStartIndex;
                if ($effectiveRowIndex < $options['skipRows']) {
                    continue;
                }

                $rowData = [];
                $cells = $row->find($options['cellSelector']);
                foreach ($cells as $cell) {
                    $text = $this->cleanCellText($cell->text(), $options['trimText']);
                    $rowData[] = $text;
                }

                // 检查是否为空行
                if ($options['removeEmpty']) {
                    $isEmpty = true;
                    foreach ($rowData as $value) {
                        if (!empty(trim($value))) {
                            $isEmpty = false;
                            break;
                        }
                    }
                    if (!$isEmpty) {
                        $result['tbody'][] = $rowData;
                    }
                } else {
                    $result['tbody'][] = $rowData;
                }
            }
        }

        return $result;
    }

    /**
     * 使用正则表达式替换文本内容
     *
     * 此方法使用正则表达式查找并替换所有匹配元素的文本内容。
     *
     * @param  string  $pattern  正则表达式模式
     * @param  string  $replacement  替换字符串
     * @param  DOMElement|null  $contextNode  上下文节点
     * @param  string|null  $attribute  属性名（如果提供则替换属性值）
     * @return self
     *
     * @example
     * // 替换所有日期格式
     * $doc->regexReplace('/(\d{4})-(\d{2})-(\d{2})/', '$1年$2月$3日');
     *
     * // 替换链接中的协议
     * $doc->regexReplace('/https?:\/\//', 'https://', null, 'href');
     *
     * // 清理多余空格
     * $doc->regexReplace('/\s+/', ' ');
     */
    public function regexReplace(string $pattern, string $replacement, ?DOMElement $contextNode = null, ?string $attribute = null): self
    {
        // 验证正则表达式
        $errorReporting = error_reporting(0);
        @preg_match($pattern, '');
        error_reporting($errorReporting);

        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new RuntimeException(sprintf('无效的正则表达式: "%s"。错误代码: %d', $pattern, preg_last_error()));
        }

        // 获取所有元素节点
        $xpath = $this->createXpath();
        $contextPath = $contextNode !== null ? '.' : '//';
        $nodeList = $xpath->query($contextPath . '//*');

        if ($nodeList === false) {
            return $this;
        }

        foreach ($nodeList as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            if ($attribute !== null && $node->hasAttribute($attribute)) {
                // 替换属性值
                $oldValue = $node->getAttribute($attribute);
                $newValue = preg_replace($pattern, $replacement, $oldValue);
                if ($newValue !== null && $newValue !== $oldValue) {
                    $node->setAttribute($attribute, $newValue);
                }
            } else {
                // 替换文本内容
                if ($node->textContent !== null) {
                    $oldText = $node->textContent;
                    $newText = preg_replace($pattern, $replacement, $oldText);
                    if ($newText !== null && $newText !== $oldText) {
                        // 清空并重新设置文本
                        while ($node->firstChild !== null) {
                            $node->removeChild($node->firstChild);
                        }
                        $node->appendChild(new \DOMText($newText));
                    }
                }
            }
        }

        return $this;
    }

    /**
     * 使用正则表达式查找元素（内部方法）
     *
     * 此方法支持多种匹配模式：
     * - 匹配文本内容（默认）
     * - 匹配特定属性值
     * - 支持复杂正则表达式模式
     *
     * @param  string  $pattern  正则表达式模式
     * @param  DOMElement|null  $contextNode  上下文节点
     * @param  string|null  $attribute  要匹配的属性名（如果提供则匹配属性值）
     * @return array<int, Element> 匹配的元素数组
     *
     * @throws RuntimeException 当正则表达式无效时抛出
     */
    protected function findByRegex(string $pattern, ?DOMElement $contextNode = null, ?string $attribute = null): array
    {
        $result = [];

        // 验证正则表达式
        $errorReporting = error_reporting(0);
        $isValid = @preg_match($pattern, '');
        error_reporting($errorReporting);

        if ($isValid === false && preg_last_error() !== PREG_NO_ERROR) {
            throw new RuntimeException(sprintf('无效的正则表达式: "%s"。错误代码: %d', $pattern, preg_last_error()));
        }

        // 获取所有元素节点
        $xpath = $this->createXpath();
        $contextPath = $contextNode !== null ? '.' : '//';
        $nodeList = $xpath->query($contextPath . '//*');

        if ($nodeList === false) {
            return $result;
        }

        foreach ($nodeList as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            $matched = false;

            // 如果指定了属性名，则匹配属性值
            if ($attribute !== null) {
                if ($node->hasAttribute($attribute)) {
                    $attrValue = $node->getAttribute($attribute);
                    if (preg_match($pattern, $attrValue) === 1) {
                        $matched = true;
                    }
                }
            } else {
                // 否则匹配文本内容
                $textContent = $node->textContent ?? '';
                if (preg_match($pattern, $textContent) === 1) {
                    $matched = true;
                }
            }

            if ($matched) {
                $result[] = $this->wrapNode($node);
            }
        }

        return $result;
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
     */
    protected function doFind(
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

        // 捕获 XPath 错误而不是直接抛出异常
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $nodeList = $xpath->query($compiledExpression, $contextNode);

        // 检查是否有 XPath 错误
        $xpathErrors = libxml_get_errors();
        libxml_clear_errors();

        if (!empty($xpathErrors)) {
            $errorMsg = '';
            foreach ($xpathErrors as $error) {
                $errorMsg .= sprintf('行 %d, 列 %d: %s', $error->line, $error->column, trim($error->message));
            }
            throw new RuntimeException(sprintf('XPath 查询失败: %s', $errorMsg));
        }

        // 检查查询结果
        if ($nodeList === false) {
            throw new RuntimeException('XPath 查询失败：无法执行查询表达式。');
        }

        // 转换结果
        $result = [];
        foreach ($nodeList as $node) {
            $result[] = $wrapNode ? $this->wrapNode($node) : $node;
        }

        return $result;
    }

    /**
     * 查找匹配选择器的直接文本节点
     *
     * 此方法专门处理 XPath 的 /text() 函数，返回元素的直接文本节点
     * 这些是未被任何标签包围的纯文本内容
     *
     * @param  string  $baseExpression  基础选择器（不包含 /text()）
     * @param  DOMElement|null  $contextNode  上下文节点
     * @return array<int, string> 文本内容数组
     *
     * @throws RuntimeException 当查询失败时抛出
     *
     * @example
     * // 获取 div.content 的直接文本（不包括子元素的文本）
     * $texts = $doc->doFindTextNodes('//div[@class="content"]');
     *
     * // 上下文节点内的直接文本
     * $texts = $doc->doFindTextNodes('.container', $contextElement);
     */
    protected function doFindTextNodes(string $baseExpression, ?DOMElement $contextNode = null): array
    {
        // 执行 XPath 查询获取元素
        $elements = $this->doFind($baseExpression, Query::TYPE_XPATH, false, $contextNode);

        // 提取每个元素的直接文本节点
        $result = [];
        foreach ($elements as $element) {
            if (!($element instanceof DOMNode)) {
                continue;
            }

            // 获取直接子文本节点
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMText) {
                    $text = trim($child->nodeValue);
                    if ($text !== '') {
                        $result[] = $text;
                    }
                }
            }
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
            $element->setValue($content);
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
     * 查找包含指定文本的元素
     *
     * @param  string  $text  要查找的文本
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
     * @return array<int, Element> 匹配的元素数组
     */
    public function findByText(string $text, string $selector = '*'): array
    {
        $elements = $this->find($selector);
        $result = [];

        foreach ($elements as $element) {
            if (is_string($element) && str_contains($element, $text)) {
                // 如果是字符串（来自 ::text 伪元素），跳过
                continue;
            }
            if ($element instanceof Element && str_contains($element->text(), $text)) {
                $result[] = $element;
            }
        }

        return $result;
    }

    /**
     * 查找包含指定文本的元素（不区分大小写）
     *
     * @param  string  $text  要查找的文本
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
     * @return array<int, Element> 匹配的元素数组
     */
    public function findByTextIgnoreCase(string $text, string $selector = '*'): array
    {
        $elements = $this->find($selector);
        $result = [];
        $lowerText = strtolower($text);

        foreach ($elements as $element) {
            if (is_string($element)) {
                // 如果是字符串（来自 ::text 伪元素），跳过
                continue;
            }
            if ($element instanceof Element && str_contains(strtolower($element->text()), $lowerText)) {
                $result[] = $element;
            }
        }

        return $result;
    }

    /**
     * 查找具有指定属性的元素
     *
     * @param  string  $attribute  属性名
     * @param  string|null  $value  属性值（如果为null则只检查属性存在）
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
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
     * 查找属性值包含指定文本的元素
     *
     * @param  string  $attribute  属性名
     * @param  string  $value  要查找的值
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
     * @return array<int, Element> 匹配的元素数组
     */
    public function findByAttributeContains(string $attribute, string $value, string $selector = '*'): array
    {
        $elements = $this->find($selector);
        $result = [];

        foreach ($elements as $element) {
            if (!($element instanceof Element)) {
                continue;
            }

            $attrValue = $element->getAttribute($attribute);
            if ($attrValue !== null && str_contains($attrValue, $value)) {
                $result[] = $element;
            }
        }

        return $result;
    }

    /**
     * 查找属性值以指定文本开头的元素
     *
     * @param  string  $attribute  属性名
     * @param  string  $prefix  前缀
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
     * @return array<int, Element> 匹配的元素数组
     */
    public function findByAttributeStartsWith(string $attribute, string $prefix, string $selector = '*'): array
    {
        $elements = $this->find($selector);
        $result = [];

        foreach ($elements as $element) {
            if (!($element instanceof Element)) {
                continue;
            }

            $attrValue = $element->getAttribute($attribute);
            if ($attrValue !== null && str_starts_with($attrValue, $prefix)) {
                $result[] = $element;
            }
        }

        return $result;
    }

    /**
     * 查找属性值以指定文本结尾的元素
     *
     * @param  string  $attribute  属性名
     * @param  string  $suffix  后缀
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
     * @return array<int, Element> 匹配的元素数组
     */
    public function findByAttributeEndsWith(string $attribute, string $suffix, string $selector = '*'): array
    {
        $elements = $this->find($selector);
        $result = [];

        foreach ($elements as $element) {
            if (!($element instanceof Element)) {
                continue;
            }

            $attrValue = $element->getAttribute($attribute);
            if ($attrValue !== null && str_ends_with($attrValue, $suffix)) {
                $result[] = $element;
            }
        }

        return $result;
    }

    /**
     * 查找包含指定 data-* 属性的元素
     *
     * 此方法用于查找包含特定 data-* 属性或 data-* 属性值的元素。
     * 适用于处理现代HTML5 data属性。
     *
     * @param  string  $dataName  data属性名（不包含 data- 前缀）
     * @param  string|null  $value  属性值（如果为null则只检查属性存在）
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * // 查找包含 data-id 的元素
     * $elements = $doc->findByData('id');
     *
     * // 查找 data-id="123" 的元素
     * $elements = $doc->findByData('id', '123');
     *
     * // 查找包含 data-user 且 data-user="admin" 的div元素
     * $elements = $doc->findByData('user', 'admin', 'div');
     */
    public function findByData(string $dataName, ?string $value = null, string $selector = '*'): array
    {
        return $this->findByAttribute('data-' . $dataName, $value, $selector);
    }

    /**
     * 查找 data-* 属性值包含指定字符串的元素
     *
     * @param  string  $dataName  data属性名（不包含 data- 前缀）
     * @param  string  $value  要查找的值
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * // 查找 data-category 包含 "news" 的元素
     * $elements = $doc->findByDataContains('category', 'news');
     */
    public function findByDataContains(string $dataName, string $value, string $selector = '*'): array
    {
        return $this->findByAttributeContains('data-' . $dataName, $value, $selector);
    }

    /**
     * 查找 data-* 属性值以指定字符串开头的元素
     *
     * @param  string  $dataName  data属性名（不包含 data- 前缀）
     * @param  string  $prefix  前缀
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * // 查找 data-id 以 "user-" 开头的元素
     * $elements = $doc->findByDataStartsWith('id', 'user-');
     */
    public function findByDataStartsWith(string $dataName, string $prefix, string $selector = '*'): array
    {
        return $this->findByAttributeStartsWith('data-' . $dataName, $prefix, $selector);
    }

    /**
     * 查找 data-* 属性值以指定字符串结尾的元素
     *
     * @param  string  $dataName  data属性名（不包含 data- 前缀）
     * @param  string  $suffix  后缀
     * @param  string  $selector  CSS选择器（可选，用于限制范围）
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * // 查找 data-id 以 "-active" 结尾的元素
     * $elements = $doc->findByDataEndsWith('id', '-active');
     */
    public function findByDataEndsWith(string $dataName, string $suffix, string $selector = '*'): array
    {
        return $this->findByAttributeEndsWith('data-' . $dataName, $suffix, $selector);
    }

    /**
     * 查找指定位置的元素（基于索引）
     *
     * 此方法从匹配的元素中返回指定索引的元素。
     *
     * @param  string  $selector  CSS选择器
     * @param  int  $index  元素索引（0-based）
     * @param  DOMElement|null  $contextNode  上下文节点（可选）
     * @return Element|null 指定索引的元素或null
     *
     * @example
     * // 获取第3个div元素
     * $element = $doc->findByIndex('div', 2);
     *
     * // 获取第5个链接
     * $element = $doc->findByIndex('a', 4);
     */
    public function findByIndex(string $selector, int $index, ?DOMElement $contextNode = null): ?Element
    {
        $elements = $this->find($selector, Query::TYPE_CSS, $contextNode);
        return $elements[$index] ?? null;
    }

    /**
     * 查找指定范围索引的元素
     *
     * 此方法返回指定范围内的元素。
     *
     * @param  string  $selector  CSS选择器
     * @param  int  $start  起始索引（包含，0-based）
     * @param  int  $end  结束索引（不包含）
     * @param  DOMElement|null  $contextNode  上下文节点（可选）
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * // 获取前3个div元素
     * $elements = $doc->findByRange('div', 0, 3);
     *
     * // 获取第5到第10个链接
     * $elements = $doc->findByRange('a', 4, 10);
     */
    public function findByRange(string $selector, int $start, int $end, ?DOMElement $contextNode = null): array
    {
        $elements = $this->find($selector, Query::TYPE_CSS, $contextNode);
        return array_slice($elements, $start, $end - $start);
    }

    /**
     * 使用选择器数组回退查找元素
     *
     * 此方法支持传入多个选择器，按顺序尝试，找到第一个非空结果即返回。
     * 支持混合使用 CSS 选择器、XPath 选择器和正则表达式。
     *
     * 正则表达式增强支持：
     * - 可以配置 `extractMode` 来指定提取模式
     *   - `elements`: 返回匹配的元素（默认）
     *   - `text`: 提取匹配的文本内容
     *   - `attr`: 提取匹配的属性值
     *   - `match`: 使用 regexMatch 提取所有匹配项
     * - 支持使用 `group` 参数指定提取特定分组
     * - 支持使用 `location` 参数指定提取多个匹配位置的数据并返回关联数组
     *
     * 选择器数组格式：
     * ```php
     * [
     *     'selector' => 'CSS选择器或XPath表达式或正则表达式',
     *     'type' => 'css|xpath|regex',  // 可选，默认为 'css'
     *     'attribute' => '属性名',       // 仅当 type='regex' 时使用，可选
     *     'extractMode' => 'elements|text|attr|match',  // 仅当 type='regex' 时使用，可选
     *     'group' => int,               // 分组索引，仅当 extractMode='match' 时有效，可选
     *     'location' => [                // 仅当 type='regex' 时使用，可选，用于提取指定位置的值
     *         'fieldName' => [           // 自定义字段名
     *             'index' => 0,          // 正则表达式分组索引
     *             'description' => '描述' // 可选，用于说明该字段的含义
     *         ],
     *         ...
     *     ]
     * ]
     * ```
     *
     * @param  array<int, array{selector: string, type?: string, attribute?: string, extractMode?: string, group?: int}>  $selectors  选择器数组
     * @param  DOMElement|null  $contextNode  上下文节点（可选）
     * @param  ?bool $getFirst  是否只获取不为空的第一个元素（可选）,默认为 true; true表示返回第一个非空的元素，false表示返回所有匹配的元素
     * @return array<int, Element|string> 匹配的元素数组或文本/属性值数组
     *
     * @example
     * // 基本用法：混合使用CSS和XPath
     * $result = $doc->findWithFallback([
     *     ['selector' => '.main-content .title'],           // CSS选择器
     *     ['selector' => '//h1[@class="main-title"]'],     // XPath选择器
     *     ['selector' => '/html/body/h1']                  // XPath绝对路径
     * ]);
     *
     * // 使用正则表达式提取文本
     * $result = $doc->findWithFallback([
     *     ['selector' => '/\d{4}-\d{2}-\d{2}/', 'type' => 'regex', 'extractMode' => 'text']
     * ]);
     *
     * // 使用正则表达式提取属性值
     * $result = $doc->findWithFallback([
     *     ['selector' => '/https?:\/\//', 'type' => 'regex', 'extractMode' => 'attr', 'attribute' => 'href']
     * ]);
     *
     * // 使用正则表达式匹配所有结果（支持分组）
     * $result = $doc->findWithFallback([
     *     ['selector' => '/(\w+)\s*[:：]\s*(\d+)/', 'type' => 'regex', 'extractMode' => 'match', 'group' => 0]
     * ]);
     *
     * // 使用 location 参数提取多个字段（推荐）
     * $result = $doc->findWithFallback([
     *     [
     *         'selector' => '/(\d{4}-\d{2}-\d{2})\s*日期\s*(\d{2}:\d{2}:\d{2})/',
     *         'type' => 'regex',
     *         'location' => [
     *             'date' => ['index' => 1, 'description' => '日期'],
     *             'time' => ['index' => 2, 'description' => '时间']
     *         ]
     *     ]
     * ]);
     * // 返回: [['date' => '2026-01-01', 'time' => '12:00:00'], ...]
     *
     * // 复杂场景：多个备选方案
     * $result = $doc->findWithFallback([
     *     ['selector' => 'div.content > h1'],
     *     ['selector' => '.article-title'],
     *     ['selector' => '//div[contains(@class, "content")]/h1', 'type' => 'xpath'],
     *     ['selector' => '/html/body/div[1]/h1', 'type' => 'xpath']
     * ]);
     */
    public function findWithFallback(
        array $selectors,
        ?DOMElement $contextNode = null,
        ?bool $getFirst = true
    ): array {
        $data = [];
        foreach ($selectors as $selectorConfig) {
            try {
                // 获取选择器配置
                $selector = $selectorConfig['selector'] ?? '';
                $type = ($selectorConfig['type'] ?? 'css');
                $attribute = $selectorConfig['attribute'] ?? null;
                $extractMode = $selectorConfig['extractMode'] ?? null;
                $group = $selectorConfig['group'] ?? null;
                $location = $selectorConfig['location'] ?? null;

                if (empty($selector)) {
                    continue;
                }

                // 根据类型执行查询
                if (strcasecmp($type, Query::TYPE_REGEX) === 0) {
                    // 正则表达式选择器
                    $result = $this->handleRegexSelector($selector, $contextNode, $attribute, $extractMode, $group, $location);
                } else {
                    // CSS 或 XPath 选择器
                    $result = $this->find($selector, $type, $contextNode);
                }

                // 如果找到结果，立即返回
                if (!empty($result)) {
                    if ($getFirst) {
                        return $result;
                    } else {
                        $data[] = $result;
                    }
                }
            } catch (\Throwable) {
                // 记录错误但继续尝试下一个选择器
                // 可以选择记录日志
                continue;
            }
        }

        return $data;
    }

    /**
     * 处理正则表达式选择器
     *
     * 根据 extractMode 参数决定提取模式：
     * - null 或 'elements': 返回匹配的元素（默认）
     * - 'text': 提取匹配的文本内容
     * - 'attr': 提取匹配的属性值
     * - 'match': 使用 regexMatch 提取所有匹配项
     * - 'table': 提取表格数据（返回结构化格式）
     *
     * 重要特性：
     * - 支持正则表达式分组捕获
     * - 支持指定分组索引提取特定分组数据
     * - 支持从属性值中提取数据
     * - 支持表格数据提取（处理HTML换行、制表符等）
     * - 支持 location 参数：可以指定提取正则表达式匹配结果中的特定分组，返回关联数组
     *
     * location 参数格式：
     * ```php
     * [
     *     'fieldName1' => [
     *         'index' => 0,              // 分组索引
     *         'description' => '描述'    // 可选
     *     ],
     *     'fieldName2' => [
     *         'index' => 1,
     *         'description' => '描述'
     *     ]
     * ]
     * ```
     *
     * @param  string  $pattern  正则表达式模式
     * @param  DOMElement|null  $contextNode  上下文节点
     * @param  string|null  $attribute  属性名
     * @param  string|null  $extractMode  提取模式
     * @param  int|null  $group  分组索引（仅对 'match' 模式有效）
     * @param  array|null  $location  位置配置（用于提取指定分组并返回关联数组）
     * @return array<int, Element|string|array> 匹配结果
     *
     * @example
     * // 提取所有匹配的元素
     * $elements = $this->handleRegexSelector('/\d{4}-\d{2}-\d{2}/');
     *
     * // 提取文本内容
     * $texts = $this->handleRegexSelector('/\d{4}-\d{2}-\d{2}/', null, null, 'text');
     *
     * // 提取属性值
     * $urls = $this->handleRegexSelector('/https?:\/\/.+/', null, 'href', 'attr', 'href');
     *
     * // 提取分组数据
     * $groups = $this->handleRegexSelector('/(\w+):\s*(\d+)/', null, null, 'match', 1);
     *
     * // 使用 location 参数提取多个字段
     * $results = $this->handleRegexSelector('/(\w+)\s*[:：]\s*(\d+)/', null, null, null, null, [
     *     'name' => ['index' => 1, 'description' => '姓名'],
     *     'age' => ['index' => 2, 'description' => '年龄']
     * ]);
     * // 返回: [['name' => '张三', 'age' => '30'], ['name' => '李四', 'age' => '25'], ...]
     */
    protected function handleRegexSelector(
        string $pattern,
        ?DOMElement $contextNode = null,
        ?string $attribute = null,
        ?string $extractMode = null,
        ?int $group = null,
        ?array $location = null
    ): array {
        // 如果指定了 location 参数，使用位置提取模式
        if ($location !== null && is_array($location) && !empty($location)) {
            return $this->extractWithLocation($pattern, $contextNode, $attribute, $location);
        }

        // 如果没有指定 extractMode 或者是 'elements'，返回匹配的元素
        if ($extractMode === null || strcasecmp($extractMode, 'elements') === 0) {
            return $this->findByRegex($pattern, $contextNode, $attribute);
        }

        // 提取文本内容
        if (strcasecmp($extractMode, 'text') === 0) {
            $matches = $this->regexMatch($pattern, $contextNode, $attribute);
            // 如果指定了分组索引，只返回该分组的值
            if ($group !== null) {
                return array_map(function($match) use ($group) {
                    if (is_array($match) && isset($match[$group])) {
                        return $match[$group];
                    }
                    return $match;
                }, $matches);
            }
            return $matches;
        }

        // 提取属性值
        if (strcasecmp($extractMode, 'attr') === 0 && $attribute !== null) {
            $matches = $this->regexMatch($pattern, $contextNode, $attribute);
            // 如果指定了分组索引，只返回该分组的值
            if ($group !== null) {
                return array_map(function($match) use ($group) {
                    if (is_array($match) && isset($match[$group])) {
                        return $match[$group];
                    }
                    return $match;
                }, $matches);
            }
            return $matches;
        }

        // 使用 regexMatch 提取所有匹配项
        if (strcasecmp($extractMode, 'match') === 0) {
            $matches = $this->regexMatch($pattern, $contextNode, $attribute);
            // 如果指定了分组索引，只返回该分组的值
            if ($group !== null) {
                return array_map(function($match) use ($group) {
                    if (is_array($match) && isset($match[$group])) {
                        return $match[$group];
                    }
                    return $match;
                }, $matches);
            }
            return $matches;
        }

        // 默认返回匹配的元素
        return $this->findByRegex($pattern, $contextNode, $attribute);
    }

    /**
     * 使用正则表达式按位置提取数据
     *
     * 根据提供的 location 配置提取正则表达式匹配结果中的特定分组，
     * 返回关联数组，每个数组的键是 location 中定义的字段名。
     *
     * @param  string  $pattern  正则表达式模式
     * @param  DOMElement|null  $contextNode  上下文节点
     * @param  string|null  $attribute  属性名（如果提供则从属性值中提取）
     * @param  array<string, array{index: int, description?: string}>  $location  位置配置
     * @return array<int, array<string, string>> 关联数组结果
     *
     * @example
     * // 提取姓名和年龄
     * $results = $this->extractWithLocation('/(\w+)\s*[:：]\s*(\d+)/', null, null, [
     *     'name' => ['index' => 1, 'description' => '姓名'],
     *     'age' => ['index' => 2, 'description' => '年龄']
     * ]);
     * // 返回: [['name' => '张三', 'age' => '30'], ['name' => '李四', 'age' => '25'], ...]
     *
     * // 从HTML中提取链接文本和URL
     * $results = $this->extractWithLocation('/<a[^>]*href="([^"]+)"[^>]*>([^<]+)<\/a>/is', null, null, [
     *     'href' => ['index' => 1, 'description' => '链接地址'],
     *     'text' => ['index' => 2, 'description' => '链接文本']
     * ]);
     */
    protected function extractWithLocation(
        string $pattern,
        ?DOMElement $contextNode,
        ?string $attribute,
        array $location
    ): array {
        $result = [];

        // 验证正则表达式
        $errorReporting = error_reporting(0);
        @preg_match($pattern, '');
        error_reporting($errorReporting);

        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new RuntimeException(sprintf('无效的正则表达式: "%s"。错误代码: %d', $pattern, preg_last_error()));
        }

        // 获取文本内容或属性值
        if ($attribute !== null) {
            // 从指定属性中提取
            $elements = $this->findByRegex($pattern, $contextNode, $attribute);
            $textContent = '';
            foreach ($elements as $element) {
                if ($element instanceof Element && $element->hasAttribute($attribute)) {
                    $textContent .= $element->getAttribute($attribute) . "\n";
                }
            }
        } else {
            // 从文本内容中提取
            $textContent = $this->html();
        }

        // 执行正则表达式匹配
        if (preg_match_all($pattern, $textContent, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        // 对每个匹配结果，提取指定位置的数据
        foreach ($matches as $match) {
            $item = [];
            foreach ($location as $fieldName => $config) {
                $index = $config['index'] ?? 0;
                if (isset($match[$index])) {
                    $item[$fieldName] = $match[$index];
                } else {
                    $item[$fieldName] = '';
                }
            }
            if (!empty($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * 使用选择器数组回退查找第一个元素
     *
     * 此方法是 findWithFallback() 的便捷版本，只返回第一个匹配的元素。
     *
     * @param  array<int, array{selector: string, type?: string, attribute?: string}>  $selectors  选择器数组
     * @param  DOMElement|null  $contextNode  上下文节点（可选）
     * @return Element|null 第一个匹配的元素或null
     *
     * @example
     * $element = $doc->findFirstWithFallback([
     *     ['selector' => '.main-title'],
     *     ['selector' => '//h1[@class="title"]', 'type' => 'xpath'],
     *     ['selector' => '/html/body/h1', 'type' => 'xpath']
     * ]);
     */
    public function findFirstWithFallback(
        array $selectors,
        ?DOMElement $contextNode = null
    ): ?Element {
        $results = $this->findWithFallback($selectors, $contextNode);

        if (empty($results)) {
            return null;
        }

        // 返回第一个结果（排除字符串类型的结果，如 ::text 或 ::attr）
        foreach ($results as $result) {
            if ($result instanceof Element) {
                return $result;
            }
        }

        return null;
    }

    /**
     * 查询选择器数组, 是 findWithFallback 获取列表的快捷方式
     * @param array $selectors
     * @param DOMElement|null $contextNode
     * @return array<int, Element|string> 匹配的元素数组或文本/属性值数组
     */
    public function queryWithFallback(
        array $selectors,
        ?DOMElement $contextNode = null
    ): array {
        return $this->findWithFallback($selectors, $contextNode, false);
    }

    /**
     * 按路径查找元素（支持绝对路径和相对路径）
     *
     * 此方法支持类似文件系统的路径语法来定位元素，提供完整的全路径选择能力：
     * - /html/body/div[1] - XPath 绝对路径
     * - //div[@class="item"] - XPath 相对路径
     * - div/div/span - CSS 路径（从根开始）
     * - div[@class="container"]/div - 带XPath条件的路径
     * - div.content > div.pages-date > span - CSS 组合选择器路径
     *
     * 全路径选择器优势：
     * 1. 精确定位：使用完整的DOM路径，避免歧义
     * 2. 高效查询：直接定位到目标元素，减少搜索范围
     * 3. 灵活组合：支持CSS选择器和XPath表达式的混合使用
     *
     * @param  string  $path  元素路径（CSS选择器或XPath表达式）
     * @param  bool  $relative  是否为相对路径（默认false，表示绝对路径）
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * // XPath 绝对路径（从根元素开始）
     * $elements = $doc->findByPath('/html/body/div[3]/div[1]/div/div[1]/span');
     *
     * // XPath 相对路径（任意位置）
     * $elements = $doc->findByPath('//div[@class="item"]/span');
     *
     * // CSS 组合选择器路径
     * $elements = $doc->findByPath('div.content > div.pages-date > span');
     *
     * // 混合使用CSS和XPath
     * $elements = $doc->findByPath('div.container/div[@class="item"]/a');
     */
    public function findByPath(string $path, bool $relative = false): array
    {
        try {
            // 如果是相对路径，转换为绝对路径
            if (!$relative) {
                // 确保以 / 开头
                if (!str_starts_with($path, '/')) {
                    $path = '/' . $path;
                }
            }

            // 将路径转换为 XPath
            $xpathExpression = $this->pathToXPath($path);
            return $this->xpath($xpathExpression);
        } catch (\Exception) {
            // 如果路径解析失败，尝试作为CSS选择器处理
            return $this->find($path);
        }
    }

    /**
     * 将路径表达式转换为 XPath
     *
     * 智能识别路径类型并进行转换：
     * - XPath 路径（以 / 或 // 开头）：直接返回
     * - CSS 选择器：转换为 XPath 表达式
     * - 混合路径：保留 XPath 条件，转换 CSS 选择器部分
     *
     * 全路径选择器支持：
     * 1. 纯 XPath 绝对路径：/html/body/div[1]/div[2]/span
     * 2. 纯 XPath 相对路径：//div[@class="container"]/p
     * 3. CSS 全路径：div.container > div.content > p.title
     * 4. 混合路径：div.container/div[@class="item"]/a
     *
     * @param  string  $path  路径表达式
     * @return string XPath 表达式
     *
     * @example
     * // XPath 路径，直接返回
     * $xpath = $this->pathToXPath('/html/body/div[1]');
     *
     * // CSS 路径，转换为 XPath
     * $xpath = $this->pathToXPath('div.container > p');
     *
     * // 混合路径
     * $xpath = $this->pathToXPath('//div[@class="item"]/p');
     *
     * // 复杂全路径
     * $xpath = $this->pathToXPath('/html/body/div[3]/div[@class="content"]/h1');
     */
    protected function pathToXPath(string $path): string
    {
        // 检测 XPath 绝对路径（以单个 / 开头）
        if (preg_match('/^\/(?!\/)/', $path)) {
            // 确保是有效的 XPath 路径
            if ($this->isValidXPath($path)) {
                return $path;
            }
        }

        // 检测 XPath 相对路径（以 // 开头）
        if (preg_match('/^\/\//', $path)) {
            if ($this->isValidXPath($path)) {
                return $path;
            }
        }

        // 如果是 CSS 路径（使用 >、空格等组合器），转换为 XPath
        try {
            $xpath = Query::cssToXpath($path);

            // 如果原始路径以 / 开头，确保 XPath 也是绝对路径
            if (str_starts_with($path, '/') && !str_starts_with($xpath, '/')) {
                $xpath = '/html' . $xpath;
            }

            return $xpath;
        } catch (\Exception) {
            // 如果转换失败，直接返回原路径
            return $path;
        }
    }

    /**
     * 验证是否为有效的 XPath 表达式
     *
     * 此方法检查字符串是否包含 XPath 特征，用于区分 CSS 选择器和 XPath 表达式。
     *
     * @param  string  $expression  表达式
     * @return bool 如果是有效的 XPath 表达式返回 true
     *
     * @example
     * $isValid = $doc->isValidXPath('/html/body/div');          // true
     * $isValid = $doc->isValidXPath('//div[@class="item"]');     // true
     * $isValid = $doc->isValidXPath('div.container');          // false
     * $isValid = $doc->isValidXPath('.class');                  // false
     */
    protected function isValidXPath(string $expression): bool
    {
        // XPath 特征：包含 @、//、/text()、/comment()、/node() 等
        $xpathPatterns = [
            '/\[@/',           // 属性选择器 [@attr="value"]
            '/\/\//',          // 相对路径 //
            '/\/text\(\)/i',   // text() 函数
            '/\/comment\(\)/i',// comment() 函数
            '/\/node\(\)/i',   // node() 函数
            '/position\(\)/i',  // position() 函数
            '/last\(\)/i',     // last() 函数
            '/count\(/i',      // count() 函数
            '/contains\(/i',    // contains() 函数
            '/starts-with\(/i',// starts-with() 函数
            '/string-length\(/i' // string-length() 函数
        ];

        foreach ($xpathPatterns as $pattern) {
            if (preg_match($pattern, $expression)) {
                return true;
            }
        }

        // 检查是否是路径结构（包含 / 和 [n]）
        if (preg_match('/^\/[a-z0-9_\/\[\]@\=\s\"\'\(\)\.]+$/i', $expression)) {
            return true;
        }

        return false;
    }

    /**
     * 获取所有匹配的文本内容（包括嵌套元素）
     *
     * @param  string  $selector  选择器表达式
     * @param  string  $type  选择器类型
     * @param  bool  $trim  是否去除空白
     * @param  bool  $unique  是否去重
     * @return array<int, string> 文本数组
     */
    public function allTexts(string $selector, string $type = Query::TYPE_CSS, bool $trim = true, bool $unique = false): array
    {
        $texts = [];
        $elements = $this->find($selector, $type);

        foreach ($elements as $element) {
            $text = $element instanceof Element ? $element->text() : (string)$element;
            if ($trim) {
                $text = trim($text);
            }
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $unique ? array_values(array_unique($texts)) : $texts;
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
     * 获取元素的直接文本节点（不包括子元素的文本）
     *
     * 此方法用于获取未被任何标签包围的纯文本内容
     * 支持CSS选择器和XPath选择器
     *
     * @param  string  $selector  选择器表达式
     * @param  string  $type  选择器类型（CSS 或 XPath）
     * @return array<int, string> 直接文本节点数组
     *
     * @example
     * // 获取 div.content 的直接文本（不包括子元素的文本）
     * $texts = $doc->directText('div.content');
     *
     * // 使用 XPath
     * $texts = $doc->directText('//div[@class="content"]', Query::TYPE_XPATH);
     */
    public function directText(string $selector, string $type = Query::TYPE_CSS): array
    {
        // 转换CSS选择器为XPath
        if (strcasecmp($type, Query::TYPE_CSS) === 0) {
            $selector = Query::cssToXpath($selector);
        }

        return $this->doFindTextNodes($selector);
    }

    /**
     * 获取元素的所有文本节点（包括子元素的文本）
     *
     * @param  string  $selector  选择器表达式
     * @param  string  $type  选择器类型（CSS 或 XPath）
     * @param  bool  $trim  是否去除空白
     * @return array<int, string> 所有文本节点数组
     *
     * @example
     * // 获取 div.content 的所有文本
     * $texts = $doc->allTextNodes('div.content');
     *
     * // 使用 XPath 获取所有文本节点（包括text()函数的结果）
     * $texts = $doc->allTextNodes('//div[@class="content"]//text()', Query::TYPE_XPATH);
     */
    public function allTextNodes(string $selector, string $type = Query::TYPE_CSS, bool $trim = true): array
    {
        $xpathExpression = $selector;

        // 如果是CSS选择器，转换为XPath
        if (strcasecmp($type, Query::TYPE_CSS) === 0) {
            $xpathExpression = Query::cssToXpath($selector);
        }

        // 如果是XPath且包含//text()，直接查询
        if (str_contains($xpathExpression, '//text()')) {
            $result = [];
            $nodeList = $this->createXpath()->query($xpathExpression);

            if ($nodeList) {
                foreach ($nodeList as $node) {
                    $text = $node->nodeValue ?? '';
                    if ($trim) {
                        $text = trim($text);
                    }
                    if ($text !== '') {
                        $result[] = $text;
                    }
                }
            }
            return $result;
        }

        // 否则获取元素的文本内容
        return $this->allTexts($xpathExpression, Query::TYPE_XPATH, $trim, false);
    }

    /**
     * 查找包含指定文本的第一个元素
     *
     * @param  string  $text  要查找的文本
     * @param  string  $selector  选择器表达式（默认为所有元素）
     * @return Element|null 匹配的元素或null
     *
     * @example
     * $element = $doc->findFirstByText('Hello');
     * $element = $doc->findFirstByText('内容', '.content');
     */
    public function findFirstByText(string $text, string $selector = '*'): ?Element
    {
        $elements = $this->findByText($text, $selector);
        return $elements[0] ?? null;
    }

    /**
     * 查找包含指定文本（不区分大小写）的第一个元素
     *
     * @param  string  $text  要查找的文本
     * @param  string  $selector  选择器表达式（默认为所有元素）
     * @return Element|null 匹配的元素或null
     *
     * @example
     * $element = $doc->findFirstByTextIgnoreCase('hello');
     */
    public function findFirstByTextIgnoreCase(string $text, string $selector = '*'): ?Element
    {
        $elements = $this->findByTextIgnoreCase($text, $selector);
        return $elements[0] ?? null;
    }

    /**
     * 查找具有指定属性值的第一个元素
     *
     * @param  string  $attribute  属性名
     * @param  string|null  $value  属性值（如果为null则只检查属性存在）
     * @param  string  $selector  选择器表达式（默认为所有元素）
     * @return Element|null 匹配的元素或null
     *
     * @example
     * $element = $doc->findFirstByAttribute('id', 'container');
     * $element = $doc->findFirstByAttribute('data-id');
     */
    public function findFirstByAttribute(string $attribute, ?string $value = null, string $selector = '*'): ?Element
    {
        $elements = $this->findByAttribute($attribute, $value, $selector);
        return $elements[0] ?? null;
    }

    /**
     * 查找包含指定属性值的第一个元素
     *
     * @param  string  $attribute  属性名
     * @param  string  $value  属性值
     * @param  string  $selector  选择器表达式（默认为所有元素）
     * @return Element|null 匹配的元素或null
     *
     * @example
     * $element = $doc->findFirstByAttributeContains('class', 'active');
     */
    public function findFirstByAttributeContains(string $attribute, string $value, string $selector = '*'): ?Element
    {
        $elements = $this->findByAttributeContains($attribute, $value, $selector);
        return $elements[0] ?? null;
    }

    /**
     * 查找属性值以指定前缀开头的第一个元素
     *
     * @param  string  $attribute  属性名
     * @param  string  $prefix  前缀
     * @param  string  $selector  选择器表达式（默认为所有元素）
     * @return Element|null 匹配的元素或null
     *
     * @example
     * $element = $doc->findFirstByAttributeStartsWith('href', 'https://');
     */
    public function findFirstByAttributeStartsWith(string $attribute, string $prefix, string $selector = '*'): ?Element
    {
        $elements = $this->findByAttributeStartsWith($attribute, $prefix, $selector);
        return $elements[0] ?? null;
    }

    /**
     * 查找属性值以指定后缀结尾的第一个元素
     *
     * @param  string  $attribute  属性名
     * @param  string  $suffix  后缀
     * @param  string  $selector  选择器表达式（默认为所有元素）
     * @return Element|null 匹配的元素或null
     *
     * @example
     * $element = $doc->findFirstByAttributeEndsWith('href', '.pdf');
     */
    public function findFirstByAttributeEndsWith(string $attribute, string $suffix, string $selector = '*'): ?Element
    {
        $elements = $this->findByAttributeEndsWith($attribute, $suffix, $selector);
        return $elements[0] ?? null;
    }

    /**
     * 查找最后匹配的元素
     *
     * @param  string  $selector  选择器表达式
     * @param  string  $type  选择器类型
     * @return Element|null 匹配的元素或null
     *
     * @example
     * $element = $doc->findLast('li');
     * $element = $doc->findLast('.item');
     */
    public function findLast(string $selector, string $type = Query::TYPE_CSS): ?Element
    {
        $elements = $this->find($selector, $type);
        if (empty($elements)) {
            return null;
        }
        return end($elements);
    }

    /**
     * 查找指定范围内的元素
     *
     * @param  string  $selector  选择器表达式
     * @param  int  $start  起始索引（从0开始）
     * @param  int  $end  结束索引（不包含）
     * @param  string  $type  选择器类型
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * $elements = $doc->findRange('li', 0, 3); // 获取前3个li元素
     * $elements = $doc->findRange('li', 5, 10); // 获取索引5-9的li元素
     */
    public function findRange(string $selector, int $start, int $end, string $type = Query::TYPE_CSS): array
    {
        $elements = $this->find($selector, $type);
        return array_slice($elements, $start, $end - $start);
    }

    /**
     * 查找包含指定HTML内容的元素
     *
     * @param  string  $html  HTML内容
     * @param  string  $selector  选择器表达式（默认为所有元素）
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * $elements = $doc->findByHtml('<span class="highlight">');
     */
    public function findByHtml(string $html, string $selector = '*'): array
    {
        $elements = $this->find($selector);
        $result = [];

        foreach ($elements as $element) {
            if ($element instanceof Element && str_contains($element->html(), $html)) {
                $result[] = $element;
            }
        }

        return $result;
    }

    /**
     * 查找包含指定HTML内容的第一个元素
     *
     * @param  string  $html  HTML内容
     * @param  string  $selector  选择器表达式（默认为所有元素）
     * @return Element|null 匹配的元素或null
     *
     * @example
     * $element = $doc->findFirstByHtml('<span class="highlight">');
     */
    public function findFirstByHtml(string $html, string $selector = '*'): ?Element
    {
        $elements = $this->findByHtml($html, $selector);
        return $elements[0] ?? null;
    }

    /**
     * 使用 XPath 获取单个元素
     *
     * 此方法返回匹配XPath表达式的第一个元素，是 xpath() 方法的便捷版本。
     * 适用于只需要单个匹配结果的场景。
     *
     * @param  string  $xpathExpression  XPath 表达式
     * @return Element|null 匹配的元素或null
     *
     * @example
     * // 获取单个元素
     * $element = $doc->xpathFirst('//div[@class="container"]');
     * $element = $doc->xpathFirst('//li[1]');
     *
     * // 使用全路径获取
     * $element = $doc->xpathFirst('/html/body/div[1]/div[2]/span');
     *
     * // 使用复杂条件
     * $element = $doc->xpathFirst('//div[@class="item" and @data-active="true"]');
     */
    public function xpathFirst(string $xpathExpression): ?Element
    {
        $elements = $this->xpath($xpathExpression);
        return $elements[0] ?? null;
    }

    /**
     * 使用正则表达式查找元素（便捷别名）
     *
     * @param  string  $pattern  正则表达式模式
     * @param  string|null  $attribute  要匹配的属性名（可选）
     * @return array<int, Element> 匹配的元素数组
     *
     * @example
     * $elements = $doc->regexFind('/test/');
     * $elements = $doc->regexFind('/\d+/', 'data-id');
     */
    public function regexFind(string $pattern, ?string $attribute = null): array
    {
        return $this->findByRegex($pattern, null, $attribute);
    }

    /**
     * 使用正则表达式查找第一个元素
     *
     * @param  string  $pattern  正则表达式模式
     * @param  string|null  $attribute  要匹配的属性名（可选）
     * @return Element|null 匹配的元素或null
     *
     * @example
     * $element = $doc->regexFirst('/\d{4}-\d{2}-\d{2}/');
     * $element = $doc->regexFirst('/example\.com/', 'href');
     */
    public function regexFirst(string $pattern, ?string $attribute = null): ?Element
    {
        $elements = $this->findByRegex($pattern, null, $attribute);
        return $elements[0] ?? null;
    }

    /**
     * 使用 XPath 获取文本内容
     *
     * 此方法专门用于获取文本节点，支持 text() 函数和节点类型选择。
     * 适用于需要提取纯文本内容的场景。
     *
     * @param  string  $xpathExpression  XPath 表达式
     * @return array<int, string> 文本内容数组
     *
     * @example
     * // 获取元素的直接文本节点
     * $texts = $doc->xpathTexts('//div[@class="item"]/text()');
     *
     * // 获取所有文本节点（包括后代）
     * $texts = $doc->xpathTexts('//div[@class="content"]//text()');
     *
     * // 获取段落文本
     * $texts = $doc->xpathTexts('//p/text()');
     *
     * // 使用全路径获取文本
     * $texts = $doc->xpathTexts('/html/body/div[1]/p/text()');
     */
    public function xpathTexts(string $xpathExpression): array
    {
        $nodeList = $this->createXpath()->query($xpathExpression);

        $result = [];
        if ($nodeList) {
            foreach ($nodeList as $node) {
                $text = trim($node->nodeValue ?? '');
                if ($text !== '') {
                    $result[] = $text;
                }
            }
        }

        return $result;
    }

    /**
     * 使用 XPath 获取属性值
     *
     * 此方法专门用于获取元素的属性值，是属性提取的便捷方法。
     * 返回匹配XPath表达式的所有元素的指定属性值数组。
     *
     * @param  string  $xpathExpression  XPath 表达式
     * @param  string  $attributeName  属性名
     * @return array<int, string|null> 属性值数组
     *
     * @example
     * // 获取所有链接的 href
     * $hrefs = $doc->xpathAttrs('//a', 'href');
     *
     * // 获取所有图片的 src
     * $srcs = $doc->xpathAttrs('//img', 'src');
     *
     * // 使用条件过滤
     * $hrefs = $doc->xpathAttrs('//a[contains(@class, "external")]', 'href');
     *
     * // 使用全路径
     * $hrefs = $doc->xpathAttrs('/html/body/div[1]/ul/li/a', 'href');
     */
    public function xpathAttrs(string $xpathExpression, string $attributeName): array
    {
        $nodeList = $this->createXpath()->query($xpathExpression);

        $result = [];
        if ($nodeList) {
            foreach ($nodeList as $node) {
                if ($node instanceof DOMElement && $node->hasAttribute($attributeName)) {
                    $result[] = $node->getAttribute($attributeName);
                }
            }
        }

        return $result;
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

    /**
     * 提取HTML表格数据（通用方法）
     *
     * 此方法从HTML表格中提取数据，返回多维数组格式。
     * 支持CSS选择器、XPath选择器、正则表达式和直接传入Element对象。
     * 自动处理HTML中的换行、制表符等空白字符。
     * 无论使用何种选择器类型，都统一返回完整的表格数据（每行每列）。
     *
     * 重要特性：
     * - 当选择器匹配多个表格时，自动返回所有表格的数据
     * - 默认返回带有 thead 和 tbody 结构的数组格式
     * - 每个表格数据包含表头(thead)和表体(tbody)数据
     * - 支持多种返回格式以满足不同需求
     *
     * @param  string|Element|null  $table  表格选择器、Element对象或null（null表示提取所有表格）
     *                                     选择器格式：
     *                                     - CSS选择器：'table', '#myTable', 'table.data-table'
     *                                     - XPath选择器：'//table[@id="myTable"]', '//div//table[1]'
     *                                     - 正则表达式：'/(<table[^>]*>.*?<\/table>)/is'
     *                                     - Element对象：$doc->first('table')
     * @param  array<string, mixed>  $options  提取选项
     *                                     - 'selectorType': 选择器类型（'css'/'xpath'/'regex'/'element'），默认自动检测
     *                                     - 'headerRow': 表头行索引（0-based），默认0
     *                                     - 'skipRows': 跳过的行数，默认0
     *                                     - 'includeHeader': 是否包含表头数据，默认true
     *                                     - 'includeHeaderAsFirstRow': 是否将表头作为第一行返回，默认false
     *                                     - 'trimText': 是否修剪空白字符，默认true
     *                                     - 'removeEmpty': 是否移除空行，默认true
     *                                     - 'cellSelector': 单元格选择器，默认'td, th'
     *                                     - 'rowSelector': 行选择器，默认'tr'
     *                                     - 'returnFormat': 返回格式（'associative'/'indexed'/'both'/'structured'），默认'structured'
     *                                     - 'preserveStructure': 是否保留表格结构（thead/tbody/tfoot），默认true
     *                                     - 'returnAllTables': 当选择器匹配多个表格时是否全部返回，默认true
     *                                     - 'tableIndex': 指定返回第几个表格（0-based），null表示返回所有
     * @return array<int, array<string, mixed>> 表格数据数组
     *                                     - structured格式（默认）: [
     *                                         0 => [
     *                                             'thead' => ['姓名', '性别', '国籍', '手机号'],
     *                                             'tbody' => [
     *                                                 0 => ['张三', '男', '中国', '183xxx'],
     *                                                 1 => ['jick liu', '男', '英国', '163xxx']
     *                                             ]
     *                                         ],
     *                                         1 => [...] // 第二个表格
     *                                     ]
     *                                     - associative格式: [['姓名' => '张三', '年龄' => '30'], ...]
     *                                     - indexed格式: [['张三', '30'], ...]
     *                                     - both格式: [['headers' => [...], 'rows' => [[...], ...]]
     *
     * @example
     * // 提取所有表格（默认结构化格式）
     * $allTables = $doc->extractTable();
     * // 返回: [表格1数据, 表格2数据, ...]
     *
     * // CSS选择器 - 提取匹配的所有表格（可能有多个）
     * $tableData = $doc->extractTable('table.cherry-table');
     * // 如果页面有多个 table.cherry-table，返回所有表格的数据
     * // 每个表格包含 thead 和 tbody
     *
     * // 只返回第一个匹配的表格
     * $tableData = $doc->extractTable('table.data-table', ['tableIndex' => 0]);
     *
     * // 返回第三个匹配的表格
     * $tableData = $doc->extractTable('table.data-table', ['tableIndex' => 2]);
     *
     * // XPath选择器
     * $tableData = $doc->extractTable('//table[@class="data-table"]');
     *
     * // 正则表达式 - 提取所有匹配的表格
     * $tableData = $doc->extractTable('/<table[^>]*class="data"[^>]*>.*?<\/table>/is');
     *
     * // Element对象
     * $tableElement = $doc->first('table');
     * $tableData = $doc->extractTable($tableElement);
     *
     * // 使用 indexed 格式
     * $tableData = $doc->extractTable('table', ['returnFormat' => 'indexed']);
     *
     * // 不保留结构
     * $tableData = $doc->extractTable('table', ['preserveStructure' => false]);
     */
    public function extractTable(string|Element|null $table = null, array $options = []): array
    {
        // 合并默认选项
        $defaultOptions = [
            'selectorType' => 'auto',  // 自动检测选择器类型
            'headerRow' => 0,
            'skipRows' => 0,
            'includeHeader' => true,
            'includeHeaderAsFirstRow' => false,
            'trimText' => true,
            'removeEmpty' => true,
            'cellSelector' => 'td, th',
            'rowSelector' => 'tr',
            'returnFormat' => 'structured',  // 默认返回结构化格式
            'preserveStructure' => true,  // 默认保留结构
            'returnAllTables' => true,  // 默认返回所有匹配的表格
            'tableIndex' => null  // 指定表格索引，null表示返回所有
        ];
        $options = array_merge($defaultOptions, $options);

        // 获取表格元素
        if ($table === null) {
            // 提取所有表格
            $tables = $this->find('table');
            if (empty($tables)) {
                return [];
            }

            // 指定表格索引
            if ($options['tableIndex'] !== null) {
                $index = (int)$options['tableIndex'];
                if (isset($tables[$index])) {
                    return [$this->extractSingleTableStructured($tables[$index], $options)];
                }
                return [];
            }

            // 返回所有表格的数据（结构化格式）
            return array_map(function($tableItem) use ($options) {
                if ($options['preserveStructure'] && $options['returnFormat'] === 'structured') {
                    return $this->extractSingleTableStructured($tableItem, $options);
                } else {
                    return $this->extractSingleTable($tableItem, $options);
                }
            }, $tables);
        } elseif ($table instanceof Element) {
            // 从Element对象提取（单个表格）
            if ($options['preserveStructure'] && $options['returnFormat'] === 'structured') {
                return [$this->extractSingleTableStructured($table, $options)];
            } else {
                return [$this->extractSingleTable($table, $options)];
            }
        } else {
            // 检测选择器类型并提取表格
            $tableElements = $this->findTableBySelector($table, $options, $options['returnAllTables']);
            
            if (empty($tableElements)) {
                return [];
            }

            // 指定表格索引
            if ($options['tableIndex'] !== null) {
                $index = (int)$options['tableIndex'];
                if (isset($tableElements[$index])) {
                    if ($options['preserveStructure'] && $options['returnFormat'] === 'structured') {
                        return [$this->extractSingleTableStructured($tableElements[$index], $options)];
                    } else {
                        return [$this->extractSingleTable($tableElements[$index], $options)];
                    }
                }
                return [];
            }

            // 返回所有或第一个表格
            if ($options['returnAllTables']) {
                return array_map(function($tableItem) use ($options) {
                    if ($options['preserveStructure'] && $options['returnFormat'] === 'structured') {
                        return $this->extractSingleTableStructured($tableItem, $options);
                    } else {
                        return $this->extractSingleTable($tableItem, $options);
                    }
                }, $tableElements);
            } else {
                // 只返回第一个表格
                $firstTable = $tableElements[0];
                if ($options['preserveStructure'] && $options['returnFormat'] === 'structured') {
                    return [$this->extractSingleTableStructured($firstTable, $options)];
                } else {
                    return [$this->extractSingleTable($firstTable, $options)];
                }
            }
        }
    }

    /**
     * 根据选择器类型查找表格元素
     *
     * @param  string  $selector  选择器
     * @param  array<string, mixed>  $options  提取选项
     * @param  bool  $returnAll  是否返回所有匹配的表格
     * @return array<int, Element>|null 表格元素数组或单个元素
     */
    protected function findTableBySelector(string $selector, array $options, bool $returnAll = false): ?array
    {
        $selectorType = $options['selectorType'];

        // 自动检测选择器类型
        if ($selectorType === 'auto') {
            if (preg_match('/^(\/\/|\/|\.\.\/)/', $selector)) {
                $selectorType = 'xpath';
            } elseif (preg_match('/^\/.*\//', $selector)) {
                $selectorType = 'regex';
            } else {
                $selectorType = 'css';
            }
        }

        // 根据类型查找表格
        switch ($selectorType) {
            case 'xpath':
                // XPath选择器
                $elements = $this->xpath($selector);
                if (!$returnAll) {
                    return !empty($elements) ? [$elements[0]] : null;
                }
                return $elements;

            case 'regex':
                // 正则表达式 - 从HTML中提取表格
                $html = $this->html();
                if (preg_match_all($selector, $html, $matches)) {
                    // 提取所有匹配的表格
                    $tables = [];
                    foreach ($matches[0] as $tableHtml) {
                        $tempDoc = new Document($tableHtml);
                        $table = $tempDoc->first('table');
                        if ($table !== null) {
                            $tables[] = $table;
                        }
                    }
                    if (!$returnAll) {
                        return !empty($tables) ? [$tables[0]] : null;
                    }
                    return $tables;
                }
                return null;

            case 'css':
            default:
                // CSS选择器（默认）
                $elements = $this->find($selector);
                if (!$returnAll) {
                    return !empty($elements) ? [$elements[0]] : null;
                }
                return $elements;
        }
    }

    /**
     * 提取单个表格的数据
     *
     * @param  Element  $tableElement  表格元素
     * @param  array<string, mixed>  $options  提取选项
     * @return array<int, array<string|int, string>>|array<int, array<int, array<string|int, string>>> 表格数据
     */
    protected function extractSingleTable(Element $tableElement, array $options): array
    {
        $data = [];
        $headers = [];

        // 是否保留表格结构
        if ($options['preserveStructure']) {
            return $this->extractTableWithStructure($tableElement, $options);
        }

        // 获取所有行
        $rows = $tableElement->find($options['rowSelector']);

        if (empty($rows)) {
            return [];
        }

        // 跳过指定的行
        $startIndex = $options['skipRows'];
        $rows = array_slice($rows, $startIndex);

        // 提取表头
        if ($options['includeHeader'] && isset($rows[$options['headerRow']])) {
            $headerRow = $rows[$options['headerRow']];
            $headerCells = $headerRow->find($options['cellSelector']);

            foreach ($headerCells as $cell) {
                $text = $this->cleanCellText($cell->text(), $options['trimText']);
                $headers[] = $text;
            }

            // 移除表头行
            unset($rows[$options['headerRow']]);
            $rows = array_values($rows); // 重新索引
        }

        // 提取数据行
        foreach ($rows as $row) {
            $rowData = [];
            $cells = $row->find($options['cellSelector']);

            foreach ($cells as $index => $cell) {
                $text = $this->cleanCellText($cell->text(), $options['trimText']);

                // 如果有表头，使用表头作为键
                if (!empty($headers) && isset($headers[$index])) {
                    $rowData[$headers[$index]] = $text;
                } else {
                    $rowData[$index] = $text;
                }
            }

            // 检查是否为空行
            if ($options['removeEmpty']) {
                $isEmpty = true;
                foreach ($rowData as $value) {
                    if (!empty(trim($value))) {
                        $isEmpty = false;
                        break;
                    }
                }
                if (!$isEmpty) {
                    $data[] = $rowData;
                }
            } else {
                $data[] = $rowData;
            }
        }

        // 根据返回格式处理结果
        return $this->formatTableData($data, $headers, $options);
    }

    /**
     * 格式化表格数据
     *
     * @param  array<int, array<string|int, string>>  $data  表格数据
     * @param  array<int, string>  $headers  表头
     * @param  array<string, mixed>  $options  选项
     * @return array<int, array<string|int, string>>|array<int, array<int, array<string|int, string>>> 格式化后的数据
     */
    protected function formatTableData(array $data, array $headers, array $options): array
    {
        $returnFormat = $options['returnFormat'];

        // both格式：返回headers和rows
        if ($returnFormat === 'both') {
            $result = [
                'headers' => $headers,
                'rows' => $data
            ];

            // 如果需要将表头作为第一行
            if ($options['includeHeaderAsFirstRow'] && !empty($headers)) {
                $result['rows'] = array_merge([$headers], $data);
            }

            return $result;
        }

        // indexed格式：只返回索引数组
        if ($returnFormat === 'indexed') {
            $indexedData = [];
            foreach ($data as $row) {
                $indexedRow = [];
                foreach ($row as $value) {
                    $indexedRow[] = $value;
                }
                $indexedData[] = $indexedRow;
            }

            // 如果需要将表头作为第一行
            if ($options['includeHeaderAsFirstRow'] && !empty($headers)) {
                $indexedData = array_merge([$headers], $indexedData);
            }

            return $indexedData;
        }

        // associative格式（默认）：返回关联数组
        if ($options['includeHeaderAsFirstRow'] && !empty($headers)) {
            $headerRow = [];
            foreach ($headers as $index => $header) {
                $headerRow[$index] = $header;
            }
            return array_merge([$headerRow], $data);
        }

        return $data;
    }

    /**
     * 提取带结构的表格数据（thead/tbody/tfoot）
     *
     * 重构说明：
     * - thead 返回一维数组，包含所有表头单元格文本
     * - tbody 返回二维数组，每行一个一维数组
     * - tfoot 返回二维数组，每行一个一维数组（如果有）
     * - thead 使用 th 选择器，tbody/tfoot 使用 td 选择器，避免混杂
     *
     * @param  Element  $tableElement  表格元素
     * @param  array<string, mixed>  $options  选项
     * @return array 结构化表格数据
     */
    protected function extractTableWithStructure(Element $tableElement, array $options): array
    {
        $result = [
            'thead' => [],
            'tbody' => []
        ];

        // 提取thead（使用专门的选择器避免混杂）
        $thead = $tableElement->first('thead');
        if ($thead !== null && $options['includeHeader']) {
            $result['thead'] = $this->extractTableHeaders($thead, $options);
        }

        // 提取tbody（可能有多个，但所有行合并到一个数组中）
        $tbodies = $tableElement->find('tbody');
        if (!empty($tbodies)) {
            $result['tbody'] = [];
            foreach ($tbodies as $tbody) {
                $tbodyRows = $tbody->find($options['rowSelector']);
                $tbodyStartIndex = count($result['tbody']);
                foreach ($tbodyRows as $row) {
                    $effectiveRowIndex = $tbodyStartIndex;
                    if ($effectiveRowIndex < $options['skipRows']) {
                        $tbodyStartIndex++;
                        continue;
                    }

                    $rowData = $this->extractRowCells($row, 'td', $options);
                    if (!$this->isEmptyRow($rowData, $options['removeEmpty'])) {
                        $result['tbody'][] = $rowData;
                    }
                    $tbodyStartIndex++;
                }
            }
        } else {
            // 如果没有tbody，直接从table获取所有行（排除thead的行）
            $result['tbody'] = $this->extractTableBodyRows($tableElement, $thead, $options);
        }

        // 提取tfoot
        $tfoot = $tableElement->first('tfoot');
        if ($tfoot !== null) {
            $result['tfoot'] = $this->extractTableFooter($tfoot, $options);
        }

        return $result;
    }

    /**
     * 提取表格表头
     *
     * @param  Element  $thead  表头元素
     * @param  array<string, mixed>  $options  选项
     * @return array<int, string> 表头单元格文本数组
     */
    protected function extractTableHeaders(Element $thead, array $options): array
    {
        $headers = [];
        $headerRowIndex = min($options['headerRow'], count($thead->find($options['rowSelector'])) - 1);
        $headerRows = $thead->find($options['rowSelector']);

        if (!empty($headerRows) && isset($headerRows[$headerRowIndex])) {
            $headerRow = $headerRows[$headerRowIndex];
            $cells = $headerRow->find('th'); // 专门使用 th 选择器

            foreach ($cells as $cell) {
                $text = $this->cleanCellText($cell->text(), $options['trimText']);
                if ($options['removeEmpty'] && empty($text)) {
                    continue;
                }
                $headers[] = $text;
            }
        }

        return $headers;
    }

    /**
     * 提取表格体行
     *
     * @param  Element  $tableElement  表格元素
     * @param  Element|null  $thead  表头元素
     * @param  array<string, mixed>  $options  选项
     * @return array<int, array<int, string>> 表体行数据
     */
    protected function extractTableBodyRows(Element $tableElement, ?Element $thead, array $options): array
    {
        $tbody = [];

        // 获取所有行
        $allRows = $tableElement->find($options['rowSelector']);

        // 获取表头行数
        $theadRowCount = 0;
        if ($thead !== null) {
            $theadRows = $thead->find($options['rowSelector']);
            $theadRowCount = count($theadRows);
        }

        // 提取表体行（跳过表头行和指定的跳过行数）
        foreach ($allRows as $rowIndex => $row) {
            // 跳过表头行
            if ($rowIndex < $theadRowCount) {
                continue;
            }

            // 计算在表体中的相对索引
            $bodyRowIndex = $rowIndex - $theadRowCount;

            // 跳过指定数量的行
            if ($bodyRowIndex < $options['skipRows']) {
                continue;
            }

            // 提取行数据（专门使用 td 选择器）
            $rowData = $this->extractRowCells($row, 'td', $options);

            // 检查是否为空行
            if (!$this->isEmptyRow($rowData, $options['removeEmpty'])) {
                $tbody[] = $rowData;
            }
        }

        return $tbody;
    }

    /**
     * 提取表格页脚
     *
     * @param  Element  $tfoot  页脚元素
     * @param  array<string, mixed>  $options  选项
     * @return array<int, array<int, string>> 页脚行数据
     */
    protected function extractTableFooter(Element $tfoot, array $options): array
    {
        $tfootData = [];
        $tfootRows = $tfoot->find($options['rowSelector']);

        foreach ($tfootRows as $row) {
            $rowData = $this->extractRowCells($row, 'td', $options);
            if (!$this->isEmptyRow($rowData, $options['removeEmpty'])) {
                $tfootData[] = $rowData;
            }
        }

        return $tfootData;
    }

    /**
     * 提取行单元格数据
     *
     * @param  Element  $row  行元素
     * @param  string  $cellSelector  单元格选择器（th 或 td）
     * @param  array<string, mixed>  $options  选项
     * @return array<int, string> 单元格文本数组
     */
    protected function extractRowCells(Element $row, string $cellSelector, array $options): array
    {
        $rowData = [];
        $cells = $row->find($cellSelector);

        foreach ($cells as $cell) {
            $text = $this->cleanCellText($cell->text(), $options['trimText']);
            $rowData[] = $text;
        }

        return $rowData;
    }

    /**
     * 检查行是否为空
     *
     * @param  array<int, string>  $rowData  行数据
     * @param  bool  $removeEmpty  是否移除空行
     * @return bool 是否为空行
     */
    protected function isEmptyRow(array $rowData, bool $removeEmpty): bool
    {
        if (!$removeEmpty) {
            return false;
        }

        foreach ($rowData as $value) {
            if (!empty(trim($value))) {
                return false;
            }
        }
        return true;
    }

    /**
     * 提取单个表格的数据（结构化格式）
     *
     * 重构说明：
     * - thead 返回一维数组，包含所有表头单元格文本
     * - tbody 返回二维数组，每行一个一维数组
     * - tfoot 返回二维数组，每行一个一维数组（如果有）
     * - thead 使用 th 选择器，tbody/tfoot 使用 td 选择器，避免数据混杂
     *
     * @param  Element  $tableElement  表格元素
     * @param  array<string, mixed>  $options  选项
     * @return array<string, array<int, string>|array<int, array<int, string>>> 结构化表格数据
     *                                                                   包含 'thead'、'tbody' 和 'tfoot' 键
     *                                                                   thead: 一维数组，包含表头文本
     *                                                                   tbody: 二维数组，每行一个数组
     *                                                                   tfoot: 二维数组，每行一个数组（如果有）
     *
     * @example
     * // 返回格式：
     * [
     *     'thead' => [
     *         0 => '姓名',
     *         1 => '性别',
     *         2 => '国籍',
     *         3 => '手机号'
     *     ],
     *     'tbody' => [
     *         0 => ['张三', '男', '中国', '183xxx'],
     *         1 => ['jick liu', '男', '英国', '163xxx'],
     *         2 => [...更多行...]
     *     ]
     * ]
     */
    protected function extractSingleTableStructured(Element $tableElement, array $options): array
    {
        $result = [
            'thead' => [],
            'tbody' => []
        ];

        // 提取 thead（表头）- 使用 th 选择器避免混杂
        $thead = $tableElement->first('thead');
        if ($thead !== null && $options['includeHeader']) {
            $result['thead'] = $this->extractTableHeaders($thead, $options);
        }

        // 提取 tbody（表体）- 使用 td 选择器避免混杂
        $tbodies = $tableElement->find('tbody');
        if (!empty($tbodies)) {
            // 从所有 tbody 提取数据
            foreach ($tbodies as $tbody) {
                $tbodyRows = $tbody->find($options['rowSelector']);
                $tbodyStartIndex = count($result['tbody']);

                foreach ($tbodyRows as $row) {
                    $effectiveRowIndex = $tbodyStartIndex;
                    if ($effectiveRowIndex < $options['skipRows']) {
                        $tbodyStartIndex++;
                        continue;
                    }

                    $rowData = $this->extractRowCells($row, 'td', $options);
                    if (!$this->isEmptyRow($rowData, $options['removeEmpty'])) {
                        $result['tbody'][] = $rowData;
                    }
                    $tbodyStartIndex++;
                }
            }
        } else {
            // 如果没有 tbody，直接从 table 获取所有行（排除 thead 的行）
            $result['tbody'] = $this->extractTableBodyRows($tableElement, $thead, $options);
        }

        // 提取 tfoot（页脚）- 使用 td 选择器避免混杂
        $tfoot = $tableElement->first('tfoot');
        if ($tfoot !== null) {
            $result['tfoot'] = $this->extractTableFooter($tfoot, $options);
        }

        return $result;
    }

    /**
     * 获取表格头部的行数
     *
     * @param  Element  $tableElement  表格元素
     * @param  array<string, mixed>  $options  选项
     * @return int  头部行数
     */
    protected function getTableHeaderRowCount(Element $tableElement, array $options): int
    {
        $thead = $tableElement->first('thead');
        if ($thead === null) {
            return 0;
        }

        $theadRows = $thead->find($options['rowSelector']);
        return count($theadRows);
    }

    /**
     * 清理单元格文本
     *
     * 处理HTML中的换行、制表符等空白字符。
     *
     * @param  string  $text  原始文本
     * @param  bool  $trim  是否修剪空白
     * @return string 清理后的文本
     */
    protected function cleanCellText(string $text, bool $trim = true): string
    {
        // 移除多余的空白字符
        $text = preg_replace('/[\r\n\t]+/', ' ', $text); // 换行和制表符替换为空格
        $text = preg_replace('/\s{2,}/', ' ', $text);    // 多个空格合并为一个

        // 移除零宽空格等不可见字符
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);

        // 修剪首尾空白
        if ($trim) {
            $text = trim($text);
        }

        // 解码HTML实体
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $text;
    }

    /**
     * 验证表格数据格式
     *
     * @param  array<string, mixed>  $data  表格数据
     * @return bool 是否有效
     */
    protected function validateTableData(array $data): bool
    {
        // 检查是否包含必要的键
        if (!isset($data['thead']) || !isset($data['tbody'])) {
            return false;
        }

        // 检查thead是否为数组
        if (!is_array($data['thead'])) {
            return false;
        }

        // 检查tbody是否为数组
        if (!is_array($data['tbody'])) {
            return false;
        }

        return true;
    }

    /**
     * 规范化表格数据
     *
     * @param  array<string, mixed>  $data  表格数据
     * @return array<string, mixed> 规范化后的数据
     */
    protected function normalizeTableData(array $data): array
    {
        $normalized = [
            'thead' => [],
            'tbody' => [],
            'tfoot' => $data['tfoot'] ?? []
        ];

        // 规范化 thead - 确保是一维数组
        if (isset($data['thead'])) {
            if (empty($data['thead'])) {
                $normalized['thead'] = [];
            } elseif (is_array($data['thead']) && isset($data['thead'][0]) && is_array($data['thead'][0])) {
                // 如果是二维数组，提取第一行作为表头
                $normalized['thead'] = $data['thead'][0] ?? [];
            } else {
                $normalized['thead'] = $data['thead'];
            }
        }

        // 规范化 tbody - 确保是二维数组
        if (isset($data['tbody'])) {
            if (empty($data['tbody'])) {
                $normalized['tbody'] = [];
            } elseif (is_array($data['tbody'])) {
                foreach ($data['tbody'] as $row) {
                    if (is_array($row)) {
                        $normalized['tbody'][] = $row;
                    } else {
                        $normalized['tbody'][] = [$row];
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * 批量提取多个表格数据
     *
     * 重构说明：支持新的结构化格式，返回格式标准化
     *
     * @param  string  $selector  表格选择器
     * @param  array<string, mixed>  $options  提取选项
     * @return array<int, array<string, mixed>> 所有表格数据
     *
     * @example
     * $allTables = $doc->extractAllTables('table.data-table');
     * // 返回: [[thead=>[], tbody=>[]], [thead=>[], tbody=>[]], ...]
     */
    public function extractAllTables(string $selector = 'table', array $options = []): array
    {
        $tables = $this->find($selector);
        $allData = [];

        foreach ($tables as $table) {
            if ($options['preserveStructure'] && $options['returnFormat'] === 'structured') {
                $tableData = $this->extractSingleTableStructured($table, $options);
            } else {
                $tableData = $this->extractSingleTable($table, $options);
            }

            // 验证和规范化数据
            if ($this->validateTableData($tableData)) {
                $normalizedData = $this->normalizeTableData($tableData);
                $allData[] = $normalizedData;
            }
        }

        return $allData;
    }

    /**
     * 通过选择器提取表格
     *
     * 支持CSS、XPath和正则表达式选择器
     *
     * @param  string  $selector  选择器（CSS、XPath或正则）
     * @param  array<string, mixed>  $options  提取选项
     * @return array<int, array<string, mixed>> 表格数据数组
     */
    public function extractTableBySelector(string $selector, array $options = []): array
    {
        return $this->extractTable($selector, $options);
    }

    /**
     * 通过属性查找并提取表格
     *
     * @param  string  $attr  属性名
     * @param  string|null  $value  属性值（可选）
     * @param  array<string, mixed>  $options  提取选项
     * @return array<int, array<string, mixed>> 表格数据数组
     */
    public function extractTableByAttribute(string $attr, ?string $value = null, array $options = []): array
    {
        $selector = $value !== null ? "table[{$attr}=\"{$value}\"]" : "table[{$attr}]";
        return $this->extractTable($selector, $options);
    }

    /**
     * 通过类名查找并提取表格
     *
     * @param  string  $className  类名
     * @param  array<string, mixed>  $options  提取选项
     * @return array<int, array<string, mixed>> 表格数据数组
     */
    public function extractTableByClass(string $className, array $options = []): array
    {
        $selector = "table.{$className}";
        return $this->extractTable($selector, $options);
    }

    /**
     * 通过ID查找并提取表格
     *
     * @param  string  $id  ID值
     * @param  array<string, mixed>  $options  提取选项
     * @return array<int, array<string, mixed>> 表格数据（单个表格）
     */
    public function extractTableById(string $id, array $options = []): array
    {
        $selector = "table#{$id}";
        return $this->extractTable($selector, array_merge($options, ['returnAllTables' => false]));
    }

    /**
     * 提取包含指定文本的表格
     *
     * @param  string  $text  文本内容
     * @param  array<string, mixed>  $options  提取选项
     * @return array<int, array<string, mixed>> 表格数据数组
     */
    public function extractTableByText(string $text, array $options = []): array
    {
        $selector = "table:contains({$text})";
        return $this->extractTable($selector, $options);
    }

    /**
     * 提取包含指定列的表格
     *
     * @param  string  $column  列名或列索引
     * @param  array<string, mixed>  $options  提取选项
     * @return array<int, array<string, mixed>> 表格数据数组
     */
    public function extractTableByColumn(string $column, array $options = []): array
    {
        $tables = $this->find('table');
        $filteredTables = [];

        foreach ($tables as $table) {
            $tableData = $this->extractSingleTableStructured($table, $options);

            // 检查表头是否包含指定列
            if (!empty($tableData['thead'])) {
                if (is_numeric($column) && isset($tableData['thead'][$column])) {
                    $filteredTables[] = $tableData;
                } elseif (in_array($column, $tableData['thead'])) {
                    $filteredTables[] = $tableData;
                }
            }
        }

        return $filteredTables;
    }

    /**
     * 提取列表数据（有序列表或无序列表）
     *
     * 此方法从ul或ol列表中提取数据，支持嵌套列表。
     *
     * @param  string|Element|null  $list  列表选择器或Element对象，null表示提取所有列表
     * @param  array<string, mixed>  $options  提取选项
     *                                      - 'recursive': 是否递归提取嵌套列表，默认false
     *                                      - 'trimText': 是否修剪空白，默认true
     *                                      - 'includeIndex': 是否包含索引，默认false
     * @return array<int, string|array> 列表数据数组
     *
     * @example
     * // 提取第一个列表
     * $listData = $doc->extractList();
     * // 返回: ['项目1', '项目2', '项目3']
     *
     * // 从指定列表提取
     * $listData = $doc->extractList('ul.products');
     *
     * // 递归提取嵌套列表
     * $listData = $doc->extractList('ul', ['recursive' => true]);
     * // 返回: ['项目1', ['子项目1', '子项目2'], '项目2', ...]
     *
     * // 包含索引
     * $listData = $doc->extractList('ol', ['includeIndex' => true]);
     * // 返回: [[1 => '项目1'], [2 => '项目2'], ...]
     */
    public function extractList(string|Element|null $list = null, array $options = []): array
    {
        // 合并默认选项
        $defaultOptions = [
            'recursive' => false,
            'trimText' => true,
            'includeIndex' => false
        ];
        $options = array_merge($defaultOptions, $options);

        // 获取列表元素
        if ($list === null) {
            // 提取第一个列表（ul或ol）
            $listElement = $this->first('ul') ?? $this->first('ol');
            if ($listElement === null) {
                return [];
            }
            return $this->extractSingleList($listElement, $options);
        } elseif ($list instanceof Element) {
            // 从Element对象提取
            return $this->extractSingleList($list, $options);
        } else {
            // 从选择器获取列表
            $listElement = $this->first($list);
            if ($listElement === null) {
                return [];
            }
            return $this->extractSingleList($listElement, $options);
        }
    }

    /**
     * 提取单个列表的数据
     *
     * @param  Element  $listElement  列表元素
     * @param  array<string, mixed>  $options  提取选项
     * @param  int  $startIndex  起始索引（用于嵌套列表）
     * @return array<int, string|array> 列表数据
     */
    protected function extractSingleList(Element $listElement, array $options, int $startIndex = 0): array
    {
        $data = [];
        $items = $listElement->find('> li'); // 只查找直接子元素li

        foreach ($items as $index => $item) {
            $text = $item->text();

            // 检查是否有嵌套列表
            $nestedLists = $item->find('> ul, > ol');
            $nestedData = [];

            if ($options['recursive'] && !empty($nestedLists)) {
                foreach ($nestedLists as $nestedList) {
                    $nestedData = array_merge($nestedData, $this->extractSingleList($nestedList, $options, 0));
                }
            }

            // 清理文本
            if ($options['trimText']) {
                $text = trim($text);
                $text = preg_replace('/\s+/', ' ', $text);
            }

            // 构建结果
            if (!empty($nestedData)) {
                // 如果有嵌套数据，移除文本中嵌套列表的内容
                foreach ($nestedLists as $nestedList) {
                    $nestedHtml = $nestedList->html();
                    $text = str_replace($nestedHtml, '', $text);
                }
                $text = trim($text);
                $text = preg_replace('/\s+/', ' ', $text);

                // 合并文本和嵌套数据
                if (!empty($text)) {
                    $data[] = $text;
                }
                $data[] = $nestedData;
            } else {
                // 没有嵌套数据
                if ($options['includeIndex']) {
                    $data[] = [$startIndex + $index + 1 => $text];
                } else {
                    $data[] = $text;
                }
            }
        }

        return $data;
    }

    /**
     * 提取所有列表数据
     *
     * @param  string  $selector  列表选择器
     * @param  array<string, mixed>  $options  提取选项
     * @return array<int, array<int, string|array>> 所有列表数据
     *
     * @example
     * $allLists = $doc->extractAllLists('ul, ol');
     * // 返回: [列表1数据, 列表2数据, ...]
     */
    public function extractAllLists(string $selector = 'ul, ol', array $options = []): array
    {
        $lists = $this->find($selector);
        $allData = [];

        foreach ($lists as $list) {
            $allData[] = $this->extractSingleList($list, $options);
        }

        return $allData;
    }

    /**
     * 提取定义列表数据（dl/dt/dd）
     *
     * @param  string|Element|null  $dl  定义列表选择器或Element对象
     * @param  array<string, mixed>  $options  提取选项
     *                                      - 'trimText': 是否修剪空白，默认true
     * @return array<string, string|array> 定义列表数组，格式: ['term' => 'definition']
     *
     * @example
     * $dlData = $doc->extractDefinitionList();
     * // 返回: ['术语1' => '定义1', '术语2' => '定义2', ...]
     */
    public function extractDefinitionList(string|Element|null $dl = null, array $options = []): array
    {
        // 合并默认选项
        $defaultOptions = ['trimText' => true];
        $options = array_merge($defaultOptions, $options);

        // 获取dl元素
        if ($dl === null) {
            $dlElement = $this->first('dl');
            if ($dlElement === null) {
                return [];
            }
        } elseif ($dl instanceof Element) {
            $dlElement = $dl;
        } else {
            $dlElement = $this->first($dl);
            if ($dlElement === null) {
                return [];
            }
        }

        $data = [];
        $currentTerm = null;

        // 获取所有dt和dd元素
        $children = $dlElement->find('> dt, > dd');

        foreach ($children as $child) {
            $text = $child->text();

            if ($options['trimText']) {
                $text = trim($text);
                $text = preg_replace('/\s+/', ' ', $text);
            }

            if ($child->tagName() === 'dt') {
                // 定义术语
                $currentTerm = $text;
            } elseif ($child->tagName() === 'dd' && $currentTerm !== null) {
                // 定义描述
                if (isset($data[$currentTerm])) {
                    // 如果已存在，转换为数组并添加
                    if (!is_array($data[$currentTerm])) {
                        $data[$currentTerm] = [$data[$currentTerm]];
                    }
                    $data[$currentTerm][] = $text;
                } else {
                    $data[$currentTerm] = $text;
                }
            }
        }

        return $data;
    }

    /**
     * 提取表单数据
     *
     * 从表单中提取所有输入字段的名称和值。
     *
     * @param  string|Element|null  $form  表单选择器或Element对象
     * @return array<string, string|array> 表单数据数组
     *
     * @example
     * $formData = $doc->extractFormData('form#login-form');
     * // 返回: ['username' => 'john', 'password' => '***', 'remember' => 'on']
     */
    public function extractFormData(string|Element|null $form = null): array
    {
        // 获取表单元素
        if ($form === null) {
            $formElement = $this->first('form');
            if ($formElement === null) {
                return [];
            }
        } elseif ($form instanceof Element) {
            $formElement = $form;
        } else {
            $formElement = $this->first($form);
            if ($formElement === null) {
                return [];
            }
        }

        $data = [];

        // 提取所有输入字段
        $inputs = $formElement->find('input, textarea, select');

        foreach ($inputs as $input) {
            $name = $input->getAttribute('name');
            if ($name === null || $name === '') {
                continue;
            }

            $tagName = $input->tagName();
            $type = $input->getAttribute('type') ?? 'text';
            $value = $input->getAttribute('value') ?? '';

            // 处理不同类型的输入
            switch ($tagName) {
                case 'input':
                    if ($type === 'checkbox' || $type === 'radio') {
                        // 复选框和单选框
                        if ($input->getAttribute('checked') !== null) {
                            if (isset($data[$name])) {
                                if (!is_array($data[$name])) {
                                    $data[$name] = [$data[$name]];
                                }
                                $data[$name][] = $value;
                            } else {
                                $data[$name] = $value;
                            }
                        }
                    } else {
                        // 其他输入类型
                        $data[$name] = $value;
                    }
                    break;

                case 'textarea':
                    $data[$name] = trim($input->text());
                    break;

                case 'select':
                    // 处理多选
                    $multiple = $input->getAttribute('multiple') !== null;
                    $options = $input->find('option[selected]');
                    $selectedValues = [];

                    foreach ($options as $option) {
                        $selectedValues[] = $option->getAttribute('value') ?? trim($option->text());
                    }

                    if ($multiple) {
                        $data[$name] = $selectedValues;
                    } else {
                        $data[$name] = $selectedValues[0] ?? '';
                    }
                    break;
            }
        }

        return $data;
    }

    /**
     * 提取元数据（meta标签）
     *
     * @param  string|null  $name  元数据名称，null表示提取所有
     * @return array<string, string> 元数据数组
     *
     * @example
     * // 提取所有meta标签
     * $metaData = $doc->extractMetaData();
     *
     * // 提取特定meta标签
     * $description = $doc->extractMetaData('description');
     */
    public function extractMetaData(?string $name = null): array|string
    {
        $metas = $this->find('meta');
        $data = [];

        foreach ($metas as $meta) {
            $metaName = $meta->getAttribute('name') ?? $meta->getAttribute('property') ?? '';
            $metaContent = $meta->getAttribute('content') ?? '';

            if ($metaName !== '') {
                $data[$metaName] = $metaContent;
            }
        }

        // 如果指定了名称，返回单个值
        if ($name !== null) {
            return $data[$name] ?? '';
        }

        return $data;
    }

    /**
     * 提取链接数据
     *
     * 提取所有链接的href和文本内容。
     *
     * @param  string|null  $selector  链接选择器，默认为所有a标签
     * @return array<int, array{href: string, text: string, title: string|null}> 链接数据数组
     *
     * @example
     * $links = $doc->extractLinks();
     * // 返回: [['href' => 'https://example.com', 'text' => 'Example', 'title' => null], ...]
     */
    public function extractLinks(?string $selector = 'a'): array
    {
        $links = $this->find($selector ?? 'a');
        $data = [];

        foreach ($links as $link) {
            if ($link->tagName() === 'a') {
                $data[] = [
                    'href' => $link->getAttribute('href') ?? '',
                    'text' => trim($link->text()),
                    'title' => $link->getAttribute('title') ?? null
                ];
            }
        }

        return $data;
    }

    /**
     * 提取图片数据
     *
     * 提取所有图片的src、alt和title属性。
     *
     * @param  string|null  $selector  图片选择器，默认为所有img标签
     * @return array<int, array{src: string, alt: string, title: string|null}> 图片数据数组
     *
     * @example
     * $images = $doc->extractImages();
     * // 返回: [['src' => 'image.jpg', 'alt' => 'Image', 'title' => null], ...]
     */
    public function extractImages(?string $selector = 'img'): array
    {
        $images = $this->find($selector ?? 'img');
        $data = [];

        foreach ($images as $img) {
            if ($img->tagName() === 'img') {
                $data[] = [
                    'src' => $img->getAttribute('src') ?? '',
                    'alt' => $img->getAttribute('alt') ?? '',
                    'title' => $img->getAttribute('title') ?? null
                ];
            }
        }

        return $data;
    }

    /**
     * 批量提取属性值
     *
     * 从匹配选择器的所有元素中提取指定属性的值。
     *
     * @param  string  $selector  选择器
     * @param  string  $attribute  属性名
     * @param  string  $type  选择器类型
     * @return array<int, string> 属性值数组
     *
     * @example
     * $hrefs = $doc->extractAttributes('a', 'href');
     * $ids = $doc->extractAttributes('[id]', 'id');
     */
    public function extractAttributes(string $selector, string $attribute, string $type = Query::TYPE_CSS): array
    {
        $elements = $this->find($selector, $type);
        $values = [];

        foreach ($elements as $element) {
            $value = $element->getAttribute($attribute);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * 批量提取文本内容
     *
     * 从匹配选择器的所有元素中提取文本内容。
     *
     * @param  string  $selector  选择器
     * @param  string  $type  选择器类型
     * @param  bool  $trim  是否修剪空白
     * @return array<int, string> 文本内容数组
     *
     * @example
     * $texts = $doc->extractTexts('div.item');
     * $headings = $doc->extractTexts('h1, h2, h3');
     */
    public function extractTexts(string $selector, string $type = Query::TYPE_CSS, bool $trim = true): array
    {
        $elements = $this->find($selector, $type);
        $texts = [];

        foreach ($elements as $element) {
            $text = $element->text();
            if ($trim) {
                $text = trim($text);
                $text = preg_replace('/\s+/', ' ', $text);
            }
            $texts[] = $text;
        }

        return $texts;
    }

    /**
     * 检查字符串是否为远程 URL（HTTP/HTTPS）
     *
     * @param  string  $url  要检查的 URL
     * @return bool 如果是远程 URL 返回 true，否则返回 false
     */
    protected function isRemoteUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//i', $url) === 1;
    }

    /**
     * 获取调试信息
     *
     * 返回文档的调试信息，包括：
     * - 文档类型
     * - 编码
     * - 节点数量
     * - 元素数量
     * - 文本节点数量
     *
     * @return array<string, mixed> 调试信息数组
     */
    public function getDebugInfo(): array
    {
        $xpath = $this->createXpath();

        return [
            'type' => $this->type,
            'encoding' => $this->encoding,
            'total_nodes' => $xpath->query('//*')->length,
            'total_elements' => $xpath->query('//*')->length,
            'total_text_nodes' => $xpath->query('//text()')->length,
            'document_element' => $this->document->documentElement ? $this->document->documentElement->tagName : null,
        ];
    }

    /**
     * 验证文档是否有效
     *
     * @return bool 如果文档有效返回 true
     */
    public function isValid(): bool
    {
        return $this->document->documentElement !== null;
    }

    /**
     * 获取文档统计信息
     *
     * @param  string|null  $tagName  标签名（如果提供则统计特定标签）
     * @return array<string, int> 统计信息数组
     */
    public function getStatistics(?string $tagName = null): array
    {
        $xpath = $this->createXpath();

        if ($tagName !== null) {
            $count = $xpath->query('//' . $tagName)->length;
            return [$tagName => $count];
        }

        // 统计所有元素标签
        $allElements = $xpath->query('//*');
        $stats = [];

        foreach ($allElements as $element) {
            $tag = $element->nodeName;
            if (!isset($stats[$tag])) {
                $stats[$tag] = 0;
            }
            $stats[$tag]++;
        }

        arsort($stats); // 按数量降序排列
        return $stats;
    }

    /**
     * 获取远程内容（使用优化的 cURL）
     *
     * 此方法通过 HTTP/HTTPS 协议获取远程内容，支持：
     * - 自动跟随重定向（最多 5 次）
     * - SSL 证书验证
     * - 自动编码检测
     * - 超时控制（连接超时 10 秒，总超时 30 秒）
     * - 模拟浏览器 User-Agent
     *
     * @param  string  $url  远程 URL
     * @return string|false 返回内容或 false
     *
     * @throws RuntimeException 当 cURL 扩展未启用时抛出
     * @throws RuntimeException 当请求失败时抛出
     * @throws RuntimeException 当 HTTP 状态码异常时抛出
     *
     * @example
     * $content = $this->fetchRemoteContent('https://example.com');
     */
    protected function fetchRemoteContent(string $url): string|false
    {
        // 检查是否启用 cURL 扩展
        if (!extension_loaded('curl')) {
            throw new RuntimeException('cURL 扩展未启用，无法获取远程内容');
        }

        $ch = curl_init();
        if ($ch === false) {
            return false;
        }

        // 配置 cURL 选项
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_0) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.56 Safari/535.11',
            CURLOPT_ENCODING => '', // 支持所有编码
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
                'Cache-Control: no-cache',
            ],
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // 检查错误
        if ($content === false || !empty($error)) {
            throw new RuntimeException(sprintf('cURL 请求失败: %s', $error ?: '未知错误'));
        }

        // 检查 HTTP 状态码
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('HTTP 请求失败，状态码: %d', $httpCode));
        }

        return $content;
    }
}

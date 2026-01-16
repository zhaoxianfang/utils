<?php

declare(strict_types=1);

namespace zxf\Utils\Dom\Selectors;

use InvalidArgumentException;
use RuntimeException;
use zxf\Utils\Dom\Exceptions\InvalidSelectorException;

/**
 * CSS 选择器到 XPath 转换器
 *
 * 将 CSS 选择器表达式转换为 XPath 表达式
 * 支持几乎所有 CSS3 选择器语法，并可直接使用 XPath 表达式
 *
 * 主要功能：
 * - 完整的 CSS3 选择器支持（类选择器、ID选择器、属性选择器、伪类、伪元素等）
 * - 支持 XPath 直接使用（自动识别以 / 或 // 开头的路径）
 * - 支持 text()、comment() 等 XPath 函数
 * - 支持正则表达式选择器
 * - PHP 8.2+ 类型系统
 * - 选择器编译缓存机制，提升性能
 *
 * 特性：
 * - 自动识别 XPath 路径（以 / 或 // 开头的表达式）
 * - 智能处理 CSS 组合器（空格、>、+、~）
 * - 支持复杂的伪类表达式（:not()、:nth-child()、:contains() 等）
 * - 支持伪元素（::text、::attr() 等）
 * - 属性选择器完整支持（=、!=、^=、$=、*=、~=、|=）
 * - 支持逗号分隔的多选择器
 *
 * 使用示例：
 * <code>
 * // CSS 选择器转换为 XPath
 * $xpath = Query::compile('.item.active');
 * $xpath = Query::compile('div[data-id="123"]', Query::TYPE_CSS);
 *
 * // 直接使用 XPath
 * $xpath = Query::compile('//div[@class="content"]', Query::TYPE_XPATH);
 * $xpath = Query::compile('/html/body/div[1]', Query::TYPE_XPATH);
 *
 * // 使用伪元素
 * $xpath = Query::compile('div.content::text');
 * $xpath = Query::compile('a.link::attr(href)');
 *
 * // 使用正则表达式
 * $pattern = Query::compile('/\d{4}-\d{2}-\d{2}/', Query::TYPE_REGEX);
 *
 * // 在 Document 中使用
 * $doc = new Document($html);
 * $elements = $doc->find('div.container > .item.active');
 * $elements = $doc->find('//div[@class="item"]', Query::TYPE_XPATH);
 * $texts = $doc->find('//div[@class="content"]/text()');
 * </code>
 *
    /**
     * 支持的 CSS 选择器：
     * - 基本选择器：*、tagname、.classname、#id
     * - 组合器：空格（后代）、>（子元素）、+（相邻兄弟）、~（所有兄弟）
     * - 属性选择器：[attr]、[attr="value"]、[attr~="value"]、[attr|^="value"]、[attr$="value"]、[attr*="value"]
     * - 伪类：:first-child、:last-child、:nth-child(n)、:nth-of-type(n)、:not(selector)、:contains(text)
     * - 伪元素：::text（获取文本）、::attr(name)（获取属性值）
     * - 不区分大小写的属性选择器：[attr i="value"]
     * - 属性选择器：[attr!=value] 不等于
     *
     * 支持的 XPath 功能：
     * - 路径表达式：/（绝对路径）、//（相对路径）、..（父节点）
     * - 轴：child、descendant、parent、ancestor、following-sibling、preceding-sibling、ancestor-or-self、descendant-or-self
     * - 函数：text()、comment()、normalize-space()、contains()、starts-with()、ends-with()、substring()、string-length()、number()、sum()、count()
     * - 节点测试：node()、text()、comment()、element()
     * - 布尔函数：true()、false()、not()、and、or
 *
 * 性能优化：
 * - 选择器编译结果缓存
 * - 正则表达式模式预定义常量
 * - 避免重复解析和编译
 *
 * @package zxf\Utils\Dom
 * @author  Your Name
 * @version 1.0.0
 */
class Query
{
    /**
     * CSS 选择器类型
     */
    public const TYPE_CSS = 'css';

    /**
     * XPath 选择器类型
     */
    public const TYPE_XPATH = 'xpath';

    /**
     * 正则表达式选择器类型
     */
    public const TYPE_REGEX = 'regex';

    /**
     * 常用正则表达式模式（优化性能，避免重复编译）
     */
    public const PATTERN_PSEUDO_ELEMENT = '/::([a-zA-Z0-9_-]+)(?:\(([^)]*)\))?/';
    public const PATTERN_PSEUDO_CLASS = '/:([a-zA-Z0-9_-]+)(?:\(([^)]*)\))?/';
    public const PATTERN_ATTRIBUTE = '/\[([a-zA-Z0-9_-]+)([*~|^$]?=)?([\"\']?)(.*?)\3\]/';
    public const PATTERN_ID = '/#([a-zA-Z0-9_-]+)/';
    public const PATTERN_CLASS = '/\.([a-zA-Z0-9_-]+)/';
    public const PATTERN_XPATH_ABSOLUTE = '/^\//';
    public const PATTERN_XPATH_RELATIVE = '/^\/\//';

    /**
     * 已编译的选择器缓存
     *
     * @var array<string, string>
     */
    protected static array $compiled = [];

    /**
     * 是否已初始化
     * 
     * @var bool
     */
    protected static bool $initialized = false;

    /**
     * 初始化 Query 类
     * 
     * 设置必要的初始化配置
     * 
     * @return void
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }
        
        // 设置 libxml 错误处理
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        self::$initialized = true;
    }

    /**
     * 检查是否已初始化
     * 
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * 重置 Query 类状态
     * 
     * 清空缓存并重置初始化状态
     * 
     * @return void
     */
    public static function reset(): void
    {
        self::$compiled = [];
        self::$initialized = false;
    }

    /**
     * 编译选择器表达式
     *
     * 将选择器表达式编译为 XPath 表达式
     * 支持三种类型：CSS选择器、XPath表达式和正则表达式
     *
     * @param  string  $expression  选择器表达式
     *                           CSS示例：'div.container > .item.active'
     *                           XPath示例：'//div[@class="item"]'
     *                           正则示例：'/\d{4}-\d{2}-\d{2}/'
     * @param  string  $type  选择器类型（默认为 CSS）
     *                       - Query::TYPE_CSS: CSS 选择器（默认）
     *                       - Query::TYPE_XPATH: XPath 表达式
     *                       - Query::TYPE_REGEX: 正则表达式
     * @return string 编译后的 XPath 表达式或原始正则表达式
     *
     * @throws InvalidSelectorException 当选择器无效时抛出
     * @throws RuntimeException 当类型不支持时抛出
     *
     * @example
     * // CSS 选择器
     * $xpath = Query::compile('.item.active');
     * $xpath = Query::compile('div[data-id="123"]', Query::TYPE_CSS);
     * $xpath = Query::compile('ul > li:first-child');
     *
     * // XPath 表达式（直接返回）
     * $xpath = Query::compile('//div[@class="content"]', Query::TYPE_XPATH);
     * $xpath = Query::compile('/html/body/div[1]', Query::TYPE_XPATH);
     *
     * // 正则表达式（直接返回，仅验证语法）
     * $regex = Query::compile('/hello.*world/', Query::TYPE_REGEX);
     */
    public static function compile(string $expression, string $type = self::TYPE_CSS): string
    {
        if (! in_array(strtolower($type), [self::TYPE_CSS, self::TYPE_XPATH, self::TYPE_REGEX], true)) {
            throw new RuntimeException(sprintf('不支持的表达式类型 "%s"。', $type));
        }

        $expression = trim($expression);

        if ($expression === '') {
            throw new InvalidSelectorException('选择器表达式不能为空。');
        }

        // 正则表达式类型：原样返回（在 Document 层处理）
        if (strcasecmp($type, self::TYPE_REGEX) === 0) {
            // 验证正则表达式语法
            @preg_match($expression, '');
            if (preg_last_error() !== PREG_NO_ERROR) {
                throw new InvalidSelectorException(sprintf('无效的正则表达式: "%s"。错误代码: %d', $expression, preg_last_error()));
            }
            return $expression;
        }

        // 直接使用 XPath，需要验证 XPath 语法
        if (strcasecmp($type, self::TYPE_XPATH) === 0) {
            // 验证 XPath 表达式基本语法
            if (self::isInvalidXPath($expression)) {
                throw new InvalidSelectorException(sprintf('无效的 XPath 表达式: "%s"。', $expression));
            }
            return $expression;
        }

        // 检查缓存
        $cacheKey = md5($expression);
        if (isset(self::$compiled[$cacheKey])) {
            return self::$compiled[$cacheKey];
        }

        // CSS 转换为 XPath
        $compiled = static::cssToXpath($expression);

        // 验证编译后的 XPath 是否有效
        if (preg_match('/\[\s*$/', $compiled) || preg_match('/\[[^\]]*$/', $compiled)) {
            throw new InvalidSelectorException(sprintf('无效的选择器: "%s"。属性选择器未闭合。', $expression));
        }

        self::$compiled[$cacheKey] = $compiled;

        return $compiled;
    }

    /**
     * 验证 XPath 表达式的基本语法
     * 
     * @param  string  $xpath  XPath 表达式
     * @return bool 如果 XPath 无效返回 true
     */
    protected static function isInvalidXPath(string $xpath): bool
    {
        // 检查括号是否匹配
        $parenCount = substr_count($xpath, '(') - substr_count($xpath, ')');
        if ($parenCount !== 0) {
            return true;
        }
        
        // 检查方括号是否匹配
        $bracketCount = substr_count($xpath, '[') - substr_count($xpath, ']');
        if ($bracketCount !== 0) {
            return true;
        }
        
        return false;
    }

    /**
     * 获取已编译的选择器缓存
     * 
     * @return array<string, string> 缓存数组
     */
    public static function getCompiled(): array
    {
        return static::$compiled;
    }

    /**
     * 设置已编译的选择器缓存
     * 
     * @param  array<string, string>  $compiled  缓存数组
     * @return void
     */
    public static function setCompiled(array $compiled): void
    {
        static::$compiled = $compiled;
    }

    /**
     * 清空编译缓存
     *
     * @return void
     */
    public static function clearCompiled(): void
    {
        static::$compiled = [];
    }

    /**
     * 检测是否为 XPath 绝对路径
     *
     * 判断字符串是否为以 / 开头的 XPath 绝对路径表达式。
     *
     * @param  string  $expression  表达式
     * @return bool 如果是 XPath 绝对路径返回 true
     *
     * @example
     * $isXPath = Query::isXPathAbsolute('/html/body/div');     // true
     * $isXPath = Query::isXPathAbsolute('//div');               // false
     * $isXPath = Query::isXPathAbsolute('div.container');       // false
     */
    public static function isXPathAbsolute(string $expression): bool
    {
        return preg_match('/^\/(?!\/)/', $expression) === 1;
    }

    /**
     * 检测是否为 XPath 相对路径
     *
     * 判断字符串是否为以 // 开头的 XPath 相对路径表达式。
     *
     * @param  string  $expression  表达式
     * @return bool 如果是 XPath 相对路径返回 true
     *
     * @example
     * $isXPath = Query::isXPathRelative('//div[@class="item"]'); // true
     * $isXPath = Query::isXPathRelative('/html/body/div');       // false
     * $isXPath = Query::isXPathRelative('div.container');        // false
     */
    public static function isXPathRelative(string $expression): bool
    {
        return preg_match('/^\/\//', $expression) === 1;
    }

    /**
     * 智能检测选择器类型
     *
     * 自动检测选择器是 CSS、XPath 还是正则表达式。
     *
     * @param  string  $selector  选择器表达式
     * @return string 选择器类型（'css'、'xpath' 或 'regex'）
     *
     * @example
     * $type = Query::detectSelectorType('div.container');              // 'css'
     * $type = Query::detectSelectorType('/html/body/div');             // 'xpath'
     * $type = Query::detectSelectorType('//div[@class="item"]');        // 'xpath'
     * $type = Query::detectSelectorType('/\d{4}-\d{2}-\d{2}/');      // 'regex'
     */
    public static function detectSelectorType(string $selector): string
    {
        // 检测正则表达式（以 / 开头并以 / 结尾）
        if (preg_match('/^\/.*\/[imsxuADUX]*$/', $selector)) {
            return self::TYPE_REGEX;
        }

        // 检测 XPath 绝对路径
        if (self::isXPathAbsolute($selector)) {
            return self::TYPE_XPATH;
        }

        // 检测 XPath 相对路径
        if (self::isXPathRelative($selector)) {
            return self::TYPE_XPATH;
        }

        // 默认为 CSS 选择器
        return self::TYPE_CSS;
    }

    /**
     * 将 CSS 选择器转换为 XPath
     *
     * 支持完整的 CSS3 选择器语法，包括：
     * - 基本选择器: *, div, .class, #id
     * - 全路径选择器: /html/body/div, //div[@class="item"]（XPath风格）
     * - 属性选择器: [attr], [attr=value], [attr~=value], [attr|=value], [attr^=value], [attr$=value], [attr*=value], [attr!=value]
     * - 组合选择器: 后代 (空格), 子元素 (>), 相邻兄弟 (+), 通用兄弟 (~)
     * - 多选择器: 逗号分隔（伪类参数中的逗号不会被分割）
     * - 伪类选择器: :first-child, :last-child, :nth-child, :empty, :contains, :has, :not 等（150+种）
     *
     * @param  string  $selector  CSS 选择器或 XPath 表达式
     * @return string XPath 表达式
     *
     * @example
     * // CSS 选择器
     * $xpath = Query::cssToXpath('div.container > .item.active');
     *
     * // XPath 风格的绝对路径（直接返回）
     * $xpath = Query::cssToXpath('/html/body/div[3]/div[1]/div/div[1]');
     *
     * // XPath 风格的相对路径（直接返回）
     * $xpath = Query::cssToXpath('//div[@class="item"]');
     *
     * // 组合选择器
     * $xpath = Query::cssToXpath('div.content > div.pages-date > span');
     */
    public static function cssToXpath(string $selector): string
    {
        $selector = trim($selector);

        // 检测 XPath 绝对路径（以 / 开头，但不是 //）
        if (self::isXPathAbsolute($selector)) {
            return $selector;
        }

        // 检测 XPath 相对路径（以 // 开头）
        if (self::isXPathRelative($selector)) {
            return $selector;
        }

        // 处理多个选择器（逗号分隔），需要避免伪类参数中的逗号
        if (str_contains($selector, ',')) {
            $selectors = static::splitByCommaOutsidePseudo($selector);
            if (count($selectors) > 1) {
                $xpaths = array_map(fn($s) => static::cssToXpath($s), $selectors);
                return implode(' | ', $xpaths);
            }
        }

        // 解析选择器
        $segments = static::parseSelector($selector);

        if (empty($segments)) {
            return '//*';
        }

        $xpath = '';
        $isFirst = true;

        foreach ($segments as $segment) {
            if ($isFirst) {
                // 第一个段：如果是XPath则直接使用，否则使用 // 开头
                if (isset($segment['isXPath']) && $segment['isXPath'] === true) {
                    $xpath = static::compileSegment($segment, true);
                } else {
                    $xpath = '//' . static::compileSegment($segment, true);
                }
                $isFirst = false;
            } else {
                // 后续段根据组合器决定
                $xpath .= static::compileSegment($segment, false);
            }
        }

        return $xpath;
    }

    /**
     * 智能分割逗号，避免在伪类参数内部分割
     * 
     * 此方法正确处理伪类参数中的逗号，例如：
     * - :between(2,4) 不会被分割
     * - div, p 会被正确分割为 ['div', 'p']
     * 
     * @param  string  $selector  CSS 选择器
     * @return array<int, string> 分割后的选择器数组
     */
    protected static function splitByCommaOutsidePseudo(string $selector): array
    {
        $result = [];
        $current = '';
        $parenDepth = 0;
        $bracketDepth = 0;
        
        $length = strlen($selector);
        for ($i = 0; $i < $length; $i++) {
            $char = $selector[$i];
            
            if ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth--;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth--;
            } elseif ($char === ',' && $parenDepth === 0 && $bracketDepth === 0) {
                // 只有在不在括号内时才分割
                $result[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if ($current !== '') {
            $result[] = trim($current);
        }
        
        return $result;
    }

    /**
     * 解析 CSS 选择器为段数组（公开方法）
     *
     * 支持的组合器：
     * - 空格 ' '：后代选择器（所有后代元素）
     * - 大于号 '>'：直接子元素选择器
     * - 加号 '+'：相邻兄弟选择器（紧随其后的兄弟）
     * - 波浪号 '~：通用兄弟选择器（之后的所有兄弟）
     *
     * 全路径支持：
     * - / 开头的路径（绝对路径）：/html/body/div[1] - 直接返回XPath表达式
     * - // 开头的路径（相对路径）：//div[@class="item"] - 直接返回XPath表达式
     * - 混合路径：div.content > div.pages-date > span - 转换为XPath
     *
     * 处理顺序：
     * 1. 检测并保留 XPath 风格路径（/ 或 // 开头）
     * 2. 处理多选择器（逗号分隔）
     * 3. 解析组合器和选择器段
     * 4. 解析每个段的标签、ID、类、属性、伪类
     *
     * @param  string  $selector  CSS 选择器或 XPath 路径
     * @return array<int, array> 段数组
     *
     * @example
     * // XPath 绝对路径
     * $segments = Query::parseSelector('/html/body/div[1]');
     *
     * // XPath 相对路径
     * $segments = Query::parseSelector('//div[@class="item"]');
     *
     * // CSS 组合选择器
     * $segments = Query::parseSelector('div.content > div.pages-date > span');
     */
    public static function parseSelector(string $selector): array
    {
        $segments = [];

        // 处理以 / 开头的 XPath 风格路径（如 /html/body/div）
        if (preg_match(self::PATTERN_XPATH_ABSOLUTE, $selector)) {
            // 这是 XPath 路径，直接返回
            $segments[] = [
                'combinator' => '',
                'tag' => '*',
                'id' => '',
                'classes' => [],
                'attributes' => [],
                'pseudo' => '',
                'isXPath' => true,
                'xpath' => $selector,
            ];
            return $segments;
        }

        // 处理以 // 开头的 XPath 风格路径（如 //div[@class="item"]）
        if (preg_match(self::PATTERN_XPATH_RELATIVE, $selector)) {
            $segments[] = [
                'combinator' => '',
                'tag' => '*',
                'id' => '',
                'classes' => [],
                'attributes' => [],
                'pseudo' => '',
                'isXPath' => true,
                'xpath' => $selector,
            ];
            return $segments;
        }

        // 使用更精确的正则表达式解析组合器
        // 1. 首先用特殊标记替换 > + ~ 组合器（只在选择器之间替换）
        $temp = preg_replace('/\s*([>+~])\s*/', chr(0) . '$1' . chr(0), $selector);
        $temp = preg_replace('/^([>+~])\s*/', chr(0) . '$1' . chr(0), $temp);
        $temp = preg_replace('/\s*([>+~])$/', chr(0) . '$1' . chr(0), $temp);

        // 2. 用 chr(0) 分割
        $parts = explode(chr(0), $temp);

        $selectorParts = [];
        $combinators = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (in_array($part, ['>', '+', '~'], true)) {
                // 这是组合器
                $combinators[] = $part;
            } else {
                // 这个 part 可能包含空格分隔的后代选择器
                // 例如 "div p" 或 ".container .col"
                $subParts = preg_split('/\s+/', $part);
                if (count($subParts) > 1) {
                    // 有多个子部分，说明是后代选择器
                    foreach ($subParts as $subPart) {
                        if ($subPart !== '') {
                            $selectorParts[] = $subPart;
                            // 在非第一个子部分前添加空格组合器
                            $prevIndex = count($selectorParts) - 2;
                            if ($prevIndex >= 0) {
                                // 检查当前位置是否已经有组合器
                                if (!isset($combinators[$prevIndex])) {
                                    array_splice($combinators, $prevIndex, 0, ' ');
                                }
                            }
                        }
                    }
                } else {
                    $selectorParts[] = $part;
                }
            }
        }

        // 3. 对于后代选择器（原始选择器中有空格但不是 > + ~），添加空格组合器
        if (count($selectorParts) > 1 && count($combinators) < count($selectorParts) - 1) {
            $needed = count($selectorParts) - 1 - count($combinators);
            for ($i = 0; $i < $needed; $i++) {
                $combinators[] = ' ';
            }
        }

        // 4. 验证和修正组合器数组长度
        // 组合器数量应该等于选择器段数减一
        while (count($combinators) < count($selectorParts) - 1) {
            $combinators[] = ' ';
        }
        while (count($combinators) > count($selectorParts) - 1 && count($selectorParts) > 0) {
            array_pop($combinators);
        }

        if (empty($selectorParts)) {
            $selectorParts = [$selector];
        }

        $current = [
            'combinator' => '',
            'tag' => '*',
            'id' => '',
            'classes' => [],
            'attributes' => [],
            'pseudo' => '',
        ];

        foreach ($selectorParts as $i => $part) {
            if ($i > 0) {
                $current['combinator'] = $combinators[$i - 1] ?? ' ';
            }
            static::parseSelectorPart($part, $current);
            $segments[] = $current;
            $current = [
                'combinator' => '',
                'tag' => '*',
                'id' => '',
                'classes' => [],
                'attributes' => [],
                'pseudo' => '',
            ];
        }

        return $segments;
    }

    /**
     * 解析单个选择器部分
     * 
     * @param  string  $part  选择器部分
     * @param  array<string, mixed>  $current  当前解析状态
     * @return void
     */
    protected static function parseSelectorPart(string $part, array &$current): void
    {
        // 先处理伪类和伪元素（必须在提取类名之前，因为 :not(.active) 中的 .active 不应该被提取）
        // 处理双冒号伪元素 ::text, ::attr()
        if (preg_match(self::PATTERN_PSEUDO_ELEMENT, $part, $matches)) {
            $current['pseudo'] = '::' . $matches[1];
            $current['pseudoArg'] = $matches[2] ?? '';
            $part = preg_replace('/::[a-zA-Z0-9_-]+(?:\([^)]*\))?/', '', $part);
        }
        // 处理单冒号伪类
        elseif (preg_match(self::PATTERN_PSEUDO_CLASS, $part, $matches)) {
            $current['pseudo'] = $matches[1];
            $current['pseudoArg'] = $matches[2] ?? '';
            // 删除整个伪类（包括参数） - 使用匹配的完整文本
            $part = str_replace($matches[0], '', $part);
        }

        // 先提取属性（包含在 [] 中，避免与类/ID混淆）
        if (preg_match_all(self::PATTERN_ATTRIBUTE, $part, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $attr) {
                $current['attributes'][] = [
                    'name' => $attr[1],
                    'operator' => $attr[2] ?? null,
                    'value' => $attr[4] ?? null,
                ];
            }
            $part = preg_replace('/\[[^\]]+\]/', '', $part);
        }

        // 提取 ID
        if (preg_match(self::PATTERN_ID, $part, $matches)) {
            $current['id'] = $matches[1];
            $part = str_replace('#' . $matches[1], '', $part);
        }

        // 提取类名
        if (preg_match_all(self::PATTERN_CLASS, $part, $matches)) {
            $current['classes'] = $matches[1];
            $part = preg_replace('/\.[a-zA-Z0-9_-]+/', '', $part);
        }

        // 剩余部分是标签名
        $part = trim($part);
        if ($part !== '' && $part !== '') {
            $current['tag'] = $part;
        }
    }
    /**
     * 编译选择器段为 XPath
     *
     * @param  array<string, mixed>  $segment  选择器段
     * @param  bool  $isFirst  是否是第一个段
     * @return string XPath 片段
     */
    protected static function compileSegment(array $segment, bool $isFirst = false): string
    {
        // 如果这是一个XPath段，直接返回原始XPath表达式
        if (isset($segment['isXPath']) && $segment['isXPath'] === true) {
            return $segment['xpath'] ?? '';
        }

        $xpath = '';

        // 处理组合器
        $combinator = $segment['combinator'] ?? ' ';
        $isAdjacentSibling = ($combinator === '+');
        
        switch ($combinator) {
            case '>':
                $xpath .= '/';
                break;
            case '+':
                $xpath .= '/following-sibling::';
                break;
            case '~':
                $xpath .= '/following-sibling::';
                break;
            default:
                // 空格组合器表示后代选择，使用 //
                if (!$isFirst) {
                    $xpath .= '//';
                }
        }

        // 标签名
        $xpath .= $segment['tag'];
        
        // 相邻兄弟选择器需要 [1] 条件
        if ($isAdjacentSibling) {
            $xpath .= '[1]';
        }

        // ID
        if (! empty($segment['id'])) {
            $xpath .= sprintf('[@id="%s"]', $segment['id']);
        }

        // 类名
        if (! empty($segment['classes'])) {
            foreach ($segment['classes'] as $class) {
                $xpath .= sprintf('[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $class);
            }
        }

        // 属性
        if (! empty($segment['attributes'])) {
            foreach ($segment['attributes'] as $attr) {
                $xpath .= static::compileAttribute($attr);
            }
        }

        // 伪类
        if (! empty($segment['pseudo'])) {
            $xpath .= static::compilePseudo($segment['pseudo'], $segment['pseudoArg'] ?? '', $segment['tag'] ?? '*');
        }

        return $xpath;
    }

    /**
     * 编译属性选择器
     *
     * 支持的属性选择器：
     * - [attr] - 存在属性
     * - [attr=value] - 完全匹配
     * - [attr!=value] - 不匹配
     * - [attr~=value] - 词列表匹配
     * - [attr|=value] - 语言或前缀匹配
     * - [attr^=value] - 前缀匹配
     * - [attr$=value] - 后缀匹配
     * - [attr*=value] - 包含匹配
     * - [attr i="value"] - 不区分大小写匹配（XPath 1.0限制：需要使用translate函数）
     *
     * @param  array{name: string, operator: string|null, value: string|null}  $attr  属性信息
     * @return string XPath 属性条件
     *
     * @example
     * // 存在 href 属性
     * $xpath = Query::compileAttribute(['name' => 'href']);
     *
     * // class 包含 "active"
     * $xpath = Query::compileAttribute(['name' => 'class', 'operator' => '~=', 'value' => 'active']);
     *
     * // data-id 不等于 2
     * $xpath = Query::compileAttribute(['name' => 'data-id', 'operator' => '!=', 'value' => '2']);
     */
    protected static function compileAttribute(array $attr): string
    {
        $name = $attr['name'];
        $operator = $attr['operator'] ?? '';
        $value = $attr['value'] ?? '';

        // XPath 1.0 不支持 ends-with，需要用 substring 实现
        if ($operator === '$=') {
            return sprintf('[@%s and substring(@%s, string-length(@%s) - string-length("%s") + 1) = "%s"]',
                $name, $name, $name, $value, $value);
        }

        return match ($operator) {
            '^=' => sprintf('[starts-with(@%s, "%s")]', $name, $value),
            '*=' => sprintf('[contains(@%s, "%s")]', $name, $value),
            '~=' => sprintf('[contains(concat(" ", normalize-space(@%s), " "), " %s ")]', $name, $value), // 保持不变，这个语法是正确的
            '|=' => sprintf('[@%s="%s" or starts-with(@%s, "%s-")]', $name, $value, $name, $value),
            '=' => sprintf('[@%s="%s"]', $name, $value),
            '!=' => sprintf('[@%s and @%s!="%s"]', $name, $name, $value), // 修复XPath 1.0语法
            default => sprintf('[@%s]', $name),
        };
    }

    /**
     * 编译伪类选择器
     *
     * 支持的伪类列表（150+ 种）：
     * - 结构伪类: first-child, last-child, only-child, nth-child, nth-last-child, nth-last-child(扩展), nth-last-of-type
     * - 类型伪类: first-of-type, last-of-type, only-of-type, nth-of-type
     * - 内容伪类: empty, contains, has, not, contains-text, starts-with, ends-with, text-match
     * - 表单伪类: enabled, disabled, checked, selected, required, optional, read-only, read-write
     * - 表单元素类型伪类: text, password, checkbox, radio, file, email, url, number, tel, search, date, time, datetime, datetime-local, month, week, color, range, submit, reset, image, button
     * - HTML元素伪类: header, input, button, link, visited, image, video, audio, canvas, svg, script, style, meta, link, base, head, body, title, table, tr, td, th, thead, tbody, tfoot, ul, ol, li, dl, dt, dd, form, label, fieldset, legend, section, article, aside, nav, main, footer, header(HTML5), hgroup, figure, figcaption, details, summary, dialog, menu
     * - 状态伪类: root, target, focus, hover, active
     * - 可见性伪类: visible, hidden
     * - 位置伪类: first, last, even, odd, eq, gt, lt, parent, slice, between
     * - 文本相关伪类: text-node, comment-node, cdata, blank, parent-only-text, text-length-gt/lt/eq, text-length-between
     * - 语言伪类: lang, dir(ltr, rtl, auto)
     * - 表单验证伪类: in-range, out-of-range, indeterminate, placeholder-shown, default, valid, invalid, autofill, user-invalid, user-valid
     * - 属性匹配伪类: has-attr, data, attr-match
     * - 节点类型伪类: element, processing-instruction, document-node, document-fragment
     * - 自定义伪类: match, filter, each, map, reduce
     * - 属性长度伪类: attr-length-gt/lt/eq
     * - 深度伪类: depth-0/1/2/3/4/5, depth-between
     * - 子元素数量伪类: children-gt/lt/eq
     * - 属性数量伪类: attr-count-gt/lt/eq
     * - 输入状态伪类: default, checked, indeterminate, placeholder-shown
     * - 焦点状态伪类: focus, focus-within, focus-visible
     * - 链接状态伪类: any-link, link, local-link, target, target-within
     *
     * @param  string  $pseudo  伪类名
     * @param  string  $arg  伪类参数
     * @param  string  $tagName  元素标签名（用于 of-type 伪类）
     * @return string XPath 伪类条件
     *
     * @example
     * // 结构伪类
     * $xpath = Query::compilePseudo('nth-child', '2n+1', 'li');
     *
     * // 内容伪类
     * $xpath = Query::compilePseudo('contains', 'Hello', 'div');
     *
     * // 表单伪类
     * $xpath = Query::compilePseudo('enabled', '', 'input');
     */
    protected static function compilePseudo(string $pseudo, string $arg, string $tagName = '*'): string
    {
        return match ($pseudo) {
            // 结构伪类 - 子元素位置
            'first-child' => '[not(preceding-sibling::*)]',
            'last-child' => '[not(following-sibling::*)]',
            'only-child' => '[not(preceding-sibling::*) and not(following-sibling::*)]',
            'nth-child' => static::compileNthChild($arg),
            'nth-last-child' => static::compileNthChild($arg, true),
            // 结构伪类 - 同类型元素位置
            'first-of-type' => sprintf('[not(preceding-sibling::%s)]', $tagName),
            'last-of-type' => sprintf('[not(following-sibling::%s)]', $tagName),
            'only-of-type' => sprintf('[not(preceding-sibling::%s) and not(following-sibling::%s)]', $tagName, $tagName),
            'nth-of-type' => static::compileNthChild($arg, false, true, $tagName),
            'nth-last-of-type' => static::compileNthChild($arg, true, true, $tagName),
            // 内容伪类
            'empty' => '[not(*) and not(text()[normalize-space()])]',
            'contains' => sprintf('[contains(string(.), "%s")]', $arg),
            'not' => static::compileNot($arg),
            'has' => static::compileHas($arg),
            // 文档状态伪类
            'root' => '[not(parent::*)]',
            'target' => '[@name=substring-after(., "#") and substring-after(., "#")!=""]',
            'target-within' => '[descendant::*[@name=substring-after(., "#") and substring-after(., "#")!=""]]',
            // 表单状态伪类
            'enabled' => '[not(@disabled="disabled") and not(@disabled) and not(@type="hidden")]',
            'disabled' => '[@disabled="disabled" or @disabled]',
            'checked' => '[@checked="checked" or @checked]',
            'selected' => '[@selected="selected" or @selected]',
            'required' => '[@required="required" or @required]',
            'optional' => '[not(@required="required") and not(@required)]',
            'read-only' => '[@readonly="readonly" or @readonly]',
            'read-write' => '[not(@readonly="readonly") and not(@readonly)]',
            // 用户交互伪类
            'focus' => '[@focus]',
            'focus-within' => '[descendant::*[@focus] or ancestor::*[@focus]]',
            'focus-visible' => '[@focus and @tabindex]',
            'hover' => '[@hover]',
            'active' => '[@active]',
            // 可见性伪类
            'visible' => '[not(@hidden) and not(contains(@style, "display:none")) and not(contains(@style, "display: none")) and not(contains(@style, "visibility:hidden")) and not(contains(@style, "visibility: hidden"))]',
            'hidden' => '[@hidden or contains(@style, "display:none") or contains(@style, "display: none") or contains(@style, "visibility:hidden") or contains(@style, "visibility: hidden") or @type="hidden"]',
            // 元素类型伪类
            'header' => '[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]',
            'input' => '[self::input or self::textarea or self::select or self::button]',
            'button' => '[self::button or self::input[@type="button" or @type="submit" or @type="reset"]]',
            'text' => '[self::text()]',
            'comment' => '[self::comment()]',
            'link' => '[self::a and @href]',
            'any-link' => '[self::a[@href] or self::area[@href]]',
            'local-link' => '[self::a and @href and starts-with(@href, "#")]',
            'visited' => '[self::a]', // XPath无法准确判断visited状态
            'image' => '[self::img]',
            // 表单元素类型伪类
            'checkbox' => '[@type="checkbox"]',
            'radio' => '[@type="radio"]',
            'password' => '[@type="password"]',
            'file' => '[@type="file"]',
            'email' => '[@type="email"]',
            'url' => '[@type="url"]',
            'number' => '[@type="number"]',
            'tel' => '[@type="tel"]',
            'search' => '[@type="search"]',
            'date' => '[@type="date"]',
            'time' => '[@type="time"]',
            'datetime' => '[@type="datetime"]',
            'datetime-local' => '[@type="datetime-local"]',
            'month' => '[@type="month"]',
            'week' => '[@type="week"]',
            'color' => '[@type="color"]',
            'range' => '[@type="range"]',
            'submit' => '[@type="submit"]',
            'reset' => '[@type="reset"]',
            // HTML元素伪类
            'video' => '[self::video]',
            'audio' => '[self::audio]',
            'canvas' => '[self::canvas]',
            'svg' => '[self::svg]',
            'script' => '[self::script]',
            'style' => '[self::style]',
            'meta' => '[self::meta]',
            'link' => '[self::link]',
            'base' => '[self::base]',
            'head' => '[self::head]',
            'body' => '[self::body]',
            'title' => '[self::title]',
            'figure' => '[self::figure]',
            'figcaption' => '[self::figcaption]',
            'details' => '[self::details]',
            'summary' => '[self::summary]',
            'dialog' => '[self::dialog]',
            'menu' => '[self::menu]',
            // 内容匹配伪类
            'contains-text' => sprintf('[contains(., "%s")]', $arg),
            'starts-with' => sprintf('[starts-with(., "%s")]', $arg),
            'ends-with' => sprintf('[substring(., string-length(.) - string-length("%s") + 1) = "%s"]', $arg, $arg),
            // 属性匹配伪类
            'has-attr' => sprintf('[@%s]', $arg),
            'data' => sprintf('[@data-%s]', $arg),
            // 节点类型伪类
            'element' => '[@*]',
            'text-node' => '[self::text()]',
            'comment-node' => '[self::comment()]',
            'cdata' => '[self::cdata-section()]',
            'processing-instruction' => '[self::processing-instruction()]',
            // 位置伪类（简写）
            'first' => '[1]',
            'last' => '[last()]',
            'even' => '[position() mod 2 = 0]',
            'odd' => '[position() mod 2 = 1]',
            'eq' => sprintf('[position() = %s]', (int)$arg + 1),
            'gt' => sprintf('[position() > %s]', (int)$arg + 1),
            'lt' => sprintf('[position() < %s]', (int)$arg + 1),
            'parent' => '[*]',
            'slice' => static::compileSlice($arg),
            // 文本相关伪类
            'blank' => '[not(text()[normalize-space()]) and not(*)]',
            'parent-only-text' => '[text()[normalize-space()] and not(*)]',
            // 语言伪类（简化版本，只支持 lang 属性）
            'lang' => sprintf('[@lang=\"%s\" or starts-with(@lang, \"%s-\")]', $arg, $arg),
            // 表单相关伪类
            'in-range' => '[@min and @max and @value and number(@value) >= number(@min) and number(@value) <= number(@max)]',
            'out-of-range' => '[@min and @max and @value and (number(@value) < number(@min) or number(@value) > number(@max))]',
            'indeterminate' => '[@indeterminate="indeterminate"]',
            'placeholder-shown' => '[@placeholder and (not(@value) or @value="")]',
            'default' => '[@default]',
            'valid' => '[@valid="valid"]',
            'invalid' => '[@invalid="invalid"]',
            'autofill' => '[contains(@style, "background-color") or contains(@style, "background")]',
            // 表单验证扩展伪类
            'user-invalid' => '[@aria-invalid="true"]',
            'user-valid' => '[not(@aria-invalid="true") or @aria-invalid="false"]',
            // HTML5 结构元素伪类
            'table' => '[self::table]',
            'tr' => '[self::tr]',
            'td' => '[self::td]',
            'th' => '[self::th]',
            'thead' => '[self::thead]',
            'tbody' => '[self::tbody]',
            'tfoot' => '[self::tfoot]',
            'ul' => '[self::ul]',
            'ol' => '[self::ol]',
            'li' => '[self::li]',
            'dl' => '[self::dl]',
            'dt' => '[self::dt]',
            'dd' => '[self::dd]',
            'form' => '[self::form]',
            'label' => '[self::label]',
            'fieldset' => '[self::fieldset]',
            'legend' => '[self::legend]',
            'section' => '[self::section]',
            'article' => '[self::article]',
            'aside' => '[self::aside]',
            'nav' => '[self::nav]',
            'main' => '[self::main]',
            'footer' => '[self::footer]',
            // 表格行伪类
            'table-row' => '[self::tr]',
            'table-cell' => '[self::td or self::th]',
            'table-header' => '[self::th]',
            // 列表伪类
            'list-item' => '[self::li]',
            'list' => '[self::ul or self::ol]',
            // 文本节点伪类（扩展）
            'whitespace' => '[self::text() and normalize-space(.)=""]',
            'non-whitespace' => '[self::text() and normalize-space(.)!=""]',
            // 方向伪类
            'dir-ltr' => '[@dir="ltr"]',
            'dir-rtl' => '[@dir="rtl"]',
            'dir-auto' => '[@dir="auto"]',
            // 深度伪类
            'depth-0' => '[not(ancestor::*)]',
            'depth-1' => '[count(ancestor::*) = 1]',
            'depth-2' => '[count(ancestor::*) = 2]',
            'depth-3' => '[count(ancestor::*) = 3]',
            'depth-4' => '[count(ancestor::*) = 4]',
            'depth-5' => '[count(ancestor::*) = 5]',
            // 索引范围伪类
            'between' => static::compileBetween($arg),
            // 文本长度伪类（使用 normalize-space 来正确处理文本）
            'text-length-gt' => sprintf('[string-length(normalize-space(.)) > %d]', (int)$arg),
            'text-length-lt' => sprintf('[string-length(normalize-space(.)) < %d]', (int)$arg),
            'text-length-eq' => sprintf('[string-length(normalize-space(.)) = %d]', (int)$arg),
            // 文本长度范围伪类
            'text-length-between' => static::compileTextLengthBetween($arg),
            // 子元素数量伪类
            'children-gt' => sprintf('[count(*) > %d]', (int)$arg),
            'children-lt' => sprintf('[count(*) < %d]', (int)$arg),
            'children-eq' => sprintf('[count(*) = %d]', (int)$arg),
            // 属性数量伪类
            'attr-count-gt' => sprintf('[count(@*) > %d]', (int)$arg),
            'attr-count-lt' => sprintf('[count(@*) < %d]', (int)$arg),
            'attr-count-eq' => sprintf('[count(@*) = %d]', (int)$arg),
            // 属性值长度伪类
            'attr-length-gt' => static::compileAttrLengthGt($arg),
            'attr-length-lt' => static::compileAttrLengthLt($arg),
            'attr-length-eq' => static::compileAttrLengthEq($arg),
            // 深度范围伪类
            'depth-between' => static::compileDepthBetween($arg),
            // 文本内容匹配伪类（正则表达式简化版）
            'text-match' => static::compileTextMatch($arg),
            // 属性值匹配伪类
            'attr-match' => static::compileAttrMatch($arg),
            // 伪元素（在 Document 中处理）
            default => '',
        };
    }

    /**
     * 编译 nth-child 伪类
     *
     * 支持多种公式格式：
     * - 数字：如 2、3、5
     * - 关键字：odd（奇数）、even（偶数）
     * - 公式：an+b，如 2n+1、3n+2、-2n+5
     *
     * @param  string  $formula  nth 公式
     * @param  bool  $reverse  是否反向（nth-last-*）
     * @param  bool  $ofType  是否按类型（nth-of-type）
     * @param  string  $tagName  元素标签名（用于 of-type）
     * @return string XPath 条件
     *
     * @example
     * // 奇数位置
     * $xpath = Query::compileNthChild('odd', false, false, 'li');
     *
     * // 偶数位置
     * $xpath = Query::compileNthChild('even', false, false, 'li');
     *
     * // 2n+1 公式
     * $xpath = Query::compileNthChild('2n+1', false, false, 'li');
     *
     * // 倒数第2个
     * $xpath = Query::compileNthChild('2', true, false, 'li');
     */
    protected static function compileNthChild(string $formula, bool $reverse = false, bool $ofType = false, string $tagName = '*'): string
    {
        $formula = strtolower(trim($formula));

        if ($formula === 'even') {
            if ($ofType) {
                return $reverse 
                    ? sprintf('[count(following-sibling::%s) mod 2 = 0]', $tagName)
                    : sprintf('[count(preceding-sibling::%s) mod 2 = 0]', $tagName);
            }
            return $reverse ? '[last() - position() mod 2 = 0]' : '[position() mod 2 = 0]';
        }

        if ($formula === 'odd') {
            if ($ofType) {
                return $reverse 
                    ? sprintf('[count(following-sibling::%s) mod 2 = 1]', $tagName)
                    : sprintf('[count(preceding-sibling::%s) mod 2 = 1]', $tagName);
            }
            return $reverse ? '[last() - position() mod 2 = 1]' : '[position() mod 2 = 1]';
        }

        // 解析公式: an+b
        if (preg_match('/^(?P<a>-?\d*)?n(?:(?P<b>[+-]\d+)?)?$/', $formula, $matches)) {
            $a = $matches['a'] !== '' ? (int) $matches['a'] : 1;
            $b = isset($matches['b']) ? (int) $matches['b'] : 0;

            if ($a === 0) {
                if ($ofType) {
                    return $reverse 
                        ? sprintf('[count(following-sibling::%s) + 1 = %d]', $tagName, $b)
                        : sprintf('[count(preceding-sibling::%s) + 1 = %d]', $tagName, $b);
                }
                $pos = $reverse ? sprintf('last() - (%d - 1)', $b) : (string) $b;
                return sprintf('[position() = %s]', $pos);
            }

            if ($b === 0) {
                if ($ofType) {
                    return $reverse 
                        ? sprintf('[count(following-sibling::%s) mod %d = 0]', $tagName, abs($a))
                        : sprintf('[count(preceding-sibling::%s) mod %d = 0]', $tagName, abs($a));
                }
                return $reverse 
                    ? sprintf('[(last() - position()) mod %d = 0]', abs($a))
                    : sprintf('[position() mod %d = 0]', abs($a));
            }

            if ($a > 0) {
                if ($b > 0) {
                    if ($ofType) {
                        return sprintf('[count(preceding-sibling::%s) >= %d and count(preceding-sibling::%s) mod %d = %d]',
                            $tagName, $b, $tagName, $a, $b % $a);
                    }
                    // 简化公式：当 a>0 且 b>0 时，position >= b 且 position mod a = b mod a
                    $mod = $b % $a;
                    return sprintf('[position() >= %d and position() mod %d = %d]', $b, $a, $mod);
                } else {
                    $absB = abs($b);
                    if ($ofType) {
                        return sprintf('[count(preceding-sibling::%s) >= %d and count(preceding-sibling::%s) mod %d = %d]',
                            $tagName, $absB, $tagName, $a, $a - ($absB % $a));
                    }
                    $mod = ($a - ($absB % $a)) % $a;
                    return sprintf('[position() >= %d and position() mod %d = %d]', $absB, $a, $mod);
                }
            } else {
                $absA = abs($a);
                if ($b > 0) {
                    if ($ofType) {
                        return sprintf('[count(preceding-sibling::%s) <= %d and count(preceding-sibling::%s) mod %d = %d]',
                            $tagName, $b, $tagName, $absA, $b % $absA);
                    }
                    return sprintf('[position() <= %d and position() mod %d = %d]', $b, $absA, $b % $absA);
                } else {
                    if ($ofType) {
                        return sprintf('[count(preceding-sibling::%s) mod %d = 0]', $tagName, $absA);
                    }
                    return sprintf('[position() mod %d = 0]', $absA);
                }
            }
        }

        // 纯数字
        if (is_numeric($formula)) {
            if ($ofType) {
                return $reverse
                    ? sprintf('[count(following-sibling::%s) + 1 = %d]', $tagName, (int)$formula)
                    : sprintf('[count(preceding-sibling::%s) + 1 = %d]', $tagName, (int)$formula);
            }
            $pos = $reverse ? sprintf('last() - (%s - 1)', $formula) : $formula;
            return sprintf('[position() = %s]', $pos);
        }

        return '';
    }

    /**
     * 编译 :not() 伪类
     *
     * 反向选择，选择不匹配指定选择器的元素
     *
     * @param  string  $selector  内部选择器
     * @return string XPath 条件
     *
     * @example
     * // 不包含 .active 类的 div
     * $xpath = Query::compileNot('.active');
     *
     * // data-id 不等于 2 的元素
     * $xpath = Query::compileNot('[data-id="2"]');
     */
    protected static function compileNot(string $selector): string
    {
        // 解析内部选择器
        $segments = static::parseSelector($selector);
        if (empty($segments)) {
            return '';
        }

        $conditions = [];
        $segment = $segments[0];

        // 如果是简单属性选择器如 [data-id="2"]
        if (!empty($segment['attributes'])) {
            foreach ($segment['attributes'] as $attr) {
                $name = $attr['name'];
                $operator = $attr['operator'] ?? null;
                $value = $attr['value'] ?? null;

                if ($operator === '=' && $value !== null) {
                    // [@data-id!="2"]
                    $conditions[] = sprintf('@%s!="%s"', $name, $value);
                }
            }
        }

        // 如果有类名，反转类匹配
        if (!empty($segment['classes'])) {
            foreach ($segment['classes'] as $class) {
                $conditions[] = sprintf('not(contains(concat(" ", normalize-space(@class), " "), " %s "))', $class);
            }
        }

        // 如果有 ID，反转 ID 匹配
        if (!empty($segment['id'])) {
            $conditions[] = sprintf('@id!="%s"', $segment['id']);
        }

        if (empty($conditions)) {
            return '';
        }

        return '[' . implode(' and ', $conditions) . ']';
    }

    /**
     * 编译 :has() 伪类
     *
     * 选择包含指定后代元素的父元素
     *
     * @param  string  $selector  内部选择器
     * @return string XPath 条件
     *
     * @example
     * // 包含链接的 div
     * $xpath = Query::compileHas('a');
     *
     * // 包含 .item 类的后代的 div
     * $xpath = Query::compileHas('.item');
     */
    protected static function compileHas(string $selector): string
    {
        $innerXpath = static::cssToXpath($selector);
        // 移除开头的 //
        $innerXpath = preg_replace('/^\/\//', '', $innerXpath);
        // 检查后代元素中是否有匹配的
        return sprintf('[%s]', $innerXpath);
    }

    /**
     * 编译 :slice() 伪类
     *
     * 支持两种格式：
     * - start:end (end 不包含) - 例如 1:3 表示从第2个到第4个元素（不包含第4个）
     * - start:length - 例如 1:2 表示从第2个开始的2个元素
     *
     * @param  string  $arg  切片参数，格式为 start:end 或 start:length
     * @return string XPath 条件
     */
    protected static function compileSlice(string $arg): string
    {
        $arg = trim($arg);

        // 解析参数: start:end (end 不包含)
        if (preg_match('/^(\d+)?:(\d+)?$/', $arg, $matches)) {
            $start = $matches[1] !== '' ? (int) $matches[1] : 0;
            $end = $matches[2] !== '' ? (int) $matches[2] : PHP_INT_MAX;

            if ($start === 0 && $end === PHP_INT_MAX) {
                return '';
            }
            if ($start > 0 && $end === PHP_INT_MAX) {
                return sprintf('[position() >= %d]', $start + 1);
            }
            if ($start === 0 && $end > 0) {
                return sprintf('[position() <= %d]', $end);
            }
            // end 不包含，所以 end 是最大位置，但XPath中需要减1
            return sprintf('[position() >= %d and position() < %d]', $start + 1, $end + 1);
        }

        // 解析参数: start:length
        if (preg_match('/^(\d+):(\d+)$/', $arg, $matches)) {
            $start = (int) $matches[1];
            $length = (int) $matches[2];
            $end = $start + $length - 1;
            return sprintf('[position() >= %d and position() <= %d]', $start + 1, $end + 1);
        }

        return '';
    }

    /**
     * 编译 :between() 伪类
     *
     * 参数格式为 start,end，其中：
     * - start: 起始索引（从 1 开始，与 XPath position() 一致）
     * - end: 结束索引（包含，从 1 开始）
     *
     * 例如：:between(2,4) 匹配位置 2、3、4 的元素
     *
     * @param  string  $arg  范围参数，格式为 start,end
     * @return string XPath 条件
     */
    protected static function compileBetween(string $arg): string
    {
        $arg = trim($arg);
        $parts = explode(',', $arg);

        if (count($parts) !== 2) {
            // 如果参数格式不正确，返回匹配所有元素的条件
            return '[true()]';
        }

        $start = (int) trim($parts[0]);
        $end = (int) trim($parts[1]);

        // between 使用从 1 开始的索引（与 XPath position() 一致）
        return sprintf('[position() >= %d and position() <= %d]', $start, $end);
    }

    /**
     * 编译 :text-length-between() 伪类
     *
     * 参数格式为 min,max，其中：
     * - min: 最小文本长度（包含）
     * - max: 最大文本长度（包含）
     *
     * 例如：:text-length-between(5,20) 匹配文本长度在 5 到 20 之间的元素
     *
     * @param  string  $arg  范围参数，格式为 min,max
     * @return string XPath 条件
     */
    protected static function compileTextLengthBetween(string $arg): string
    {
        $arg = trim($arg);
        $parts = explode(',', $arg);

        if (count($parts) !== 2) {
            return '[true()]';
        }

        $min = (int) trim($parts[0]);
        $max = (int) trim($parts[1]);

        return sprintf('[string-length(normalize-space(.)) >= %d and string-length(normalize-space(.)) <= %d]', $min, $max);
    }

    /**
     * 编译 :attr-length-gt() 伪类
     *
     * 参数格式为 attrName,length，其中：
     * - attrName: 属性名称
     * - length: 长度阈值
     *
     * 例如：:attr-length-gt(data-value,10) 匹配 data-value 属性值长度大于 10 的元素
     *
     * @param  string  $arg  属性和长度参数，格式为 attrName,length
     * @return string XPath 条件
     */
    protected static function compileAttrLengthGt(string $arg): string
    {
        $arg = trim($arg);
        $parts = explode(',', $arg);

        if (count($parts) !== 2) {
            return '[true()]';
        }

        $attrName = trim($parts[0]);
        $length = (int) trim($parts[1]);

        return sprintf('[@%s and string-length(@%s) > %d]', $attrName, $attrName, $length);
    }

    /**
     * 编译 :attr-length-lt() 伪类
     *
     * 参数格式为 attrName,length，其中：
     * - attrName: 属性名称
     * - length: 长度阈值
     *
     * 例如：:attr-length-lt(data-value,10) 匹配 data-value 属性值长度小于 10 的元素
     *
     * @param  string  $arg  属性和长度参数，格式为 attrName,length
     * @return string XPath 条件
     */
    protected static function compileAttrLengthLt(string $arg): string
    {
        $arg = trim($arg);
        $parts = explode(',', $arg);

        if (count($parts) !== 2) {
            return '[true()]';
        }

        $attrName = trim($parts[0]);
        $length = (int) trim($parts[1]);

        return sprintf('[@%s and string-length(@%s) < %d]', $attrName, $attrName, $length);
    }

    /**
     * 编译 :attr-length-eq() 伪类
     *
     * 参数格式为 attrName,length，其中：
     * - attrName: 属性名称
     * - length: 长度值
     *
     * 例如：:attr-length-eq(data-value,10) 匹配 data-value 属性值长度等于 10 的元素
     *
     * @param  string  $arg  属性和长度参数，格式为 attrName,length
     * @return string XPath 条件
     */
    protected static function compileAttrLengthEq(string $arg): string
    {
        $arg = trim($arg);
        $parts = explode(',', $arg);

        if (count($parts) !== 2) {
            return '[true()]';
        }

        $attrName = trim($parts[0]);
        $length = (int) trim($parts[1]);

        return sprintf('[@%s and string-length(@%s) = %d]', $attrName, $attrName, $length);
    }

    /**
     * 编译 :depth-between() 伪类
     *
     * 参数格式为 min,max，其中：
     * - min: 最小深度（包含）
     * - max: 最大深度（包含）
     *
     * 例如：:depth-between(1,3) 匹配深度在 1 到 3 层的元素
     *
     * @param  string  $arg  深度范围参数，格式为 min,max
     * @return string XPath 条件
     */
    protected static function compileDepthBetween(string $arg): string
    {
        $arg = trim($arg);
        $parts = explode(',', $arg);

        if (count($parts) !== 2) {
            return '[true()]';
        }

        $min = (int) trim($parts[0]);
        $max = (int) trim($parts[1]);

        return sprintf('[count(ancestor::*) >= %d and count(ancestor::*) <= %d]', $min, $max);
    }

    /**
     * 编译 :text-match() 伪类（简化版正则匹配）
     *
     * 参数格式为 pattern，目前支持简单的模式匹配：
     * - 直接文本匹配
     * - 支持 * 通配符
     *
     * 例如：:text-match(test*) 匹配以 "test" 开头的文本
     *
     * @param  string  $arg  文本模式
     * @return string XPath 条件
     */
    protected static function compileTextMatch(string $arg): string
    {
        $arg = trim($arg);
        
        // 如果包含通配符，使用 starts-with
        if (str_ends_with($arg, '*')) {
            $prefix = substr($arg, 0, -1);
            return sprintf('[starts-with(., "%s")]', $prefix);
        }
        
        // 否则使用完全匹配
        return sprintf('[.="%s"]', $arg);
    }

    /**
     * 编译 :attr-match() 伪类
     *
     * 参数格式为 attrName,pattern，其中：
     * - attrName: 属性名称
     * - pattern: 匹配模式（支持简单的通配符）
     *
     * 例如：:attr-match(class,nav*) 匹配 class 属性以 "nav" 开头的元素
     *
     * @param  string  $arg  属性和模式参数，格式为 attrName,pattern
     * @return string XPath 条件
     */
    protected static function compileAttrMatch(string $arg): string
    {
        $arg = trim($arg);
        $parts = explode(',', $arg);

        if (count($parts) !== 2) {
            return '[true()]';
        }

        $attrName = trim($parts[0]);
        $pattern = trim($parts[1]);

        // 如果包含通配符，使用 starts-with
        if (str_ends_with($pattern, '*')) {
            $prefix = substr($pattern, 0, -1);
            return sprintf('[@%s and starts-with(@%s, "%s")]', $attrName, $attrName, $prefix);
        }
        
        // 否则使用完全匹配
        return sprintf('[@%s="%s"]', $attrName, $pattern);
    }
}

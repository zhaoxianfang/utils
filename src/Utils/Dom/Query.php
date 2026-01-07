<?php

declare(strict_types=1);

namespace zxf\Utils\Dom;

use InvalidArgumentException;
use RuntimeException;
use zxf\Utils\Dom\Exceptions\InvalidSelectorException;

/**
 * CSS 选择器到 XPath 转换器
 * 
 * 将 CSS 选择器表达式转换为 XPath 表达式
 * 支持几乎所有 CSS3 选择器语法
 * 
 * 特性：
 * - 完整的 CSS3 选择器支持
 * - 支持 XPath 直接使用
 * - 支持 text() 等 XPath 函数
 * - PHP 8.2+ 类型系统
 * - 伪类和伪元素支持
 * 
 * @example
 * $xpath = Query::compile('.item.active');
 * $xpath = Query::compile('div[data-id="123"]', Query::TYPE_CSS);
 * $result = $document->find('//div[contains(text(), "hello")]', Query::TYPE_XPATH);
 * 
 * @package zxf\Utils\Dom
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
     * @param  string  $expression  选择器表达式
     * @param  string  $type  选择器类型（CSS 或 XPath）
     * @return string 编译后的 XPath 表达式
     * 
     * @throws InvalidSelectorException 当选择器无效时抛出
     * @throws RuntimeException 当类型不支持时抛出
     */
    public static function compile(string $expression, string $type = self::TYPE_CSS): string
    {
        if (! in_array(strtolower($type), [self::TYPE_CSS, self::TYPE_XPATH], true)) {
            throw new RuntimeException(sprintf('不支持的表达式类型 "%s"。', $type));
        }

        $expression = trim($expression);

        if ($expression === '') {
            throw new InvalidSelectorException('选择器表达式不能为空。');
        }

        // 直接使用 XPath
        if (strcasecmp($type, self::TYPE_XPATH) === 0) {
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
     * 将 CSS 选择器转换为 XPath
     * 
     * 支持的 CSS 选择器语法：
     * - 基本选择器: *, div, .class, #id
     * - 属性选择器: [attr], [attr=value], [attr~=value], [attr|=value], [attr^=value], [attr$=value], [attr*=value]
     * - 组合选择器: 后代 (空格), 子元素 (>), 相邻兄弟 (+), 通用兄弟 (~)
     * - 多选择器: 逗号分隔
     * - 伪类选择器: :first-child, :last-child, :nth-child, :empty, :contains, :has, :not 等
     * 
     * @param  string  $selector  CSS 选择器
     * @return string XPath 表达式
     */
    protected static function cssToXpath(string $selector): string
    {
        $selector = trim($selector);

        // 处理多个选择器（逗号分隔）
        if (str_contains($selector, ',')) {
            $selectors = array_map('trim', explode(',', $selector));
            $xpaths = array_map(fn($s) => static::cssToXpath($s), $selectors);
            return implode(' | ', $xpaths);
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
                // 第一个段使用 // 开头
                $xpath = '//' . static::compileSegment($segment, true);
                $isFirst = false;
            } else {
                // 后续段根据组合器决定
                $xpath .= static::compileSegment($segment, false);
            }
        }

        return $xpath;
    }

    /**
     * 解析 CSS 选择器为段数组（公开方法）
     * 
     * @param  string  $selector  CSS 选择器
     * @return array<int, array> 段数组
     */
    public static function parseSelector(string $selector): array
    {
        $segments = [];
        
        // 先用特殊标记替换 > + ~ 组合器（只匹配前后是空格或字符串边界的）
        $temp = preg_replace('/\s+([>+~])\s+/', chr(0) . '$1' . chr(0), $selector);
        $temp = preg_replace('/^([>+~])\s+/', chr(0) . '$1' . chr(0), $temp);
        $temp = preg_replace('/\s+([>+~])$/', chr(0) . '$1' . chr(0), $temp);
        // 用 chr(0) 分割
        $parts = explode(chr(0), $temp);
        
        $selectorParts = [];
        $combinators = [];
        
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            
            if (in_array($part, ['>', '+', '~'], true)) {
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
                            // 添加空格组合器
                            if (count($selectorParts) > 1 && 
                                ($combinators[count($selectorParts)-2] ?? '') !== ' ') {
                                array_splice($combinators, count($selectorParts)-1, 0, ' ');
                            }
                        }
                    }
                } else {
                    $selectorParts[] = $part;
                }
            }
        }
        
        // 对于后代选择器（原始选择器中有空格但不是 > + ~），添加空格组合器
        if (count($selectorParts) > 1 && count($combinators) < count($selectorParts) - 1) {
            $needed = count($selectorParts) - 1 - count($combinators);
            for ($i = 0; $i < $needed; $i++) {
                $combinators[] = ' ';
            }
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
        if (preg_match('/::([a-zA-Z0-9_-]+)(?:\(([^)]*)\))?/', $part, $matches)) {
            $current['pseudo'] = '::' . $matches[1];
            $current['pseudoArg'] = $matches[2] ?? '';
            $part = preg_replace('/::[a-zA-Z0-9_-]+(?:\([^)]*\))?/', '', $part);
        }
        // 处理单冒号伪类
        elseif (preg_match('/:([a-zA-Z0-9_-]+)(?:\(([^)]*)\))?/', $part, $matches)) {
            $current['pseudo'] = $matches[1];
            $current['pseudoArg'] = $matches[2] ?? '';
            // 删除整个伪类（包括参数） - 使用匹配的完整文本
            $part = str_replace($matches[0], '', $part);
        }

        // 先提取属性（包含在 [] 中，避免与类/ID混淆）
        if (preg_match_all('/\[([a-zA-Z0-9_-]+)([*~|^$]?=)?([\\"\']?)(.*?)\3\]/', $part, $matches, PREG_SET_ORDER)) {
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
        if (preg_match('/#([a-zA-Z0-9_-]+)/', $part, $matches)) {
            $current['id'] = $matches[1];
            $part = str_replace('#' . $matches[1], '', $part);
        }

        // 提取类名
        if (preg_match_all('/\.([a-zA-Z0-9_-]+)/', $part, $matches)) {
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
            $xpath .= static::compilePseudo($segment['pseudo'], $segment['pseudoArg'] ?? '');
        }

        return $xpath;
    }

    /**
     * 编译属性选择器
     * 
     * @param  array{name: string, operator: string|null, value: string|null}  $attr  属性信息
     * @return string XPath 属性条件
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
            '~=' => sprintf('[contains(concat(" ", normalize-space(@%s), " "), " %s ")]', $name, $value),
            '|=' => sprintf('[@%s="%s" or starts-with(@%s, "%s-")]', $name, $value, $name, $value),
            '=' => sprintf('[@%s="%s"]', $name, $value),
            default => sprintf('[@%s]', $name),
        };
    }

    /**
     * 编译伪类选择器
     *
     * 支持的伪类列表：
     * - 结构伪类: first-child, last-child, only-child, nth-child, nth-last-child
     * - 类型伪类: first-of-type, last-of-type, only-of-type, nth-of-type, nth-last-of-type
     * - 内容伪类: empty, contains, has, not
     * - 表单伪类: enabled, disabled, checked, selected, focus
     * - 状态伪类: root, target, hover, visible, hidden
     * - 类型伪类: header, input, button, text, comment
     * - 位置伪类: first, last, even, odd, eq, gt, lt, slice
     * - 表单元素类型伪类: checkbox, radio, password, file, email, url, number, tel, search, date, time, datetime, month, week, color, range, submit, reset
     * - HTML元素伪类: video, audio, canvas, svg, script, style, meta, link, base, head, body, title
     * - 内容匹配伪类: contains-text, starts-with, ends-with, blank, parent-only-text
     * - 语言伪类: lang
     * - 表单验证伪类: in-range, out-of-range, indeterminate, placeholder-shown, default, valid, invalid, autofill
     *
     * @param  string  $pseudo  伪类名
     * @param  string  $arg  伪类参数
     * @return string XPath 伪类条件
     */
    protected static function compilePseudo(string $pseudo, string $arg): string
    {
        return match ($pseudo) {
            // 结构伪类 - 子元素位置
            'first-child' => '[not(preceding-sibling::*)]',
            'last-child' => '[not(following-sibling::*)]',
            'only-child' => '[not(preceding-sibling::*) and not(following-sibling::*)]',
            'nth-child' => static::compileNthChild($arg),
            'nth-last-child' => static::compileNthChild($arg, true),
            // 结构伪类 - 同类型元素位置
            'first-of-type' => '[not(preceding-sibling::*)]', // 需要结合元素标签名使用
            'last-of-type' => '[not(following-sibling::*)]', // 需要结合元素标签名使用
            'only-of-type' => '[not(preceding-sibling::*[name()=name(current())]) and not(following-sibling::*[name()=name(current())])]',
            'nth-of-type' => static::compileNthChild($arg, false, true),
            'nth-last-of-type' => static::compileNthChild($arg, true, true),
            // 内容伪类
            'empty' => '[not(*) and not(text()[normalize-space()])]',
            'contains' => sprintf('[contains(string(.), "%s")]', $arg),
            'not' => static::compileNot($arg),
            'has' => static::compileHas($arg),
            // 文档状态伪类
            'root' => '[not(parent::*)]',
            'target' => '[@name=substring-after(., "#") and substring-after(., "#")!=""]',
            // 表单状态伪类
            'enabled' => '[not(@disabled="disabled") and not(@type="hidden")]',
            'disabled' => '[@disabled="disabled"]',
            'checked' => '[@checked="checked"]',
            'selected' => '[@selected="selected"]',
            'required' => '[@required="required"]',
            'optional' => '[@required!="required"]',
            'read-only' => '[@readonly="readonly"]',
            'read-write' => '[@readonly!="readonly"]',
            // 用户交互伪类
            'focus' => '[@focus]',
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
            // 内容匹配伪类
            'contains-text' => sprintf('[contains(text(), "%s")]', $arg),
            'starts-with' => sprintf('[starts-with(text(), "%s")]', $arg),
            'ends-with' => sprintf('[substring(text(), string-length(text()) - string-length("%s") + 1) = "%s"]', $arg, $arg),
            // 属性匹配伪类
            'has-attr' => sprintf('[@%s]', $arg),
            'data' => sprintf('[@data-%s]', $arg),
            // 节点类型伪类
            'element' => '[@*]',
            'text-node' => '[self::text()]',
            'comment-node' => '[self::comment()]',
            'cdata' => '[self::cdata-section()]',
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
            'in-range' => '[@min and @max and number(.) >= number(@min) and number(.) <= number(@max)]',
            'out-of-range' => '[@min and @max and (number(.) < number(@min) or number(.) > number(@max))]',
            'indeterminate' => '[@indeterminate="indeterminate"]',
            'placeholder-shown' => '[@placeholder and not(@value) or @value=""]',
            'default' => '[@default]',
            'valid' => '[@valid="valid"]',
            'invalid' => '[@invalid="invalid"]',
            'autofill' => '[contains(@style, "background-color") or contains(@style, "background")]',
            // 结构伪类扩展
            'only-child' => '[not(preceding-sibling::*) and not(following-sibling::*)]',
            'only-of-type' => sprintf('[not(preceding-sibling::%s) and not(following-sibling::%s)]', $arg, $arg),
            // 伪元素（在 Document 中处理）
            default => '',
        };
    }

    /**
     * 编译 nth-child 伪类
     * 
     * @param  string  $formula  nth 公式
     * @param  bool  $reverse  是否反向（nth-last-*）
     * @param  bool  $ofType  是否按类型（nth-of-type）
     * @return string XPath 条件
     */
    protected static function compileNthChild(string $formula, bool $reverse = false, bool $ofType = false): string
    {
        $formula = strtolower(trim($formula));

        if ($formula === 'even') {
            return $reverse ? '[last() - position() mod 2 = 0]' : '[position() mod 2 = 0]';
        }

        if ($formula === 'odd') {
            return $reverse ? '[last() - position() mod 2 = 1]' : '[position() mod 2 = 1]';
        }

        // 解析公式: an+b
        if (preg_match('/^(?P<a>-?\d*)?n(?:(?P<b>[+-]\d+)?)?$/', $formula, $matches)) {
            $a = $matches['a'] !== '' ? (int) $matches['a'] : 1;
            $b = isset($matches['b']) ? (int) $matches['b'] : 0;

            if ($a === 0) {
                $pos = $reverse ? sprintf('last() - (%d - 1)', $b) : (string) $b;
                return sprintf('[position() = %s]', $pos);
            }

            if ($b === 0) {
                return $reverse 
                    ? sprintf('[(last() - position()) mod %d = 0]', abs($a))
                    : sprintf('[position() mod %d = 0]', abs($a));
            }

            if ($a > 0) {
                if ($b > 0) {
                    return sprintf('[position() >= %d and position() mod %d = %d]', $b, $a, $b % $a);
                } else {
                    $absB = abs($b);
                    return sprintf('[position() >= %d and position() mod %d = %d]', $absB, $a, $a - ($absB % $a));
                }
            } else {
                $absA = abs($a);
                if ($b > 0) {
                    return sprintf('[position() <= %d and position() mod %d = %d]', $b, $absA, $b % $absA);
                } else {
                    return sprintf('[position() mod %d = 0]', $absA);
                }
            }
        }

        // 纯数字
        if (is_numeric($formula)) {
            $pos = $reverse ? sprintf('last() - (%s - 1)', $formula) : $formula;
            return sprintf('[position() = %s]', $pos);
        }

        return '';
    }

    /**
     * 编译 :not() 伪类
     * 
     * @param  string  $selector  内部选择器
     * @return string XPath 条件
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
     * @param  string  $selector  内部选择器
     * @return string XPath 条件
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
}

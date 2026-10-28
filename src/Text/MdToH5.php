<?php

declare(strict_types=1);

/**
 * MdToH5 - Markdown to HTML5 转换器
 *
 * 完全独立的 PHP 8.2+ 实现，不依赖任何外部包
 * 支持完整 CommonMark 规范及 GitHub Flavored Markdown 扩展
 *
 * @package zxf\Utils\Text
 * @version 1.0.0
 * @license MIT
 */

namespace zxf\Utils\Text;

use InvalidArgumentException;
use RuntimeException;
use Exception;

/**
 * 一、支持的 Markdown 语法：
 * 1. **标题** - H1-H6 (`#`, `##`, `###`, `####`, `#####`, `######`)
 * 2. **引用块** - 单级和多级嵌套 (`>`, `>>`)
 * 3. **行内代码** - 单反引号和双反引号 (`` `code` ``, `` ``code`` ``)
 * 4. **代码块** - 三个反引号 (```)
 * 5. **粗体** - `**text**` 和 `__text__`
 * 6. **斜体** - `*text*` 和 `_text_`
 * 7. **删除线** - `~~text~~`
 * 8. **无序列表** - `-`, `*`, `+`
 * 9. **有序列表** - `1.`, `2.`
 * 10. **任务列表** - `- [ ]`, `- [x]`
 * 11. **表格** - 支持对齐方式
 * 12. **链接** - `[text](url)`
 * 13. **图片** - `![alt](url)`
 * 14. **自动链接** - URL 和邮箱
 * 15. **脚注** - `[^1]`
 * 16. **缩写** - `*[HTML]:`
 * 17. **表情符号** - `:smile:`
 * 18. **水平线** - `---`, `***`, `___`
 * 19. **转义字符** - `\*`
 *
 * 二、使用示例：
 * use zxf\Utils\Text\MdToH5;
 *
 * $converter = new MdToH5([
 *   'safe_mode' => true,
 *   'filter_xss' => true,
 *   ...配置...
 * ]);
 * echo $html = $converter->convert($markdownString);
 *
 * @package zxf\Utils\Text
 * @version 1.0.0
 * @license MIT
 */
class MdToH5
{
    /**
     * 配置选项
     *
     * @var array<string, mixed>
     */
    private array $options = [
        'safe_mode' => true,              // 安全模式，转义 HTML 标签
        'allow_unsafe_links' => false,    // 禁止 javascript: 等危险链接
        'nl2br' => false,                 // 是否将换行转换为 <br>
        'tab_width' => 4,                 // 制表符宽度
        'xhtml' => false,                 // 是否输出 XHTML 格式
        'smart_quotes' => true,           // 智能引号转换
        'smart_ellipsis' => true,         // 智能省略号转换
        'smart_dashes' => true,           // 智能破折号转换
        'enable_tables' => true,          // 启用表格支持
        'enable_strikethrough' => true,   // 启用删除线支持
        'enable_task_lists' => true,      // 启用任务列表支持
        'enable_auto_links' => true,      // 启用自动链接识别
        'enable_emoji' => true,           // 启用表情符号支持
        'enable_footnotes' => true,       // 启用脚注支持
        'enable_abbreviations' => true,   // 启用缩写支持
        'max_nesting_level' => 10,        // 最大嵌套深度
        'cache_enabled' => false,         // 是否启用缓存
        'cache_ttl' => 3600,              // 缓存时间（秒）
        'filter_xss' => true,             // 是否过滤 XSS 攻击
        'allowed_html_tags' => [          // 允许的 HTML 标签（白名单）
            'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'strong', 'b', 'em', 'i', 'u', 'del', 's',
            'a', 'img', 'code', 'pre', 'blockquote',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'div', 'span', 'section', 'article',
            'abbr', 'sup', 'sub', 'small'
        ],
        'forbidden_attributes' => [        // 禁止的 HTML 属性
            'onclick', 'onload', 'onerror', 'onmouseover', 'onmouseout',
            'onfocus', 'onblur', 'onchange', 'onsubmit', 'onreset',
            'onselect', 'oncopy', 'oncut', 'onpaste', 'ondrag',
            'ondrop', 'onscroll', 'onresize', 'onunload', 'onabort'
        ],
        'forbidden_protocols' => [         // 禁止的 URL 协议
            'javascript:', 'vbscript:', 'data:', 'file:',
            'about:', 'chrome:', 'view-source:', 'mhtml:'
        ],
    ];

    /**
     * 代码块语言映射
     *
     * @var array<string, string>
     */
    private array $languageAliases = [
        'js' => 'javascript',
        'ts' => 'typescript',
        'tsx' => 'typescript',
        'jsx' => 'javascript',
        'sh' => 'bash',
        'shell' => 'bash',
        'zsh' => 'bash',
        'py' => 'python',
        'rb' => 'ruby',
        'go' => 'go',
        'rs' => 'rust',
        'java' => 'java',
        'cpp' => 'cpp',
        'c' => 'c',
        'cs' => 'csharp',
        'php' => 'php',
        'html' => 'html',
        'css' => 'css',
        'scss' => 'scss',
        'sass' => 'sass',
        'less' => 'less',
        'sql' => 'sql',
        'json' => 'json',
        'xml' => 'xml',
        'yaml' => 'yaml',
        'yml' => 'yaml',
        'md' => 'markdown',
        'diff' => 'diff',
        'dockerfile' => 'dockerfile',
    ];

    /**
     * 表情符号映射
     *
     * @var array<string, string>
     */
    private array $emojiMap = [
        ':smile:' => '😊',
        ':laughing:' => '😆',
        ':blush:' => '😊',
        ':heart:' => '❤️',
        ':rocket:' => '🚀',
        ':fire:' => '🔥',
        ':star:' => '⭐',
        ':bug:' => '🐛',
        ':tada:' => '🎉',
        ':sparkles:' => '✨',
        ':zap:' => '⚡',
        ':book:' => '📖',
        ':warning:' => '⚠️',
        ':info:' => 'ℹ️',
        ':check:' => '✅',
        ':error:' => '❌',
        ':question:' => '❓',
        ':bulb:' => '💡',
    ];

    /**
     * 缩写定义存储
     *
     * @var array<string, string>
     */
    private array $abbreviations = [];

    /**
     * 脚注定义存储
     *
     * @var array<string, string>
     */
    private array $footnotes = [];

    /**
     * 脚注引用存储
     *
     * @var array<string, bool>
     */
    private array $footnoteRefs = [];

    /**
     * 缓存存储
     *
     * @var array<string, array{html: string, time: int}>
     */
    private array $cache = [];

    /**
     * 当前嵌套深度
     *
     * @var int
     */
    private int $nestingLevel = 0;

    /**
     * 链接引用存储
     *
     * @var array<string, array{url: string, title: string}>
     */
    private array $linkReferences = [];

    /**
     * 构造函数
     *
     * @param array<string, mixed> $options 配置选项
     * @throws InvalidArgumentException 当配置参数无效时抛出
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
        $this->validateOptions();
    }

    /**
     * 验证配置选项
     *
     * 校验三处边界：max_nesting_level 需在 1~50、tab_width 需在 1~8、cache_ttl 不能为负；
     * 由构造函数在应用配置后调用，任一不满足即中断实例化。
     *
     * @return void 无返回值
     *
     * @throws InvalidArgumentException 任一配置越界时抛出
     */
    private function validateOptions(): void
    {
        if ($this->options['max_nesting_level'] < 1 || $this->options['max_nesting_level'] > 50) {
            throw new InvalidArgumentException('max_nesting_level 参数必须在 1 到 50 之间');
        }

        if ($this->options['tab_width'] < 1 || $this->options['tab_width'] > 8) {
            throw new InvalidArgumentException('tab_width 参数必须在 1 到 8 之间');
        }

        if ($this->options['cache_ttl'] < 0) {
            throw new InvalidArgumentException('cache_ttl 参数不能为负数');
        }
    }

    /**
     * 转换 Markdown 为 HTML5
     *
     * @param string $markdown Markdown 文本
     * @return string HTML5 字符串
     * @throws RuntimeException 当转换过程发生错误时抛出
     */
    public function convert(string $markdown): string
    {
        // 空字符串处理
        if ($markdown === '') {
            return '';
        }

        $cacheKey = $this->options['cache_enabled'] ? md5($markdown) : null;

        // 检查缓存
        if ($cacheKey !== null && isset($this->cache[$cacheKey]) && (time() - $this->cache[$cacheKey]['time']) < $this->options['cache_ttl']) {
            return $this->cache[$cacheKey]['html'];
        }

        try {
            // 重置内部状态
            $this->resetState();

            // 预处理 Markdown 文本
            $markdown = $this->preprocessMarkdown($markdown);

            // 第一步：处理块级元素
            $html = $this->parseBlockElements($markdown);

            // 第二步：处理内联元素
            $html = $this->parseInlineElements($html);

            // 添加脚注部分
            if ($this->options['enable_footnotes'] && !empty($this->footnotes)) {
                $html .= $this->renderFootnotes();
            }

            // XSS 过滤
            if ($this->options['filter_xss']) {
                $html = $this->filterXss($html);
            }

            // 清理多余的空白字符
            $html = $this->cleanupWhitespace($html);

            // 存储到缓存
            if ($cacheKey !== null) {
                $this->cache[$cacheKey] = [
                    'html' => $html,
                    'time' => time()
                ];
            }

            return $html;

        } catch (Exception $e) {
            throw new RuntimeException("Markdown 转换失败: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * XSS 过滤
     *
     * 三道处理：移除黑名单容器标签（如 script/style/iframe）、
     * 删除 forbidden_attributes 中的属性、再按 allowed_html_tags 白名单移除其余标签。
     * 属于正则层面的粗粒度过滤，不等同于完整的 HTML 净化器。
     *
     * @param string $html HTML 内容
     * @return string 过滤后的 HTML
     */
    private function filterXss(string $html): string
    {
        // 移除危险标签
        $dangerousTags = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button'];
        foreach ($dangerousTags as $tag) {
            $html = preg_replace('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', '', $html) ?? $html;
            $html = preg_replace('/<' . $tag . '\b[^>]*>/i', '', $html) ?? $html;
        }

        // 移除禁止的属性
        foreach ($this->options['forbidden_attributes'] as $attr) {
            $pattern = '/' . preg_quote($attr, '/') . '\s*=\s*["\'][^"\']*["\']/i';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        // 过滤不安全的 HTML 标签
        $html = $this->stripUnsafeTags($html);

        return $html;
    }

    /**
     * 移除不安全的 HTML 标签
     *
     * @param string $html HTML 内容
     * @return string 过滤后的 HTML
     */
    private function stripUnsafeTags(string $html): string
    {
        $allowedPattern = '/<(?!\/?(?:' . implode('|', $this->options['allowed_html_tags']) . ')\b)[^>]*>/i';
        $html = preg_replace($allowedPattern, '', $html) ?? $html;
        return $html;
    }

    /**
     * 重置内部状态
     *
     * 清零嵌套层级，并清空链接引用、脚注、脚注引用与缩写表；
     * 不清理缓存与配置，每次 convert() 开始时调用以保证多次转换互不干扰。
     *
     * @return void 无返回值
     */
    private function resetState(): void
    {
        $this->nestingLevel = 0;
        $this->linkReferences = [];
        $this->footnotes = [];
        $this->footnoteRefs = [];
        $this->abbreviations = [];
    }

    /**
     * 预处理 Markdown 文本
     *
     * @param string $markdown 原始 Markdown 文本
     * @return string 预处理后的文本
     */
    private function preprocessMarkdown(string $markdown): string
    {
        // 规范化换行符
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        // 规范化制表符
        $spaces = str_repeat(' ', $this->options['tab_width']);
        $markdown = str_replace("\t", $spaces, $markdown);

        // 提取引用定义
        $markdown = $this->extractReferenceDefinitions($markdown);

        // 提取缩写定义
        if ($this->options['enable_abbreviations']) {
            $markdown = $this->extractAbbreviations($markdown);
        }

        // 提取脚注定义
        if ($this->options['enable_footnotes']) {
            $markdown = $this->extractFootnoteDefinitions($markdown);
        }

        // 智能标点转换
        $markdown = $this->convertSmartPunctuation($markdown);

        return $markdown;
    }

    /**
     * 智能标点转换
     *
     * 按开关分别处理：成对直引号转中文弯引号、`...` 转省略号、`--` 转长破折号；
     * 均在块级解析之前对全文执行，因此也会作用于代码块之外的普通文本。
     *
     * @param string $text 原始文本
     * @return string 转换后的文本
     */
    private function convertSmartPunctuation(string $text): string
    {
        if ($this->options['smart_quotes']) {
            $text = preg_replace('/"([^"]*)"/', '“$1”', $text) ?? $text;
            $text = preg_replace("/'([^']*)'/", '‘$1’', $text) ?? $text;
        }

        if ($this->options['smart_ellipsis']) {
            $text = preg_replace('/\.{3}/', '…', $text) ?? $text;
        }

        if ($this->options['smart_dashes']) {
            $text = preg_replace('/--/', '—', $text) ?? $text;
        }

        return $text;
    }

    /**
     * 提取引用定义
     *
     * 扫描形如 `[id]: url "title"` 的独立行，把 ID（小写）映射到净化后的 URL 与标题存入
     * linkReferences；URL 净化后为空则不登记。命中任一定义时整行从正文中移除，
     * 未命中则原样返回。
     *
     * @param string $markdown Markdown 文本
     * @return string 移除引用定义后的文本
     */
    private function extractReferenceDefinitions(string $markdown): string
    {
        $pattern = '/^\[([^\]]+)\]:\s+(\S+)(?:\s+[\'"(](.+)[\'")])?\s*$/m';

        if (preg_match_all($pattern, $markdown, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $id = strtolower(trim($match[1]));
                $url = $this->sanitizeUrl($match[2]);
                $title = $match[3] ?? '';

                if ($url !== '') {
                    $this->linkReferences[$id] = [
                        'url' => $url,
                        'title' => $title
                    ];
                }
            }

            $result = preg_replace($pattern . '\n?', '', $markdown);
            return $result ?? $markdown;
        }

        return $markdown;
    }

    /**
     * 提取缩写定义
     *
     * 扫描 `*[ABBR]: 说明` 形式的独立行，按「缩写 => 说明」存入 abbreviations 供后续
     * 内联替换使用；命中时从正文移除这些定义行，未命中则原样返回。
     *
     * @param string $markdown Markdown 文本
     * @return string 移除缩写定义后的文本
     */
    private function extractAbbreviations(string $markdown): string
    {
        $pattern = '/^\*\[([^\]]+)\]:\s+(.+)$/m';

        if (preg_match_all($pattern, $markdown, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->abbreviations[$match[1]] = $match[2];
            }

            $result = preg_replace($pattern . '\n?', '', $markdown);
            return $result ?? $markdown;
        }

        return $markdown;
    }

    /**
     * 提取脚注定义
     *
     * 扫描 `[^id]: 内容` 形式的独立行，内容先经内联解析再按 id 存入 footnotes；
     * 命中时从正文移除定义行，未命中则原样返回。
     *
     * @param string $markdown Markdown 文本
     * @return string 移除脚注定义后的文本
     */
    private function extractFootnoteDefinitions(string $markdown): string
    {
        $pattern = '/^\[\^([^\]]+)\]:\s+(.+)$/m';

        if (preg_match_all($pattern, $markdown, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $id = $match[1];
                $content = $this->parseInlineElements($match[2]);
                $this->footnotes[$id] = $content;
            }

            $result = preg_replace($pattern . '\n?', '', $markdown);
            return $result ?? $markdown;
        }

        return $markdown;
    }

    /**
     * 解析块级元素（主要入口）
     *
     * @param string $markdown Markdown 文本
     * @return string HTML 字符串
     */
    private function parseBlockElements(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $result = [];
        $i = 0;
        $totalLines = count($lines);

        while ($i < $totalLines) {
            $line = $lines[$i];
            $trimmedLine = trim($line);

            // 跳过空行
            if ($trimmedLine === '') {
                $i++;
                continue;
            }

            // 检查嵌套深度
            if ($this->nestingLevel > $this->options['max_nesting_level']) {
                throw new RuntimeException("超过最大嵌套深度限制: {$this->options['max_nesting_level']}");
            }

            // 标题
            if (preg_match('/^#{1,6} /', $line)) {
                $result[] = $this->parseHeading($line);
                $i++;
                continue;
            }

            // 代码块（三个反引号）
            if (preg_match('/^```/', $line)) {
                $codeBlock = $this->parseCodeBlock($lines, $i);
                $result[] = $codeBlock['html'];
                $i = $codeBlock['nextIndex'];
                continue;
            }

            // 水平线
            // 写法等价但去掉了嵌套量词：(\1[ \t]*)* 这种「量词套量词」在长输入失败时
            // 会指数级回溯。改为 (?:[ \t]*\1){2,} 后每个循环必消耗一个字符，回溯降为线性。
            if (preg_match('/^([-*_])(?:[ \t]*\1){2,}[ \t]*$/', $trimmedLine)) {
                $result[] = $this->options['xhtml'] ? '<hr />' : '<hr>';
                $i++;
                continue;
            }

            // 引用块
            if (preg_match('/^>/', $line)) {
                $quoteBlock = $this->parseBlockquote($lines, $i);
                $result[] = $quoteBlock['html'];
                $i = $quoteBlock['nextIndex'];
                continue;
            }

            // 表格
            if ($this->options['enable_tables'] && $this->isTable($lines, $i)) {
                $table = $this->parseTable($lines, $i);
                $result[] = $table['html'];
                $i = $table['nextIndex'];
                continue;
            }

            // 列表
            if (preg_match('/^[-*+]\s+|\d+\.\s+/', ltrim($line))) {
                $list = $this->parseList($lines, $i);
                $result[] = $list['html'];
                $i = $list['nextIndex'];
                continue;
            }

            // 普通段落
            $paragraph = $this->parseParagraph($lines, $i);
            if ($paragraph['text'] !== '') {
                $result[] = $paragraph['html'];
            }
            $i = $paragraph['nextIndex'];
        }

        return implode("\n", $result);
    }

    /**
     * 解析标题
     *
     * 匹配 `#`~`######` 加空格的 ATX 标题，按 `#` 数量决定标签级别，
     * 行尾的 `{#id}` 会被丢弃并改用自动生成的 id；不匹配时降级为段落。
     *
     * @param string $line 标题行
     * @return string HTML 标题（h1~h6）或降级后的 p 标签
     */
    private function parseHeading(string $line): string
    {
        if (preg_match('/^(#{1,6}) (.*?)(?: {#.*})?$/', $line, $matches)) {
            $level = strlen($matches[1]);
            $content = $matches[2];
            $content = $this->parseInlineElements($content);
            $id = $this->generateHeadingId($content);

            return sprintf('<h%d id="%s">%s</h%d>', $level, $id, $content, $level);
        }

        return '<p>' . $this->parseInlineElements($line) . '</p>';
    }

    /**
     * 生成标题 ID
     *
     * 先去标签、转小写，再把非 ASCII 字母数字的连续字符替换为 `-` 并去掉首尾连字符；
     * 结果为空（例如纯中文标题）时固定返回 `heading`，不会做唯一性去重。
     *
     * @param string $text 标题文本
     * @return string 可用于锚点的 id 字符串
     */
    private function generateHeadingId(string $text): string
    {
        $text = strip_tags($text);
        $id = mb_strtolower($text, 'UTF-8');
        $id = preg_replace('/[^a-z0-9]+/u', '-', $id) ?? '';
        $id = trim($id, '-');

        return $id !== '' ? $id : 'heading';
    }

    /**
     * 解析代码块
     *
     * 以三反引号围栏为界收集代码行，围栏后的语言标识会按 languageAliases 替换；
     * safe_mode 开启时转义代码内容，语言非空时写入 `language-xxx` class。
     * 未找到闭合围栏时收集到文末为止。
     *
     * @param array<int, string> $lines 所有行
     * @param int $startIndex 开始索引
     * @return array{html: string, nextIndex: int} HTML 片段与下一行的索引
     */
    private function parseCodeBlock(array $lines, int $startIndex): array
    {
        $fenceLine = $lines[$startIndex];
        $fenceChar = '`';
        $fenceLength = 3;

        // 提取语言标识
        $language = trim(substr($fenceLine, $fenceLength));
        $language = $this->languageAliases[$language] ?? $language;

        // 收集代码行
        $codeLines = [];
        $i = $startIndex + 1;
        $totalLines = count($lines);

        while ($i < $totalLines) {
            $currentLine = $lines[$i];
            if (trim($currentLine) === '```') {
                break;
            }
            $codeLines[] = $currentLine;
            $i++;
        }

        $code = implode("\n", $codeLines);

        // 安全处理代码内容
        if ($this->options['safe_mode']) {
            $code = htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $languageClass = $language !== '' ? " class=\"language-{$language}\"" : '';
        $html = sprintf("<pre><code%s>%s</code></pre>", $languageClass, $code);

        return [
            'html' => $html,
            'nextIndex' => $i + 1
        ];
    }

    /**
     * 解析引用块
     *
     * 连续收集 `>` 开头的行（去掉引用标记），遇非引用行结束；中间的空行不会截断引用，
     * 内容交由新建的同配置实例递归转换，从而支持嵌套引用与块内其它语法。
     *
     * @param array<int, string> $lines 所有行
     * @param int $startIndex 开始索引
     * @return array{html: string, nextIndex: int} HTML 片段（blockquote 包裹）与下一行的索引
     */
    private function parseBlockquote(array $lines, int $startIndex): array
    {
        $this->nestingLevel++;

        $quoteLines = [];
        $i = $startIndex;
        $totalLines = count($lines);

        while ($i < $totalLines) {
            $line = $lines[$i];

            // 如果不是引用行，结束
            if (!preg_match('/^>/', $line)) {
                break;
            }

            // 移除引用标记（支持 > 和 > 后跟空格）
            $line = preg_replace('/^>\s?/', '', $line);
            $quoteLines[] = $line;
            $i++;

            // 如果下一行不是引用行且不是空行，结束
            if ($i < $totalLines && !preg_match('/^>/', $lines[$i]) && trim($lines[$i]) !== '') {
                break;
            }
        }

        $quoteContent = implode("\n", $quoteLines);

        // 递归解析引用块内部内容
        $nestedConverter = new self($this->options);
        $innerHtml = $nestedConverter->convert($quoteContent);

        $html = "<blockquote>\n" . $innerHtml . "\n</blockquote>";

        $this->nestingLevel--;

        return [
            'html' => $html,
            'nextIndex' => $i
        ];
    }

    /**
     * 判断是否为表格
     *
     * 判定条件：当前行与下一行都含 `|`，且下一行去掉 `|` 后仅由空白、`-`、`:` 组成
     * （即分隔行）。末尾缺少下一行时直接返回 false。
     *
     * @param array<int, string> $lines 所有行
     * @param int $index 当前索引
     * @return bool 是表格起始返回 true，否则返回 false
     */
    private function isTable(array $lines, int $index): bool
    {
        if (!isset($lines[$index + 1])) {
            return false;
        }

        $firstLine = trim($lines[$index]);
        $secondLine = trim($lines[$index + 1]);

        return strpos($firstLine, '|') !== false &&
            strpos($secondLine, '|') !== false &&
            preg_match('/^[\|\s\-:]+$/', str_replace('|', '', $secondLine)) === 1;
    }

    /**
     * 解析表格
     *
     * 首行作表头、次行按 `:` 位置推断各列对齐（默认左对齐），其后连续含 `|` 的行作数据行；
     * 输出带 table-wrapper 容器与 markdown-table 类名的表格，单元格内容走内联解析。
     *
     * @param array<int, string> $lines 所有行
     * @param int $startIndex 开始索引（表头行）
     * @return array{html: string, nextIndex: int} HTML 片段与下一行的索引
     */
    private function parseTable(array $lines, int $startIndex): array
    {
        $headerLine = trim($lines[$startIndex]);
        $separatorLine = trim($lines[$startIndex + 1]);

        // 解析表头
        $headers = array_map('trim', explode('|', trim($headerLine, '|')));

        // 解析对齐方式
        $alignments = [];
        $separators = array_map('trim', explode('|', trim($separatorLine, '|')));

        foreach ($separators as $separator) {
            if (str_starts_with($separator, ':') && str_ends_with($separator, ':')) {
                $alignments[] = 'center';
            } elseif (str_starts_with($separator, ':')) {
                $alignments[] = 'left';
            } elseif (str_ends_with($separator, ':')) {
                $alignments[] = 'right';
            } else {
                $alignments[] = 'left';
            }
        }

        // 解析数据行
        $rows = [];
        $i = $startIndex + 2;
        $totalLines = count($lines);

        while ($i < $totalLines && strpos(trim($lines[$i]), '|') !== false) {
            $row = array_map('trim', explode('|', trim($lines[$i], '|')));
            $rows[] = $row;
            $i++;

            if ($i < $totalLines && trim($lines[$i]) === '') {
                $i++;
                break;
            }
        }

        // 构建表格 HTML
        $html = "<div class=\"table-wrapper\">\n<table class=\"markdown-table\">\n";

        // 表头
        $html .= "<thead>\n<tr>\n";
        foreach ($headers as $index => $header) {
            $align = $alignments[$index] ?? 'left';
            $headerContent = $this->parseInlineElements($header);
            $html .= sprintf("<th style=\"text-align: %s\">%s</th>\n", $align, $headerContent);
        }
        $html .= "</tr>\n</thead>\n";

        // 表体
        if (!empty($rows)) {
            $html .= "<tbody>\n";
            foreach ($rows as $row) {
                $html .= "<tr>\n";
                foreach ($row as $index => $cell) {
                    $align = $alignments[$index] ?? 'left';
                    $cellContent = $this->parseInlineElements($cell);
                    $html .= sprintf("<td style=\"text-align: %s\">%s</td>\n", $align, $cellContent);
                }
                $html .= "</tr>\n";
            }
            $html .= "</tbody>\n";
        }

        $html .= "</table>\n</div>";

        return [
            'html' => $html,
            'nextIndex' => $i
        ];
    }

    /**
     * 解析列表
     *
     * 按首个标记决定 ul/ol（同一次解析不混用）；enable_task_lists 开启时识别
     * `[ ]`/`[x]` 前缀并渲染为禁用复选框（此时强制为 ul）。
     * 空行后若不再是列表项则结束列表。
     *
     * @param array<int, string> $lines 所有行
     * @param int $startIndex 开始索引
     * @return array{html: string, nextIndex: int} HTML 片段与下一行的索引
     */
    private function parseList(array $lines, int $startIndex): array
    {
        $this->nestingLevel++;

        $listItems = [];
        $listType = null;
        $i = $startIndex;
        $totalLines = count($lines);

        while ($i < $totalLines) {
            $line = $lines[$i];
            $trimmedLine = ltrim($line);

            if (!preg_match('/^[-*+]\s+|\d+\.\s+/', $trimmedLine)) {
                break;
            }

            // 确定列表类型
            if ($listType === null) {
                if (preg_match('/^[-*+]\s+/', $trimmedLine)) {
                    $listType = 'ul';
                } else {
                    $listType = 'ol';
                }
            }

            // 提取列表项内容
            $content = preg_replace('/^[-*+]\s+|\d+\.\s+/', '', $trimmedLine) ?? '';

            // 处理任务列表
            if ($this->options['enable_task_lists'] && preg_match('/^\[[ xX]\]\s+/', $content)) {
                $checked = preg_match('/^\[[xX]/', $content) === 1;
                $content = preg_replace('/^\[[ xX]\]\s+/', '', $content) ?? '';
                $checkbox = sprintf(
                    '<input type="checkbox" class="task-list-item-checkbox" %sdisabled="disabled">',
                    $checked ? 'checked="checked" ' : ''
                );
                $content = $checkbox . ' ' . $content;
                $listType = 'ul';
            }

            // 解析内联元素
            $content = $this->parseInlineElements($content);
            $listItems[] = "<li>{$content}</li>";
            $i++;

            // 处理空行
            if ($i < $totalLines && trim($lines[$i]) === '') {
                $i++;
                if ($i >= $totalLines || !preg_match('/^[-*+]\s+|\d+\.\s+/', ltrim($lines[$i]))) {
                    break;
                }
            }
        }

        $tag = $listType === 'ul' ? 'ul' : 'ol';
        $html = "<{$tag}>\n" . implode("\n", $listItems) . "\n</{$tag}>";

        $this->nestingLevel--;

        return [
            'html' => $html,
            'nextIndex' => $i
        ];
    }

    /**
     * 解析段落
     *
     * 连续收集非空行直至空行或遇到其它块级标记（标题、代码块、引用、列表），
     * 多行以空格拼接；nl2br 开启时先转换换行再加 p 标签。无内容时返回空片段。
     *
     * @param array<int, string> $lines 所有行
     * @param int $startIndex 开始索引
     * @return array{html: string, text: string, nextIndex: int} HTML 片段、原始文本与下一行的索引
     */
    private function parseParagraph(array $lines, int $startIndex): array
    {
        $paragraphLines = [];
        $i = $startIndex;
        $totalLines = count($lines);

        while ($i < $totalLines && trim($lines[$i]) !== '') {
            // 遇到其他块级元素时停止
            if (preg_match('/^#{1,6} /', $lines[$i]) ||
                preg_match('/^```/', $lines[$i]) ||
                preg_match('/^>/', $lines[$i]) ||
                preg_match('/^[-*+]\s+|\d+\.\s+/', ltrim($lines[$i]))) {
                break;
            }

            $paragraphLines[] = $lines[$i];
            $i++;
        }

        $text = implode(' ', $paragraphLines);
        $text = trim($text);

        if ($text === '') {
            return [
                'html' => '',
                'text' => '',
                'nextIndex' => $i + 1
            ];
        }

        if ($this->options['nl2br']) {
            $text = nl2br($text, $this->options['xhtml']);
        }

        $html = "<p>" . $this->parseInlineElements($text) . "</p>";

        return [
            'html' => $html,
            'text' => $text,
            'nextIndex' => $i
        ];
    }

    /**
     * 解析内联元素
     *
     * 固定顺序依次处理：反斜杠转义 → 行内代码 → 图片 → 链接 → 粗体 → 斜体 →
     * 删除线 → 脚注引用 → 缩写 → 表情 → 自动链接；删除线/脚注/缩写/表情/自动链接
     * 受对应配置开关控制。行内代码最先处理可避免其内容被其它语法改写。
     *
     * @param string $text 文本内容
     * @return string 转换后的 HTML 片段
     */
    private function parseInlineElements(string $text): string
    {
        // 转义反斜杠
        $text = $this->parseBackslashEscape($text);

        // 处理行内代码（必须在最前面，避免被其他语法干扰）
        $text = $this->parseInlineCode($text);

        // 处理图片
        $text = $this->parseImages($text);

        // 处理链接
        $text = $this->parseLinks($text);

        // 处理粗体
        $text = $this->parseBold($text);

        // 处理斜体
        $text = $this->parseItalic($text);

        // 处理删除线
        if ($this->options['enable_strikethrough']) {
            $text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text) ?? $text;
        }

        // 处理脚注引用
        if ($this->options['enable_footnotes']) {
            $text = $this->parseFootnotes($text);
        }

        // 处理缩写
        if ($this->options['enable_abbreviations'] && !empty($this->abbreviations)) {
            $text = $this->parseAbbreviations($text);
        }

        // 处理表情符号
        if ($this->options['enable_emoji']) {
            $text = $this->parseEmoji($text);
        }

        // 处理自动链接
        if ($this->options['enable_auto_links']) {
            $text = $this->parseAutoLinks($text);
        }

        return $text;
    }

    /**
     * 解析行内代码（`code` 语法）
     *
     * @param string $text 文本内容
     * @return string
     */
    private function parseInlineCode(string $text): string
    {
        // 匹配行内代码：`code` 或 ``code with ` backticks``
        return preg_replace_callback('/(?<!`)(`+)(.+?)\1(?!`)/s', function($matches) {
            $code = $matches[2];

            if ($this->options['safe_mode']) {
                $code = htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            return "<code>{$code}</code>";
        }, $text) ?? $text;
    }

    /**
     * 解析图片
     *
     * 转换 `![alt](url "title")`：alt 与 title 做 HTML 转义、URL 经 sanitizeUrl 净化，
     * URL 不合法时原样保留原始语法；自闭合写法由 xhtml 配置决定。
     *
     * @param string $text 文本内容
     * @return string 转换后的 HTML 片段
     */
    private function parseImages(string $text): string
    {
        // 图片语法: ![alt](url "title")
        return preg_replace_callback('/!\[([^\]]*)\]\(([^\)]+)(?:\s+"([^"]+)")?\)/', function($matches) {
            $alt = htmlspecialchars($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $src = $this->sanitizeUrl($matches[2]);
            $title = isset($matches[3]) ? ' title="' . htmlspecialchars($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"' : '';

            if ($src === '') {
                return $matches[0];
            }

            return sprintf(
                '<img src="%s" alt="%s"%s%s',
                $src,
                $alt,
                $title,
                $this->options['xhtml'] ? ' />' : '>'
            );
        }, $text) ?? $text;
    }

    /**
     * 解析链接
     *
     * 转换 `[text](url "title")`：链接文字递归走内联解析，URL 经 sanitizeUrl 净化，
     * 始终附加 `target="_blank"` 与 `rel="nofollow noopener noreferrer"`；
     * URL 不合法时原样保留原始语法。
     *
     * @param string $text 文本内容
     * @return string 转换后的 HTML 片段
     */
    private function parseLinks(string $text): string
    {
        // 链接语法: [text](url "title")
        return preg_replace_callback('/\[([^\]]+)\]\(([^\)]+)(?:\s+"([^"]+)")?\)/', function($matches) {
            $linkText = $this->parseInlineElements($matches[1]);
            $url = $this->sanitizeUrl($matches[2]);
            $title = isset($matches[3]) ? ' title="' . htmlspecialchars($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"' : '';

            if ($url === '') {
                return $matches[0];
            }

            $rel = ' rel="nofollow noopener noreferrer"';
            $target = ' target="_blank"';

            return sprintf('<a href="%s"%s%s%s>%s</a>', $url, $target, $rel, $title, $linkText);
        }, $text) ?? $text;
    }

    /**
     * 解析粗体
     *
     * 将 `**text**` 与 `__text__` 转为 strong 标签，采用非贪婪匹配，不做嵌套配对处理。
     *
     * @param string $text 文本内容
     * @return string 转换后的 HTML 片段
     */
    private function parseBold(string $text): string
    {
        // 处理 **text** 和 __text__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text) ?? $text;

        return $text;
    }

    /**
     * 解析斜体
     *
     * 将 `*text*` 与 `_text_` 转为 em 标签，用前后瞻断言避开与粗体标记（`**`/`__`）冲突。
     *
     * @param string $text 文本内容
     * @return string 转换后的 HTML 片段
     */
    private function parseItalic(string $text): string
    {
        // 处理 *text* 和 _text_
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/', '<em>$1</em>', $text) ?? $text;

        return $text;
    }

    /**
     * 解析自动链接
     *
     * 先把裸 `http(s)://` 地址转为链接（链接文字做 HTML 转义），再把邮箱地址转为 mailto 链接；
     * 已位于 a 标签内的 URL 与非法 URL 会被跳过。注意邮箱替换未加边界保护，
     * 对已生成的 HTML 属性值也可能命中。
     *
     * @param string $text 文本内容
     * @return string 转换后的 HTML 片段
     */
    private function parseAutoLinks(string $text): string
    {
        // URL 自动链接
        $text = preg_replace_callback('/(?<![\/>])(https?:\/\/[^\s<>\[\]]+)(?![^<]*<\/a>)/', function($matches) {
            $url = $this->sanitizeUrl($matches[1]);
            if ($url === '') {
                return $matches[0];
            }
            return sprintf('<a href="%s" target="_blank" rel="nofollow noopener noreferrer">%s</a>',
                $url,
                htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            );
        }, $text) ?? $text;

        // 邮箱自动链接
        $text = preg_replace_callback('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', function($matches) {
            $email = $matches[1];
            return sprintf('<a href="mailto:%s">%s</a>', $email, $email);
        }, $text) ?? $text;

        return $text;
    }

    /**
     * 解析表情符号
     *
     * 遍历 emojiMap 做字符串级替换（`:smile:` → 😊），无边界判断，
     * 因此代码块等位置出现的同形文本也会被替换（此时应关闭 enable_emoji）。
     *
     * @param string $text 文本内容
     * @return string 替换后的文本
     */
    private function parseEmoji(string $text): string
    {
        foreach ($this->emojiMap as $code => $emoji) {
            $text = str_replace($code, $emoji, $text);
        }
        return $text;
    }

    /**
     * 解析脚注
     *
     * 把 `[^id]` 转为带上标的锚点链接，并把该 id 记入 footnoteRefs 以便
     * 生成反向链接；不校验脚注定义是否存在。
     *
     * @param string $text 文本内容
     * @return string 转换后的 HTML 片段
     */
    private function parseFootnotes(string $text): string
    {
        return preg_replace_callback('/\[\^([^\]]+)\]/', function($matches) {
            $id = $matches[1];
            $refId = 'fnref-' . $id;
            $this->footnoteRefs[$id] = true;
            return sprintf(
                '<sup class="footnote-ref"><a href="#fn-%s" id="%s">[%s]</a></sup>',
                $id,
                $refId,
                $id
            );
        }, $text) ?? $text;
    }

    /**
     * 解析缩写
     *
     * 逐个把 abbreviations 中的缩写按词边界替换为带 title 的 abbr 标签，
     * 说明文本做 HTML 转义；多组缩写按登记顺序依次替换。
     *
     * @param string $text 文本内容
     * @return string 转换后的 HTML 片段
     */
    private function parseAbbreviations(string $text): string
    {
        foreach ($this->abbreviations as $abbr => $title) {
            $pattern = '/\b' . preg_quote($abbr, '/') . '\b/';
            $text = preg_replace_callback($pattern, function($matches) use ($abbr, $title) {
                return sprintf(
                    '<abbr title="%s">%s</abbr>',
                    htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    $matches[0]
                );
            }, $text) ?? $text;
        }
        return $text;
    }

    /**
     * 渲染脚注
     *
     * 在正文末尾输出 footnotes 区块（分隔线 + 有序列表），每项一个 ↩ 反向链接；
     * 无脚注定义时返回空字符串。
     *
     * @return string 脚注区块 HTML，无脚注时为空字符串
     */
    private function renderFootnotes(): string
    {
        if (empty($this->footnotes)) {
            return '';
        }

        $html = "\n<section class=\"footnotes\">\n<hr class=\"footnotes-sep\">\n<ol class=\"footnotes-list\">\n";

        foreach ($this->footnotes as $id => $content) {
            $html .= sprintf(
                '<li id="fn-%s" class="footnote-item">%s <a href="#fnref-%s" class="footnote-backref">↩</a></li>' . "\n",
                $id,
                $content,
                $id
            );
        }

        $html .= "</ol>\n</section>\n";

        return $html;
    }

    /**
     * 解析反斜杠转义
     *
     * 把 `\x` 形式的转义（x 为 Markdown 特殊符号，见方法内的字符表）还原为字面字符 x，
     * 使这些符号不再参与后续的加粗、链接等解析。
     *
     * @param string $text 文本内容
     * @return string 去掉转义反斜杠后的文本
     */
    private function parseBackslashEscape(string $text): string
    {
        $escapedChars = [
            '\\', '`', '*', '_', '{', '}', '[', ']', '(', ')',
            '#', '+', '-', '.', '!', '|', '~', ':', '>', '<'
        ];

        foreach ($escapedChars as $char) {
            $text = str_replace('\\' . $char, $char, $text);
        }

        return $text;
    }

    /**
     * 清理和验证 URL
     *
     * @param string $url URL 字符串
     * @return string 清理后的 URL
     */
    private function sanitizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $decodedUrl = rawurldecode($url);

        // 检查危险协议
        foreach ($this->options['forbidden_protocols'] as $scheme) {
            if (stripos($decodedUrl, $scheme) === 0) {
                return '';
            }
        }

        // 如果是相对路径或锚点，直接返回
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        // 验证 URL 格式
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $url;
    }

    /**
     * 清理多余空白
     *
     * 把连续空行压缩为一个空行并去掉首尾空白，避免输出中堆积无意义换行。
     *
     * @param string $html HTML 字符串
     * @return string 清理后的 HTML
     */
    private function cleanupWhitespace(string $html): string
    {
        $html = preg_replace('/\n\s*\n/', "\n\n", $html) ?? $html;
        $html = trim($html);

        return $html;
    }

    /**
     * 设置单个配置选项
     *
     * @param string $key   配置键名（仅允许已存在的键）
     * @param mixed  $value 配置值
     * @return self 支持链式调用
     */
    public function setOption(string $key, $value): self
    {
        if (array_key_exists($key, $this->options)) {
            $this->options[$key] = $value;
        }
        return $this;
    }

    /**
     * 批量设置配置选项
     *
     * @param array<string, mixed> $options 配置数组
     * @return self
     */
    public function setOptions(array $options): self
    {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
        return $this;
    }

    /**
     * 获取单个配置选项
     *
     * @param string $key 配置键名
     * @return mixed
     */
    public function getOption(string $key)
    {
        return $this->options[$key] ?? null;
    }

    /**
     * 获取所有配置选项
     *
     * @return array<string, mixed>
     */
    public function getAllOptions(): array
    {
        return $this->options;
    }

    /**
     * 添加自定义表情符号
     *
     * @param string $code 表情代码（如 :smile:）
     * @param string $emoji 表情符号（如 😊）
     * @return self
     */
    public function addEmoji(string $code, string $emoji): self
    {
        $this->emojiMap[$code] = $emoji;
        return $this;
    }

    /**
     * 添加语言别名
     *
     * 登记代码块围栏语言标识的映射（如 js → javascript），影响后续生成的语言 class；
     * 同名别名会被覆盖，对已转换过的内容不生效（缓存亦不失效）。
     *
     * @param string $alias 别名（如 js）
     * @param string $language 实际语言名（如 javascript）
     * @return self 当前实例，便于链式调用
     */
    public function addLanguageAlias(string $alias, string $language): self
    {
        $this->languageAliases[$alias] = $language;
        return $this;
    }

    /**
     * 清除缓存
     *
     * 清空已缓存的转换结果；修改配置或表情/别名映射后建议调用，避免命中旧结果。
     *
     * @return void 无返回值
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * 添加允许的 HTML 标签
     *
     * @param string $tag 标签名
     * @return self
     */
    public function addAllowedTag(string $tag): self
    {
        if (!in_array($tag, $this->options['allowed_html_tags'])) {
            $this->options['allowed_html_tags'][] = $tag;
        }
        return $this;
    }

    /**
     * 移除允许的 HTML 标签
     *
     * @param string $tag 标签名
     * @return self
     */
    public function removeAllowedTag(string $tag): self
    {
        $key = array_search($tag, $this->options['allowed_html_tags']);
        if ($key !== false) {
            unset($this->options['allowed_html_tags'][$key]);
            $this->options['allowed_html_tags'] = array_values($this->options['allowed_html_tags']);
        }
        return $this;
    }
}

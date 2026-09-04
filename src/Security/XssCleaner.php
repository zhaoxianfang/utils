<?php

/**
 * ============================================================================
 * XSS 清理工具
 * ============================================================================
 *
 * 提供多种级别的 XSS 过滤策略，包括危险标签移除、白名单过滤、实体转义等。
 * 适用于用户输入过滤、富文本清理、评论内容安全处理等场景。
 *
 * 实现说明（重要）：
 * 本类统一使用 DOM 解析器（DOMDocument）而非正则处理 HTML。
 * 早期基于正则的实现存在多处可被绕过的缺陷，例如：
 *  - `<div onclick=alert(1)>`（属性无引号）匹配不到，事件处理器被保留
 *  - `href="java\tscript:alert(1)"`（协议中插入 TAB/换行）浏览器仍会执行
 *  - `href="&#106;avascript:alert(1)"`（实体编码）解码后即为 javascript:
 *  - `strip_tags()` 只过滤标签不过滤属性，`<p onclick=...>` 原样保留
 * 因此过滤必须在「解析后的 DOM 树」上进行，且协议判定前需先解码实体、
 * 剔除空白与控制字符。
 *
 * @package     zxf\Utils\Security
 * @author      zxf\Utils
 * @version     2.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Security;

use DOMAttr;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class XssCleaner
{
    /**
     * 危险标签列表（clean() 中连同内容一并移除）
     */
    private const DANGEROUS_TAGS = [
        'script', 'iframe', 'object', 'embed', 'form',
        'input', 'textarea', 'style', 'link', 'meta',
        'base', 'svg', 'math', 'video', 'audio', 'applet',
        'frame', 'frameset', 'param', 'source', 'track', 'template',
    ];

    /**
     * strict() 的默认白名单：[标签 => 允许的属性]
     */
    private const DEFAULT_ALLOWED = [
        'p'          => [],
        'br'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'u'          => [],
        's'          => [],
        'strike'     => [],
        'span'       => ['class'],
        'a'          => ['href', 'title', 'target', 'rel'],
        'img'        => ['src', 'alt', 'title', 'width', 'height'],
        'ul'         => [],
        'ol'         => [],
        'li'         => [],
        'h1'         => [],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
        'h5'         => [],
        'h6'         => [],
        'blockquote' => [],
        'code'       => [],
        'pre'        => [],
        'hr'         => [],
        'table'      => [],
        'thead'      => [],
        'tbody'      => [],
        'tr'         => [],
        'th'         => [],
        'td'         => [],
    ];

    /**
     * 允许出现在 URL 属性（href/src 等）中的协议白名单
     */
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel', 'ftp'];

    /**
     * 需要按 URL 规则校验的属性名
     */
    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'background', 'cite', 'longdesc', 'profile', 'usemap', 'formaction', 'poster'];

    /**
     * 危险标签是否连同内部内容一起移除
     *
     * 例如 `<script>alert(1)</script>` 中的脚本正文显然不应保留，
     * 因此默认整体移除；置为 false 时仅删除标签本身而保留文本内容。
     */
    private const REMOVE_DANGEROUS_CONTENT = true;

    /**
     * 基础清理：移除危险标签、所有事件处理器属性与危险协议
     *
     * 保留其它标签与属性，适用于需要保留大部分富文本格式的场景。
     */
    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = self::loadDom($html);

        if ($doc === null) {
            // 解析失败时退回纯转义，宁可损失格式也不放行任何标签
            return self::escape($html);
        }

        self::removeDangerousElements($doc);
        self::sanitizeAttributes($doc, null);

        return self::dumpBody($doc);
    }

    /**
     * 严格清理：仅保留白名单标签与白名单属性，其余全部移除
     *
     * 与 `strip_tags()` 不同：本方法**同时过滤属性**，
     * 白名单之外的属性（含所有 on* 事件处理器）都会被删除，
     * 且 URL 属性会经过协议白名单校验。
     *
     * @param array<string, array<string>> $allowed 白名单 [tag => [attr1, attr2]]，为空时使用默认白名单
     */
    public static function strict(string $html, array $allowed = []): string
    {
        if (trim($html) === '') {
            return '';
        }

        $allowed = $allowed === [] ? self::DEFAULT_ALLOWED : $allowed;

        // 统一小写，避免白名单与实际标签大小写不一致导致漏判
        $normalized = [];
        foreach ($allowed as $tag => $attrs) {
            $normalized[strtolower((string) $tag)] = array_map(
                static fn ($attr): string => strtolower((string) $attr),
                (array) $attrs
            );
        }

        $doc = self::loadDom($html);

        if ($doc === null) {
            return self::escape($html);
        }

        self::applyWhitelist($doc, $normalized);

        return self::dumpBody($doc);
    }

    /**
     * 转义 HTML 实体（输出到 HTML 上下文时的首选方案）
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 清理数组中的所有字符串值（递归）
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function cleanArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // 键名原样保留（调用方负责键名安全），仅清理值
            $result[$key] = match (true) {
                is_string($value) => self::clean($value),
                is_array($value)  => self::cleanArray($value),
                default           => $value,
            };
        }

        return $result;
    }

    /**
     * 移除所有 HTML 标签
     */
    public static function strip(string $html): string
    {
        // strip_tags 不过滤属性，但标签已全部移除，残留属性片段无执行能力；
        // 再转义一次可彻底避免 "a > b" 之类文本被误判成标签后产生畸形输出。
        return self::escape(trim(strip_tags($html)));
    }

    /* ======================================================================
     * 内部实现
     * ====================================================================== */

    /**
     * 返回用于强制 UTF-8 解析的 XML 声明前缀
     *
     * 拆成两段拼接的原因：直接书写完整的 `<?xml ...` 字面量虽然在当前
     * short_open_tag 配置下安全，但一旦部署环境开启 short_open_tag，
     * 词法分析器对 `<?` 的处理可能出现差异；拆分拼接可彻底规避该风险，
     * 同时避免注释/字符串中误写 PHP 结束标签导致文件被提前截断。
     */
    private static function htmlEncodingPrefix(): string
    {
        return '<' . '?xml encoding="UTF-8">';
    }

    /**
     * 把 HTML 片段载入 DOM
     *
     * LIBXML_NONET 禁止加载外部资源（防 XXE / SSRF）。
     */
    private static function loadDom(string $html): ?DOMDocument
    {
        if (! class_exists(DOMDocument::class)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        // 关闭外部实体加载，防止 XXE。
        //
        // 前缀用于强制 libxml 按 UTF-8 解析（否则中文会被当成 ISO-8859-1）。
        // 注意两点：
        //  1. 结尾不能带 XML 声明的结束符（即写成 `encoding="UTF-8">` 而非
        //     `encoding="UTF-8" ...>`），否则 libxml 会把它拆成独立的处理指令节点；
        //  2. 本文件注释中不要写出 PHP 的结束标签，否则会让 PHP 提前退出代码模式。
        $loaded = $doc->loadHTML(
            self::htmlEncodingPrefix() . $html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $doc : null;
    }

    /**
     * 移除危险元素（含注释节点，防止条件注释绕过）
     */
    private static function removeDangerousElements(DOMDocument $doc): void
    {
        $body = $doc->getElementsByTagName('body')->item(0);
        $root = $body ?? $doc->documentElement;

        if ($root === null) {
            return;
        }

        self::walkElements($root, static function (DOMElement $element) use ($doc): void {
            $tag = strtolower($element->nodeName);

            if (! in_array($tag, self::DANGEROUS_TAGS, true)) {
                return;
            }

            if (self::REMOVE_DANGEROUS_CONTENT) {
                $element->parentNode?->removeChild($element);

                return;
            }

            // 仅移除标签、保留子节点：把子元素上提到父节点
            $parent = $element->parentNode;
            if ($parent === null) {
                return;
            }

            while ($element->firstChild !== null) {
                $parent->insertBefore($element->firstChild, $element);
            }

            $parent->removeChild($element);
        });

        self::removeComments($root);
    }

    /**
     * 应用标签/属性白名单
     *
     * @param array<string, array<string>> $allowed 已归一化为小写的白名单
     */
    private static function applyWhitelist(DOMDocument $doc, array $allowed): void
    {
        $body = $doc->getElementsByTagName('body')->item(0);
        $root = $body ?? $doc->documentElement;

        if ($root === null) {
            return;
        }

        self::walkElements($root, static function (DOMElement $element) use ($allowed): void {
            $tag = strtolower($element->nodeName);

            if (! isset($allowed[$tag])) {
                // 不在白名单：整体移除（含内容），避免残留不可信文本结构
                $element->parentNode?->removeChild($element);

                return;
            }

            $allowedAttrs = $allowed[$tag];

            /** @var DOMAttr[] $attributes */
            $attributes = [];
            foreach ($element->attributes as $attr) {
                $attributes[] = $attr;
            }

            foreach ($attributes as $attr) {
                $name = strtolower($attr->nodeName);

                if (! in_array($name, $allowedAttrs, true) || ! self::isSafeAttribute($name, $attr->value)) {
                    $element->removeAttribute($attr->nodeName);
                }
            }
        });

        self::removeComments($root);
    }

    /**
     * 清理属性：删除所有 on* 事件处理器与危险协议
     *
     * @param DOMDocument                       $doc
     * @param array<string, array<string>>|null $allowed 白名单；为 null 时只做危险项过滤
     */
    private static function sanitizeAttributes(DOMDocument $doc, ?array $allowed): void
    {
        $body = $doc->getElementsByTagName('body')->item(0);
        $root = $body ?? $doc->documentElement;

        if ($root === null) {
            return;
        }

        self::walkElements($root, static function (DOMElement $element) use ($allowed): void {
            /** @var DOMAttr[] $attributes */
            $attributes = [];
            foreach ($element->attributes as $attr) {
                $attributes[] = $attr;
            }

            foreach ($attributes as $attr) {
                $name = strtolower($attr->nodeName);

                $inWhitelist = $allowed === null
                    || ! isset($allowed[strtolower($element->nodeName)])
                    || in_array($name, $allowed[strtolower($element->nodeName)], true);

                if (! $inWhitelist || ! self::isSafeAttribute($name, $attr->value)) {
                    $element->removeAttribute($attr->nodeName);
                }
            }
        });
    }

    /**
     * 判断属性是否可以保留
     *
     * @param string $name  已小写的属性名
     * @param string $value 属性值
     */
    private static function isSafeAttribute(string $name, string $value): bool
    {
        // 所有事件处理器（on*）一律移除
        if (str_starts_with($name, 'on')) {
            return false;
        }

        // style 属性可能携带 expression()/url(javascript:) 等执行向量
        if ($name === 'style' && self::containsDangerousUrl($value)) {
            return false;
        }

        // URL 属性走协议白名单
        if (in_array($name, self::URL_ATTRIBUTES, true)) {
            return self::isSafeUrl($value);
        }

        return true;
    }

    /**
     * 校验 URL 是否安全
     *
     * 判定前先做三步归一化，对应三类常见绕过手法：
     *  1. 解码 HTML 实体  —— 应对 `&#106;avascript:`
     *  2. 剔除所有空白与控制字符 —— 应对 `java\tscript:`、`java\nscript:`
     *  3. 统一小写
     *
     * @param string $url 原始 URL
     */
    public static function isSafeUrl(string $url): bool
    {
        $normalized = self::normalizeUrl($url);

        if ($normalized === '') {
            return true; // 空值（如 href=""）无害
        }

        // 相对路径 / 锚点 / 协议相对 URL（//host/path）没有显式协议，放行
        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $matches)) {
            // 以 // 开头的协议相对 URL 在页面为 http(s) 时是安全的
            return ! str_starts_with($normalized, '\\\\');
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_URL_SCHEMES, true);
    }

    /**
     * 判断字符串中是否包含危险协议（用于 style 等属性）
     */
    private static function containsDangerousUrl(string $value): bool
    {
        $normalized = self::normalizeUrl($value);

        if ($normalized === '') {
            return false;
        }

        foreach (['javascript', 'vbscript', 'livescript', 'mocha', 'data'] as $scheme) {
            if (str_contains($normalized, $scheme . ':')) {
                return true;
            }
        }

        return str_contains($normalized, 'expression(') || str_contains($normalized, 'url(javascript');
    }

    /**
     * URL 归一化：解码实体 → 剔除空白与控制字符 → 小写
     */
    private static function normalizeUrl(string $url): string
    {
        // 1. 解码 HTML 实体（可能多层编码，最多解两次已覆盖常见手法）
        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. 剔除空白与控制字符：浏览器解析 URL 时会忽略它们
        $stripped = (string) preg_replace('/[\x00-\x20\x7F-\x9F\s]/u', '', $decoded);

        // 3. 统一小写便于比较
        return strtolower($stripped);
    }

    /**
     * 递归遍历所有元素节点并执行回调
     *
     * 先快照子节点列表再遍历，避免回调中删除节点导致迭代器失效。
     *
     * @param callable(DOMElement): void $callback
     */
    private static function walkElements(DOMNode $node, callable $callback): void
    {
        /** @var DOMNode[] $children */
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $callback($child);

                // 节点可能已被回调移除，需确认仍挂在树上
                if ($child->parentNode !== null) {
                    self::walkElements($child, $callback);
                }
            } elseif ($child instanceof DOMText || $child instanceof DOMComment) {
                continue;
            } elseif ($child->hasChildNodes()) {
                self::walkElements($child, $callback);
            }
        }
    }

    /**
     * 移除注释节点（防止 IE 条件注释等绕过手法）
     */
    private static function removeComments(DOMNode $node): void
    {
        /** @var DOMNode[] $children */
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }

            if ($child->hasChildNodes()) {
                self::removeComments($child);
            }
        }
    }

    /**
     * 导出 body 内的 HTML 片段
     */
    private static function dumpBody(DOMDocument $doc): string
    {
        $body = $doc->getElementsByTagName('body')->item(0);
        $root = $body ?? $doc->documentElement;

        if ($root === null) {
            return '';
        }

        $html = '';

        foreach ($root->childNodes as $child) {
            // 输入只含 head 级内容（如纯 <script>）时 libxml 不会生成 body，
            // 此时根节点是 <html>，必须跳过 <head>，否则会残留空的 head 标签。
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'head') {
                continue;
            }

            $html .= $doc->saveHTML($child);
        }

        return $html;
    }
}

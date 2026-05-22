<?php

/**
 * ============================================================================
 * XSS 清理工具
 * ============================================================================
 *
 * 提供多种级别的 XSS 过滤策略，包括危险标签移除、白名单过滤、实体转义等。
 * 适用于用户输入过滤、富文本清理、评论内容安全处理等场景。
 *
 * @package     zxf\Utils\Security
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Security;

/**
 * XSS 清理工具
 *
 * 提供多种级别的 XSS 过滤策略
 */
final class XssCleaner
{
    /**
     * 危险标签列表
     */
    private const DANGEROUS_TAGS = [
        'script', 'iframe', 'object', 'embed', 'form',
        'input', 'textarea', 'style', 'link', 'meta',
        'base', 'svg', 'math', 'video', 'audio',
    ];

    /**
     * 危险属性列表
     */
    private const DANGEROUS_ATTRIBUTES = [
        'onabort', 'onactivate', 'onafterprint', 'onafterupdate',
        'onbeforeactivate', 'onbeforecopy', 'onbeforecut',
        'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste',
        'onbeforeprint', 'onbeforeunload', 'onbeforeupdate',
        'onblur', 'onbounce', 'oncellchange', 'onchange',
        'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy',
        'oncut', 'ondataavailable', 'ondatasetchanged',
        'ondatasetcomplete', 'ondblclick', 'ondeactivate',
        'ondrag', 'ondragend', 'ondragenter', 'ondragleave',
        'ondragover', 'ondragstart', 'ondrop', 'onerror',
        'onerrorupdate', 'onfilterchange', 'onfinish',
        'onfocus', 'onfocusin', 'onfocusout', 'onhashchange',
        'onhelp', 'oninput', 'onkeydown', 'onkeypress',
        'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture',
        'onmessage', 'onmousedown', 'onmouseenter', 'onmouseleave',
        'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup',
        'onmousewheel', 'onmove', 'onmoveend', 'onmovestart',
        'onoffline', 'ononline', 'onpagehide', 'onpageshow',
        'onpaste', 'onpopstate', 'onprogress', 'onpropertychange',
        'onreadystatechange', 'onreset', 'onresize', 'onresizeend',
        'onresizestart', 'onrowenter', 'onrowexit',
        'onrowsdelete', 'onrowsinserted', 'onscroll',
        'onsearch', 'onselect', 'onselectionchange',
        'onselectstart', 'onstart', 'onstop', 'onsubmit',
        'onunload', 'javascript:', 'data:', 'vbscript:',
    ];

    /**
     * 基础清理：移除危险标签和属性
     */
    public static function clean(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 移除危险标签
        $pattern = '/<(' . implode('|', self::DANGEROUS_TAGS) . ')\b[^>]*>.*?<\/\1>/is';
        $html = preg_replace($pattern, '', $html) ?? $html;

        // 移除自闭合的危险标签
        $pattern = '/<(' . implode('|', self::DANGEROUS_TAGS) . ')\b[^>]*\/?>/is';
        $html = preg_replace($pattern, '', $html) ?? $html;

        // 移除事件处理器和危险协议
        foreach (self::DANGEROUS_ATTRIBUTES as $attr) {
            $pattern = '/\s' . preg_quote($attr, '/') . '=["\'][^"\']*["\']/i';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        // 移除 javascript: 伪协议
        $html = preg_replace('/(href|src|action|background|cites?|longdesc|profile|usemap)\s*=\s*["\']\s*javascript:[^"\']*["\']/i', '$1="#"', $html) ?? $html;
        $html = preg_replace('/(href|src|action|background|cites?|longdesc|profile|usemap)\s*=\s*javascript:/i', '$1="#"', $html) ?? $html;

        return $html;
    }

    /**
     * 严格清理：仅保留白名单标签
     *
     * @param array<string, array<string>> $allowed 白名单 [tag => [attr1, attr2]]
     */
    public static function strict(string $html, array $allowed = []): string
    {
        if (empty($allowed)) {
            $allowed = [
                'p' => [],
                'br' => [],
                'strong' => [],
                'b' => [],
                'em' => [],
                'i' => [],
                'u' => [],
                'strike' => [],
                'span' => ['class'],
                'a' => ['href', 'title', 'target'],
                'img' => ['src', 'alt', 'title', 'width', 'height'],
                'ul' => [],
                'ol' => [],
                'li' => [],
                'h1' => [],
                'h2' => [],
                'h3' => [],
                'h4' => [],
                'h5' => [],
                'h6' => [],
                'blockquote' => [],
                'code' => [],
                'pre' => [],
            ];
        }

        return strip_tags($html, '<' . implode('><', array_keys($allowed)) . '>');
    }

    /**
     * 转义 HTML 实体
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 清理数组中的所有字符串值
     */
    public static function cleanArray(array $data): array
    {
        return array_map(
            fn ($value) => is_string($value) ? self::clean($value) : (
                is_array($value) ? self::cleanArray($value) : $value
            ),
            $data
        );
    }

    /**
     * 移除所有 HTML 标签
     */
    public static function strip(string $html): string
    {
        return strip_tags($html);
    }
}

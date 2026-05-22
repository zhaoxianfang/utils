<?php

/**
 * ============================================================================
 * 简单模板引擎
 * ============================================================================
 *
 * 提供轻量级模板渲染功能，支持变量替换、条件判断、循环和过滤器。
 * 适用于简单的文本模板、邮件内容生成、配置文件渲染等场景。
 *
 * @package     zxf\Utils\Text
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Text;

use InvalidArgumentException;
use RuntimeException;

final class Template
{
    private string $template = '';

    private array $data = [];

    private array $filters = [];

    /**
     * 内置过滤器
     */
    private static array $defaultFilters = [
        'upper'   => 'strtoupper',
        'lower'   => 'strtolower',
        'trim'    => 'trim',
        'escape'  => 'htmlspecialchars',
        'e'       => 'htmlspecialchars',
        'nl2br'   => 'nl2br',
        'date'    => 'date',
        'json'    => 'json_encode',
        'url'     => 'urlencode',
        'raw'     => 'rawurlencode',
        'base64'  => 'base64_encode',
        'md5'     => 'md5',
        'length'  => 'strlen',
        'abs'     => 'abs',
        'round'   => 'round',
        'int'     => 'intval',
        'float'   => 'floatval',
    ];

    public static function make(string $template = ''): self
    {
        $instance = new self();
        $instance->template = $template;
        return $instance;
    }

    /**
     * 从文件加载模板
     */
    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("模板文件不存在: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("无法读取模板文件: {$path}");
        }
        return self::make($content);
    }

    /**
     * 设置模板变量
     */
    public function with(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }

    /**
     * 注册自定义过滤器
     */
    public function filter(string $name, callable $callback): self
    {
        $this->filters[$name] = $callback;
        return $this;
    }

    /**
     * 渲染模板
     */
    public function render(): string
    {
        return $this->parse($this->template, $this->data);
    }

    /**
     * 渲染并保存到文件
     */
    public function save(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            throw new RuntimeException("无法创建目录: {$dir}");
        }
        file_put_contents($path, $this->render(), LOCK_EX);
    }

    /**
     * 解析模板字符串
     */
    private function parse(string $template, array $data): string
    {
        // 处理条件语句 {{if condition}} ... {{else}} ... {{/if}}
        $template = preg_replace_callback(
            '/\{\{if\s+([^}]+)\}\}(.*?)\{\{\/if\}\}/s',
            function (array $matches) use ($data): string {
                return $this->evaluateCondition($matches[1], $data) ? $this->parse($matches[2], $data) : '';
            },
            $template
        );

        // 处理 if-else
        $template = preg_replace_callback(
            '/\{\{if\s+([^}]+)\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s',
            function (array $matches) use ($data): string {
                $condition = $this->evaluateCondition($matches[1], $data);
                return $this->parse($condition ? $matches[2] : $matches[3], $data);
            },
            $template
        );

        // 处理循环 {{foreach items as item}} ... {{/foreach}}
        $template = preg_replace_callback(
            '/\{\{foreach\s+(\w+)\s+as\s+(\w+)\}\}(.*?)\{\{\/foreach\}\}/s',
            function (array $matches) use ($data): string {
                $items = $data[$matches[1]] ?? [];
                if (! is_array($items)) {
                    return '';
                }
                $result = '';
                foreach ($items as $item) {
                    $loopData = array_merge($data, [$matches[2] => $item]);
                    $result .= $this->parse($matches[3], $loopData);
                }
                return $result;
            },
            $template
        );

        // 处理变量 {{var}} 或 {{var|filter}} 或 {{var|filter:"arg"}}
        $template = preg_replace_callback(
            '/\{\{(\w+(?:\.\w+)*)(?:\|([^:}]+)(?::([^}]*))?)?\}\}/',
            function (array $matches) use ($data): string {
                $value = $this->getValue($matches[1], $data);
                if (isset($matches[2])) {
                    $value = $this->applyFilter($matches[2], $value, $matches[3] ?? null);
                }
                return (string) $value;
            },
            $template
        );

        return $template;
    }

    /**
     * 获取嵌套值
     */
    private function getValue(string $path, array $data): mixed
    {
        foreach (explode('.', $path) as $key) {
            if (is_array($data) && array_key_exists($key, $data)) {
                $data = $data[$key];
            } else {
                return '';
            }
        }
        return $data;
    }

    /**
     * 应用过滤器
     */
    private function applyFilter(string $name, mixed $value, ?string $arg): mixed
    {
        $callback = $this->filters[$name] ?? (self::$defaultFilters[$name] ?? null);
        if ($callback === null) {
            return $value;
        }
        return $arg !== null ? $callback($value, $arg) : $callback($value);
    }

    /**
     * 评估条件表达式
     */
    private function evaluateCondition(string $expression, array $data): bool
    {
        $expression = trim($expression);

        // 处理 !var
        if (str_starts_with($expression, '!')) {
            return ! $this->evaluateCondition(substr($expression, 1), $data);
        }

        // 处理 var == value
        if (preg_match('/^(\w+(?:\.\w+)*)\s*==\s*(.+)$/', $expression, $m)) {
            return $this->getValue($m[1], $data) == trim($m[2], '"\'');
        }

        // 处理 var != value
        if (preg_match('/^(\w+(?:\.\w+)*)\s*!=\s*(.+)$/', $expression, $m)) {
            return $this->getValue($m[1], $data) != trim($m[2], '"\'');
        }

        // 默认：判断值是否为真
        $value = $this->getValue($expression, $data);
        return ! empty($value);
    }
}

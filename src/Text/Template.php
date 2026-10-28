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
 *
 * @example
 * use zxf\Utils\Text\Template;
 * $tpl = Template::make('Hello {{name|upper}}!');
 * echo $tpl->with('name', 'world')->render(); // Hello WORLD!
 * // 支持 {{if}} / {{foreach}} / {{else}} 块与 |escape 等过滤器
 */

declare(strict_types=1);

namespace zxf\Utils\Text;

use InvalidArgumentException;
use RuntimeException;

/**
 * 轻量级模板引擎
 *
 * 通过 {{var}}、{{var|filter}} 语法进行变量替换，并支持 {{if}}/{{foreach}} 块级逻辑。
 * 变量默认进行 HTML 转义（防 XSS），可用 |raw 原样输出、|escape 显式转义。
 *
 * @package zxf\Utils\Text
 */
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
        // 'date' 有独立特判：date() 的 (format, timestamp) 参数顺序与模板调用相反
        'json'    => 'json_encode',
        'url'     => 'urlencode',
        // 注意：'raw' 是「原样输出（跳过转义）」的保留名，不能绑定 rawurlencode，
        // 否则 {{v|raw}} 会变成 URL 编码而非原文输出（需要 URL 编码请用 |url）
        'base64'  => 'base64_encode',
        'md5'     => 'md5',
        'length'  => 'strlen',
        'abs'     => 'abs',
        'round'   => 'round',
        'int'     => 'intval',
        'float'   => 'floatval',
    ];

    /**
     * 创建模板引擎实例
     *
     * @param string $template 模板字符串，可后续通过 with() 注入变量
     * @return static
     */
    public static function make(string $template = ''): self
    {
        $instance = new self();
        $instance->template = $template;
        return $instance;
    }

    /**
     * 从文件加载模板
     *
     * 读取文件全部内容作为模板字符串；文件不存在或读取失败时抛出异常，
     * 不会静默返回空模板。
     *
     * @param string $path 模板文件路径
     * @return static 模板实例（支持链式调用）
     * @throws RuntimeException 文件不存在或读取失败时抛出
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
     *
     * 传关联数组时与已有变量合并（同名键覆盖），传字符串时设置单个变量；
     * 变量在 render() 时才会被解析与转义。
     *
     * @param string|array $key   变量名，或「变量名 => 值」的关联数组
     * @param mixed         $value 变量值（当 $key 为字符串时使用）
     * @return static 支持链式调用
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
     * 注册自定义过滤器（可覆盖内置同名过滤器）
     *
     * @param string   $name     过滤器名称
     * @param callable $callback 过滤器回调，签名为 (mixed $value, ?string $arg): mixed
     * @return static 支持链式调用
     */
    public function filter(string $name, callable $callback): self
    {
        $this->filters[$name] = $callback;
        return $this;
    }

    /**
     * 渲染模板并返回结果字符串
     *
     * @return string 渲染后的内容（变量默认 HTML 转义，除非使用 |raw）
     */
    public function render(): string
    {
        return $this->parse($this->template, $this->data);
    }

    /**
     * 渲染模板并写入文件
     *
     * @param string $path 输出文件路径
     * @return void
     * @throws RuntimeException 目标目录创建失败或文件写入失败时抛出
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
     * 解析模板字符串（递归下降式块解析，正确处理任意嵌套的 if/foreach）
     *
     * 实现说明：正则方案无法处理嵌套块的配对（非贪婪匹配会把外层闭合符吞给内层，
     * 而"先内层"又会丢失外层循环变量上下文）。这里用 preg_split 带捕获分组把模板
     * 切分为「文本 + 块标记」token 流，再用显式栈配对 if/foreach 块：
     * - {{if}}/{{foreach}} 压栈；
     * - {{else}} 切换栈顶 if 的收集相位；
     * - {{/if}}/{{/foreach}} 弹栈并对块体递归 parse（此时外层循环变量已在 $data 中）。
     *
     * @param string $template 待解析的模板字符串
     * @param array $data 渲染上下文变量表
     * @return string 解析后的结果文本
     *
     * @throws RuntimeException 正则解析失败时抛出
     */
    private function parse(string $template, array $data): string
    {
        // foreach 集合支持点路径（如 r.c 遍历外层循环项的子数组），与变量引用 {{a.b}} 一致
        $pattern = '/\{\{if\s+([^}]+)\}\}|\{\{foreach\s+(\w+(?:\.\w+)*)\s+as\s+(\w+)\}\}|\{\{\/if\}\}|\{\{\/foreach\}\}|\{\{else\}\}/';
        if (preg_match_all($pattern, $template, $matches, PREG_OFFSET_CAPTURE) === false) {
            throw new RuntimeException('模板解析失败');
        }

        // 上下文：stack 保存未闭合块；out 累积最终输出
        $ctx = ['stack' => [], 'out' => ''];

        $append = function (string $chunk) use (&$ctx): void {
            if ($ctx['stack'] === []) {
                $ctx['out'] .= $chunk;
                return;
            }
            $idx = count($ctx['stack']) - 1;
            $top = &$ctx['stack'][$idx];
            if ($top['type'] === 'if') {
                if ($top['phase'] === 'body') {
                    $top['body'] .= $chunk;
                } else {
                    $top['elseBody'] .= $chunk;
                }
            } else {
                $top['body'] .= $chunk;
            }
            unset($top);
        };

        // 用匹配 offset 游标把模板切分为「块前文本 + 块」，逐块用栈配对处理
        $cursor = 0;
        foreach ($matches[0] as $mi => $mm) {
            $offset = $mm[1];
            if ($offset > $cursor) {
                $append(substr($template, $cursor, $offset - $cursor));
            }
            $delim = $mm[0];
            // 捕获组归属（交替模式）：组1=if 条件；组2=foreach 集合；组3=foreach 别名
            if (str_starts_with($delim, '{{if')) {
                $cond = (string) ($matches[1][$mi][0] ?? '');
                $ctx['stack'][] = ['type' => 'if', 'cond' => trim($cond), 'body' => '', 'elseBody' => '', 'phase' => 'body', 'openPos' => $offset];
            } elseif (str_starts_with($delim, '{{foreach')) {
                $src = (string) ($matches[2][$mi][0] ?? '');
                $alias = (string) ($matches[3][$mi][0] ?? '');
                $ctx['stack'][] = ['type' => 'foreach', 'src' => $src, 'as' => $alias, 'body' => '', 'openPos' => $offset];
            } elseif ($delim === '{{else}}') {
                if ($ctx['stack'] === [] || $ctx['stack'][count($ctx['stack']) - 1]['type'] !== 'if') {
                    throw new InvalidArgumentException('模板中 {{else}} 必须位于 {{if}} 块内');
                }
                $ctx['stack'][count($ctx['stack']) - 1]['phase'] = 'else';
            } else { // {{/if}} 或 {{/foreach}}
                if ($ctx['stack'] === []) {
                    throw new InvalidArgumentException("模板块闭合标签不配对: {$delim}");
                }
                $top = array_pop($ctx['stack']);
                if (($delim === '{{/if}}' && $top['type'] !== 'if') || ($delim === '{{/foreach}}' && $top['type'] !== 'foreach')) {
                    throw new InvalidArgumentException("模板块闭合标签类型不匹配: {$delim}");
                }

                if ($ctx['stack'] !== []) {
                    // 该块是某个父块的子块：嵌套块引用的外层变量（如 {{foreach r as x}} 内
                    // 遍历 {{foreach r.c as n}}）只有等父块闭合、进入带循环变量的递归上下文后
                    // 才能正确求值。因此不在此处执行，而是把整块原文重放到父级收集缓冲，
                    // 由父块闭合时统一递归 parse。
                    $closeEnd = $offset + strlen($delim);
                    $append(substr($template, $top['openPos'], $closeEnd - $top['openPos']));
                } else {
                    // 顶层块：当前 $data 即最终上下文，立即执行
                    $rendered = '';
                    if ($top['type'] === 'if') {
                        $rendered = $this->evaluateCondition($top['cond'], $data)
                            ? $this->parse($top['body'], $data)
                            : $this->parse($top['elseBody'], $data);
                    } else {
                        $items = $this->getValue($top['src'], $data); // 支持点路径
                        if ($items === null) {
                            $items = [];
                        }
                        if (is_array($items) || $items instanceof \Traversable) {
                            foreach ($items as $item) {
                                $loopData = array_merge($data, [$top['as'] => $item]);
                                $rendered .= $this->parse($top['body'], $loopData);
                            }
                        }
                    }
                    $append($rendered);
                }
            }
            $cursor = $offset + strlen($delim);
        }

        // 尾部纯文本
        if ($cursor < strlen($template)) {
            $append(substr($template, $cursor));
        }

        if ($ctx['stack'] !== []) {
            $name = $ctx['stack'][count($ctx['stack']) - 1];
            throw new InvalidArgumentException('模板块标签未闭合: ' . ($name['type'] === 'if' ? '{{if}}' : '{{foreach}}'));
        }

        // 变量替换：{{var}} / {{var|filter}} / {{var|filter:"arg"}}
        return preg_replace_callback(
            '/\{\{(\w+(?:\.\w+)*)(?:\|([^:}]+)(?::([^}]*))?)?\}\}/',
            function (array $matches) use ($data): string {
                $value = $this->getValue($matches[1], $data);
                $filterName = strtolower(trim($matches[2] ?? ''));
                $rawArg = $matches[3] ?? null;
                if (isset($matches[2])) {
                    // 剥除参数最外层引号：{{var|date:"Y-m-d"}} 的 "Y-m-d" → Y-m-d
                    $arg = $rawArg;
                    if ($arg !== null && $arg !== '') {
                        $arg = trim($arg);
                        if ((str_starts_with($arg, '"') && str_ends_with($arg, '"')) || (str_starts_with($arg, "'") && str_ends_with($arg, "'"))) {
                            $arg = substr($arg, 1, -1);
                        }
                    }
                    $value = $this->applyFilter($matches[2], $value, $arg);
                }
                // 转义策略：
                // - |raw 原样输出（调用方需自行保证内容安全）；
                // - |escape / |e 显式转义过，无需再转义（否则 & → &amp;amp; 双重转义）；
                // - 其余过滤器与无过滤器变量一律默认 HTML 转义，防 XSS。
                $rendered = (string) $value;
                if (! in_array($filterName, ['raw', 'escape', 'e'], true)) {
                    $rendered = htmlspecialchars($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                return $rendered;
            },
            $ctx['out']
        ) ?? '';
    }

    /**
     * 按点路径从数据上下文中取值（如 user.name）
     *
     * @param string $path 点分隔的键路径
     * @param array  $data 当前数据上下文
     * @return mixed 取到的值；路径中任意一级不存在时返回空字符串
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
     * 应用过滤器到值上
     *
     * @param string  $name  过滤器名称（内置或用户自定义）
     * @param mixed   $value 待处理的值
     * @param string|null $arg 过滤器参数（如 date 的格式字符串）
     * @return mixed 处理后的值；未找到过滤器时原样返回
     */
    private function applyFilter(string $name, mixed $value, ?string $arg): mixed
    {
        // 用户自定义过滤器优先（可覆盖内置同名）
        if (isset($this->filters[$name])) {
            $cb = $this->filters[$name];
            return $arg !== null ? $cb($value, $arg) : $cb($value);
        }

        $normalized = strtolower(trim($name));

        // date 内置过滤器特判：date(format, timestamp) 的参数顺序与模板调用 {{var|date:"Y-m-d"}}
        // （变量为时间戳、参数为格式）相反，直接注册 'date' => 'date' 会把格式当时间戳用
        if ($normalized === 'date') {
            $ts = is_numeric($value)
                ? (int) $value
                : (is_string($value) ? strtotime($value) : false);
            if ($ts === false) {
                return '';
            }
            return date($arg !== null && $arg !== '' ? $arg : 'Y-m-d H:i:s', $ts);
        }

        $callback = self::$defaultFilters[$normalized] ?? null;
        if ($callback === null) {
            return $value;
        }
        return $arg !== null ? $callback($value, $arg) : $callback($value);
    }

    /**
     * 评估模板条件表达式
     *
     * 支持 !var 取反、var == value / var != value 比较，以及纯变量真值判断。
     *
     * @param string $expression 条件表达式
     * @param array  $data       当前数据上下文
     * @return bool 条件是否成立
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

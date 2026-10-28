<?php

/**
 * ============================================================================
 * 输入数据清理工具类
 * ============================================================================
 *
 * 提供统一的数据清理和规范化接口，用于过滤用户输入。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @example
 * $clean = Sanitizer::make($_POST)
 *     ->string('name')
 *     ->email('email')
 *     ->int('age', 0, 150)
 *     ->float('price', 0)
 *     ->bool('active')
 *     ->get();
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

/**
 * 输入数据清洗器（链式调用）。
 *
 * 以「白名单」思路逐字段声明期望的类型与取值范围，把不可信输入（$_GET / $_POST /
 * 接口入参等）规范化为确定类型的值，避免把原始输入直接带进业务逻辑。
 *
 * 设计要点：
 * - 每个声明方法（string/email/int/...）处理一个字段并返回 $this，支持链式调用；
 * - 结果集只含「显式声明过」的字段，配合 only()/except() 可做字段收敛，
 *   天然缓解批量赋值（Mass Assignment）风险；
 * - 默认（非严格）模式下非法值静默降级为默认值（如 int 非法 → 0）；
 *   开启 strict() 后改为把错误记录进 errors()，由调用方决定如何处理；
 * - 构造器私有，只能通过 Sanitizer::make() 创建实例。
 *
 * @package zxf\Utils\Support
 * @version 1.0.0
 * @license MIT
 *
 * @example 基本用法
 * ```php
 * $clean = Sanitizer::make($_POST)
 *     ->string('name', 50)      // 字符串，最多 50 个字符
 *     ->email('email')          // 邮箱，非法则为空字符串
 *     ->int('age', 0, 150)      // 整数，并钳制到 0~150
 *     ->float('price', 0)       // 浮点数
 *     ->bool('active')          // 布尔值
 *     ->only('name', 'email')   // 只保留这两个字段
 *     ->get();
 * ```
 *
 * @example 严格模式：收集错误而非静默降级
 * ```php
 * $s = Sanitizer::make(['age' => 'abc'])->strict()->int('age');
 * $s->hasErrors(); // true
 * $s->errors();    // ['age' => 'Invalid integer']
 * ```
 */
final class Sanitizer
{
    /** 原始待清洗数据 */
    private array $data = [];

    /** 已清洗的结果数据 */
    private array $cleaned = [];

    /** 严格模式下收集到的字段错误（字段名 => 错误描述） */
    private array $errors = [];

    /** 是否处于严格模式 */
    private bool $strict = false;

    /**
     * 私有构造器：禁止直接 new，请统一使用 Sanitizer::make()。
     *
     * @param array $data 原始待清洗的输入数据
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * 创建清洗器实例（唯一入口）。
     *
     * @param  array  $data  原始输入数据，通常传入 $_GET / $_POST / 接口入参
     * @return self 清洗器实例
     *
     * @example
     * ```php
     * $clean = Sanitizer::make($_POST)->string('name')->get();
     * ```
     */
    public static function make(array $data): self
    {
        return new self($data);
    }

    /**
     * 切换严格模式。
     *
     * 开启后，字段校验失败时不再静默降级为默认值，而是把错误记录进 errors()，
     * 可通过 hasErrors() / errors() 读取后自行决定响应方式。
     *
     * @param  bool  $strict  true 开启严格模式，false 关闭（默认降级行为）
     * @return self
     *
     * @example
     * ```php
     * $s = Sanitizer::make(['age' => 'abc'])->strict()->int('age');
     * $s->hasErrors(); // true
     * ```
     */
    public function strict(bool $strict = true): self
    {
        $this->strict = $strict;
        return $this;
    }

    /**
     * 清洗为字符串。
     *
     * 非字符串输入会先转换为字符串，再去除首尾空白；指定 $maxLength 时按「字符」
     * 截断（mb_substr，多字节安全，中文不会被截成乱码）。
     *
     * @param  string  $key        字段名
     * @param  int     $maxLength  最大字符数，传 0 表示不限制
     * @return self
     *
     * @example
     * ```php
     * Sanitizer::make(['name' => '  张三  '])->string('name', 10)->get();
     * // ['name' => '张三']
     * ```
     */
    public function string(string $key, int $maxLength = 0): self
    {
        $value = $this->data[$key] ?? null;
        if (! is_string($value)) {
            $value = $value !== null ? (string) $value : '';
        }
        $value = trim($value);
        if ($maxLength > 0) {
            $value = mb_substr($value, 0, $maxLength);
        }
        $this->cleaned[$key] = $value;
        return $this;
    }

    /**
     * 清洗为邮箱地址。
     *
     * 先用 FILTER_SANITIZE_EMAIL 去除非法字符，再用 FILTER_VALIDATE_EMAIL 做校验；
     * 校验不通过时归为空字符串，而不是保留「半截」脏数据。
     *
     * @param  string  $key  字段名
     * @return self
     *
     * @example
     * ```php
     * Sanitizer::make(['e' => 'a@@b.com'])->email('e')->getString('e'); // ''
     * Sanitizer::make(['e' => 'a@b.com'])->email('e')->getString('e'); // 'a@b.com'
     * ```
     */
    public function email(string $key): self
    {
        $value = filter_var($this->data[$key] ?? '', FILTER_SANITIZE_EMAIL);
        $value = filter_var($value, FILTER_VALIDATE_EMAIL) ?: '';
        $this->cleaned[$key] = $value;
        return $this;
    }

    /**
     * 清洗为 URL 地址。
     *
     * 先用 FILTER_SANITIZE_URL 去除非法字符，再用 FILTER_VALIDATE_URL 校验；
     * 校验不通过时归为空字符串。
     *
     * @param  string  $key  字段名
     * @return self
     *
     * @example
     * ```php
     * Sanitizer::make(['u' => 'https://a.com'])->url('u')->getString('u'); // 'https://a.com'
     * Sanitizer::make(['u' => 'not a url'])->url('u')->getString('u');    // ''
     * ```
     */
    public function url(string $key): self
    {
        $value = filter_var($this->data[$key] ?? '', FILTER_SANITIZE_URL);
        $value = filter_var($value, FILTER_VALIDATE_URL) ?: '';
        $this->cleaned[$key] = $value;
        return $this;
    }

    /**
     * 清洗为整数，并钳制到指定区间。
     *
     * 校验失败时（非整数字符串等）降级为 0；严格模式下额外记录一条错误。
     * 结果一定落在 [$min, $max] 之内，可放心用于数量、页码等场景。
     *
     * @param  string  $key  字段名
     * @param  int     $min  下界，小于它的值会被抬到 $min
     * @param  int     $max  上界，大于它的值会被压到 $max
     * @return self
     *
     * @example
     * ```php
     * Sanitizer::make(['age' => '999'])->int('age', 0, 150)->getInt('age'); // 150（被钳制）
     * Sanitizer::make(['age' => 'abc'])->int('age', 0, 150)->getInt('age'); // 0（降级）
     * Sanitizer::make(['age' => '-5'])->int('age', 0, 150)->getInt('age');  // 0（被钳制）
     * ```
     */
    public function int(string $key, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): self
    {
        $value = filter_var($this->data[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false) {
            $value = 0;
            if ($this->strict) {
                $this->errors[$key] = 'Invalid integer';
            }
        }
        $this->cleaned[$key] = max($min, min($max, $value));
        return $this;
    }

    /**
     * 清洗为浮点数，并钳制到指定区间。
     *
     * 校验失败时降级为 0.0；严格模式下额外记录一条错误。
     * 结果一定落在 [$min, $max] 之内。
     *
     * @param  string  $key  字段名
     * @param  float   $min  下界，小于它的值会被抬到 $min
     * @param  float   $max  上界，大于它的值会被压到 $max
     * @return self
     *
     * @example
     * ```php
     * Sanitizer::make(['p' => '12.5'])->float('p', 0)->getFloat('p'); // 12.5
     * Sanitizer::make(['p' => 'abc'])->float('p', 0)->getFloat('p');  // 0.0（降级）
     * ```
     */
    public function float(string $key, float $min = PHP_FLOAT_MIN, float $max = PHP_FLOAT_MAX): self
    {
        $value = filter_var($this->data[$key] ?? null, FILTER_VALIDATE_FLOAT);
        if ($value === false) {
            $value = 0.0;
            if ($this->strict) {
                $this->errors[$key] = 'Invalid float';
            }
        }
        $this->cleaned[$key] = max($min, min($max, $value));
        return $this;
    }

    /**
     * 清洗为布尔值。
     *
     * 识别常见真值（'1'、'true'、'on'、'yes' 及非空真值）与假值（'0'、'false'、
     * 'off'、'no'、''、null）；无法识别时（FILTER_NULL_ON_FAILURE 返回 null）回落为 false。
     *
     * @param  string  $key  字段名
     * @return self
     *
     * @example
     * ```php
     * Sanitizer::make(['a' => 'on'])->bool('a')->getBool('a');   // true
     * Sanitizer::make(['a' => 'no'])->bool('a')->getBool('a');   // false
     * Sanitizer::make(['a' => 'xyz'])->bool('a')->getBool('a');  // false（无法识别）
     * ```
     */
    public function bool(string $key): self
    {
        $value = $this->data[$key] ?? false;
        $this->cleaned[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        return $this;
    }

    /**
     * 清洗为数组。
     *
     * 非数组值会被包装成单元素数组，保证后续 foreach / 数组函数不会因类型不符而报错。
     *
     * @param  string  $key  字段名
     * @return self
     *
     * @example
     * ```php
     * Sanitizer::make(['t' => 'php'])->array('t')->get(); // ['t' => ['php']]
     * Sanitizer::make(['t' => ['a','b']])->array('t')->get(); // ['t' => ['a','b']]
     * ```
     */
    public function array(string $key): self
    {
        $value = $this->data[$key] ?? [];
        $this->cleaned[$key] = is_array($value) ? $value : [$value];
        return $this;
    }

    /**
     * 清洗为枚举实例。
     *
     * 通过 $enumClass::from() 构造；失败（ValueError/TypeError）时使用 $default，
     * 严格模式下额外记录一条错误。
     *
     * @param  string  $key        字段名
     * @param  string  $enumClass  枚举类名（应为 BackedEnum，如 Status::class）
     * @param  mixed   $default    校验失败时使用的默认值
     * @return self
     *
     * @example
     * ```php
     * enum Status: string { case On = 'on'; case Off = 'off'; }
     *
     * Sanitizer::make(['s' => 'on'])->enum('s', Status::class)->get()['s']; // Status::On
     * Sanitizer::make(['s' => 'x'])->enum('s', Status::class)->get()['s'];  // null（默认值）
     * ```
     */
    public function enum(string $key, string $enumClass, mixed $default = null): self
    {
        $value = $this->data[$key] ?? null;
        try {
            $this->cleaned[$key] = $enumClass::from($value);
        } catch (\ValueError|\TypeError) {
            $this->cleaned[$key] = $default;
            if ($this->strict) {
                $this->errors[$key] = 'Invalid enum value';
            }
        }
        return $this;
    }

    /**
     * 使用自定义回调清洗字段。
     *
     * 回调接收原始值，其返回值「直接」作为该字段结果（不再做额外类型处理），
     * 因此回调内部需自行保证返回值类型正确且安全。
     *
     * @param  string    $key       字段名
     * @param  callable  $callback  清洗回调，约定签名 fn(mixed $raw): mixed
     * @return self
     *
     * @example
     * ```php
     * Sanitizer::make(['v' => '  A,b ,C '])
     *     ->custom('v', fn ($raw) => array_filter(array_map('trim', explode(',', (string) $raw))))
     *     ->get();
     * // ['v' => ['A', 'b', 'C']]
     * ```
     */
    public function custom(string $key, callable $callback): self
    {
        $this->cleaned[$key] = $callback($this->data[$key] ?? null);
        return $this;
    }

    /**
     * 原样保留字段，不做任何清洗。
     *
     * 仅用于确实需要原始值的场景（如富文本内容）。注意：该值未经过滤，
     * 输出到 HTML 前必须自行转义，否则存在 XSS 风险。
     *
     * @param  string  $key  字段名
     * @return self
     */
    public function raw(string $key): self
    {
        $this->cleaned[$key] = $this->data[$key] ?? null;
        return $this;
    }

    /**
     * 从结果中移除指定字段。
     *
     * @param  string  ...$keys  要移除的字段名（可变参数）
     * @return self
     *
     * @example
     * ```php
     * // 移除敏感字段，避免误入库
     * Sanitizer::make($_POST)->string('name')->raw('password')->except('password')->get();
     * ```
     */
    public function except(string ...$keys): self
    {
        foreach ($keys as $key) {
            unset($this->cleaned[$key]);
        }
        return $this;
    }

    /**
     * 仅保留指定字段（白名单收敛）。
     *
     * 推荐在清洗链末尾调用，确保只有声明过的字段进入业务层，
     * 可有效防止批量赋值（Mass Assignment）类漏洞。
     *
     * @param  string  ...$keys  要保留的字段名（可变参数）
     * @return self
     *
     * @example
     * ```php
     * Sanitizer::make($_POST)->string('name')->string('email')->only('name', 'email')->get();
     * ```
     */
    public function only(string ...$keys): self
    {
        $this->cleaned = array_intersect_key($this->cleaned, array_flip($keys));
        return $this;
    }

    /**
     * 获取全部已清洗数据。
     *
     * @return array 字段名 => 清洗后的值（只包含显式声明过的字段）
     */
    public function get(): array
    {
        return $this->cleaned;
    }

    /**
     * 获取单个字段并强制转换为字符串。
     *
     * @param  string  $key  字段名
     * @return string 字段不存在时返回空字符串
     */
    public function getString(string $key): string
    {
        return (string) ($this->cleaned[$key] ?? '');
    }

    /**
     * 获取单个字段并强制转换为整数。
     *
     * @param  string  $key  字段名
     * @return int 字段不存在时返回 0
     */
    public function getInt(string $key): int
    {
        return (int) ($this->cleaned[$key] ?? 0);
    }

    /**
     * 获取单个字段并强制转换为浮点数。
     *
     * @param  string  $key  字段名
     * @return float 字段不存在时返回 0.0
     */
    public function getFloat(string $key): float
    {
        return (float) ($this->cleaned[$key] ?? 0.0);
    }

    /**
     * 获取单个字段并强制转换为布尔值。
     *
     * @param  string  $key  字段名
     * @return bool 字段不存在时返回 false
     */
    public function getBool(string $key): bool
    {
        return (bool) ($this->cleaned[$key] ?? false);
    }

    /**
     * 获取清洗过程中记录的错误。
     *
     * @return array 字段名 => 错误描述；仅在严格模式下才可能非空
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 是否存在清洗错误。
     *
     * @return bool 严格模式且有字段校验失败时为 true
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }
}

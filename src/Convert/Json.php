<?php

/**
 * ============================================================================
 * JSON 处理工具类
 * ============================================================================
 *
 * 提供 JSON 编码、解码、美化、路径查询、合并、验证等功能。
 * 比原生 json_* 函数更友好，支持链式操作和详细错误处理。
 *
 * @package     zxf\Utils\Convert
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Convert;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use zxf\Utils\Support\Arr;

/**
 * JSON 处理工具类
 *
 * 对原生 json_* 函数进行封装，提供编码、解码、美化、路径查询、合并、
 * 类型校验与转换等功能，统一以 JsonException/RuntimeException 暴露错误，
 * 并可与 Arr 协同处理嵌套结构，比原生函数更友好、更安全。
 */
final class Json
{
    /**
     * 将值编码为 JSON 字符串
     *
     * 固定追加 JSON_THROW_ON_ERROR 与 JSON_UNESCAPED_UNICODE 两个选项，
     * 因此中文不会被转义为 \uXXXX，失败时也不会返回 false 而是抛异常。
     *
     * @param mixed $value 待编码的值，支持标量、数组与实现了 JsonSerializable 的对象
     * @param int   $flags 额外的 json_encode 选项位，如 JSON_PRETTY_PRINT
     * @param int   $depth 最大递归深度，必须大于 0
     * @return string 编码后的 JSON 字符串
     *
     * @throws JsonException 编码失败（如非法 UTF-8、超出深度、递归引用）时抛出
     */
    public static function encode(mixed $value, int $flags = 0, int $depth = 512): string
    {
        $flags |= JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;
        return json_encode($value, $flags, $depth);
    }

    /**
     * 将值编码为格式化的 JSON 字符串
     *
     * 在 encode() 的基础上追加 JSON_PRETTY_PRINT，输出带缩进的多行 JSON，
     * 适合写入配置文件或调试输出；其余转义与异常行为同 encode()。
     *
     * @param mixed $value 待编码的值
     * @param int   $flags 额外的 json_encode 选项位
     * @param int   $depth 最大递归深度，必须大于 0
     * @return string 带缩进换行的 JSON 字符串
     *
     * @throws JsonException 编码失败时抛出
     */
    public static function pretty(mixed $value, int $flags = 0, int $depth = 512): string
    {
        return self::encode($value, $flags | JSON_PRETTY_PRINT, $depth);
    }

    /**
     * 解码 JSON 字符串
     *
     * 固定追加 JSON_THROW_ON_ERROR，语法错误时抛异常而不是返回 null；
     * $associative 为 false 时 JSON 对象会解码为 stdClass，为 true 时解码为关联数组。
     *
     * @param string $json        JSON 字符串
     * @param bool   $associative 是否把对象解码为关联数组，false 则得到 stdClass
     * @param int    $depth       最大递归深度，必须大于 0
     * @param int    $flags       额外的 json_decode 选项位，如 JSON_BIGINT_AS_STRING
     * @return mixed 解码结果，可能是标量、数组或对象（由 JSON 内容与 $associative 决定）
     *
     * @throws JsonException 语法错误或超出深度限制时抛出
     */
    public static function decode(string $json, bool $associative = true, int $depth = 512, int $flags = 0): mixed
    {
        return json_decode($json, $associative, $depth, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * 安全解码 JSON 字符串，失败时返回默认值
     *
     * 内部固定深度 512，捕获 JsonException 后返回 $default，不向调用方抛异常；
     * 适合处理来源不可控的外部 JSON。
     *
     * @param string $json        JSON 字符串
     * @param mixed  $default     解码失败时返回的默认值
     * @param bool   $associative 是否把对象解码为关联数组，false 则得到 stdClass
     * @return mixed 解码成功返回结果，失败返回 $default
     */
    public static function decodeSafe(string $json, mixed $default = null, bool $associative = true): mixed
    {
        try {
            return json_decode($json, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $default;
        }
    }

    /**
     * 判断字符串是否为有效 JSON
     *
     * 空串或仅含空白字符直接判为 false；其余情况以严格模式尝试解码，
     * 只要不抛 JsonException 即视为合法。注意裸标量（如 "1"、"null"）也算合法 JSON。
     *
     * @param string $string 待校验的字符串
     * @return bool 合法返回 true，为空、空白或解析失败返回 false
     */
    public static function isValid(string $string): bool
    {
        if (trim($string) === '') {
            return false;
        }
        try {
            json_decode($string, true, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * 判断字符串是否为 JSON 数组
     *
     * 解码成功且结果为「连续整数下标列表」才返回 true；`[1,2]` 判定为数组，
     * `{"a":1}` 判定为对象。注意 `{"0":"a","1":"b"}` 解码成关联数组后键会被
     * 转成整数 0、1，因此同样会被判为数组，这是 json_decode 的固有行为。
     * 解析失败时返回 false。
     *
     * @param string $string 待判断的 JSON 字符串
     * @return bool 是 JSON 数组返回 true，否则返回 false
     */
    public static function isArray(string $string): bool
    {
        $decoded = self::decodeSafe($string);
        return is_array($decoded) && array_is_list($decoded);
    }

    /**
     * 判断字符串是否为 JSON 对象
     *
     * 与 isArray() 互为反面：解码成功且结果不是连续整数下标列表时返回 true，
     * 因此 `{"a":1}` 为对象，而 `[1,2]` 不是；解析失败或解码为标量时返回 false。
     *
     * @param string $string 待判断的 JSON 字符串
     * @return bool 是 JSON 对象返回 true，否则返回 false
     */
    public static function isObject(string $string): bool
    {
        $decoded = self::decodeSafe($string);
        return is_array($decoded) && ! array_is_list($decoded);
    }

    /**
     * 通过路径表达式从 JSON 字符串或数组中获取值
     *
     * @param array|string $data JSON 字符串或已解码的数组
     * @param string $path
     * @param mixed|null $default
     * @return mixed
     * @throws JsonException
     */
    public static function get(array|string $data, string $path, mixed $default = null): mixed
    {
        $array = is_string($data) ? self::decode($data) : $data;
        if (! is_array($array)) {
            return $default;
        }
        return Arr::get($array, $path, $default);
    }

    /**
     * 通过路径表达式设置 JSON 中的值，返回新 JSON 字符串
     *
     * 先把 $json 解码为数组，用 Arr::set() 按点分路径写入（路径不存在时自动创建
     * 中间层级），再重新编码返回。原字符串不被修改。
     *
     * @param string $json  JSON 字符串
     * @param string $path  点分路径表达式，如 "user.name"、"list.0.id"
     * @param mixed  $value 要写入的值
     * @return string 写入后的新 JSON 字符串
     *
     * @throws JsonException 原字符串非法或结果无法编码时抛出
     */
    public static function set(string $json, string $path, mixed $value): string
    {
        $array = self::decode($json);
        Arr::set($array, $path, $value);
        return self::encode($array);
    }

    /**
     * 合并多个 JSON 字符串（递归合并）
     *
     * 逐个解码后交给 Arr::mergeRecursive() 做深层递归合并，同名键由后者覆盖前者，
     * 最终统一编码为一个新 JSON 字符串。
     *
     * @param string ...$jsons 待合并的 JSON 字符串，至少一个；任一非法都会导致解码异常
     * @return string 合并后的 JSON 字符串
     *
     * @throws JsonException 任一入参非法或合并结果无法编码时抛出
     */
    public static function merge(string ...$jsons): string
    {
        $arrays = array_map(static fn ($json) => self::decode($json), $jsons);
        return self::encode(Arr::mergeRecursive(...$arrays));
    }

    /**
     * 从数组或对象中提取指定路径的值列表
     *
     * 把 $data 当作「集合」遍历其每个元素，按 $path 取出对应值并顺序收集，
     * 结果键被重新索引为 0..n。元素缺少该路径时对应位置为 null。
     * 若 $data 解码后不是数组（如标量 JSON），直接返回空数组。
     *
     * @param array|string $data JSON 字符串或已解码的数组（顶层应为集合）
     * @param string       $path 点分路径表达式，如 "id"、"profile.name"
     * @return array<int, mixed> 提取到的值列表，输入不是数组时为空数组
     *
     * @throws JsonException $data 为字符串且非法时抛出
     */
    public static function pluck(array|string $data, string $path): array
    {
        $array = is_string($data) ? self::decode($data) : $data;
        if (! is_array($array)) {
            return [];
        }

        $results = [];
        foreach ($array as $item) {
            $results[] = Arr::get($item, $path);
        }
        return $results;
    }

    /**
     * 将 JSON 字符串转为 PHP 数组，确保所有对象都被转为数组
     *
     * 根节点为标量（如 `5`、`"str"`、`true`、`null`）时，
     * 按 PHP 的标量转数组规则包装为单元素数组（如 `[5]`）；
     * 直接返回标量会触发 "Return value must be of type array" 的 TypeError。
     *
     * @param string $json  JSON 字符串
     * @param bool   $wrapScalar 根节点为标量时是否包装为数组；false 则抛 JsonException
     * @return array<mixed>
     *
     * @throws JsonException JSON 非法且根节点为标量、同时 $wrapScalar 为 false 时抛出
     */
    public static function toArray(string $json, bool $wrapScalar = true): array
    {
        $decoded = self::decode($json, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if ($wrapScalar) {
            return [$decoded];
        }

        throw new JsonException('JSON 根节点不是对象或数组，无法转换为数组');
    }

    /**
     * 将 JSON 字符串转为 stdClass 对象
     *
     * @param string $json JSON 字符串
     * @return object 解码结果；根节点为标量时会得到 stdClass 转换后的对象
     */
    public static function toObject(string $json): object
    {
        // associative=false 时，JSON 对象会得到 stdClass，
        // 但 JSON 数组（如 [1,2]）仍得到 PHP 数组，直接返回会触发返回类型的
        // TypeError，因此对非对象结果统一转换为 stdClass。
        $decoded = self::decode($json, false);

        return is_object($decoded) ? $decoded : (object) $decoded;
    }

    /**
     * 比较两个 JSON 字符串是否等价（忽略键顺序和空白）
     *
     * 双方解码后各自递归按键排序再重新编码，用编码结果做严格比较，
     * 从而消除键顺序与空白差异，也不受 PHP 弱类型比较（"1" 与 1）影响。
     * 任一字符串非法时返回 false，不抛异常。
     *
     * @param string $a 第一个 JSON 字符串
     * @param string $b 第二个 JSON 字符串
     * @return bool 规范化后完全一致返回 true，否则返回 false
     */
    public static function equals(string $a, string $b): bool
    {
        try {
            $decodedA = self::decode($a);
            $decodedB = self::decode($b);
            // 使用规范化后的 JSON 字符串进行严格比较，避免 `==` 对数值字符串与数字
            // （如 "1" 与 1）产生误判，以及嵌套结构的弱类型比较陷阱。
            $canonicalA = self::encode(Arr::sortRecursive((array) $decodedA));
            $canonicalB = self::encode(Arr::sortRecursive((array) $decodedB));
            return $canonicalA === $canonicalB;
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * 获取 JSON 字符串中的键列表（顶层）
     *
     * 仅取顶层键，不递归；解码失败、或顶层是标量而非数组/对象时返回空数组，
     * 该方法不抛异常。
     *
     * @param string $json JSON 字符串
     * @return array<int, int|string> 顶层键列表，失败时为空数组
     */
    public static function keys(string $json): array
    {
        $decoded = self::decodeSafe($json, []);
        return is_array($decoded) ? array_keys($decoded) : [];
    }

    /**
     * 获取 JSON 字符串中的值列表（顶层）
     *
     * 仅取顶层值，结果重建为 0 起连续下标；解码失败、或顶层是标量时返回空数组，
     * 该方法不抛异常。
     *
     * @param string $json JSON 字符串
     * @return array<int, mixed> 顶层值列表（已重新索引），失败时为空数组
     */
    public static function values(string $json): array
    {
        $decoded = self::decodeSafe($json, []);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * 删除 JSON 中指定路径的值，返回新 JSON 字符串
     *
     * 按点分路径逐层下钻；若中间层级不存在或不是数组，则原样返回输入字符串
     * （不做任何修改，也不报错）。目标存在时用 unset 删除并重新编码。
     *
     * @param string $json JSON 字符串
     * @param string $path 点分路径表达式，如 "user.token"
     * @return string 删除后的新 JSON 字符串，路径不存在时为原始字符串
     *
     * @throws JsonException 原字符串非法或结果无法编码时抛出
     */
    public static function remove(string $json, string $path): string
    {
        $array = self::decode($json);
        $keys = explode('.', $path);
        $target = &$array;

        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (! isset($target[$key]) || ! is_array($target[$key])) {
                return $json;
            }
            $target = &$target[$key];
        }

        unset($target[array_shift($keys) ?? '']);
        return self::encode($array);
    }

    /**
     * 将文件内容读取并解码为数组
     *
     * 依次检查文件存在性、读取结果与 JSON 合法性，任一环节失败都抛出异常。
     * 以关联数组模式解码，因此文件根节点需为对象或数组。
     *
     * @param string $path JSON 文件路径
     * @return array<mixed> 解码后的数组
     *
     * @throws RuntimeException 文件不存在或无法读取时抛出
     * @throws JsonException 文件内容不是合法 JSON 时抛出
     */
    public static function fromFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("JSON 文件不存在: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("无法读取 JSON 文件: {$path}");
        }

        // 文件根节点可能是标量（如 `5`），decode() 此时返回非数组，
        // 直接返回会违反 array 返回类型；统一用 (array) 包一层。
        return (array) self::decode($content);
    }

    /**
     * 将数组编码并写入文件
     *
     * 以美化格式（带缩进）写入，目标目录不存在时递归创建（权限 0755），
     * 写入时持有独占锁，避免并发写出现交错内容。文件已存在会被整体覆盖。
     *
     * @param string $path  目标文件路径
     * @param mixed  $value 待写入的值，通常为数组
     * @param int    $flags 额外的 json_encode 选项位
     * @return void
     *
     * @throws RuntimeException 目录创建失败或文件写入失败时抛出
     * @throws JsonException 值无法编码为 JSON 时抛出
     */
    public static function toFile(string $path, mixed $value, int $flags = 0): void
    {
        $json = self::pretty($value, $flags);
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            throw new RuntimeException("无法创建目录: {$dir}");
        }
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException("无法写入 JSON 文件: {$path}");
        }
    }
}

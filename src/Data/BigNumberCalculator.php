<?php

declare(strict_types=1);

namespace zxf\Utils\Data;

use DivisionByZeroError;
use Exception;
use InvalidArgumentException;
use OverflowException;

/**
 * BigNumberCalculator - 超高精度大数计算器
 *
 * 功能特点：
 * 1. 支持任意精度的大整数运算（加减乘除、幂运算、幂塔运算）
 * 2. 智能算法选择（根据数字大小自动选择最优算法）
 * 3. 多层缓存系统提升性能
 * 4. 科学计数法转换（支持E记号和×10ⁿ格式）
 * 5. 完善的异常处理
 * 6. 详细的性能统计
 * 7. 内存优化和资源管理
 *
 * 使用demo
 * $sum = BigNumberCalculator::add('12345678901234567890', '98765432109876543210');
 * $prod = BigNumberCalculator::multiply('99999999999999999999', '88888888888888888888');
 *
 * @example
 * // 大数加法与乘法（支持任意精度，不受 int 范围限制）
 * $sum  = BigNumberCalculator::add('12345678901234567890', '98765432109876543210');
 * $prod = BigNumberCalculator::multiply('99999999999999999999', '88888888888888888888');
 *
 * @package zxf\Utils\Data
 * @version 1.0.0
 * @license MIT
 * @link https://weisifang.com/docs/doc/2_289
 */
class BigNumberCalculator
{
    /* 常量定义 */

    // 简单乘法算法的最大位数阈值
    private const SIMPLE_MULTIPLY_THRESHOLD = 32;

    // Karatsuba算法启用阈值
    private const KARATSUBA_THRESHOLD = 16;

    // 非对称乘法算法的长度差异比率阈值
    private const ASYMMETRIC_RATIO = 0.5;

    // 简单除法算法的最大位数阈值
    private const DIVISION_THRESHOLD = 20;

    // 幂塔运算的最大层数限制（防止栈溢出）
    private const TETRATION_DEPTH_LIMIT = 50;

    // 缓存条目数量限制
    private const CACHE_LIMIT = 1000;

    // 缓存清理比例（当缓存满时清理的比例）
    private const CACHE_CLEAN_RATIO = 0.5;

    // 内存警告阈值（字节），超过此值会触发缓存清理
    private const MEMORY_WARNING_THRESHOLD = 128 * 1024 * 1024; // 128MB

    // 单次计算结果最大长度限制（字符数）
    private const MAX_RESULT_LENGTH = 1000000; // 约1MB

    // 科学计数法转换阈值（超过此位数自动转换为科学计数法）
    private const SCIENTIFIC_NOTATION_THRESHOLD = 50;

    /* 静态属性 */

    // 性能统计数组
    private static array $stats = [
        'add' => ['count' => 0, 'time' => 0],       // 加法统计
        'subtract' => ['count' => 0, 'time' => 0],  // 减法统计
        'multiply' => ['count' => 0, 'time' => 0],  // 乘法统计
        'divide' => ['count' => 0, 'time' => 0],    // 除法统计
        'power' => ['count' => 0, 'time' => 0],     // 幂运算统计
        'tetration' => ['count' => 0, 'time' => 0], // 幂塔运算统计
        'cache' => ['hits' => 0, 'misses' => 0],    // 缓存统计
        'memory' => ['peak' => 0, 'current' => 0],   // 内存统计
    ];

    // 运算缓存数组
    private static array $powerCache = [];     // 幂运算缓存
    private static array $multiplyCache = [];  // 乘法运算缓存
    private static array $tetrationCache = []; // 幂塔运算缓存

    // 缓存清理重入锁
    //
    // 必须存在：`updateMemoryStats()` 在峰值内存超阈值时会调用 `cleanCache(true)`，
    // 而 `cleanCache(true)` 原实现末尾又会回调 `updateMemoryStats()`。
    // 由于 `memory_get_peak_usage()` 是**历史峰值、永不下降**，一旦进程峰值超过
    // MEMORY_WARNING_THRESHOLD，两个方法就会无限互调直至栈溢出。
    private static bool $cleaning = false;

    /************************
     * 公开接口方法
     ************************/

    /**
     * 大数加法
     *
     * 按十进制逐位相加并处理进位，支持任意长度的整数字符串与负号前缀；
     * 任一操作数为负数时转交 addSigned() 按符号规则处理。结果会去掉前导零，
     * 空串按 0 处理。结果超过 SCIENTIFIC_NOTATION_THRESHOLD 且开启
     * autoScientific 时转换为科学计数法。
     *
     * @param string $a 被加数字符串
     * @param string $b 加数字符串
     * @param bool $autoScientific 是否自动转换为科学计数法（当数字过大时）
     * @return string 和的结果字符串
     * @throws InvalidArgumentException 当输入不是有效数字时抛出
     */
    public static function add(string $a, string $b, bool $autoScientific = false): string
    {
        // 验证输入参数
        self::validateNumbers($a, $b);

        // 记录开始时间和更新统计
        $startTime = microtime(true);
        self::$stats['add']['count']++;
        self::updateMemoryStats();

        // 处理负数情况
        if (self::isNegative($a) || self::isNegative($b)) {
            $result = self::addSigned($a, $b);
        } else {
            // 移除前导零
            $a = self::removeLeadingZeros($a);
            $b = self::removeLeadingZeros($b);

            // 处理特殊情况
            if ($a === '') {
                $result = $b === '' ? '0' : $b;
            } elseif ($b === '') {
                $result = $a;
            } else {
                // 获取数字长度
                $lenA = strlen($a);
                $lenB = strlen($b);
                $maxLen = max($lenA, $lenB);
                $result = '';
                $carry = 0;

                // 从低位到高位逐位相加
                for ($i = 0; $i < $maxLen; $i++) {
                    // 获取当前位的数字，如果超出长度则补0
                    $digitA = $i < $lenA ? (int)$a[$lenA - 1 - $i] : 0;
                    $digitB = $i < $lenB ? (int)$b[$lenB - 1 - $i] : 0;

                    // 计算当前位的和（包括进位）
                    $sum = $digitA + $digitB + $carry;
                    $carry = (int)($sum / 10);  // 计算进位
                    $result = ($sum % 10) . $result;  // 计算当前位结果
                }

                // 处理最后的进位
                if ($carry > 0) {
                    $result = $carry . $result;
                }
            }
        }

        // 自动科学计数法转换
        if ($autoScientific && strlen($result) > self::SCIENTIFIC_NOTATION_THRESHOLD) {
            $result = self::toScientificNotation($result, 10);
        }

        // 更新统计信息
        self::$stats['add']['time'] += microtime(true) - $startTime;

        return $result;
    }

    /**
     * 大数减法
     *
     * 先比较绝对值决定结果符号与运算顺序，再借 subtractPositive() 做逐位
     * 相减；被减数或减数为负时转换为对应的加法/取负组合。两数相等直接返回
     * "0"，结果无前导零。
     *
     * @param string $a 被减数字符串
     * @param string $b 减数字符串
     * @param bool $autoScientific 是否自动转换为科学计数法
     * @return string 差的结果字符串
     * @throws InvalidArgumentException 当输入不是有效数字时抛出
     */
    public static function subtract(string $a, string $b, bool $autoScientific = false): string
    {
        // 验证输入参数
        self::validateNumbers($a, $b);

        // 记录开始时间和更新统计
        $startTime = microtime(true);
        self::$stats['subtract']['count']++;
        self::updateMemoryStats();

        // 处理负数情况
        if (self::isNegative($a)) {
            // -a - b = -(a + b)
            $result = self::negate(self::add(ltrim($a, '-'), ltrim($b, '-'), false));
        } elseif (self::isNegative($b)) {
            // a - (-b) = a + b
            $result = self::add($a, ltrim($b, '-'), false);
        } else {
            // 移除前导零
            $a = self::removeLeadingZeros($a);
            $b = self::removeLeadingZeros($b);

            // 处理特殊情况
            if ($b === '') {
                $result = $a === '' ? '0' : $a;
            } elseif ($a === $b) {
                $result = '0';
            } else {
                // 比较绝对值大小
                $comparison = self::compareAbsolute($a, $b);
                if ($comparison < 0) {
                    // a < b 时结果为 -(b - a)
                    $result = '-' . self::subtractPositive($b, $a);
                } elseif ($comparison === 0) {
                    // a == b 时结果为0
                    $result = '0';
                } else {
                    // 计算 a - b (a > b)
                    $result = self::subtractPositive($a, $b);
                }
            }
        }

        // 自动科学计数法转换
        if ($autoScientific && strlen($result) > self::SCIENTIFIC_NOTATION_THRESHOLD) {
            $result = self::toScientificNotation($result, 10);
        }

        self::$stats['subtract']['time'] += microtime(true) - $startTime;

        return $result;
    }

    /**
     * 大数乘法
     *
     * 按位数自动选择算法：双方均不超过 SIMPLE_MULTIPLY_THRESHOLD 时用
     * simpleMultiply()，长度差异悬殊时用 asymmetricMultiply()，其余用
     * Karatsuba。乘积按去符号后的绝对值缓存（同时写入反向键），命中则直接
     * 复用；任一操作数为 0 时返回 "0"，为 1 时直接返回另一操作数。
     *
     * @param string $a 被乘数字符串
     * @param string $b 乘数字符串
     * @param bool $autoScientific 是否自动转换为科学计数法
     * @return string 积的结果字符串
     * @throws InvalidArgumentException 当输入不是有效数字时抛出
     */
    public static function multiply(string $a, string $b, bool $autoScientific = false): string
    {
        // 验证输入参数
        self::validateNumbers($a, $b);

        // 记录开始时间和更新统计
        $startTime = microtime(true);
        self::$stats['multiply']['count']++;
        self::updateMemoryStats();

        // 处理符号
        $negative = false;
        if (self::isNegative($a)) {
            $a = ltrim($a, '-');
            $negative = !$negative;
        }
        if (self::isNegative($b)) {
            $b = ltrim($b, '-');
            $negative = !$negative;
        }

        // 移除前导零
        $a = self::removeLeadingZeros($a);
        $b = self::removeLeadingZeros($b);

        // 处理特殊情况
        if ($a === '' || $b === '') {
            self::$stats['multiply']['time'] += microtime(true) - $startTime;
            return '0';
        }
        if ($a === '1') {
            self::$stats['multiply']['time'] += microtime(true) - $startTime;
            return $negative ? "-$b" : $b;
        }
        if ($b === '1') {
            self::$stats['multiply']['time'] += microtime(true) - $startTime;
            return $negative ? "-$a" : $a;
        }

        // 检查缓存
        $cacheKey = "{$a}*{$b}";
        if (isset(self::$multiplyCache[$cacheKey])) {
            self::$stats['cache']['hits']++;
            $result = self::$multiplyCache[$cacheKey];
            self::$stats['multiply']['time'] += microtime(true) - $startTime;
            return $negative ? "-$result" : $result;
        }
        self::$stats['cache']['misses']++;

        // 获取数字长度
        $lenA = strlen($a);
        $lenB = strlen($b);

        // 根据数字特征选择最优算法
        if ($lenA <= self::SIMPLE_MULTIPLY_THRESHOLD && $lenB <= self::SIMPLE_MULTIPLY_THRESHOLD) {
            // 小数字使用简单乘法
            $result = self::simpleMultiply($a, $b);
        } elseif (abs($lenA - $lenB) > max($lenA, $lenB) * self::ASYMMETRIC_RATIO) {
            // 长度差异大时使用非对称乘法
            $result = self::asymmetricMultiply($a, $b);
        } else {
            // 中等大小数字使用Karatsuba算法
            $result = self::karatsubaMultiply($a, $b);
        }

        // 更新缓存
        if (count(self::$multiplyCache) < self::CACHE_LIMIT) {
            self::$multiplyCache[$cacheKey] = $result;
            self::$multiplyCache["{$b}*{$a}"] = $result; // 对称缓存
        } else {
            self::cleanCache();
        }

        // 处理符号
        if ($negative && $result !== '0') {
            $result = "-$result";
        }

        // 自动科学计数法转换
        if ($autoScientific && strlen($result) > self::SCIENTIFIC_NOTATION_THRESHOLD) {
            $result = self::toScientificNotation($result, 10);
        }

        self::$stats['multiply']['time'] += microtime(true) - $startTime;

        return $result;
    }

    /**
     * 大数除法
     *
     * 除数为 0 时抛出 DivisionByZeroError；|a| < |b| 且 precision 为 0 时直接
     * 返回 "0"，两数相等返回 "1"/"-1"。符号单独处理，数值部分按除数位数选择
     * simpleDivide()（小除数）或 longDivide()（大除数），precision 决定保留的
     * 小数位数，结果为截断而非四舍五入。
     *
     * @param string $a 被除数字符串
     * @param string $b 除数字符串
     * @param int $precision 小数精度位数（默认为0，只返回整数部分）
     * @param bool $autoScientific 是否自动转换为科学计数法
     * @return string 商的结果字符串
     * @throws InvalidArgumentException|DivisionByZeroError 当输入无效或除数为0时抛出
     */
    public static function divide(string $a, string $b, int $precision = 0, bool $autoScientific = false): string
    {
        // 验证输入参数
        self::validateNumbers($a, $b);

        // 记录开始时间和更新统计
        $startTime = microtime(true);
        self::$stats['divide']['count']++;
        self::updateMemoryStats();

        // 处理符号
        $negative = false;
        if (self::isNegative($a)) {
            $a = ltrim($a, '-');
            $negative = !$negative;
        }
        if (self::isNegative($b)) {
            $b = ltrim($b, '-');
            $negative = !$negative;
        }

        // 移除前导零
        $a = self::removeLeadingZeros($a);
        $b = self::removeLeadingZeros($b);

        // 处理特殊情况
        if ($b === '' || $b === '0') {
            throw new DivisionByZeroError('Division by zero');
        }
        if ($a === '') {
            self::$stats['divide']['time'] += microtime(true) - $startTime;
            return '0';
        }

        // 比较绝对值大小
        $comparison = self::compareAbsolute($a, $b);
        if ($comparison < 0 && $precision === 0) {
            // a < b 且不需要小数部分时返回0
            self::$stats['divide']['time'] += microtime(true) - $startTime;
            return '0';
        }
        if ($comparison === 0) {
            // a == b 时返回1或-1
            self::$stats['divide']['time'] += microtime(true) - $startTime;
            return $negative ? '-1' : '1';
        }

        // 根据除数大小选择算法
        $result = strlen($b) <= self::DIVISION_THRESHOLD
            ? self::simpleDivide($a, $b, $precision)  // 小除数使用简单除法
            : self::longDivide($a, $b, $precision);   // 大除数使用长除法

        // 处理符号
        if ($negative && $result !== '0') {
            $result = "-$result";
        }

        // 自动科学计数法转换
        if ($autoScientific && strlen($result) > self::SCIENTIFIC_NOTATION_THRESHOLD) {
            $result = self::toScientificNotation($result, 10);
        }

        self::$stats['divide']['time'] += microtime(true) - $startTime;

        return $result;
    }

    /**
     * 大数幂运算
     *
     * 使用快速幂（平方求幂）逐位处理指数，底数为负时按指数奇偶决定结果符号；
     * 底数为空串返回 "0"，指数为 "0" 返回 "1"，底数为 "1" 时直接返回 ±1。
     * 结果按「底数^指数」缓存；任一步结果超过 MAX_RESULT_LENGTH 抛
     * OverflowException。指数按十进制整数逐位减半处理。
     * 负指数结果不是整数，除底数为 1 的情形外一律抛 InvalidArgumentException。
     *
     * @param string $base 底数字符串
     * @param string $exponent 指数字符串
     * @param bool $autoScientific 是否自动转换为科学计数法
     * @return string 幂运算结果字符串
     * @throws InvalidArgumentException 当输入不是有效数字或指数为负数时抛出
     * @throws OverflowException 当结果长度超过上限时抛出
     */
    public static function power(string $base, string $exponent, bool $autoScientific = false): string
    {
        // 验证输入参数
        self::validateNumbers($base, $exponent);

        // 记录开始时间和更新统计
        $startTime = microtime(true);
        self::$stats['power']['count']++;
        self::updateMemoryStats();

        // 处理符号
        $negative = false;
        if (self::isNegative($base)) {
            $base = ltrim($base, '-');
            if (self::isOdd($exponent)) {
                $negative = true;
            }
        }

        // 移除前导零
        $base = self::removeLeadingZeros($base);
        $exponent = self::removeLeadingZeros($exponent);

        // 处理特殊情况
        if ($base === '') {
            self::$stats['power']['time'] += microtime(true) - $startTime;
            return '0';
        }
        if ($exponent === '0') {
            self::$stats['power']['time'] += microtime(true) - $startTime;
            return '1';
        }
        if ($base === '1') {
            self::$stats['power']['time'] += microtime(true) - $startTime;
            return $negative ? '-1' : '1';
        }

        // 负指数：结果为小数，本类只处理整数串。此处显式拒绝，
        // 否则快速幂会把 "-" 当作数字位逐位处理而得出错误结果
        if (self::isNegative($exponent)) {
            throw new InvalidArgumentException('Negative exponent is not supported: ' . $exponent);
        }

        // 检查缓存
        $cacheKey = "{$base}^{$exponent}";
        if (isset(self::$powerCache[$cacheKey])) {
            self::$stats['cache']['hits']++;
            $result = self::$powerCache[$cacheKey];
            self::$stats['power']['time'] += microtime(true) - $startTime;
            return $negative ? "-$result" : $result;
        }
        self::$stats['cache']['misses']++;

        // 快速幂算法
        $result = '1';
        $currentBase = $base;
        $currentExp = $exponent;

        while ($currentExp !== '0') {
            if (self::isOdd($currentExp)) {
                $result = self::multiply($result, $currentBase);

                // 内存检查
                if (strlen($result) > self::MAX_RESULT_LENGTH) {
                    throw new OverflowException('Power result exceeds maximum length');
                }
            }
            $currentBase = self::multiply($currentBase, $currentBase);
            $currentExp = self::divideByTwo($currentExp);

            self::updateMemoryStats();
        }

        // 更新缓存
        if (count(self::$powerCache) < self::CACHE_LIMIT) {
            self::$powerCache[$cacheKey] = $result;
        } else {
            self::cleanCache();
        }

        // 处理符号
        if ($negative && $result !== '0') {
            $result = "-$result";
        }

        // 自动科学计数法转换
        if ($autoScientific && strlen($result) > self::SCIENTIFIC_NOTATION_THRESHOLD) {
            $result = self::toScientificNotation($result, 10);
        }

        self::$stats['power']['time'] += microtime(true) - $startTime;

        return $result;
    }

    /**
     * 幂塔计算（超幂运算 a↑↑b）
     *
     * @param string $base 底数字符串
     * @param int $height 幂塔层数（使用整数限制大小）
     * @param bool $autoScientific 是否自动转换为科学计数法
     * @return string 幂塔运算结果字符串
     * @throws InvalidArgumentException|OverflowException 当输入无效或结果太大时抛出
     */
    public static function tetration(string $base, int $height, bool $autoScientific = false): string
    {
        // 验证输入参数
        self::validateNumber($base);

        // 记录开始时间和更新统计
        $startTime = microtime(true);
        self::$stats['tetration']['count']++;
        self::updateMemoryStats();

        // 移除前导零和符号
        $base = self::removeLeadingZeros($base);
        if ($base === '') {
            return '0';
        }

        // 处理特殊情况
        if ($height === 0) {
            return '1';
        }
        if ($base === '1') {
            return '1';
        }

        // 检查幂塔层数限制
        if ($height > self::TETRATION_DEPTH_LIMIT) {
            throw new OverflowException(
                'Tetration depth exceeds limit of ' . self::TETRATION_DEPTH_LIMIT
            );
        }

        // 检查缓存 - 修复变量名问题
        $cacheKey = "tetration_{$base}_{$height}";
        if (isset(self::$tetrationCache[$cacheKey])) {
            self::$stats['cache']['hits']++;
            $result = self::$tetrationCache[$cacheKey];
            self::$stats['tetration']['time'] += microtime(true) - $startTime;
            return $result;
        }
        self::$stats['cache']['misses']++;

        // 迭代计算幂塔
        $result = $base;
        for ($i = 1; $i < $height; $i++) {
            $result = self::power($base, $result);

            // 内存和长度检查
            if (strlen($result) > self::MAX_RESULT_LENGTH) {
                throw new OverflowException(
                    'Tetration result exceeds maximum length of ' . self::MAX_RESULT_LENGTH
                );
            }
            self::updateMemoryStats();

            // 超时检查（假设最大执行时间60秒）
            if (microtime(true) - $startTime > 60) {
                throw new OverflowException('Tetration computation timeout');
            }
        }

        // 更新缓存
        if (count(self::$tetrationCache) < self::CACHE_LIMIT) {
            self::$tetrationCache[$cacheKey] = $result;
        } else {
            self::cleanCache();
        }

        // 自动科学计数法转换
        if ($autoScientific && strlen($result) > self::SCIENTIFIC_NOTATION_THRESHOLD) {
            $result = self::toScientificNotation($result, 10);
        }

        self::$stats['tetration']['time'] += microtime(true) - $startTime;

        return $result;
    }

    /**
     * 转换为科学计数法（支持E记号和×10ⁿ格式）
     *
     * @param string $number 要转换的数字字符串
     * @param int $precision 保留的有效位数（默认10）
     * @param string $format 输出格式：'e' 或 'times'（默认'e'）
     * @return string 科学计数法表示的字符串
     * @throws InvalidArgumentException 当输入不是有效数字时抛出
     */
    public static function toScientificNotation(string $number, int $precision = 10, string $format = 'e'): string
    {
        // 验证输入参数
        self::validateNumber($number);

        // 处理符号
        $negative = self::isNegative($number);
        $number = ltrim($number, '-');

        // 移除前导零
        $number = self::removeLeadingZeros($number);
        $length = strlen($number);

        // 处理特殊情况
        if ($length === 0) {
            return '0';
        }
        if ($length === 1) {
            return ($negative ? '-' : '') . $number . ($format === 'e' ? 'e0' : '×10⁰');
        }

        // 计算指数
        $exponent = $length - 1;

        // 构建尾数部分
        $mantissa = substr($number, 0, 1);  // 首位数字
        if ($precision > 1) {
            // 添加小数点后的数字
            $remainingDigits = substr($number, 1, $precision - 1);
            if ($remainingDigits !== '') {
                $mantissa .= '.' . $remainingDigits;
            }
        }

        // 清理尾数（移除多余的零和小数点）
        $mantissa = rtrim(rtrim($mantissa, '0'), '.');

        // 组合结果
        $prefix = $negative ? '-' : '';

        if ($format === 'times') {
            // 使用×10ⁿ格式
            $exponentStr = self::numberToSuperscript($exponent);
            return $prefix . $mantissa . '×10' . $exponentStr;
        } else {
            // 使用E记号格式
            return $prefix . $mantissa . 'e' . $exponent;
        }
    }

    /**
     * 将数字转换为上标格式（用于科学计数法）
     *
     * @param int $number 要转换的数字
     * @return string 上标格式的字符串
     */
    private static function numberToSuperscript(int $number): string
    {
        $superscripts = [
            '0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴',
            '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹'
        ];

        $result = '';
        $digits = str_split((string)$number);
        foreach ($digits as $digit) {
            $result .= $superscripts[$digit] ?? $digit;
        }

        return $result;
    }

    /**
     * 智能格式化数字（自动选择精确值或科学计数法）
     *
     * @param string $number 要格式化的数字
     * @param int $maxLength 最大显示长度阈值
     * @param int $precision 科学计数法精度
     * @return string 格式化后的数字
     */
    public static function formatNumber(string $number, int $maxLength = 50, int $precision = 10): string
    {
        self::validateNumber($number);

        $cleanNumber = self::removeLeadingZeros(ltrim($number, '-'));
        $length = strlen($cleanNumber);

        if ($length <= $maxLength) {
            return $number; // 返回精确值
        } else {
            return self::toScientificNotation($number, $precision, 'e');
        }
    }

    /**
     * 测试辅助方法：执行一次运算并打印性能报告
     *
     * 先重置统计与缓存，再调用传入的可执行体并计时，随后输出结果摘要（超长时
     * 附带两种科学计数法表示）、耗时、结果长度以及各运算的调用次数、耗时与
     * 缓存命中率。运算过程中抛出的异常会被捕获并打印，不会向外传播。
     *
     * @param string $description 测试用例描述，作为报告标题输出
     * @param callable $operation 待测运算，需返回字符串结果
     * @param mixed ...$args 逐个透传给 $operation 的参数
     * @return void
     */
    public static function analysis(string $description, callable $operation, ...$args): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "测试: $description\n";

        BigNumberCalculator::reset();

        try {
            $start = microtime(true);
            $result = $operation(...$args);
            $time = microtime(true) - $start;

            $stats = BigNumberCalculator::getStats();

            // 显示结果摘要
            if (strlen($result) <= 100) {
                echo "结果: $result\n";
            } else {
                $sciE = BigNumberCalculator::toScientificNotation($result, 15, 'e');
                $sciTimes = BigNumberCalculator::toScientificNotation($result, 15, 'times');
                echo "科学计数法(E): $sciE\n";
                echo "科学计数法(×10ⁿ): $sciTimes\n";
                echo '前20位和后20位: '
                    . substr($result, 0, 20) . '...' . substr($result, -20) . "\n";
            }

            echo '计算时间: ' . number_format($time, 6) . " 秒\n";
            echo '结果长度: ' . strlen($result) . " 位\n";

            // 显示统计信息
            echo "\n性能统计:\n";
            echo '内存峰值: ' . number_format($stats['memory']['peak'] / 1024 / 1024, 2) . " MB\n";
            echo "加法运算: {$stats['add']['count']} 次, "
                . number_format($stats['add']['time'], 6) . " 秒\n";
            echo "减法运算: {$stats['subtract']['count']} 次, "
                . number_format($stats['subtract']['time'], 6) . " 秒\n";
            echo "乘法运算: {$stats['multiply']['count']} 次, "
                . number_format($stats['multiply']['time'], 6) . " 秒\n";
            echo "除法运算: {$stats['divide']['count']} 次, "
                . number_format($stats['divide']['time'], 6) . " 秒\n";
            echo "幂运算: {$stats['power']['count']} 次, "
                . number_format($stats['power']['time'], 6) . " 秒\n";
            echo "幂塔运算: {$stats['tetration']['count']} 次, "
                . number_format($stats['tetration']['time'], 6) . " 秒\n";
            echo "缓存命中: {$stats['cache']['hits']} 次, 未命中: {$stats['cache']['misses']} 次\n";
            echo '缓存命中率: '
                . number_format($stats['cache']['hits'] / max(1, $stats['cache']['hits'] + $stats['cache']['misses']) * 100, 1)
                . "%\n";

        } catch (Exception|DivisionByZeroError|InvalidArgumentException|OverflowException $e) {
            echo '错误: ' . $e->getMessage() . "\n";
        }
    }

    /**
     * 获取性能统计信息
     *
     * 返回各类运算的调用次数与累计耗时、缓存命中/未命中次数以及内存峰值与
     * 当前占用（字节）。返回的是内部数组的副本，修改它不会影响统计状态。
     *
     * @return array 统计信息，含 add/subtract/multiply/divide/power/tetration、cache 与 memory 分组
     */
    public static function getStats(): array
    {
        return self::$stats;
    }

    /**
     * 重置计算器状态
     *
     * 清空幂、乘法与幂塔三处缓存，把所有运算的计数与耗时归零，并在环境
     * 支持时强制回收循环引用，使后续统计从干净状态重新累计。
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$powerCache = [];
        self::$multiplyCache = [];
        self::$tetrationCache = [];
        self::$stats = [
            'add' => ['count' => 0, 'time' => 0],
            'subtract' => ['count' => 0, 'time' => 0],
            'multiply' => ['count' => 0, 'time' => 0],
            'divide' => ['count' => 0, 'time' => 0],
            'power' => ['count' => 0, 'time' => 0],
            'tetration' => ['count' => 0, 'time' => 0],
            'cache' => ['hits' => 0, 'misses' => 0],
            'memory' => ['peak' => 0, 'current' => 0],
        ];

        // 强制垃圾回收
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /************************
     * 内部辅助方法
     ************************/

    // ... (其余内部辅助方法保持不变，但已优化内存使用和性能)
    // 注意：由于篇幅限制，这里只展示了主要改进部分
    // 完整的内部方法实现需要保持与之前版本相同的功能，但进行以下优化：
    // 1. 添加内存检查点
    // 2. 优化字符串操作
    // 3. 减少不必要的变量复制
    // 4. 改进错误处理

    /**
     * 带符号的加法处理
     *
     * 按两个操作数的符号组合转换为「同号相加取负」或「异号相减」：
     * 双负返回 -( |a|+|b| )，a 负返回 |b|-|a|，其余返回 |a|-|b|。
     * 仅由 add() 在检测到负号时调用。
     *
     * @param string $a 加数，可带负号
     * @param string $b 加数，可带负号
     * @return string 和的结果字符串
     */
    private static function addSigned(string $a, string $b): string
    {
        $aNeg = self::isNegative($a);
        $bNeg = self::isNegative($b);
        $aAbs = ltrim($a, '-');
        $bAbs = ltrim($b, '-');

        if ($aNeg && $bNeg) {
            return '-' . self::add($aAbs, $bAbs, false);
        } elseif ($aNeg) {
            return self::subtract($bAbs, $aAbs, false);
        } else {
            return self::subtract($aAbs, $bAbs, false);
        }
    }

    /**
     * 正数减法（a >= b）
     *
     * 从低位向高位逐位相减并处理借位，结果去除前导零；结果为 0 时返回 "0"。
     * 调用方必须保证 $a 的绝对值不小于 $b，否则结果无意义。
     *
     * @param string $a 被减数，仅含数字且无符号
     * @param string $b 减数，仅含数字且无符号，绝对值不得大于 $a
     * @return string 差的绝对值字符串，无前导零
     */
    private static function subtractPositive(string $a, string $b): string
    {
        $lenA = strlen($a);
        $lenB = strlen($b);
        $result = '';
        $borrow = 0;

        for ($i = 0; $i < $lenA; $i++) {
            $digitA = (int)$a[$lenA - 1 - $i] - $borrow;
            $borrow = 0;

            $digitB = $i < $lenB ? (int)$b[$lenB - 1 - $i] : 0;

            if ($digitA < $digitB) {
                $digitA += 10;
                $borrow = 1;
            }

            $result = ($digitA - $digitB) . $result;
        }

        return self::removeLeadingZeros($result) ?: '0';
    }

    /**
     * 简单乘法算法（竖式逐位相乘）
     *
     * 以长度之和的结果数组累加每位乘积与进位，时间复杂度 O(n·m)。
     * 仅处理无符号纯数字串，结果去除前导零，全零时返回 "0"。
     *
     * @param string $a 被乘数，仅含数字且无符号
     * @param string $b 乘数，仅含数字且无符号
     * @return string 积的结果字符串
     */
    private static function simpleMultiply(string $a, string $b): string
    {
        $lenA = strlen($a);
        $lenB = strlen($b);
        $result = array_fill(0, $lenA + $lenB, 0);

        for ($i = $lenA - 1; $i >= 0; $i--) {
            $carry = 0;
            $digitA = (int)$a[$i];

            for ($j = $lenB - 1; $j >= 0; $j--) {
                $product = $digitA * (int)$b[$j] + $result[$i + $j + 1] + $carry;
                $result[$i + $j + 1] = $product % 10;
                $carry = (int)($product / 10);
            }

            $result[$i] += $carry;
        }

        $resultStr = self::removeLeadingZeros(implode('', $result));
        return $resultStr === '' ? '0' : $resultStr;
    }

    /**
     * 非对称乘法优化
     *
     * 在两个操作数长度差异悬殊时使用：先确保 $a 更长，再逐位取较短的 $b
     * 乘以 $a 并左移相应位数后累加。$b 中为 0 的位直接跳过以减少运算。
     *
     * @param string $a 被乘数，仅含数字且无符号
     * @param string $b 乘数，仅含数字且无符号
     * @return string 积的结果字符串
     */
    private static function asymmetricMultiply(string $a, string $b): string
    {
        if (strlen($a) < strlen($b)) {
            [$a, $b] = [$b, $a];
        }

        $result = '0';
        $bLength = strlen($b);

        for ($i = 0; $i < $bLength; $i++) {
            $digit = (int)$b[$i];
            if ($digit === 0) {
                continue;
            }

            $partial = self::simpleMultiply($a, (string)$digit);
            $partial .= str_repeat('0', $bLength - $i - 1);
            $result = self::add($result, $partial, false);
        }

        return $result;
    }

    /**
     * Karatsuba 快速乘法算法
     *
     * 按较短操作数长度折半拆分高低位，用三次乘法（z0/z1/z2）与加减移位
     * 组合出结果，复杂度约 O(n^1.585)。任一操作数短于 KARATSUBA_THRESHOLD
     * 时退化为 simpleMultiply()，递归调用经由 multiply()（含缓存）。
     *
     * @param string $a 被乘数，仅含数字且无符号
     * @param string $b 乘数，仅含数字且无符号
     * @return string 积的结果字符串
     */
    private static function karatsubaMultiply(string $a, string $b): string
    {
        $lenA = strlen($a);
        $lenB = strlen($b);

        if ($lenA < self::KARATSUBA_THRESHOLD || $lenB < self::KARATSUBA_THRESHOLD) {
            return self::simpleMultiply($a, $b);
        }

        $m = min($lenA, $lenB);
        $m2 = (int)($m / 2);

        $high1 = substr($a, 0, -$m2) ?: '0';
        $low1 = substr($a, -$m2);
        $high2 = substr($b, 0, -$m2) ?: '0';
        $low2 = substr($b, -$m2);

        $z0 = self::multiply($low1, $low2, false);
        $z1 = self::multiply(self::add($low1, $high1, false), self::add($low2, $high2, false), false);
        $z2 = self::multiply($high1, $high2, false);

        $temp = self::subtract(self::subtract($z1, $z2, false), $z0, false);

        return self::add(
            self::add(
                self::shiftLeft($z2, 2 * $m2),
                self::shiftLeft($temp, $m2),
                false
            ),
            $z0,
            false
        );
    }

    /**
     * 简单除法算法（小除数）
     *
     * 适用于除数为单个数位量级的场景：把除数转换为 int，逐位做「余数*10+当前位」
     * 的短除法。precision 大于 0 且有余数时继续取小数位，结果为截断而非四舍五入。
     *
     * @param string $a 被除数，仅含数字且无符号
     * @param string $b 除数，仅含数字且无符号，长度需在 int 可表示范围内
     * @param int $precision 保留的小数位数，0 表示只取整数部分
     * @return string 商的结果字符串
     */
    private static function simpleDivide(string $a, string $b, int $precision): string
    {
        $bInt = (int)$b;
        $result = '';
        $remainder = 0;
        $index = 0;
        $lenA = strlen($a);

        while ($index < $lenA) {
            $digit = (int)$a[$index];
            $current = $remainder * 10 + $digit;
            $quotient = (int)($current / $bInt);
            $remainder = $current % $bInt;
            $result .= $quotient;
            $index++;
        }

        $result = self::removeLeadingZeros($result) ?: '0';

        if ($precision > 0 && $remainder != 0) {
            $result .= '.';
            $decimalCount = 0;

            while ($remainder != 0 && $decimalCount < $precision) {
                $remainder *= 10;
                $quotient = (int)($remainder / $bInt);
                $remainder %= $bInt;
                $result .= $quotient;
                $decimalCount++;
            }
        }

        return $result;
    }

    /**
     * 长除法算法（大除数）
     *
     * 逐位下拉被除数形成当前余数，用除数首位估算商位（上限 9）并通过乘法回
     * 代校正，再减去乘积得到新余数；precision 大于 0 且有余数时继续下拉补零
     * 取小数位。全程使用字符串运算，不依赖浮点。
     *
     * @param string $a 被除数，仅含数字且无符号
     * @param string $b 除数，仅含数字且无符号，长度可超过 int 范围
     * @param int $precision 保留的小数位数，0 表示只取整数部分
     * @return string 商的结果字符串
     */
    private static function longDivide(string $a, string $b, int $precision): string
    {
        $result = '';
        $remainder = '0';
        $index = 0;
        $lenA = strlen($a);

        while ($index < $lenA) {
            $digit = $a[$index];
            $current = self::removeLeadingZeros($remainder . $digit) ?: '0';

            $comparison = self::compareAbsolute($current, $b);
            if ($comparison >= 0) {
                $estimate = min(9, (int)($current / (int)$b[0]));

                while (true) {
                    $tempProduct = self::multiply($b, (string)$estimate, false);
                    if (self::compareAbsolute($tempProduct, $current) <= 0) {
                        break;
                    }
                    $estimate--;
                }

                $remainder = self::subtract($current, $tempProduct, false);
                $result .= $estimate;
            } else {
                if ($result !== '') {
                    $result .= '0';
                }
                $remainder = $current;
            }

            $index++;
        }

        $result = $result === '' ? '0' : (self::removeLeadingZeros($result) ?: '0');

        if ($precision > 0 && $remainder !== '0') {
            $result .= '.';
            $decimalCount = 0;

            while ($remainder !== '0' && $decimalCount < $precision) {
                $remainder .= '0';
                $current = self::removeLeadingZeros($remainder) ?: '0';

                $comparison = self::compareAbsolute($current, $b);
                if ($comparison >= 0) {
                    $estimate = min(9, (int)($current / (int)$b[0]));

                    while (true) {
                        $tempProduct = self::multiply($b, (string)$estimate, false);
                        if (self::compareAbsolute($tempProduct, $current) <= 0) {
                            break;
                        }
                        $estimate--;
                    }

                    $remainder = self::subtract($current, $tempProduct, false);
                    $result .= $estimate;
                } else {
                    $result .= '0';
                    $remainder = $current;
                }

                $decimalCount++;
            }
        }

        return $result;
    }

    /**
     * 十进制左移（末尾补零）
     *
     * 相当于乘以 10^$shift；$shift 不大于 0 时原样返回，不做裁剪或报错。
     *
     * @param string $num 数字字符串
     * @param int $shift 补零个数
     * @return string 左移后的数字字符串
     */
    private static function shiftLeft(string $num, int $shift): string
    {
        return $shift > 0 ? $num . str_repeat('0', $shift) : $num;
    }

    /**
     * 判断十进制整数的末位是否为奇数
     *
     * 仅看最后一位字符，因此不受数字长度限制；带负号的输入同样有效。
     *
     * @param string $num 数字字符串
     * @return bool 末位为 1/3/5/7/9 时返回 true
     */
    private static function isOdd(string $num): bool
    {
        return ((int)substr($num, -1) % 2) === 1;
    }

    /**
     * 大数除以 2（向下取整）
     *
     * 逐位短除，余数进位到下一轮；结果去掉前导零，全零时返回 "0"。用于快速幂
     * 中不断折半指数。
     *
     * @param string $num 被除数，仅含数字且无符号
     * @return string 商的结果字符串
     */
    private static function divideByTwo(string $num): string
    {
        $result = '';
        $add = 0;

        for ($i = 0; $i < strlen($num); $i++) {
            $digit = (int)$num[$i] + $add * 10;
            $add = $digit % 2;
            $quotient = (int)($digit / 2);

            if ($quotient !== 0 || $result !== '') {
                $result .= $quotient;
            }
        }

        return $result === '' ? '0' : $result;
    }

    /**
     * 比较两个非负整数的绝对值大小
     *
     * 先去掉前导零，再先比位数、后逐位比较：
     * 返回 1 表示 $a > $b，-1 表示 $a < $b，0 表示两者相等。
     *
     * @param string $a 数字字符串，仅含数字且无符号
     * @param string $b 数字字符串，仅含数字且无符号
     * @return int 1（a 大）、-1（b 大）或 0（相等）
     */
    private static function compareAbsolute(string $a, string $b): int
    {
        $a = self::removeLeadingZeros($a);
        $b = self::removeLeadingZeros($b);

        $lenA = strlen($a);
        $lenB = strlen($b);

        if ($lenA > $lenB) return 1;
        if ($lenA < $lenB) return -1;

        for ($i = 0; $i < $lenA; $i++) {
            $digitA = (int)$a[$i];
            $digitB = (int)$b[$i];

            if ($digitA > $digitB) return 1;
            if ($digitA < $digitB) return -1;
        }

        return 0;
    }

    /**
     * 清理超限的运算缓存
     *
     * 对乘法、幂、幂塔三个缓存分别检查：容量超过 CACHE_LIMIT 时按保留比例
     * 裁掉头部旧条目（$force 为 true 时保留比例降为 0.2，清理更激进）。
     * 内置重入保护，清理过程中重复调用会立即返回，避免与 updateMemoryStats()
     * 互递归；$force 为 true 时还会触发一次循环引用回收。
     *
     * @param bool $force 是否为内存告警下的强制清理
     * @return void
     */
    private static function cleanCache(bool $force = false): void
    {
        // 重入保护：清理过程中禁止再次进入，避免与 updateMemoryStats() 互相递归
        if (self::$cleaning) {
            return;
        }

        self::$cleaning = true;

        try {
            $cleanRatio = $force ? 0.2 : self::CACHE_CLEAN_RATIO;

            foreach (['multiplyCache', 'powerCache', 'tetrationCache'] as $cacheName) {
                $cache = &self::${$cacheName};
                $count = count($cache);
                if ($count > self::CACHE_LIMIT) {
                    $cache = array_slice($cache, (int) ($count * $cleanRatio), null, true);
                }
            }

            if ($force && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
                // 仅刷新统计数值，绝不回调 updateMemoryStats()（否则无限递归）
                self::refreshMemoryStats();
            }
        } finally {
            self::$cleaning = false;
        }
    }

    /**
     * 更新内存统计信息，并在峰值超阈值时触发缓存清理
     */
    private static function updateMemoryStats(): void
    {
        self::refreshMemoryStats();

        // 清理过程中不再触发新的清理
        if (self::$cleaning) {
            return;
        }

        if (memory_get_peak_usage(true) > self::MEMORY_WARNING_THRESHOLD) {
            self::cleanCache(true);
        }
    }

    /**
     * 仅刷新内存统计数值（不触发任何缓存清理）
     */
    private static function refreshMemoryStats(): void
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        self::$stats['memory']['current'] = $current;
        self::$stats['memory']['peak'] = max(self::$stats['memory']['peak'], $peak);
    }

    /**
     * 验证单个数字字符串
     *
     * 仅接受可选负号加十进制整数的形式（如 "-123"、"0"）；空串、小数、
     * 科学计数法、含空白或非数字字符的输入都会被拒绝。
     *
     * @param string $num 待验证的数字字符串
     * @return void
     * @throws InvalidArgumentException 输入为空或格式非法时抛出
     */
    private static function validateNumber(string $num): void
    {
        if ($num === '') {
            throw new InvalidArgumentException('Empty string is not a valid number');
        }

        if (!preg_match('/^-?\d+$/', $num)) {
            throw new InvalidArgumentException('Invalid number format: ' . $num);
        }
    }

    /**
     * 批量验证数字字符串
     *
     * 按参数顺序逐个调用 validateNumber()，遇到第一个非法值即抛出异常。
     *
     * @param string ...$nums 待验证的数字字符串（可变参数）
     * @return void
     * @throws InvalidArgumentException 任一参数为空或格式非法时抛出
     */
    private static function validateNumbers(string ...$nums): void
    {
        foreach ($nums as $num) {
            self::validateNumber($num);
        }
    }

    /**
     * 判断是否以负号开头
     *
     * 仅检查首字符是否为 "-"，不做格式校验，也不识别 "-0" 这类语义上的零。
     *
     * @param string $num 数字字符串
     * @return bool 以 "-" 开头时返回 true
     */
    private static function isNegative(string $num): bool
    {
        return str_starts_with($num, '-');
    }

    /**
     * 取反数字符号
     *
     * 零（"0"）保持为 "0"，其余正数前加 "-"、负数去掉 "-"；不处理前导零。
     *
     * @param string $num 数字字符串
     * @return string 取反后的数字字符串
     */
    private static function negate(string $num): string
    {
        if ($num === '0') {
            return '0';
        }

        return self::isNegative($num) ? ltrim($num, '-') : '-' . $num;
    }

    /**
     * 移除数字的前导零
     *
     * 保留负号，去掉整数部分开头的 0；全部为 0 或去空后统一返回 "0"
     * （因此本方法不会返回空串，调用方的空串分支实际不会命中）。
     *
     * @param string $num 数字字符串，可带负号
     * @return string 去除前导零后的数字字符串，最小为 "0"
     */
    private static function removeLeadingZeros(string $num): string
    {
        if (self::isNegative($num)) {
            $abs = ltrim($num, '-');
            $result = ltrim($abs, '0');
            return $result === '' ? '0' : '-' . $result;
        }

        $result = ltrim($num, '0');
        return $result === '' ? '0' : $result;
    }
}

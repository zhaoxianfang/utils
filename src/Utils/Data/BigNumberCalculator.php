<?php

namespace zxf\Utils\Data;

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
 *
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

    /************************
     * 公开接口方法
     ************************/

    /**
     * 大数加法
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
        if ($b === '') {
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
     * @param string $base 底数字符串
     * @param string $exponent 指数字符串
     * @param bool $autoScientific 是否自动转换为科学计数法
     * @return string 幂运算结果字符串
     * @throws InvalidArgumentException 当输入不是有效数字时抛出
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
     * 测试辅助方法
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
     */
    public static function getStats(): array
    {
        return self::$stats;
    }

    /**
     * 重置计算器状态
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
     * 简单乘法算法
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
     * Karatsuba快速乘法算法
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
     * 简单除法算法
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
     * 长除法算法
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
     * 数字左移
     */
    private static function shiftLeft(string $num, int $shift): string
    {
        return $shift > 0 ? $num . str_repeat('0', $shift) : $num;
    }

    /**
     * 判断数字是否为奇数
     */
    private static function isOdd(string $num): bool
    {
        return ((int)substr($num, -1) % 2) === 1;
    }

    /**
     * 大数除以2
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
     * 比较两个正数的大小
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
     * 清理缓存
     */
    private static function cleanCache(bool $force = false): void
    {
        $cleanRatio = $force ? 0.2 : self::CACHE_CLEAN_RATIO;

        foreach (['multiplyCache', 'powerCache', 'tetrationCache'] as $cacheName) {
            $cache = &self::${$cacheName};
            $count = count($cache);
            if ($count > self::CACHE_LIMIT) {
                $cache = array_slice($cache, (int)($count * $cleanRatio), null, true);
            }
        }

        if ($force && function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
            self::updateMemoryStats();
        }
    }

    /**
     * 更新内存统计信息
     */
    private static function updateMemoryStats(): void
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        self::$stats['memory']['current'] = $current;
        self::$stats['memory']['peak'] = max(self::$stats['memory']['peak'], $peak);

        if ($peak > self::MEMORY_WARNING_THRESHOLD) {
            self::cleanCache(true);
        }
    }

    /**
     * 验证数字字符串
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
     * 验证多个数字字符串
     */
    private static function validateNumbers(string ...$nums): void
    {
        foreach ($nums as $num) {
            self::validateNumber($num);
        }
    }

    /**
     * 判断是否为负数
     */
    private static function isNegative(string $num): bool
    {
        return str_starts_with($num, '-');
    }

    /**
     * 取反数字
     */
    private static function negate(string $num): string
    {
        if ($num === '0') {
            return '0';
        }

        return self::isNegative($num) ? ltrim($num, '-') : '-' . $num;
    }

    /**
     * 移除前导零
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

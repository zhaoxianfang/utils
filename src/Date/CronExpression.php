<?php

/**
 * ============================================================================
 * Cron 表达式解析器
 * ============================================================================
 *
 * 解析 Cron 表达式并计算下一次执行时间。
 * 支持标准 5 字段格式（分 时 日 月 周）以及可选的秒字段。
 *
 * @package     zxf\Utils\Date
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Date;

use InvalidArgumentException;

final class CronExpression
{
    private array $fields = [];

    private static array $fieldRanges = [
        0 => [0, 59],   // 分钟
        1 => [0, 23],   // 小时
        2 => [1, 31],   // 日期
        3 => [1, 12],   // 月份
        4 => [0, 6],    // 星期（0=周日）
    ];

    private static array $monthAliases = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
        'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private static array $weekAliases = [
        'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3,
        'thu' => 4, 'fri' => 5, 'sat' => 6,
    ];

    public function __construct(string $expression)
    {
        $this->parse($expression);
    }

    public static function make(string $expression): self
    {
        return new self($expression);
    }

    /**
     * 判断表达式是否有效
     */
    public static function isValid(string $expression): bool
    {
        try {
            new self($expression);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * 获取下一次执行时间
     */
    public function getNextRunDate(?\DateTimeInterface $currentTime = null): ?\DateTimeImmutable
    {
        $current = $currentTime ? \DateTimeImmutable::createFromInterface($currentTime) : new \DateTimeImmutable();
        $current = $current->setTime((int) $current->format('H'), (int) $current->format('i'), 0)->add(new \DateInterval('PT1M'));

        $maxIterations = 366 * 24 * 60;
        for ($i = 0; $i < $maxIterations; $i++) {
            if ($this->isMatching($current)) {
                return $current;
            }
            $current = $current->add(new \DateInterval('PT1M'));
        }

        return null;
    }

    /**
     * 获取未来 N 次执行时间
     *
     * @return array<int, \DateTimeImmutable>
     */
    public function getMultipleRunDates(int $count = 5, ?\DateTimeInterface $currentTime = null): array
    {
        $dates = [];
        $current = $currentTime ? \DateTimeImmutable::createFromInterface($currentTime) : new \DateTimeImmutable();

        for ($i = 0; $i < $count; $i++) {
            $next = $this->getNextRunDate($current);
            if ($next === null) {
                break;
            }
            $dates[] = $next;
            $current = $next->add(new \DateInterval('PT1M'));
        }

        return $dates;
    }

    /**
     * 判断给定时间是否匹配 Cron 表达式
     */
    public function isMatching(\DateTimeInterface $dateTime): bool
    {
        $date = \DateTimeImmutable::createFromInterface($dateTime);
        $checks = [
            (int) $date->format('i'),
            (int) $date->format('H'),
            (int) $date->format('d'),
            (int) $date->format('m'),
            (int) $date->format('w'),
        ];

        foreach ($checks as $i => $value) {
            if (! in_array($value, $this->fields[$i], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 解析 Cron 表达式
     */
    private function parse(string $expression): void
    {
        $expression = trim($expression);
        $parts = preg_split('/\s+/', $expression);

        if ($parts === false || count($parts) !== 5) {
            throw new InvalidArgumentException("Cron 表达式必须是 5 个字段，用空格分隔: {$expression}");
        }

        foreach ($parts as $i => $part) {
            $this->fields[$i] = $this->expandField($part, $i);
        }
    }

    /**
     * 展开单个字段
     */
    private function expandField(string $field, int $index): array
    {
        $range = self::$fieldRanges[$index];
        $values = [];

        // 处理逗号分隔的多个值
        $segments = explode(',', $field);
        foreach ($segments as $segment) {
            $segment = trim($segment);
            $segment = $this->replaceAliases($segment, $index);

            if ($segment === '*') {
                return range($range[0], $range[1]);
            }

            if (str_contains($segment, '/')) {
                [$base, $step] = explode('/', $segment);
                $step = (int) $step;
                if ($step < 1) {
                    throw new InvalidArgumentException("步长必须大于 0: {$segment}");
                }
                $start = $base === '*' ? $range[0] : (int) $base;
                $end   = $base === '*' ? $range[1] : $range[1];
                if (str_contains($base, '-')) {
                    [$start, $end] = array_map('intval', explode('-', $base));
                }
                for ($i = $start; $i <= $end; $i += $step) {
                    $values[] = $i;
                }
            } elseif (str_contains($segment, '-')) {
                [$start, $end] = array_map('intval', explode('-', $segment));
                $values = array_merge($values, range($start, $end));
            } else {
                $values[] = (int) $segment;
            }
        }

        $values = array_unique($values);
        sort($values);

        // 验证范围
        foreach ($values as $value) {
            if ($value < $range[0] || $value > $range[1]) {
                throw new InvalidArgumentException("字段 {$index} 的值 {$value} 超出范围 [{$range[0]}, {$range[1]}]");
            }
        }

        return $values;
    }

    /**
     * 替换月份和星期的别名
     */
    private function replaceAliases(string $segment, int $index): string
    {
        $lower = strtolower($segment);
        if ($index === 3 && isset(self::$monthAliases[$lower])) {
            return (string) self::$monthAliases[$lower];
        }
        if ($index === 4 && isset(self::$weekAliases[$lower])) {
            return (string) self::$weekAliases[$lower];
        }
        return $segment;
    }
}

<?php

declare(strict_types=1);

namespace zxf\Utils\Date;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

/**
 * 时间工具类
 *
 * 提供常用的时间日期处理功能，兼容 PHP 8.2+
 */
class DateTools
{
    private static ?string $defaultTimezone = null;

    /**
     * 设置默认时区
     */
    public static function setTimezone(string $timezone): void
    {
        self::$defaultTimezone = $timezone;
        date_default_timezone_set($timezone);
    }

    /**
     * 获取当前时区
     */
    public static function getTimezone(): string
    {
        return self::$defaultTimezone ?: date_default_timezone_get();
    }

    /**
     * 得到某天凌晨零点的时间戳
     */
    public static function getSomeZeroTimeStamp(string $str = 'today'): int
    {
        return match ($str) {
            'today' => strtotime(date('Y-m-d')),
            'yesterday' => strtotime(date('Y-m-d')) - 86400,
            'tomorrow' => strtotime(date('Y-m-d')) + 86400,
            'month_first' => strtotime(date('Y-m')),
            'year_first' => strtotime(date('Y-01')),
            default => strtotime(date('Y-m-d', strtotime($str))),
        };
    }

    /**
     * 友好时间显示
     *
     * @param string|int $time 时间戳或时间字符串
     * @param string $lang 语言, cn 中文, en 英文
     */
    public static function get_friend_date(int|string $time, string $lang = 'cn'): string
    {
        if (empty($time)) {
            return '';
        }

        $timestamp = is_numeric($time) ? (int) $time : (int) strtotime((string) $time);
        if ($timestamp === 0) {
            return '';
        }

        $currentTime = time();
        $diff = $currentTime - $timestamp;

        $langConfig = [
            'cn' => [
                'just_now' => '刚刚',
                'seconds_ago' => '%d秒前',
                'minutes_ago' => '%d分钟前',
                'hours_ago' => '%d小时前',
                'yesterday' => '昨天%s',
                'day_before_yesterday' => '前天%s',
                'tomorrow' => '明天%s',
                'day_after_tomorrow' => '后天%s',
                'month_day' => 'm月d日',
                'month_day_time' => 'm月d日 H:i',
                'year_month_day' => 'Y年m月d日',
            ],
            'en' => [
                'just_now' => 'just now',
                'seconds_ago' => '%d seconds ago',
                'minutes_ago' => '%d minutes ago',
                'hours_ago' => '%d hours ago',
                'yesterday' => 'yesterday%s',
                'day_before_yesterday' => 'the day before yesterday%s',
                'tomorrow' => 'tomorrow%s',
                'day_after_tomorrow' => 'the day after tomorrow%s',
                'month_day' => 'm-d',
                'month_day_time' => 'm-d H:i',
                'year_month_day' => 'Y-m-d',
            ]
        ];

        $config = $langConfig[$lang] ?? $langConfig['cn'];
        $timeFormat = 'H:i';

        if ($diff < 0) {
            $daysDiff = (int) floor($diff / 86400);

            return match (true) {
                $daysDiff === -2 => sprintf($config['day_after_tomorrow'], date($timeFormat, $timestamp)),
                $daysDiff === -1 => sprintf($config['tomorrow'], date($timeFormat, $timestamp)),
                default => date($config['year_month_day'], $timestamp),
            };
        }

        if ($diff <= 10) {
            return $config['just_now'];
        }

        if ($diff < 60) {
            return sprintf($config['seconds_ago'], $diff);
        }

        if ($diff < 3600) {
            return sprintf($config['minutes_ago'], (int) floor($diff / 60));
        }

        $todayStart = strtotime('today');
        $yesterdayStart = strtotime('yesterday');
        $dayBeforeYesterdayStart = strtotime('-2 days');

        if ($timestamp >= $todayStart) {
            return sprintf($config['hours_ago'], (int) floor($diff / 3600));
        }

        if ($timestamp >= $yesterdayStart) {
            return sprintf($config['yesterday'], date($timeFormat, $timestamp));
        }

        if ($timestamp >= $dayBeforeYesterdayStart) {
            return sprintf($config['day_before_yesterday'], date($timeFormat, $timestamp));
        }

        if (date('Y', $timestamp) === date('Y')) {
            $showTime = (time() - $timestamp) < 30 * 86400;
            return $showTime ? date($config['month_day_time'], $timestamp) : date($config['month_day'], $timestamp);
        }

        return date($config['year_month_day'], $timestamp);
    }

    /**
     * 获取当前时间的前 n 天日期列表
     */
    public static function getLastDays(int $day = 7): array
    {
        $dayStr = $day > 0 ? '-' . ($day - 1) . ' days' : '+' . $day . ' days';
        $begin = strtotime(date('Y-m-d', strtotime($dayStr)));
        $now = time();
        $daySecond = 86400;

        $result = ['date' => [], 'weeks' => [], 'day' => []];
        $weekMap = ['日', '一', '二', '三', '四', '五', '六'];

        for ($i = $begin; $i <= $now; $i += $daySecond) {
            $result['date'][] = $i;
            $result['weeks'][] = '星期' . $weekMap[(int) date('w', $i)];
            $result['day'][] = date('Y-m-d', $i);
        }

        return $result;
    }

    /**
     * 获取星期几的信息
     */
    public static function getWeekDay(int|string $time, string $lang = 'cn'): string
    {
        $timestamp = is_numeric($time) ? (int) $time : (int) strtotime((string) $time);

        if ($lang === 'cn') {
            $weekArray = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
            return $weekArray[(int) date('w', $timestamp)];
        }

        return date('l', $timestamp);
    }

    /**
     * 获取月份
     */
    public static function getMonth(int|string $time, string $lang = 'cn'): string
    {
        $timestamp = is_numeric($time) ? (int) $time : (int) strtotime((string) $time);
        $month = (int) date('n', $timestamp);

        return match ($lang) {
            'cn' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'][$month - 1],
            default => ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'May.', 'Jun.', 'Jul.', 'Aug.', 'Sept.', 'Oct.', 'Nov.', 'Dec.'][$month - 1],
        };
    }

    /**
     * 判断一个值是否为有效时间戳
     */
    public static function is_timestamp(int|string $timestamp): bool
    {
        $ts = (int) $timestamp;
        return strtotime(date('m-d-Y H:i:s', $ts)) === $ts;
    }

    /**
     * 获取两个日期之间的所有日期
     */
    public static function between(string $startDate, string $endDate, string $format = 'Y-m-d'): array
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $result = [];

        while ($start <= $end) {
            $result[] = $start->format($format);
            $start->modify('+1 day');
        }

        return $result;
    }

    /**
     * 计算两个时间之间的差值（人性化显示）
     */
    public static function diffForHumans(int|string $from, int|string $to, string $lang = 'cn'): string
    {
        $fromTs = is_numeric($from) ? (int) $from : (int) strtotime((string) $from);
        $toTs = is_numeric($to) ? (int) $to : (int) strtotime((string) $to);
        $diff = abs($toTs - $fromTs);

        $units = match ($lang) {
            'cn' => [
                ['秒', 60],
                ['分钟', 3600],
                ['小时', 86400],
                ['天', 2592000],
                ['个月', 31536000],
                ['年', PHP_INT_MAX],
            ],
            default => [
                ['seconds', 60],
                ['minutes', 3600],
                ['hours', 86400],
                ['days', 2592000],
                ['months', 31536000],
                ['years', PHP_INT_MAX],
            ],
        };

        foreach ($units as [$label, $threshold]) {
            if ($diff < $threshold) {
                $value = match ($label) {
                    '秒', 'seconds' => $diff,
                    '分钟', 'minutes' => (int) floor($diff / 60),
                    '小时', 'hours' => (int) floor($diff / 3600),
                    '天', 'days' => (int) floor($diff / 86400),
                    '个月', 'months' => (int) floor($diff / 2592000),
                    default => (int) floor($diff / 31536000),
                };
                return $lang === 'cn' ? $value . $label : $value . ' ' . $label;
            }
        }

        return '';
    }

    /**
     * 判断是否为工作日（周一至周五）
     */
    public static function isWorkDay(int|string|null $time = null): bool
    {
        $timestamp = $time === null ? time() : (is_numeric($time) ? (int) $time : (int) strtotime((string) $time));
        $dayOfWeek = (int) date('N', $timestamp);
        return $dayOfWeek >= 1 && $dayOfWeek <= 5;
    }

    /**
     * 判断是否为周末
     */
    public static function isWeekend(int|string|null $time = null): bool
    {
        return ! self::isWorkDay($time);
    }

    /**
     * 获取年龄
     */
    public static function age(string $birthday): int
    {
        $birth = new DateTime($birthday);
        $now = new DateTime();
        return (int) $birth->diff($now)->format('%y');
    }

    /**
     * 获取星座
     */
    public static function zodiac(string $birthday): string
    {
        $date = new DateTime($birthday);
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');

        $zodiacs = [
            [1, 20, '水瓶座'], [2, 19, '双鱼座'], [3, 21, '白羊座'],
            [4, 20, '金牛座'], [5, 21, '双子座'], [6, 22, '巨蟹座'],
            [7, 23, '狮子座'], [8, 23, '处女座'], [9, 23, '天秤座'],
            [10, 24, '天蝎座'], [11, 23, '射手座'], [12, 22, '摩羯座'],
        ];

        foreach ($zodiacs as [$zMonth, $zDay, $name]) {
            if ($month === $zMonth && $day < $zDay) {
                continue;
            }
            if ($month < $zMonth) {
                continue;
            }
            return $name;
        }

        return '摩羯座';
    }

    /**
     * 获取生肖
     */
    public static function chineseZodiac(string $year): string
    {
        $animals = ['猴', '鸡', '狗', '猪', '鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊'];
        return $animals[(int) $year % 12];
    }

    /**
     * 将秒数格式化为时分秒
     */
    public static function formatDuration(int $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * 转换为 ISO 8601 格式
     */
    public static function toIso8601(int|string|null $time = null): string
    {
        $timestamp = $time === null ? time() : (is_numeric($time) ? (int) $time : (int) strtotime((string) $time));
        return (new DateTimeImmutable('@' . $timestamp))->format(DateTimeInterface::ATOM);
    }

    /**
     * 解析 ISO 8601 格式为时间戳
     */
    public static function fromIso8601(string $iso): int|false
    {
        try {
            return (new DateTimeImmutable($iso))->getTimestamp();
        } catch (Exception) {
            return false;
        }
    }
}

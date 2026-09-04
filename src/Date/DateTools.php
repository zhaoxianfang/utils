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
            // 修复：旧实现按 floor($diff / 86400) 判断未来天数，未来 5 小时会算成
            // floor(-18000/86400) === -1 而误显示「明天」；改按自然日边界判断。
            $targetDay = date('Y-m-d', $timestamp);
            $today     = date('Y-m-d');

            if ($targetDay === $today) {
                return date('H:i', $timestamp); // 今天稍后的未来时间
            }
            if ($targetDay === date('Y-m-d', strtotime('+1 day'))) {
                return sprintf($config['tomorrow'], date($timeFormat, $timestamp));
            }
            if ($targetDay === date('Y-m-d', strtotime('+2 days'))) {
                return sprintf($config['day_after_tomorrow'], date($timeFormat, $timestamp));
            }

            return date($config['year_month_day'], $timestamp);
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
     * 获取当前时间的前 n 天日期列表（含今天，共 $day 项）
     *
     * 修复：旧实现对 $day <= 0 生成 "+-2 days" 之类的非法表达式（strtotime 返回
     * false → 1970 年），且用固定 86400 秒累加在夏令时切换日会漂移。现改为：
     * - $day <= 0 抛 InvalidArgumentException（语义上“最近 N 天”的 N 必须为正）；
     * - 用 DateTime::modify('+1 day') 逐日推进，天然处理 DST。
     */
    public static function getLastDays(int $day = 7): array
    {
        if ($day <= 0) {
            throw new \InvalidArgumentException('天数必须大于 0');
        }

        $result = ['date' => [], 'weeks' => [], 'day' => []];
        $weekMap = ['日', '一', '二', '三', '四', '五', '六'];

        $cursor = new DateTime('today');
        $cursor->modify('-' . ($day - 1) . ' days');

        for ($i = 0; $i < $day; $i++) {
            $timestamp = $cursor->getTimestamp();
            $result['date'][] = $timestamp;
            $result['weeks'][] = '星期' . $weekMap[(int) $cursor->format('w')];
            $result['day'][] = $cursor->format('Y-m-d');
            $cursor->modify('+1 day');
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
     *
     * 修复：旧实现用 (int) 强转后做 date/strtotime 回环校验，
     * 对 "abc" 这类非数字字符串 (int) 得 0，回环后恒为 true（假阳性）。
     * 现先要求「纯十进制数字串 / 整数」，再做回环校验。
     */
    public static function is_timestamp(int|string $timestamp): bool
    {
        if (is_string($timestamp) && ! preg_match('/^\d{1,12}$/', trim($timestamp))) {
            return false;
        }

        $ts = (int) $timestamp;

        // 时间戳必须在合理区间（大于 0，且未超出 date() 的可表达范围）
        if ($ts <= 0) {
            return false;
        }

        // 修复：旧实现用 date('m-d-Y H:i:s') 回环，但 strtotime 对 "-" 分隔按 d-m-Y 解析
        // （如 04-08-2024 → 8 月 4 日），对多数日期（月≠日 且 月≤12）都会误判为 false；
        // 统一使用无歧义的 'Y-m-d H:i:s' 格式回环。
        return strtotime(date('Y-m-d H:i:s', $ts)) === $ts;
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
     *
     * 修复：旧算法遍历起点表时条件错乱（对月份大于 2 的一切日期都提前返回首个
     * 「水瓶座」），自 2/20 起的所有日期返回结果均错误。现改为标准的
     * 「起始日(月*100+日) 顺序表」定位：日期编号 >= 该星座起始日即落入该星座，
     * 1/1~1/19（小于水瓶座起始日 120）回退摩羯座。
     */
    public static function zodiac(string $birthday): string
    {
        $date = new DateTime($birthday);
        $num = (int) $date->format('n') * 100 + (int) $date->format('j');

        // 各星座起始日（月*100+日）
        $starts = [
            120 => '水瓶座', 219 => '双鱼座', 321 => '白羊座', 420 => '金牛座',
            521 => '双子座', 622 => '巨蟹座', 723 => '狮子座', 823 => '处女座',
            923 => '天秤座', 1024 => '天蝎座', 1123 => '射手座', 1222 => '摩羯座',
        ];

        $result = '摩羯座';
        foreach ($starts as $start => $name) {
            if ($num >= $start) {
                $result = $name;
            } else {
                break;
            }
        }

        return $result;
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
        } catch (Exception $e) {
            return false;
        }
    }
}

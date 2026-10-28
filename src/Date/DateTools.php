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
     *
     * 同时写入类内部的默认时区缓存并调用 date_default_timezone_set() 修改
     * PHP 进程级默认时区，因此会影响后续所有依赖默认时区的时间函数。
     * 时区名需为合法的时区标识（如 "Asia/Shanghai"）。
     *
     * @param string $timezone 时区标识，如 "Asia/Shanghai"、"UTC"
     * @return void
     */
    public static function setTimezone(string $timezone): void
    {
        self::$defaultTimezone = $timezone;
        date_default_timezone_set($timezone);
    }

    /**
     * 获取当前时区
     *
     * 优先返回 setTimezone() 设置过的时区；从未设置时回退到 PHP 的当前默认时区
     * （date_default_timezone_get()），不会返回空字符串。
     *
     * @return string 时区标识，如 "Asia/Shanghai"
     */
    public static function getTimezone(): string
    {
        return self::$defaultTimezone ?: date_default_timezone_get();
    }

    /**
     * 得到某天凌晨零点的时间戳
     *
     * 支持四个固定别名：today（今天）、yesterday（昨天）、tomorrow（明天）、
     * month_first（本月 1 日）、year_first（本年 1 月 1 日）。
     * 传入其他字符串时先交给 strtotime 解析成日期，再取该日零点；
     * 因此 "2024-05-01"、"+3 days" 等相对/绝对日期写法均可用。
     * 解析失败（strtotime 返回 false）会退化为 1970-01-01 零点所在时区的时间戳。
     *
     * @param string $str 日期关键字或可被 strtotime 解析的日期字符串
     * @return int 目标日期零点的 Unix 时间戳
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
     * 以「当前时间」为参照把时间戳转成口语化描述：
     * - 过去：10 秒内「刚刚」→ 分钟前 → 小时前 → 昨天/前天（带时刻）
     *   → 今年内按「m月d日」显示（30 天内附带时刻）→ 跨年显示完整年月日；
     * - 未来：今天稍后仅显示时刻，明天/后天带前缀，更远显示完整年月日。
     *
     * 空值或无法解析为有效时间戳（结果为 0）时返回空字符串；
     * $lang 不是 'cn' 时统一按英文文案输出。
     *
     * @param string|int $time 时间戳或可被 strtotime 解析的时间字符串
     * @param string     $lang 语言，'cn' 中文（默认），其他值按英文输出
     * @return string 友好时间文案，输入无效时为空字符串
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
     * 返回三个平行索引数组，下标一一对应：
     * - date：每天零点的 Unix 时间戳；
     * - weeks：中文星期，如 "星期一"；
     * - day：Y-m-d 格式日期字符串。
     *
     * 结果按时间升序排列，最后一项为今天。逐日推进使用 DateTime::modify，
     * 夏令时切换日不会出现时间漂移。
     *
     * 修复：旧实现对 $day <= 0 生成 "+-2 days" 之类的非法表达式（strtotime 返回
     * false → 1970 年），且用固定 86400 秒累加在夏令时切换日会漂移。现改为：
     * - $day <= 0 抛 InvalidArgumentException（语义上“最近 N 天”的 N 必须为正）；
     * - 用 DateTime::modify('+1 day') 逐日推进，天然处理 DST。
     *
     * @param int $day 天数，必须大于 0
     * @return array{date: array<int, int>, weeks: array<int, string>, day: array<int, string>} 三个平行数组
     *
     * @throws \InvalidArgumentException 天数小于等于 0 时抛出
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
     *
     * $lang 为 'cn' 时返回「星期日」到「星期六」的中文；其他值返回英文全称
     * （如 "Monday"），英文结果受 PHP 默认 locale 影响。
     * 传入非数字字符串时按 strtotime 解析，解析失败会退化为 1970 年。
     *
     * @param int|string $time 时间戳或可被 strtotime 解析的时间字符串
     * @param string     $lang 语言，'cn' 中文（默认），其他值输出英文
     * @return string 星期名称
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
     *
     * $lang 为 'cn' 时返回「1月」…「12月」；其他值返回英文缩写（含 Sept. 这类
     * 非标准缩写形式）。月份取自传入时间所在时区，与年份无关。
     *
     * @param int|string $time 时间戳或可被 strtotime 解析的时间字符串
     * @param string     $lang 语言，'cn' 中文（默认），其他值输出英文缩写
     * @return string 月份名称，如 "5月"、"May."
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
     * 字符串入参先要求是 1~12 位纯十进制数字（去空白后），否则直接判为 false；
     * 随后要求数值大于 0，并用 'Y-m-d H:i:s' 格式做「格式化 → 解析」回环校验，
     * 以排除超出 date() 可表达范围的数值。因此 "abc"、"-1"、"999999999999999"
     * 都会被判为无效。
     *
     * 修复：旧实现用 (int) 强转后做 date/strtotime 回环校验，
     * 对 "abc" 这类非数字字符串 (int) 得 0，回环后恒为 true（假阳性）。
     * 现先要求「纯十进制数字串 / 整数」，再做回环校验。
     *
     * @param int|string $timestamp 待校验的时间戳
     * @return bool 是有效时间戳返回 true，否则返回 false
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
     *
     * 含首尾两端，按天递增，逐项用 $format 格式化。开始日期晚于结束日期时
     * 循环不执行，返回空数组；两参数均按 DateTime 构造函数规则解析
     * （支持 "2024-01-01"、"tomorrow" 等写法）。
     *
     * @param string $startDate 起始日期字符串
     * @param string $endDate   结束日期字符串
     * @param string $format    输出格式，需符合 date() 格式规则
     * @return array<int, string> 日期字符串列表，升序排列
     *
     * @throws Exception 日期字符串无法被 DateTime 解析时抛出
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
     *
     * 取两时刻差的绝对值，按「秒 → 分钟 → 小时 → 天 → 个月 → 年」逐级
     * 找到第一个小于其上限的单位输出；其中「天」上限按 30 天、「个月」上限按
     * 365 天换算，因此月度/年度换算为近似值。两个时刻的先后顺序不影响结果。
     * 中文文案直接拼接数字与单位（如 "3小时"），英文会插入空格（如 "3 hours"）。
     *
     * @param int|string $from 起始时间戳或时间字符串
     * @param int|string $to   结束时间戳或时间字符串
     * @param string     $lang 语言，'cn' 中文（默认），其他值输出英文
     * @return string 人性化差值文案
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
     *
     * 仅按星期的 ISO 序号判断，不识别法定节假日与调休；$time 为 null 时以
     * 当前时间为准，非数字字符串按 strtotime 解析（解析失败会退化为 1970 年）。
     *
     * @param int|string|null $time 时间戳或时间字符串，null 表示当前时间
     * @return bool 周一至周五返回 true，周六周日返回 false
     */
    public static function isWorkDay(int|string|null $time = null): bool
    {
        $timestamp = $time === null ? time() : (is_numeric($time) ? (int) $time : (int) strtotime((string) $time));
        $dayOfWeek = (int) date('N', $timestamp);
        return $dayOfWeek >= 1 && $dayOfWeek <= 5;
    }

    /**
     * 判断是否为周末
     *
     * 为 isWorkDay() 取反，因此周六、周日返回 true；同样不识别法定节假日
     * 与调休。$time 为 null 时以当前时间为准。
     *
     * @param int|string|null $time 时间戳或时间字符串，null 表示当前时间
     * @return bool 周六或周日返回 true，工作日返回 false
     */
    public static function isWeekend(int|string|null $time = null): bool
    {
        return ! self::isWorkDay($time);
    }

    /**
     * 获取年龄
     *
     * 以「今天」为基准计算整年数，已过生日的今年加 1 岁、未过生日则不计，
     * 结果只保留整年部分。生日晚于今天（未来日期）时返回负数，用于区分
     * 「尚未出生」与「刚出生」，其绝对值为距今年数。
     *
     * @param string $birthday 出生日期字符串，需可被 DateTime 解析
     * @return int 整岁年龄；未来日期返回负数
     *
     * @throws Exception 生日字符串无法解析时抛出
     */
    public static function age(string $birthday): int
    {
        $birth = new DateTime($birthday);
        $now = new DateTime();
        $years = (int) $birth->diff($now)->format('%y');

        // diff() 的 %y 不带符号，未来日期会与过去日期得到相同数值，这里补回方向
        return $birth > $now ? -$years : $years;
    }

    /**
     * 获取星座
     *
     * 把日期折算为「月*100+日」的编号，与各星座起始日顺序比对，取最后一个
     * 起始日不大于该编号的星座；1 月 1 日至 1 月 19 日早于水瓶座起始日
     * （1/20），按摩羯座处理。12/22 起为摩羯座。
     *
     * 修复：旧算法遍历起点表时条件错乱（对月份大于 2 的一切日期都提前返回首个
     * 「水瓶座」），自 2/20 起的所有日期返回结果均错误。现改为标准的
     * 「起始日(月*100+日) 顺序表」定位：日期编号 >= 该星座起始日即落入该星座，
     * 1/1~1/19（小于水瓶座起始日 120）回退摩羯座。
     *
     * @param string $birthday 出生日期字符串，需可被 DateTime 解析
     * @return string 中文星座名，如 "狮子座"
     *
     * @throws Exception 日期字符串无法解析时抛出
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
     *
     * 直接以「年份 % 12」查表，0 对应猴年（如 2016）、1 对应鸡年，依此类推。
     * 只做取模映射，不做农历/立春换算，跨春节的年份需调用方自行校正；
     * 负数年份会先归一化到 0~11，不会产生非法下标。
     *
     * @param string $year 年份，非纯数字字符串会被截断为整数（如 "2024年" → 2024）
     * @return string 中文生肖，如 "龙"
     */
    public static function chineseZodiac(string $year): string
    {
        $animals = ['猴', '鸡', '狗', '猪', '鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊'];
        $index = (((int) $year % 12) + 12) % 12;

        return $animals[$index];
    }

    /**
     * 将秒数格式化为时分秒
     *
     * 时长达到 1 小时输出 "HH:MM:SS"，不足 1 小时输出 "MM:SS"，各段均补零到
     * 两位；小时数不设上限（如 100 小时输出 "100:00:00"）。负数秒数会得到
     * 带负号的分秒片段，调用方应自行保证入参非负。
     *
     * @param int $seconds 总秒数
     * @return string 形如 "01:02:03" 或 "02:03" 的时长字符串
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
     *
     * 输出形如 "2024-05-01T12:30:00+00:00" 的字符串。由于内部以 "@时间戳"
     * 构造 DateTimeImmutable，结果固定为 UTC（时区偏移恒为 +00:00），
     * 不会转换成本地时区，也不会受 setTimezone() 影响。
     *
     * @param int|string|null $time 时间戳或时间字符串，null 表示当前时间
     * @return string ISO 8601（ATOM）格式的 UTC 时间字符串
     */
    public static function toIso8601(int|string|null $time = null): string
    {
        $timestamp = $time === null ? time() : (is_numeric($time) ? (int) $time : (int) strtotime((string) $time));
        return (new DateTimeImmutable('@' . $timestamp))->format(DateTimeInterface::ATOM);
    }

    /**
     * 解析 ISO 8601 格式为时间戳
     *
     * 交给 DateTimeImmutable 解析，除标准 ISO 8601 外也接受其他可解析格式；
     * 解析失败（含语法错误、非法日期）时捕获异常并返回 false，不向外抛出。
     *
     * @param string $iso ISO 8601 时间字符串，如 "2024-05-01T12:30:00+08:00"
     * @return int|false 解析成功返回 Unix 时间戳，失败返回 false
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

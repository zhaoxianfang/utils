<?php

namespace zxf\Utils\Date;

// 设置中国时间为默认时区
date_default_timezone_set('PRC');

/**
 * 时间工具类
 * Class DateTools
 */
class DateTools
{
    /**
     * @desc 得到某天凌晨零点的时间戳
     *
     * @return int
     */
    public static function getSomeZeroTimeStamp(string $str = 'today')
    {
        return match ($str) {
            'today' => strtotime(date('Y-m-d'), time()),
            'yesterday' => strtotime(date('Y-m-d'), time()) - 3600 * 24,
            'tomorrow' => strtotime(date('Y-m-d'), time()) + 3600 * 24,
            'month_first' => strtotime(date('Y-m'), time()),
            'year_first' => strtotime(date('Y-01'), time()),
            default => strtotime(date('Y-m-d'), strtotime($str)),
        };
    }

    /**
     * @desc 友好时间显示
     *
     * @param string|int $time 时间戳或时间字符串
     * @param string $lang 语言, cn 中文, en 英文
     * @return string
     */
    public static function get_friend_date($time, string $lang = 'cn'): string
    {
        if (empty($time)) {
            return '';
        }

        // 转换为时间戳
        $timestamp = is_numeric($time) ? (int)$time : strtotime($time);
        if ($timestamp === false) {
            return '';
        }

        $currentTime = time();
        $diff = $currentTime - $timestamp;

        // 语言配置
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

        // 处理未来时间
        if ($diff < 0) {
            $daysDiff = floor($diff / (60 * 60 * 24));

            switch ($daysDiff) {
                case -2:
                    return sprintf($config['day_after_tomorrow'], date($timeFormat, $timestamp));
                case -1:
                    return sprintf($config['tomorrow'], date($timeFormat, $timestamp));
                default:
                    return date($config['year_month_day'], $timestamp);
            }
        }

        // 处理过去时间 - 刚刚
        if ($diff <= 10) {
            return $config['just_now'];
        }

        // 秒
        if ($diff < 60) {
            return sprintf($config['seconds_ago'], $diff);
        }

        // 分钟
        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return sprintf($config['minutes_ago'], $minutes);
        }

        $todayStart = strtotime('today');
        $yesterdayStart = strtotime('yesterday');
        $dayBeforeYesterdayStart = strtotime('-2 days');

        // 今天内的小时
        if ($timestamp >= $todayStart) {
            $hours = floor($diff / 3600);
            return sprintf($config['hours_ago'], $hours);
        }

        // 昨天
        if ($timestamp >= $yesterdayStart) {
            return sprintf($config['yesterday'], date($timeFormat, $timestamp));
        }

        // 前天
        if ($timestamp >= $dayBeforeYesterdayStart) {
            return sprintf($config['day_before_yesterday'], date($timeFormat, $timestamp));
        }

        // 今年内的日期
        if (date('Y', $timestamp) === date('Y')) {
            // 一个月内显示详细时间，否则只显示日期
            $showTime = (time() - $timestamp) < 30 * 24 * 60 * 60;
            return $showTime ?
                date($config['month_day_time'], $timestamp) :
                date($config['month_day'], $timestamp);
        }

        // 往年日期
        return date($config['year_month_day'], $timestamp);
    }

    /**
     * @desc 获取当前时间的前n天
     *
     * @return array
     */
    public static function getLastDays(int $day = 7)
    {
        $dayStr = $day > 0 ? '-'.($day - 1).'days' : '+'.($day).'days';
        $begin = strtotime(date('Y-m-d', strtotime($dayStr)));  // n天前
        $today_time = strtotime(date('Y-m-d'));  // 今天
        $now_time = time();
        $weeks_arr = [];
        $weeks_arr['date'] = [];
        $weeks_arr['weeks'] = [];
        $weeks_arr['day'] = [];
        $weeks_array = ['日', '一', '二', '三', '四', '五', '六']; // 先定义一个数组
        $day_second = 3600 * 24;
        for ($i = $begin; $i < $now_time; $i = $i + $day_second) {
            if ($i != $today_time) {
                $weeks_arr['date'][] = $i;
            } else {
                $weeks_arr['date'][] = $now_time;
            }
            $weeks_arr['weeks'][] = '星期'.$weeks_array[date('w', $i)];
            $weeks_arr['day'][] = date('Y-m-d', $i);
        }

        return $weeks_arr;

    }

    /**
     * @desc 获取星期几的信息
     *
     * @param  string  $time  时间
     * @param  string  $lang  语言, cn 中文, en 英文
     * @return string
     */
    public static function getWeekDay(string $time, $lang = 'cn')
    {
        $time = is_numeric($time) ? $time : strtotime($time);
        if ($lang == 'cn') {
            $week_array = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];

            return $week_array[date('w', $time)];
        } else {
            return date('l', $time); // date("l") 可以获取英文的星期比如Sunday
        }
    }

    /**
     * @desc 获取月份
     *
     * @param  string  $time  时间
     * @param  string  $lang  cn 中文, en 英语
     * @return string
     */
    public static function getMonth($time, $lang = 'cn')
    {
        $timestamp = is_numeric($time) ? $time : strtotime($time);
        if ($lang == 'cn') {
            $month_arr = [
                '1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月',
            ];
        } else {
            $month_arr = [
                'Jan.', 'Feb.', 'Mar.', 'Apr.', 'May.', 'Jun.', 'Jul.', 'Aug.', 'Sept.', 'Oct.', 'Nov.', 'Dec.',
            ];
        }
        $month = date('n', $timestamp);

        return $month_arr[$month - 1];
    }

    /**
     * @desc  判断一个字符串是否为时间戳
     *
     * @param int|string $timestamp  时间戳
     * @return bool|int
     */
    public static function is_timestamp(int|string $timestamp)
    {
        $timestamp = intval($timestamp);
        if (strtotime(date('m-d-Y H:i:s', $timestamp)) === $timestamp) {
            return $timestamp;
        } else {
            return false;
        }
    }
}

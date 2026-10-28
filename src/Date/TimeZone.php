<?php
declare(strict_types=1);

namespace zxf\Utils\Date;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * 时区转换器
 *      1. toTimeZone: 计算 A 时区的在某个时间时 B 时区的当地时间（返回指定时间格式字符串）「支持使用数组方式同时处理多个时间」
 *      2. toTimeZoneTimestamp: 计算 A 时区的在某个时间时 B 时区的当地时间（返回时间戳）「支持使用数组方式同时处理多个时间」
 *      3. timeZoneDiff: 计算 A 时区和 B 时区的时差
 *      4. getTimeZoneOfUTC: 计算 A 时区和 UTC 时区(0时区)的时差
 *      5. setTimeZone: 计算默认时区
 *      6. format: 格式化时间「支持使用数组方式同时处理多个时间」
 *
 * eg:
 *      $converter = TimeZone::instance();
 *      // 1、当上海时间是 2024-04-08 12:00:00 时，计算出纽约的当地时间（返回指定时间格式字符串）
 *      $converter->toTimeZone('2024-04-08 12:00:00','Asia/Shanghai', 'America/New_York','Y-m-d H:i:s');
 *      $converter->toTimeZone(['2024-04-08 12:00:00','1712632337'],'Asia/Shanghai', 'America/New_York','Y-m-d H:i:s');
 *      // 2、当上海时间是 2024-04-08 12:00:00 时，计算出纽约的当地时间（返回时间戳）
 *      $converter->toTimeZoneTimestamp('2024-04-08 12:00:00','Asia/Shanghai', 'America/New_York');
 *      // 3、计算上海和纽约的时差
 *      $converter->timeZoneDiff('Asia/Shanghai', 'America/New_York',$useAbs = true);
 *      // 4、计算上海和UTC 时区(0时区)的时差
 *      $converter->getTimeZoneOfUTC('Asia/Shanghai');
 *      // 5、设置默认时区
 *      TimeZoneConverter::setTimeZone('PRC');
 *      // 6、格式化时间
 *      $converter->format('1712632337', 'Y-m-d H:i:s');
 */
class TimeZone
{
    /** @var array<class-string, static> 按调用类缓存实例 */
    private static array $instances = [];

    /**
     * 初始化实例
     *
     * 按「实际调用类」分别缓存：旧实现用 self::$instance 单例，
     * 子类继承后首次由父类创建会把父类实例误当作子类返回（static 返回类型 → TypeError）。
     *
     * 同一进程内每个调用类只创建一个实例，配置（如 addTimeZoneDifferences()
     * 添加的差值）在该类的实例间共享。
     *
     * @return static 当前调用类对应的单例实例
     */
    public static function instance(): static
    {
        $class = static::class;

        return self::$instances[$class] ??= new static();
    }

    /**
     * 添加时区差值对照数据
     *
     * @param  array  $timeZoneDifferences  时区差值 eg:例如纽约使用西五区时区 ['America/New_York'=> -5]
     * @return $this
     */
    public function addTimeZoneDifferences(array $timeZoneDifferences): self
    {
        $this->timeZoneDifferences = array_merge($this->timeZoneDifferences, $timeZoneDifferences);

        return $this;
    }

    /**
     * 计算 $fromTimeZone 时区的 $datetime 时间在 $toTimeZone 时区的当地时间,并返回指定时间字符串格式
     *
     * @param  string|int|array  $datetime  $fromTimeZone时区 时间字符串或者时间戳,默认为当前时间now; 支持使用数组方式同时处理多个时间
     *                                      eg: '2024-04-08 12:00:00'、'1712632337'、['2024-04-08 12:00:00','1712632337']
     * @param  string  $fromTimeZone  $datetime的时区 eg: 'Asia/Shanghai'
     * @param  string  $toTimeZone  目标时区 eg: 'America/New_York'
     * @param  string  $format  返回时间格式 eg: 'Y-m-d H:i:s'
     * @return string|array 单个时间返回格式化字符串；数组入参返回「键名 => 格式化字符串」的数组
     *
     * @throws Exception
     */
    public function toTimeZone(string|int|array $datetime, string $fromTimeZone, string $toTimeZone, string $format = 'Y-m-d H:i:s'): string|array
    {
        if (is_array($datetime)) {
            $result = [];
            foreach ($datetime as $key => $item) {
                $result[$key] = $this->toTimeZone($item, $fromTimeZone, $toTimeZone, $format);
            }

            return $result;
        }
        $dateTimeObj = $this->convertToTimeZoneDateTime($datetime, $fromTimeZone, $toTimeZone);

        // 返回目标时区的时间
        return $dateTimeObj->format($format ?? 'Y-m-d H:i:s');
    }

    /**
     * 计算 $fromTimeZone 时区的 $datetime 时间在 $toTimeZone 时区的当地时间的 时间戳
     *
     * 时间戳是绝对时刻，两种时区之间转换不会改变其值：本方法主要用于把
     * 「源时区的本地时间字符串」按其字面值解析成对应时间戳，再换算出目标时区视角。
     * 传入数组时逐项递归处理并保留原键名。
     *
     * @param  string|int|array  $datetime  $fromTimeZone时区 时间字符串或者时间戳,默认为当前时间now; 支持使用数组方式同时处理多个时间
     *                                      eg: '2024-04-08 12:00:00'、'1712632337'、['2024-04-08 12:00:00','1712632337']
     * @param  string  $fromTimeZone  $datetime 所属时区 eg: 'Asia/Shanghai'
     * @param  string  $toTimeZone  目标时区 eg: 'America/New_York'
     * @return int|array 单个时间返回 Unix 时间戳；数组入参返回「键名 => 时间戳」的数组
     *
     * @throws Exception
     */
    public function toTimeZoneTimestamp(string|int|array $datetime, string $fromTimeZone, string $toTimeZone): int|array
    {
        if (is_array($datetime)) {
            $result = [];
            foreach ($datetime as $key => $item) {
                $result[$key] = $this->toTimeZoneTimestamp($item, $fromTimeZone, $toTimeZone);
            }

            return $result;
        }
        $dateTimeObj = $this->convertToTimeZoneDateTime($datetime, $fromTimeZone, $toTimeZone);

        // 返回目标时区的时间的时间戳
        return $dateTimeObj->getTimestamp();
    }

    /**
     * 计算 $fromTimeZone 时区的 $datetime 时间在 $toTimeZone 时区的当地时间
     *
     * 空值（null、空串、0 等）按 "now" 处理；数值型入参被视为 Unix 时间戳，
     * 以 UTC 纪元构造后直接切换时区（与 PHP 默认时区无关）；字符串则按
     * 「源时区」解析其字面时间。最终统一转为目标时区返回，期间由 PHP 时区
     * 数据库处理夏令时（DST）偏移。
     *
     * @param string|int $datetime     源时区下的时间字符串或时间戳
     * @param string     $fromTimeZone 源时区标识，如 'Asia/Shanghai'
     * @param string     $toTimeZone   目标时区标识，如 'America/New_York'
     * @return DateTime 已切换到目标时区的 DateTime 对象
     *
     * @throws Exception 时间字符串无法解析或时区标识非法时抛出
     */
    private function convertToTimeZoneDateTime(string|int $datetime, string $fromTimeZone, string $toTimeZone): DateTime
    {
        if (empty($datetime)) {
            // 默认为当前时间
            $datetime = 'now';
        }
        // 如果是时间戳则转换为时间字符串
        if (is_numeric($datetime)) {
            // 修复：旧实现用 date('Y-m-d H:i:s', $ts)（依赖 PHP 默认时区）生成字符串，
            // 再按 $fromTimeZone 解析——当默认时区 ≠ 源时区时整体偏移数小时（同一时刻错位）。
            // 时间戳是绝对时刻，应直接用 @epoch（UTC）构造。
            $dateTimeObj = new DateTime('@' . (int) $datetime);
            $dateTimeObj->setTimezone(new DateTimeZone($fromTimeZone));
        } else {
            // 将时间字符串转换为 DateTime 对象（携带源时区）
            $dateTimeObj = new DateTime($datetime, new DateTimeZone($fromTimeZone));
        }

        // 直接使用 DateTime 的时区转换：PHP 会基于时区数据库自动处理夏令时（DST）切换，
        // 比“固定小时偏移相减”更准确（避免跨夏令时边界时差 1 小时的错误）。
        $dateTimeObj->setTimezone(new DateTimeZone($toTimeZone));

        return $dateTimeObj;
    }

    /**
     * 获取$timeZone时区和0时区的差值(小时)
     *
     * @param  string  $timeZone  时区 eg: 'Asia/Shanghai'
     * @return float|int|mixed
     *
     * @throws Exception
     */
    public function getTimeZoneOfUTC(string $timeZone): mixed
    {
        if (array_key_exists($timeZone, $this->timeZoneDifferences)) {
            $diff = $this->timeZoneDifferences[$timeZone];
        } else {
            // 计算$timeZoneA时区和UTC时区的差值
            // 设置所需的时区
            $timezone1 = new DateTimeZone($timeZone);
            // 获取时区相对于UTC的偏移量
            $offset = $timezone1->getOffset(new DateTime('now', new DateTimeZone('UTC')));
            $diff = $offset / 3600; // 将秒转换为小时
        }

        return $diff;
    }

    /**
     * 获取两个时区的时差(小时)
     *
     * 分别取得两时区相对 UTC 的偏移后相减：优先查内建对照表（表中为「标准时」
     * 偏移，不含夏令时），表中没有的时区则按当前时刻实时计算 UTC 偏移。
     * $useAbs 为 true 时返回绝对值，因此无法区分 A 比 B 早还是晚。
     *
     * @param  string  $timeZoneA  eg: 'Asia/Shanghai'
     * @param  string  $timeZoneB  eg: 'America/New_York'
     * @param  bool  $useAbs  是否使用绝对值计算
     * @return float|int 两时区相差的小时数；$useAbs 为 true 时恒为非负
     *
     * @throws Exception
     */
    public function timeZoneDiff(string $timeZoneA, string $timeZoneB, bool $useAbs = true): float|int
    {
        $diff = $this->getTimeZoneOfUTC($timeZoneA) - $this->getTimeZoneOfUTC($timeZoneB);

        return $useAbs ? abs($diff) : $diff;
    }

    /**
     * 设置默认时区
     *
     * 直接修改 PHP 进程级默认时区，影响所有依赖默认时区的时间函数；
     * 不预先校验时区名，非法标识由 date_default_timezone_set() 产生 Notice 并返回 false。
     *
     * @param  string  $timeZone  时区 eg: 'Asia/Shanghai'
     * @return bool 设置成功返回 true，时区标识非法返回 false
     */
    public static function setTimeZone(string $timeZone = 'PRC'): bool
    {
        // 设置默认时区（参数已带默认值 'PRC'，旧的 ?? 'PRC' 属死代码）
        return date_default_timezone_set($timeZone);
    }

    /**
     * 格式化时间
     *
     * 入参为 DateTimeInterface（含 DateTimeImmutable）时按其自带时区格式化，
     * 时间戳按 PHP 默认时区解释，字符串按 PHP 默认时区解析；null 或空串取当前时间。
     * 数组入参逐项递归处理并保留原键名，元素可为上述任意类型。
     *
     * @param  string|int|DateTime|array  $datetime  时间字符串或者时间戳,默认为当前时间now; 支持使用数组方式同时处理多个时间 eg: '2024-04-08 12:00:00'
     * @param  string  $format  返回时间格式 eg: 'Y-m-d H:i:s'
     * @return string|array 单个时间返回格式化字符串；数组入参返回「键名 => 格式化字符串」的数组
     *
     * @throws Exception
     */
    public function format(string|int|DateTimeInterface|array|null $datetime = null, string $format = 'Y-m-d H:i:s'): string|array
    {
        if (is_array($datetime)) {
            $result = [];
            foreach ($datetime as $key => $item) {
                $result[$key] = $this->format($item, $format);
            }

            return $result;
        }
        if ($datetime instanceof DateTimeInterface) {
            // 修复：旧实现仅接受 DateTime，传 DateTimeImmutable 会因对象无法转字符串而抛
            // "Object of class DateTimeImmutable could not be converted to string"
            // 兼容 PHP 8.2：createFromInterface 自 8.3 才提供，改用 setTimestamp + setTimezone 复刻同一时刻与时区
            $dateTimeObj = (new DateTime())
                ->setTimestamp($datetime->getTimestamp())
                ->setTimezone($datetime->getTimezone());
        } else {
            if ($datetime === null || $datetime === '') {
                // 默认为当前时间
                $datetime = 'now';
            }
            // 如果是时间戳则转换为时间字符串
            if (is_numeric($datetime)) {
                $datetime = date('Y-m-d H:i:s', (int) $datetime);
            }
            // 将时间字符串转换为 DateTime 对象
            $dateTimeObj = new DateTime((string) $datetime);
        }

        // 返回格式化时间
        return $dateTimeObj->format($format ?: 'Y-m-d H:i:s');
    }

    // 时区差值对照表
    private array $timeZoneDifferences = [
        'Pacific/Midway' => -11,     // 中途岛标准时间 (UTC-11:00)
        'Pacific/Niue' => -11,     // 纽埃标准时间 (UTC-11:00)
        'Pacific/Pago_Pago' => -11,     // 帕果帕果标准时间 (UTC-11:00)
        'Pacific/Honolulu' => -10,     // 夏威夷标准时间 (UTC-10:00)
        'Pacific/Tahiti' => -10,     // 大溪地标准时间 (UTC-10:00)
        'Pacific/Rarotonga' => -10,     // 拉罗汤加标准时间 (UTC-10:00)
        'Pacific/Marquesas' => -9.5,   // 马克萨斯标准时间 (UTC-09:30)
        'America/Adak' => -9,     // 阿达克标准时间 (UTC-09:00)
        'Pacific/Gambier' => -9,     // 甘比尔标准时间 (UTC-09:00)
        'America/Anchorage' => -8,     // 阿拉斯加标准时间 (UTC-08:00)
        'Pacific/Pitcairn' => -8,     // 皮特凯恩标准时间 (UTC-08:00)
        'America/Los_Angeles' => -8,     // 太平洋标准时间 (UTC-08:00)
        'America/Tijuana' => -8,     // 太平洋标准时间 (UTC-08:00)
        'America/Phoenix' => -7,     // 美国山区标准时间 (UTC-07:00)
        'America/Denver' => -7,     // 美国山区标准时间 (UTC-07:00)
        'America/Mazatlan' => -7,     // 墨西哥西北标准时间 (UTC-07:00)
        'America/Chihuahua' => -7,     // 墨西哥西北标准时间 (UTC-07:00)
        'America/Boise' => -6,     // 北美中部标准时间 (UTC-06:00)
        'America/Regina' => -6,     // 北美中部标准时间 (UTC-06:00)
        'America/Guatemala' => -6,     // 北美中部标准时间 (UTC-06:00)
        'Pacific/Galapagos' => -6,     // 加拉帕戈斯标准时间 (UTC-06:00)
        'America/Chicago' => -6,     // 北美中部标准时间 (UTC-06:00)
        'America/Mexico_City' => -6,     // 北美中部标准时间 (UTC-06:00)
        'America/Belize' => -6,     // 北美中部标准时间 (UTC-06:00)
        'America/Costa_Rica' => -6,     // 北美中部标准时间 (UTC-06:00)
        'America/El_Salvador' => -6,     // 北美中部标准时间 (UTC-06:00)
        'America/Winnipeg' => -5,     // 北美东部标准时间 (UTC-05:00)
        'America/Lima' => -5,     // 北美东部标准时间 (UTC-05:00)
        'America/Rio_Branco' => -5,     // 亚马逊标准时间 (UTC-05:00)
        'America/Bogota' => -5,     // 北美东部标准时间 (UTC-05:00)
        'America/Panama' => -5,     // 北美东部标准时间 (UTC-05:00)
        'America/Jamaica' => -5,     // 北美东部标准时间 (UTC-05:00)
        'America/Cayman' => -5,     // 北美东部标准时间 (UTC-05:00)
        'America/New_York' => -5,     // 北美东部标准时间 (UTC-05:00)
        'America/Havana' => -5,     // 北美东部标准时间 (UTC-05:00)
        'America/Port-au-Prince' => -5,     // 北美东部标准时间 (UTC-05:00)
        'America/Indianapolis' => -4,     // 北美东部标准时间 (UTC-04:00)
        'America/Caracas' => -4,     // 委内瑞拉标准时间 (UTC-04:00)
        'America/Asuncion' => -4,     // 巴拉圭标准时间 (UTC-04:00)
        'America/Halifax' => -4,     // 大西洋标准时间 (UTC-04:00)
        'America/Manaus' => -4,     // 亚马逊标准时间 (UTC-04:00)
        'America/Goose_Bay' => -4,     // 大西洋标准时间 (UTC-04:00)
        'America/Thule' => -4,     // 大西洋标准时间 (UTC-04:00)
        'America/St_Johns' => -3.5,   // 纽芬兰标准时间 (UTC-03:30)
        'America/Araguaina' => -3,     // 巴西东部标准时间 (UTC-03:00)
        'America/Godthab' => -3,     // 西格林兰标准时间 (UTC-03:00)
        'America/Belem' => -3,     // 巴西东部标准时间 (UTC-03:00)
        'America/Montevideo' => -3,     // 乌拉圭标准时间 (UTC-03:00)
        'America/Buenos_Aires' => -3,     // 阿根廷标准时间 (UTC-03:00)
        'America/Cordoba' => -3,     // 阿根廷标准时间 (UTC-03:00)
        'America/Sao_Paulo' => -3,     // 巴西东部标准时间 (UTC-03:00)
        'America/Campo_Grande' => -3,     // 巴西东部标准时间 (UTC-03:00)
        'America/Paramaribo' => -3,     // 苏里南标准时间 (UTC-03:00)
        'America/Cayenne' => -3,     // 法属圭亚那标准时间 (UTC-03:00)
        'America/Fortaleza' => -3,     // 巴西东部标准时间 (UTC-03:00)
        'America/Santiago' => -3,     // 智利标准时间 (UTC-03:00)
        'America/Miquelon' => -2,     // 圣皮埃尔和密克隆群岛标准时间 (UTC-02:00)
        'America/Noronha' => -2,     // 费尔南多·迪诺罗尼亚标准时间 (UTC-02:00)
        'Atlantic/South_Georgia' => -2,     // 南乔治亚岛标准时间 (UTC-02:00)
        'Atlantic/Cape_Verde' => -1,     // 佛得角标准时间 (UTC-01:00)
        'Africa/Casablanca' => 0,     // 摩洛哥标准时间 (UTC+00:00)
        'Europe/London' => 0,     // 格林尼治标准时间 (UTC+00:00)
        'Atlantic/Reykjavik' => 0,     // 冰岛标准时间 (UTC+00:00)
        'Africa/Monrovia' => 0,     // 莫罗维亚标准时间 (UTC+00:00)
        'Africa/Algiers' => 1,     // 中欧标准时间 (UTC+01:00)
        'Europe/Paris' => 1,     // 中欧标准时间 (UTC+01:00)
        'Europe/Brussels' => 1,     // 中欧标准时间 (UTC+01:00)
        'Africa/Windhoek' => 1,     // 中非标准时间 (UTC+01:00)
        'Africa/Ceuta' => 1,     // 中欧标准时间 (UTC+01:00)
        'Europe/Berlin' => 1,     // 中欧标准时间 (UTC+01:00)
        'Europe/Budapest' => 1,     // 中欧标准时间 (UTC+01:00)
        'Europe/Rome' => 1,     // 中欧标准时间 (UTC+01:00)
        'Europe/Stockholm' => 1,     // 中欧标准时间 (UTC+01:00)
        'Europe/Vienna' => 1,     // 中欧标准时间 (UTC+01:00)
        'Europe/Warsaw' => 1,     // 中欧标准时间 (UTC+01:00)
        'Africa/Tunis' => 1,     // 中欧标准时间 (UTC+01:00)
        'Europe/Madrid' => 1,     // 中欧标准时间 (UTC+01:00)
        'Africa/Maputo' => 2,     // 南非标准时间 (UTC+02:00)
        'Africa/Cairo' => 2,     // 埃及标准时间 (UTC+02:00)
        'Europe/Istanbul' => 2,     // 土耳其标准时间 (UTC+02:00)
        'Africa/Johannesburg' => 2,     // 南非标准时间 (UTC+02:00)
        'Asia/Jerusalem' => 2,     // 以色列标准时间 (UTC+02:00)
        'Asia/Amman' => 2,     // 东欧标准时间 (UTC+02:00)
        'Asia/Beirut' => 2,     // 东欧标准时间 (UTC+02:00)
        'Asia/Damascus' => 2,     // 东欧标准时间 (UTC+02:00)
        'Asia/Gaza' => 2,     // 东欧标准时间 (UTC+02:00)
        'Africa/Tripoli' => 2,     // 东欧标准时间 (UTC+02:00)
        'Africa/Khartoum' => 2,     // 东非标准时间 (UTC+02:00)
        'Europe/Kaliningrad' => 2,     // 东欧标准时间 (UTC+02:00)
        'Europe/Minsk' => 3,     // 莫斯科标准时间 (UTC+03:00)
        'Asia/Baghdad' => 3,     // 阿拉伯标准时间 (UTC+03:00)
        'Asia/Riyadh' => 3,     // 阿拉伯标准时间 (UTC+03:00)
        'Africa/Nairobi' => 3,     // 东非标准时间 (UTC+03:00)
        'Asia/Tehran' => 3.5,   // 伊朗标准时间 (UTC+03:30)
        'Europe/Moscow' => 3,     // 莫斯科标准时间 (UTC+03:00)
        'Europe/Volgograd' => 3,     // 莫斯科标准时间 (UTC+03:00)
        'Asia/Tbilisi' => 4,     // 格鲁吉亚标准时间 (UTC+04:00)
        'Asia/Yerevan' => 4,     // 亚美尼亚标准时间 (UTC+04:00)
        'Asia/Dubai' => 4,     // 阿联酋标准时间 (UTC+04:00)
        'Indian/Mauritius' => 4,     // 毛里求斯标准时间 (UTC+04:00)
        'Indian/Reunion' => 4,     // 留尼汪标准时间 (UTC+04:00)
        'Asia/Baku' => 4,     // 阿塞拜疆标准时间 (UTC+04:00)
        'Indian/Mahe' => 4,     // 塞舌尔标准时间 (UTC+04:00)
        'Asia/Kabul' => 4.5,   // 阿富汗标准时间 (UTC+04:30)
        'Asia/Tashkent' => 5,     // 乌兹别克斯坦标准时间 (UTC+05:00)
        'Asia/Yekaterinburg' => 5,     // 叶卡捷琳堡标准时间 (UTC+05:00)
        'Asia/Karachi' => 5,     // 巴基斯坦标准时间 (UTC+05:00)
        'Asia/Qyzylorda' => 5,     // 哈萨克斯坦标准时间 (UTC+05:00)
        'Asia/Colombo' => 5.5,   // 印度标准时间 (UTC+05:30)
        'Asia/Kolkata' => 5.5,   // 印度标准时间 (UTC+05:30)
        'Asia/Kathmandu' => 5.75,  // 尼泊尔标准时间 (UTC+05:45)
        'Asia/Dhaka' => 6,     // 孟加拉标准时间 (UTC+06:00)
        'Asia/Almaty' => 6,     // 哈萨克斯坦标准时间 (UTC+06:00)
        'Asia/Bishkek' => 6,     // 吉尔吉斯斯坦标准时间 (UTC+06:00)
        'Asia/Omsk' => 6,     // 鄂木斯克标准时间 (UTC+06:00)
        'Asia/Rangoon' => 6.5,   // 缅甸标准时间 (UTC+06:30)
        'Asia/Bangkok' => 7,     // 印度支那标准时间 (UTC+07:00)
        'Asia/Jakarta' => 7,     // 印度尼西亚西部标准时间 (UTC+07:00)
        'Asia/Novosibirsk' => 7,     // 新西伯利亚标准时间 (UTC+07:00)
        'Asia/Ho_Chi_Minh' => 7,     // 印度支那标准时间 (UTC+07:00)
        'Asia/Hovd' => 7,     // 西部标准时间 (UTC+07:00)
        'Asia/Ulaanbaatar' => 8,     // 乌兰巴托标准时间 (UTC+08:00)
        'Asia/Krasnoyarsk' => 8,     // 克拉斯诺亚尔斯克标准时间 (UTC+08:00)
        'Asia/Shanghai' => 8,     // 东八区 | 北京时间
        'Asia/Irkutsk' => 8,     // 伊尔库茨克标准时间 (UTC+08:00)
        'Asia/Singapore' => 8,     // 新加坡标准时间 (UTC+08:00)
        'Asia/Brunei' => 8,     // 文莱标准时间 (UTC+08:00)
        'Asia/Kuala_Lumpur' => 8,     // 马来西亚标准时间 (UTC+08:00)
        'Asia/Taipei' => 8,     // 台北标准时间 (UTC+08:00)
        'Asia/Makassar' => 8,     // 印度尼西亚中部标准时间 (UTC+08:00)
        'Asia/Manila' => 8,     // 菲律宾标准时间 (UTC+08:00)
        'Australia/Perth' => 8,     // 澳大利亚西部标准时间 (UTC+08:00)
        'Asia/Seoul' => 9,     // 首尔标准时间 (UTC+09:00)
        'Asia/Tokyo' => 9,     // 东京标准时间 (UTC+09:00)
        'Asia/Yakutsk' => 9,     // 雅库茨克标准时间 (UTC+09:00)
        'Australia/Darwin' => 9.5,   // 澳大利亚中部标准时间 (UTC+09:30)
        'Australia/Adelaide' => 9.5,   // 澳大利亚中部标准时间 (UTC+09:30)
        'Australia/Brisbane' => 10,     // 澳大利亚东部标准时间 (UTC+10:00)
        'Australia/Sydney' => 10,     // 澳大利亚东部标准时间 (UTC+10:00)
        'Asia/Vladivostok' => 10,     // 海参崴标准时间 (UTC+10:00)
        'Pacific/Guam' => 10,     // 关岛标准时间 (UTC+10:00)
        'Asia/Sakhalin' => 11,     // 萨哈林标准时间 (UTC+11:00)
        'Pacific/Noumea' => 11,     // 努美阿标准时间 (UTC+11:00)
        'Pacific/Fiji' => 12,     // 斐济标准时间 (UTC+12:00)
        'Asia/Kamchatka' => 12,     // 堪察加标准时间 (UTC+12:00)
        'Pacific/Majuro' => 12,     // 马绍尔群岛标准时间 (UTC+12:00)
        'Pacific/Auckland' => 12,     // 新西兰标准时间 (UTC+12:00)
        'Pacific/Tongatapu' => 13,     // 汤加标准时间 (UTC+13:00)
        'Pacific/Apia' => 13,     // 阿皮亚标准时间 (UTC+13:00)
        'Pacific/Fakaofo' => 13,     // 托克劳标准时间 (UTC+13:00)
        'Pacific/Kiritimati' => 14,     // 莫桑比克标准时间 (UTC+14:00)

        'PRC' => 8,                     // 中国标准时间 (UTC+08:00)
        'MST' => -7,                    // 山地标准时间 (UTC-07:00)
        'CST' => -6,                    // 中部标准时间 (UTC-06:00)
        'EST' => -5,                    // 东部标准时间 (UTC-05:00)
        'NST' => -3.5,                  // 纽芬兰标准时间 (UTC-03:30)
        'NDT' => -2.5,                  // 纽芬兰夏令时 (UTC-02:30)
        'GST' => 4,                     // 波斯湾标准时间 (UTC+04:00)
        'EET' => 2,                     // 东欧标准时间 (UTC+02:00)
        'PST' => -8,                    // 太平洋标准时间 (UTC-08:00)（修复：旧值 8 与注释矛盾，差 16 小时）
        'AST' => 4,                     // 亚美尼亚标准时间 (UTC+04:00)（与 Asia/Yerevan 一致；修复：旧值 3 与真实偏移不符）
        'UTC' => 0,                     // 协调世界时 (UTC+00:00)
        'CET' => 1,                     // 中欧标准时间 (UTC+01:00)
        'CEST' => 2,                     // 中欧夏令时 (UTC+02:00)
        'IST' => 5.5,                   // 印度标准时间 (UTC+05:30)
        'JST' => 9,                     // 日本标准时间 (UTC+09:00)
        'KST' => 9,                     // 韩国标准时间 (UTC+09:00)

        'Etc/GMT+12' => -12,                    // UTC-12:00
        'Etc/GMT+11' => -11,                    // UTC-11:00
        'Etc/GMT+10' => -10,                    // UTC-10:00
        'Etc/GMT+9' => -9,                     // UTC-09:00
        'Etc/GMT+8' => -8,                     // UTC-08:00
        'Etc/GMT+7' => -7,                     // UTC-07:00
        'Etc/GMT+6' => -6,                     // UTC-06:00
        'Etc/GMT+5' => -5,                     // UTC-05:00
        'Etc/GMT+4' => -4,                     // UTC-04:00
        'Etc/GMT+3' => -3,                     // UTC-03:00
        'Etc/GMT+2' => -2,                     // UTC-02:00
        'Etc/GMT+1' => -1,                     // UTC-01:00
        'Etc/GMT' => 0,                      // UTC+00:00
        'Etc/GMT-1' => 1,                      // UTC+01:00
        'Etc/GMT-2' => 2,                      // UTC+02:00
        'Etc/GMT-3' => 3,                      // UTC+03:00
        'Etc/GMT-4' => 4,                      // UTC+04:00
        'Etc/GMT-5' => 5,                      // UTC+05:00
        'Etc/GMT-6' => 6,                      // UTC+06:00
        'Etc/GMT-7' => 7,                      // UTC+07:00
        'Etc/GMT-8' => 8,                      // UTC+08:00
        'Etc/GMT-9' => 9,                      // UTC+09:00
        'Etc/GMT-10' => 10,                     // UTC+10:00
        'Etc/GMT-11' => 11,                     // UTC+11:00
        'Etc/GMT-12' => 12,                     // UTC+12:00
        'Etc/GMT-13' => 13,                     // UTC+13:00
        'Etc/GMT-14' => 14,                     // UTC+14:00

        'UTC+0' => 0,                     // 协调世界时 (UTC+00:00)
        'UTC+1' => 1,                     // 中欧标准时间 (UTC+01:00)
        'UTC+2' => 2,                     // 东欧标准时间 (UTC+02:00)
        'UTC+3' => 3,                     // 东欧标准时间 (UTC+03:00)
        'UTC+4' => 4,                     // 波斯湾标准时间 (UTC+04:00)
        'UTC+5' => 5,                     // 乌兹别克斯坦标准时间 (UTC+05:00)
        'UTC+6' => 6,                     // 哈萨克斯坦标准时间 (UTC+06:00)
        'UTC+7' => 7,                     // 印度支那标准时间 (UTC+07:00)
        'UTC+8' => 8,                     // 中国标准时间 (UTC+08:00)
        'UTC+9' => 9,                     // 首尔标准时间 (UTC+09:00)
        'UTC+10' => 10,                    // 澳大利亚东部标准时间 (UTC+10:00)
        'UTC+11' => 11,                    // 萨哈林标准时间 (UTC+11:00)
        'UTC+12' => 12,                    // 斐济标准时间 (UTC+12:00)
        'UTC+13' => 13,                    // 汤加标准时间 (UTC+13:00)
        'UTC+14' => 14,                    // 莫桑比克标准时间 (UTC+14:00)
    ];
}

<?php

declare(strict_types=1);

namespace zxf\Utils\Data;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * 中国身份证号码生成与验证工具类
 *
 * 功能特点：
 * - 支持15位/18位身份证生成
 * - 支持自定义性别参数（m/male/f/female）
 * - 支持批量生成N个身份证
 * - 精确的行政区划处理
 * - 完整的生日、性别、年龄等信息解析
 * - 生肖、星座、世代计算
 * - 严格的输入验证
 *
 * 使用示例：
 * 1、生成单个身份证号码：
 * $idCard = IDCardGenerator::generate(); // 随机生成
 * $idCard = IDCardGenerator::generate(['province' => '广东省']); // 生成指定省份的身份证
 * $idCard = IDCardGenerator::generate(['gender' => 'm']); // 指定男性 （支持m/male/f/female）
 * $idCard = IDCardGenerator::generate(['birthday' => '1990-08-15']); // 生成指定出生日期的身份证
 * $idCard = IDCardGenerator::generate(['length' => 15]); // 生成15位身份证
 * $idCard = IDCardGenerator::generate([
 *              'province' => '江苏省', // 省份
 *              'birthday' => '1985-05-20', // 生日
 *              'gender' => 'f', //  性别 （支持m/male/f/female）
 *              'length' => 18  // 18位身份证
 *           ]);
 *
 * 2、批量生成多个身份证号码：
 * $idCards = IDCardGenerator::generateBatch(10);
 * $idCards = IDCardGenerator::generateBatch(5, ['province' => '广东省']); // 批量生成指定省份的身份证
 * $idCards = IDCardGenerator::generateBatch(5, ['length' => 15]); // 批量生成15位身份证
 * $idCards =IDCardGenerator::generateBatch(3, [ // 批量生成3个1990年出生的男性身份证
 *              'birthday' => '1990-01-01',
 *              'gender' => 'male'
 *          ]);
 *
 * 3、验证身份证号码：
 * $isValid = IDCardGenerator::validate('11010519491231002X'); // 验证身份证 返回bool 是否有效
 *
 * 4、解析身份证信息：
 * $info = IDCardGenerator::parse('11010519491231002X'); // 返回身份证信息数组
 *
 * 5、15位身份证升级为18位
 * $idCard18 = IDCardGenerator::upgradeTo18('110105491231002');
 *
 * 6. 获取支持的省份列表
 * $provinces = IDCardGenerator::getProvinces();
 *
 * @example
 * // 生成一个随机 18 位有效身份证号码
 * $idCard = IDCardGenerator::generate();
 * // 生成指定省份、性别与出生日期的身份证
 * $idCard = IDCardGenerator::generate(['province' => '广东省', 'gender' => 'm', 'birthday' => '1990-08-15']);
 * // 校验身份证是否有效
 * $isValid = IDCardGenerator::validate('11010519491231002X');
 *
 * @package zxf\Utils\Data
 * @version 1.0.0
 * @license MIT
 */
final class IDCardGenerator
{
    // 省份代码映射
    private const PROVINCE_CODES = [
        11 => '北京市', 12 => '天津市', 13 => '河北省', 14 => '山西省', 15 => '内蒙古自治区',
        21 => '辽宁省', 22 => '吉林省', 23 => '黑龙江省',
        31 => '上海市', 32 => '江苏省', 33 => '浙江省', 34 => '安徽省', 35 => '福建省',
        36 => '江西省', 37 => '山东省',
        41 => '河南省', 42 => '湖北省', 43 => '湖南省', 44 => '广东省', 45 => '广西壮族自治区',
        46 => '海南省',
        50 => '重庆市', 51 => '四川省', 52 => '贵州省', 53 => '云南省', 54 => '西藏自治区',
        61 => '陕西省', 62 => '甘肃省', 63 => '青海省', 64 => '宁夏回族自治区', 65 => '新疆维吾尔自治区',
        71 => '台湾省', 81 => '香港特别行政区', 82 => '澳门特别行政区',
    ];

    // 校验码权重因子
    private const CHECKSUM_WEIGHTS = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];

    // 校验码对应值
    private const CHECKSUM_CODES = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

    // 直辖市和特别行政区代码
    private const MUNICIPALITIES = [11, 12, 31, 50, 81, 82];

    // 生肖列表
    private const ZODIAC_SIGNS = ['猴', '鸡', '狗', '猪', '鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊'];

    // 星座日期范围
    private const CONSTELLATION_RANGES = [
        ['name' => '水瓶座', 'start' => '01-20', 'end' => '02-18'],
        ['name' => '双鱼座', 'start' => '02-19', 'end' => '03-20'],
        ['name' => '白羊座', 'start' => '03-21', 'end' => '04-19'],
        ['name' => '金牛座', 'start' => '04-20', 'end' => '05-20'],
        ['name' => '双子座', 'start' => '05-21', 'end' => '06-21'],
        ['name' => '巨蟹座', 'start' => '06-22', 'end' => '07-22'],
        ['name' => '狮子座', 'start' => '07-23', 'end' => '08-22'],
        ['name' => '处女座', 'start' => '08-23', 'end' => '09-22'],
        ['name' => '天秤座', 'start' => '09-23', 'end' => '10-23'],
        ['name' => '天蝎座', 'start' => '10-24', 'end' => '11-22'],
        ['name' => '射手座', 'start' => '11-23', 'end' => '12-21'],
        ['name' => '摩羯座', 'start' => '12-22', 'end' => '01-19'],
    ];

    // 年龄限制范围
    private const MIN_AGE = 0;

    private const MAX_AGE = 125;

    /**
     * 生成单个身份证号码
     *
     * 由地区码（6 位）、出生日期码（8 位或 6 位）与顺序码（3 位）拼接而成；
     * 18 位时追加 ISO 7064 MOD 11-2 校验位，15 位则直接返回前 15 位。未指定
     * 的可选项使用随机值，因此同一组选项每次生成的结果不同。
     *
     * @param array{province?: string|null, birthday?: string|DateTimeInterface|null, gender?: 'm'|'male'|'f'|'female'|null, length?: 15|18} $options 生成选项：province 省份名（null 为随机省份），birthday 出生日期（Y-m-d 或 DateTimeInterface，null 为随机日期），gender 性别（null 为随机），length 位数（15 或 18，默认 18）
     * @return string 身份证号码
     *
     * @throws InvalidArgumentException 当参数无效时抛出
     */
    public static function generate(array $options = []): string
    {
        $options = array_merge([
            'province' => null,
            'birthday' => null,
            'gender' => null,
            'length' => 18,
        ], $options);

        self::validateOptions($options);

        $areaCode = self::generateAreaCode($options['province']);
        $birthCode = self::generateBirthCode($options['birthday'], $options['length'] === 18);
        $sequenceCode = self::generateSequenceCode($options['gender']);

        if ($options['length'] === 15) {
            return $areaCode.$birthCode.$sequenceCode;
        }

        $idBase = $areaCode.$birthCode.$sequenceCode;

        return $idBase.self::calculateChecksum($idBase);
    }

    /**
     * 批量生成身份证号码
     *
     * 循环调用 generate() 生成 $count 个号码；由于每次都会重新随机未指定
     * 的字段，结果之间不保证唯一。数量不大于 0 时抛出异常。
     *
     * @param  int  $count  要生成的数量
     * @param array{province?: string|null, birthday?: string|DateTimeInterface|null, gender?: 'm'|'male'|'f'|'female'|null, length?: 15|18} $options 生成选项：province 省份名，birthday 出生日期，gender 性别，length 位数（15 或 18），未指定字段各自随机
     * @return array<string> 生成的身份证号码数组
     *
     * @throws InvalidArgumentException 当参数无效时抛出
     */
    public static function generateBatch(int $count, array $options = []): array
    {
        if ($count <= 0) {
            throw new InvalidArgumentException('生成数量必须大于0');
        }

        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = self::generate($options);
        }

        return $ids;
    }

    /**
     * 验证生成选项
     *
     * 校验位数是否为 15/18、性别是否在 m/male/f/female（不区分大小写）之内，
     * 以及生日是否为合法日期：字符串必须严格匹配 Y-m-d 且真实存在（如
     * 2023-02-30 会被拒绝），否则须为 DateTimeInterface 实例。
     *
     * @param array $options 生成选项（含 province/birthday/gender/length）
     * @return void
     * @throws InvalidArgumentException 当位数非法、性别非法或日期格式无效时抛出
     */
    private static function validateOptions(array $options): void
    {
        if (! in_array($options['length'], [15, 18], true)) {
            throw new InvalidArgumentException('身份证位数必须是15或18');
        }

        if ($options['gender'] !== null) {
            $gender = strtolower($options['gender']);
            if (! in_array($gender, ['m', 'male', 'f', 'female'], true)) {
                throw new InvalidArgumentException("性别必须是'm/male'或'f/female'");
            }
        }

        if ($options['birthday'] !== null) {
            if (is_string($options['birthday'])) {
                $date = DateTimeImmutable::createFromFormat('Y-m-d', $options['birthday']);
                if ($date === false || $date->format('Y-m-d') !== $options['birthday']) {
                    throw new InvalidArgumentException('出生日期格式无效，请使用YYYY-MM-DD格式');
                }
            } elseif (! ($options['birthday'] instanceof DateTimeInterface)) {
                throw new InvalidArgumentException('出生日期必须是字符串或DateTimeInterface实例');
            }
        }
    }

    /**
     * 生成地区编码（6 位：省 2 + 市 2 + 区/县 2）
     *
     * @param string|null $province 省份名称；为 null 时随机选择一个省份
     * @return string 6 位地区编码
     */
    private static function generateAreaCode(?string $province): string
    {
        $provinceCode = $province ? self::resolveProvinceCode($province) : array_rand(self::PROVINCE_CODES);
        $isMunicipality = in_array($provinceCode, self::MUNICIPALITIES, true);

        $cityMax = $isMunicipality ? 1 : 99;
        $districtMax = $isMunicipality ? 20 : 99;

        $cityCode = str_pad((string) random_int(1, $cityMax), 2, '0', STR_PAD_LEFT);
        $districtCode = str_pad((string) random_int(1, $districtMax), 2, '0', STR_PAD_LEFT);

        return $provinceCode.$cityCode.$districtCode;
    }

    /**
     * 解析省份名称到代码
     *
     * 通过去除"省/市/自治区/特别行政区"等后缀做模糊匹配，
     * 以便接受"广东省""广东""广东省广州市"等写法。
     *
     * @param string $province 省份名称
     * @return int 两位省份代码
     * @throws InvalidArgumentException 当无法匹配到已知省份时抛出
     */
    private static function resolveProvinceCode(string $province): int
    {
        $normalizedProvince = str_replace(['省', '市', '自治区', '特别行政区', '壮族', '回族', '维吾尔'], '', $province);

        foreach (self::PROVINCE_CODES as $code => $name) {
            $normalizedName = str_replace(['省', '市', '自治区', '特别行政区', '壮族', '回族', '维吾尔'], '', $name);
            if ($normalizedName === $normalizedProvince) {
                return $code;
            }
        }

        throw new InvalidArgumentException("无效的省份名称: {$province}");
    }

    /**
     * 生成出生日期编码
     *
     * @param null|string|DateTimeInterface $birthday 出生日期；为 null 时随机生成
     * @param bool $is18Digit 是否为 18 位格式（18 位用 4 位年，15 位用 2 位年）
     * @return string 出生日期编码（18 位为 8 位，15 位为 6 位）
     */
    private static function generateBirthCode(null|string|DateTimeInterface $birthday, bool $is18Digit): string
    {
        if ($birthday !== null) {
            $date = $birthday instanceof DateTimeInterface
                // 兼容 PHP 8.2：createFromInterface 自 8.3 才提供，
                // 这里改用 setTimestamp + setTimezone 复刻同一时刻与时区。
                ? (new DateTimeImmutable())
                    ->setTimestamp($birthday->getTimestamp())
                    ->setTimezone($birthday->getTimezone())
                : DateTimeImmutable::createFromFormat('Y-m-d', $birthday);

            return $is18Digit
                ? $date->format('Ymd')
                : $date->format('ymd');
        }

        return self::generateRandomBirthCode($is18Digit);
    }

    /**
     * 生成随机出生日期编码
     *
     * 年份在 [当前年-125, 当前年] 范围内随机（对应 MAX_AGE/MIN_AGE），
     * 月份与日期会按实际月份天数取值以避免出现 2 月 30 日等非法日期。
     *
     * @param bool $is18Digit 是否为 18 位格式
     * @return string 出生日期编码
     */
    private static function generateRandomBirthCode(bool $is18Digit): string
    {
        $currentYear = (int) date('Y');
        $year = random_int($currentYear - self::MAX_AGE, $currentYear - self::MIN_AGE);
        $month = random_int(1, 12);
        $day = random_int(1, self::daysInMonth($year, $month));

        return $is18Digit
            ? sprintf('%04d%02d%02d', $year, $month, $day)
            : sprintf('%02d%02d%02d', $year % 100, $month, $day);
    }

    /**
     * 计算某年某月的天数
     *
     * @param int $year 年份（用于判断 2 月闰年天数）
     * @param int $month 月份（1-12）
     * @return int 该月天数
     */
    private static function daysInMonth(int $year, int $month): int
    {
        return $month === 2
            ? (self::isLeapYear($year) ? 29 : 28)
            : (($month === 4 || $month === 6 || $month === 9 || $month === 11) ? 30 : 31);
    }

    /**
     * 判断闰年
     *
     * 规则：能被 4 整除但不能被 100 整除，或能被 400 整除。
     *
     * @param int $year 年份
     * @return bool 是闰年返回 true
     */
    private static function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }

    /**
     * 生成顺序码（包含性别信息）
     *
     * 顺序码为 1-999 的整数，奇数为男性、偶数为女性。
     * 当指定了性别但随机出的奇偶性不符时，会按位运算修正奇偶性
     * （`| 1` 强制奇数，`& ~1` 强制偶数），并夹紧到 [1, 999] 范围。
     *
     * @param string|null $gender 期望性别（m/male/f/female）；为 null 时不限制
     * @return string 3 位补零顺序码
     */
    private static function generateSequenceCode(?string $gender): string
    {
        $sequence = random_int(1, 999);

        if ($gender !== null) {
            $gender = strtolower($gender);
            $shouldBeOdd = in_array($gender, ['m', 'male'], true);
            $isOdd = $sequence % 2 === 1;

            if ($shouldBeOdd !== $isOdd) {
                // 修正奇偶性（顺序码范围 1..999），保证顺序码与请求性别一致
                $sequence = $shouldBeOdd ? ($sequence | 1) : ($sequence & ~1);
                if ($sequence < 1) {
                    $sequence = 2;
                } elseif ($sequence > 999) {
                    $sequence = 998;
                }
            }
        }

        return str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 计算校验码（第 18 位）
     *
     * 依据国家标准 GB 11643-1999 的 MOD 11-2 算法：
     * 将前 17 位依次乘以固定加权因子 CHECKSUM_WEIGHTS（7,9,10,5,8,4,2,1,6,3,7,9,10,5,8,4,2），
     * 求和后对 11 取模，余数按 CHECKSUM_CODES（['1','0','X','9','8','7','6','5','4','3','2']）映射到校验码。
     *
     * @param string $idBase 前 17 位数字符串
     * @return string 单个校验码字符（可能为 'X'）
     * @throws InvalidArgumentException 当长度不为 17 或包含非数字字符时抛出
     */
    private static function calculateChecksum(string $idBase): string
    {
        if (strlen($idBase) !== 17) {
            throw new InvalidArgumentException('身份证前17位长度必须为17');
        }

        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            if (! ctype_digit($idBase[$i])) {
                throw new InvalidArgumentException('身份证前17位必须为数字');
            }
            // 加权求和：每一位乘以对应权重后累加
            $sum += (int) $idBase[$i] * self::CHECKSUM_WEIGHTS[$i];
        }

        // 对 11 取模后映射到校验码字符集（余数 2 对应 'X'）
        return self::CHECKSUM_CODES[$sum % 11];
    }

    /**
     * 验证身份证号码有效性
     *
     * 按长度分派：15 位走 validate15Digit()，18 位走 validate18Digit()，
     * 其余长度一律视为无效。18 位会额外校验 MOD 11-2 校验位（X 不区分大小写），
     * 15 位无校验码，仅校验数字、省份与日期。
     *
     * @param string $idCard 待验证的身份证号（15 或 18 位）
     * @return bool 合法返回 true，长度不符或任一校验失败返回 false
     */
    public static function validate(string $idCard): bool
    {
        $length = strlen($idCard);

        return match ($length) {
            15 => self::validate15Digit($idCard),
            18 => self::validate18Digit($idCard),
            default => false
        };
    }

    /**
     * 验证15位身份证
     *
     * 15 位身份证无校验码，仅校验：全数字、省份代码合法、日期合法。
     * 年份按两位解析（<50 视为 20xx，否则 19xx）。
     *
     * @param string $idCard 15 位身份证号
     * @return bool 合法返回 true
     */
    private static function validate15Digit(string $idCard): bool
    {
        if (! ctype_digit($idCard)) {
            return false;
        }

        $provinceCode = (int) substr($idCard, 0, 2);
        if (! isset(self::PROVINCE_CODES[$provinceCode])) {
            return false;
        }

        $year = substr($idCard, 6, 2);
        $month = substr($idCard, 8, 2);
        $day = substr($idCard, 10, 2);

        return self::isValidDate($year, $month, $day, false);
    }

    /**
     * 验证18位身份证
     *
     * 依次校验：前 17 位为数字、省份代码合法、出生日期合法，
     * 以及第 18 位校验码与 calculateChecksum() 计算结果一致（不区分大小写）。
     *
     * @param string $idCard 18 位身份证号
     * @return bool 合法返回 true
     */
    private static function validate18Digit(string $idCard): bool
    {
        $base = substr($idCard, 0, 17);
        if (! ctype_digit($base)) {
            return false;
        }

        $provinceCode = (int) substr($idCard, 0, 2);
        if (! isset(self::PROVINCE_CODES[$provinceCode])) {
            return false;
        }

        $year = substr($idCard, 6, 4);
        $month = substr($idCard, 10, 2);
        $day = substr($idCard, 12, 2);

        if (! self::isValidDate($year, $month, $day, true)) {
            return false;
        }

        return strtoupper($idCard[17]) === self::calculateChecksum($base);
    }

    /**
     * 验证出生日期是否真实存在
     *
     * 校验月份在 1-12 之间、日期在 1 到当月实际天数之间（自动处理闰年 2 月）。
     * 两位年份按 <50 视为 20xx、否则 19xx 补全。
     *
     * @param string $year 年份字符串（15 位为 2 位、18 位为 4 位）
     * @param string $month 月份字符串（2 位）
     * @param string $day 日期字符串（2 位）
     * @param bool $fullYear 是否为完整 4 位年份（15 位需按 <50 规则补世纪）
     * @return bool 日期合法返回 true
     */
    private static function isValidDate(string $year, string $month, string $day, bool $fullYear): bool
    {
        $monthInt = (int) $month;
        $dayInt = (int) $day;

        if ($monthInt < 1 || $monthInt > 12) {
            return false;
        }

        $yearInt = (int) $year;
        if (! $fullYear) {
            $yearInt += ($yearInt < 50) ? 2000 : 1900;
        }

        return $dayInt >= 1 && $dayInt <= self::daysInMonth($yearInt, $monthInt);
    }

    /**
     * 解析身份证信息
     *
     * 先调用 validate() 校验，无效则抛异常；随后提取地区码、出生日期、顺序码，
     * 并据此推算年龄（周岁，夹紧到 MIN_AGE-MAX_AGE）、性别（顺序码奇男偶女）、
     * 生肖、星座与代际。15 位输入的年月日会按 <50 补 20xx 的规则还原。
     *
     * @param string $idCard 身份证号（15 或 18 位）
     * @return array{
     *     is_18_digit: bool,
     *     area_code: string,
     *     province: string,
     *     birthday: string,
     *     age: int,
     *     gender: 'male'|'female',
     *     sequence_code: string,
     *     check_code: string|null,
     *     zodiac: string,
     *     constellation: string,
     *     generation: string
     * }
     *
     * @throws InvalidArgumentException 当身份证无效时抛出
     */
    public static function parse(string $idCard): array
    {
        if (! self::validate($idCard)) {
            throw new InvalidArgumentException('无效的身份证号码');
        }

        $is18Digit = strlen($idCard) === 18;
        $provinceCode = (int) substr($idCard, 0, 2);
        $sequence = substr($idCard, $is18Digit ? 14 : 12, 3);
        $birthday = self::parseBirthday($idCard, $is18Digit);
        $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $birthday);

        return [
            'is_18_digit' => $is18Digit,
            'area_code' => substr($idCard, 0, 6),
            'province' => self::PROVINCE_CODES[$provinceCode] ?? '未知',
            'birthday' => $birthday,
            'age' => self::calculateAge($birthDate),
            'gender' => ((int) $sequence % 2 === 0) ? 'female' : 'male',
            'sequence_code' => $sequence,
            'check_code' => $is18Digit ? strtoupper($idCard[17]) : null,
            'zodiac' => self::getZodiacSign($birthDate),
            'constellation' => self::getConstellation($birthDate),
            'generation' => self::getGeneration($birthDate),
        ];
    }

    /**
     * 解析出生日期
     *
     * 18 位取第 7-14 位（YYYYMMDD），15 位取第 7-12 位并补世纪（<50 为 20xx）。
     *
     * @param string $idCard 身份证号
     * @param bool $is18Digit 是否为 18 位格式
     * @return string 标准 YYYY-MM-DD 格式日期
     */
    private static function parseBirthday(string $idCard, bool $is18Digit): string
    {
        if ($is18Digit) {
            $birthday = substr($idCard, 6, 8);

            return sprintf('%s-%s-%s', substr($birthday, 0, 4), substr($birthday, 4, 2), substr($birthday, 6, 2));
        }

        $birthday = substr($idCard, 6, 6);
        $century = (int) substr($birthday, 0, 2) < 50 ? '20' : '19';

        return sprintf('%s%s-%s-%s', $century, substr($birthday, 0, 2), substr($birthday, 2, 2), substr($birthday, 4, 2));
    }

    /**
     * 计算年龄
     *
     * 以当前日期计算周岁，并夹紧到 [MIN_AGE, MAX_AGE] 范围。
     *
     * @param DateTimeImmutable $birthDate 出生日期
     * @return int 周岁年龄
     */
    private static function calculateAge(DateTimeImmutable $birthDate): int
    {
        $now = new DateTimeImmutable;
        $age = $now->diff($birthDate)->y;

        return max(self::MIN_AGE, min($age, self::MAX_AGE));
    }

    /**
     * 获取生肖
     *
     * 以农历近似规则（按公历年份 mod 12）从 ZODIAC_SIGNS 取值。
     *
     * @param DateTimeImmutable $birthDate 出生日期
     * @return string 生肖名称（如"鼠""牛"）
     */
    private static function getZodiacSign(DateTimeImmutable $birthDate): string
    {
        return self::ZODIAC_SIGNS[$birthDate->format('Y') % 12];
    }

    /**
     * 获取星座
     *
     * 按 CONS`TELLATION_RANGES 中"月-日"区间匹配；摩羯座跨年（12-22 至 01-19），
     * 未命中任何区间时兜底返回"摩羯座"。
     *
     * @param DateTimeImmutable $birthDate 出生日期
     * @return string 星座名称
     */
    private static function getConstellation(DateTimeImmutable $birthDate): string
    {
        $monthDay = $birthDate->format('m-d');

        foreach (self::CONSTELLATION_RANGES as $range) {
            if ($monthDay >= $range['start'] && $monthDay <= $range['end']) {
                return $range['name'];
            }
        }

        return '摩羯座';
    }

    /**
     * 获取世代
     *
     * 按出生年份划分：Z世代(>=2010)/千禧一代(>=1995)/Y世代(>=1980)/
     * X世代(>=1965)/婴儿潮一代(>=1946)/传统一代。
     *
     * @param DateTimeImmutable $birthDate 出生日期
     * @return string 世代名称
     */
    private static function getGeneration(DateTimeImmutable $birthDate): string
    {
        $year = (int) $birthDate->format('Y');

        if ($year >= 2010) {
            return 'Z世代';
        }
        if ($year >= 1995) {
            return '千禧一代';
        }
        if ($year >= 1980) {
            return 'Y世代';
        }
        if ($year >= 1965) {
            return 'X世代';
        }
        if ($year >= 1946) {
            return '婴儿潮一代';
        }

        return '传统一代';
    }

    /**
     * 将15位身份证升级为18位
     *
     * 在原 6 位地区码后插入世纪前缀（年份 <50 补 20，否则补 19）得到 8 位
     * 出生日期码，再按 MOD 11-2 计算末位校验码。入参长度不为 15 或未通过
     * validate() 时抛出异常。
     *
     * @param string $idCard15 15 位身份证号
     * @return string 升级后的 18 位身份证号
     * @throws InvalidArgumentException 当入参不是有效的 15 位身份证号时抛出
     */
    public static function upgradeTo18(string $idCard15): string
    {
        if (strlen($idCard15) !== 15 || ! self::validate($idCard15)) {
            throw new InvalidArgumentException('无效的15位身份证号码');
        }

        $areaCode = substr($idCard15, 0, 6);
        $birthYear = substr($idCard15, 6, 2);
        $century = (int) $birthYear < 50 ? '20' : '19';
        $birthCode = $century.$birthYear.substr($idCard15, 8, 4);
        $sequenceCode = substr($idCard15, 12, 3);
        $idBase = $areaCode.$birthCode.$sequenceCode;

        return $idBase.self::calculateChecksum($idBase);
    }

    /**
     * 获取所有支持的省份列表
     *
     * 返回内置的「两位省份代码 => 省份名称」映射，可用于展示可选省份或做
     * 反查；该数组为静态常量内容，直接返回其副本。
     *
     * @return array<int,string> 省份代码到省份名称的映射
     */
    public static function getProvinces(): array
    {
        return self::PROVINCE_CODES;
    }
}

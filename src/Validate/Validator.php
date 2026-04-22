<?php

declare(strict_types=1);

namespace zxf\Utils\Validate;

use InvalidArgumentException;

/**
 * 通用数据验证器
 * 提供邮箱、手机号、URL、IP、身份证、银行卡、车牌、统一社会信用代码等常见数据格式的验证
 * 支持批量管道式验证和自定义正则规则
 *
 * @package Validate
 * @version 1.0.0
 * @license MIT
 */
class Validator
{
    /** @var string 中国手机号段正则 */
    private const MOBILE_PATTERN = '/^1[3-9]\d{9}$/';

    /** @var string 中国固定电话正则 */
    private const TEL_PATTERN = '/^(0\d{2,3}-?)?\d{7,8}$/';

    /** @var string 邮箱正则 */
    private const EMAIL_PATTERN = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

    /** @var string URL正则 */
    private const URL_PATTERN = '/^https?:\/\/(?:[a-zA-Z0-9-]+\.)*[a-zA-Z0-9-]+(?::\d+)?(?:\/[^\s]*)?$/';

    /** @var string IPv4正则 */
    private const IPV4_PATTERN = '/^(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)$/';

    /** @var int[] 身份证号加权因子（前17位） */
    private const IDCARD_WEIGHTS = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];

    /** @var string[] 身份证号校验码映射 */
    private const IDCARD_CHECK_CODES = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

    /**
     * 验证值是否为空（null、空字符串、空数组视为空）
     *
     * @param mixed $value 待验证的值
     * @return bool 为空返回 true
     */
    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * 验证值是否为非空
     *
     * @param mixed $value 待验证的值
     * @return bool 非空返回 true
     */
    public static function isNotEmpty(mixed $value): bool
    {
        return !self::isEmpty($value);
    }

    /**
     * 验证邮箱地址格式
     *
     * @param string $email 邮箱地址
     * @return bool 格式有效返回 true
     */
    public static function isEmail(string $email): bool
    {
        return (bool) preg_match(self::EMAIL_PATTERN, $email);
    }

    /**
     * 验证中国手机号（大陆）
     *
     * 支持 13x-19x 号段（不含 10-12x 等早期号段）
     *
     * @param string $mobile 手机号
     * @return bool 格式有效返回 true
     */
    public static function isMobile(string $mobile): bool
    {
        return (bool) preg_match(self::MOBILE_PATTERN, $mobile);
    }

    /**
     * 验证中国固定电话号码
     *
     * @param string $tel 固定电话号码，如 "010-12345678" 或 "0512-87654321"
     * @return bool 格式有效返回 true
     */
    public static function isTel(string $tel): bool
    {
        return (bool) preg_match(self::TEL_PATTERN, $tel);
    }

    /**
     * 验证 URL 地址格式（仅支持 http/https）
     *
     * @param string $url URL 地址
     * @return bool 格式有效返回 true
     */
    public static function isUrl(string $url): bool
    {
        return (bool) preg_match(self::URL_PATTERN, $url);
    }

    /**
     * 验证 IPv4 地址格式
     *
     * @param string $ip IP 地址
     * @return bool 格式有效返回 true
     */
    public static function isIpv4(string $ip): bool
    {
        return (bool) preg_match(self::IPV4_PATTERN, $ip);
    }

    /**
     * 验证 IPv6 地址格式
     *
     * @param string $ip IP 地址
     * @return bool 格式有效返回 true
     */
    public static function isIpv6(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * 验证 IP 地址格式（IPv4 或 IPv6）
     *
     * @param string $ip IP 地址
     * @return bool 格式有效返回 true
     */
    public static function isIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP);
    }

    /**
     * 验证是否为内网/私有 IP 地址
     *
     * @param string $ip IP 地址
     * @return bool 为内网 IP 返回 true
     */
    public static function isPrivateIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            && self::isIp($ip);
    }

    /**
     * 验证中国大陆身份证号（15位或18位）
     *
     * 15位身份证自动转换为18位后进行加权校验码验证
     *
     * @param string $idCard 身份证号
     * @return bool 格式和校验码均有效返回 true
     */
    public static function isIdCard(string $idCard): bool
    {
        $len = strlen($idCard);
        if ($len !== 15 && $len !== 18) {
            return false;
        }

        // 15位转18位
        if ($len === 15) {
            $idCard = self::idCard15To18($idCard);
            if ($idCard === null) {
                return false;
            }
        }

        // 格式校验
        if (!preg_match('/^\d{17}[\dX]$/i', $idCard)) {
            return false;
        }

        // 校验码验证
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += (int) $idCard[$i] * self::IDCARD_WEIGHTS[$i];
        }

        return strtoupper($idCard[17]) === self::IDCARD_CHECK_CODES[$sum % 11];
    }

    /**
     * 验证银行卡号（Luhn 算法校验）
     *
     * @param string $cardNo 银行卡号（13-19位数字）
     * @return bool 校验通过返回 true
     */
    public static function isBankCard(string $cardNo): bool
    {
        if (!preg_match('/^\d{13,19}$/', $cardNo)) {
            return false;
        }

        $sum = 0;
        $alternate = false;
        for ($i = strlen($cardNo) - 1; $i >= 0; $i--) {
            $n = (int) $cardNo[$i];
            if ($alternate) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alternate = !$alternate;
        }

        return $sum % 10 === 0;
    }

    /**
     * 验证中国邮政编码
     *
     * @param string $zip 邮政编码
     * @return bool 格式有效返回 true
     */
    public static function isZipCode(string $zip): bool
    {
        return (bool) preg_match('/^\d{6}$/', $zip);
    }

    /**
     * 验证 QQ 号格式
     *
     * @param string $qq QQ 号码
     * @return bool 格式有效返回 true
     */
    public static function isQq(string $qq): bool
    {
        return (bool) preg_match('/^[1-9]\d{4,10}$/', $qq);
    }

    /**
     * 验证微信号格式
     *
     * @param string $wechat 微信号
     * @return bool 格式有效返回 true
     */
    public static function isWechat(string $wechat): bool
    {
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{5,19}$/', $wechat);
    }

    /**
     * 验证是否为纯数字字符串（不含负号和小数点）
     *
     * @param string $value 待验证字符串
     * @return bool 纯数字返回 true
     */
    public static function isNumeric(string $value): bool
    {
        return ctype_digit($value);
    }

    /**
     * 验证是否为整数字符串（支持正负号）
     *
     * @param string $value 待验证字符串
     * @return bool 整数格式返回 true
     */
    public static function isInteger(string $value): bool
    {
        return (bool) preg_match('/^-?\d+$/', $value);
    }

    /**
     * 验证是否为浮点数字符串（支持正负号）
     *
     * @param string $value 待验证字符串
     * @return bool 浮点数格式返回 true
     */
    public static function isFloat(string $value): bool
    {
        return (bool) preg_match('/^-?\d+(\.\d+)?$/', $value);
    }

    /**
     * 验证是否为纯中文字符串
     *
     * @param string $value 待验证字符串
     * @param bool   $pure  true 为纯中文（不含空格），false 允许包含空格
     * @return bool 符合条件返回 true
     */
    public static function isChinese(string $value, bool $pure = true): bool
    {
        $pattern = $pure ? '/^[\x{4e00}-\x{9fa5}]+$/' : '/^[\x{4e00}-\x{9fa5}\s]+$/';
        return (bool) preg_match($pattern, $value);
    }

    /**
     * 验证字符串是否包含中文
     *
     * @param string $value 待验证字符串
     * @return bool 包含中文返回 true
     */
    public static function hasChinese(string $value): bool
    {
        return (bool) preg_match('/[\x{4e00}-\x{9fa5}]/u', $value);
    }

    /**
     * 验证字符串是否仅包含字母、数字、下划线和横线
     *
     * 常见于用户名、标识符等场景
     *
     * @param string $value 待验证字符串
     * @param int    $min   最小长度
     * @param int    $max   最大长度
     * @return bool 符合条件返回 true
     */
    public static function isAlphaNumDash(string $value, int $min = 1, int $max = 255): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]{' . $min . ',' . $max . '}$/', $value);
    }

    /**
     * 验证日期格式（YYYY-MM-DD）及有效性
     *
     * @param string $date 日期字符串
     * @return bool 格式有效且日期真实存在返回 true
     */
    public static function isDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $parts = explode('-', $date);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    /**
     * 验证日期时间格式（YYYY-MM-DD HH:ii:ss）
     *
     * @param string $datetime 日期时间字符串
     * @return bool 格式有效返回 true
     */
    public static function isDateTime(string $datetime): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime)) {
            return false;
        }
        return (bool) date_create_from_format('Y-m-d H:i:s', $datetime);
    }

    /**
     * 验证是否为有效的 JSON 字符串
     *
     * @param string $value 待验证字符串
     * @return bool 有效 JSON 返回 true
     */
    public static function isJson(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 验证是否为有效的标准 Base64 字符串
     *
     * @param string $value 待验证字符串
     * @return bool 有效 Base64 返回 true
     */
    public static function isBase64(string $value): bool
    {
        return base64_decode($value, true) !== false && base64_encode(base64_decode($value, true)) === $value;
    }

    /**
     * 验证是否为有效的十六进制字符串
     *
     * @param string $value 待验证字符串
     * @return bool 有效十六进制返回 true
     */
    public static function isHex(string $value): bool
    {
        return (bool) preg_match('/^[a-fA-F0-9]+$/', $value);
    }

    /**
     * 验证是否为标准的 UUID 格式（8-4-4-4-12）
     *
     * @param string $value 待验证字符串
     * @return bool 有效 UUID 返回 true
     */
    public static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * 验证 MAC 地址格式（支持冒号或横线分隔）
     *
     * @param string $value 待验证字符串
     * @return bool 有效 MAC 地址返回 true
     */
    public static function isMac(string $value): bool
    {
        return (bool) preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $value);
    }

    /**
     * 验证中国车牌号格式
     *
     * 支持普通车牌、新能源车牌、港澳车牌、教练车、警车等
     *
     * @param string $value 车牌号
     * @return bool 格式有效返回 true
     */
    public static function isPlateNumber(string $value): bool
    {
        return (bool) preg_match('/^[京津沪渝冀豫云辽黑湘皖鲁新苏浙赣鄂桂甘晋蒙陕吉闽贵粤青藏川宁琼][A-Z][A-HJ-NP-Z0-9]{4,5}[A-HJ-NP-Z0-9挂学警港澳]?$/', $value);
    }

    /**
     * 验证统一社会信用代码（18位）
     *
     * 采用 GB 32100-2015 标准加权校验算法
     *
     * @param string $code 统一社会信用代码
     * @return bool 格式和校验码均有效返回 true
     */
    public static function isCreditCode(string $code): bool
    {
        if (!preg_match('/^[0-9A-HJ-NPQRTUWXY]{2}\d{6}[0-9A-HJ-NPQRTUWXY]{10}$/', $code)) {
            return false;
        }

        $weights = [1, 3, 9, 27, 19, 26, 16, 17, 20, 29, 25, 13, 8, 24, 10, 30, 28];
        $chars = '0123456789ABCDEFGHJKLMNPQRTUWXY';
        $sum = 0;

        for ($i = 0; $i < 17; $i++) {
            $sum += strpos($chars, $code[$i]) * $weights[$i];
        }

        $check = (31 - ($sum % 31)) % 31;
        return $code[17] === $chars[$check];
    }

    /**
     * 验证字符串长度是否在指定范围内（按字符数，支持多字节）
     *
     * @param string $value 待验证字符串
     * @param int    $min   最小长度
     * @param int    $max   最大长度
     * @return bool 在范围内返回 true
     */
    public static function lengthBetween(string $value, int $min, int $max): bool
    {
        $len = mb_strlen($value);
        return $len >= $min && $len <= $max;
    }

    /**
     * 验证数值是否在指定范围内
     *
     * @param int|float $value 待验证数值
     * @param int|float $min   最小值
     * @param int|float $max   最大值
     * @return bool 在范围内返回 true
     */
    public static function between(int|float $value, int|float $min, int|float $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * 验证值是否在允许的枚举列表中（严格类型比较）
     *
     * @param mixed $value   待验证值
     * @param array $allowed 允许的值的数组
     * @return bool 在列表中返回 true
     */
    public static function in(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    /**
     * 使用自定义正则表达式验证字符串
     *
     * @param string $value   待验证字符串
     * @param string $pattern 正则表达式（含分隔符）
     * @return bool 匹配成功返回 true
     */
    public static function regex(string $value, string $pattern): bool
    {
        return (bool) preg_match($pattern, $value);
    }

    /**
     * 批量验证数据（管道式规则校验）
     *
     * 支持规则：required, email, mobile, url, ip, ipv4, ipv6, idcard, bankcard,
     * zipcode, numeric, integer, float, date, datetime, json, base64, uuid, mac, hex,
     * min:length, max:length, between:min,max, in:a,b,c, regex:pattern,
     * alpha_num, alpha_dash
     *
     * @param array $data  待验证数据，格式为 ['字段名' => '值']
     * @param array $rules 验证规则，格式为 ['字段名' => 'required|email|min:5']
     * @return array 错误信息数组，空数组表示全部通过
     */
    public static function batch(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $ruleString);

            foreach ($ruleList as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                } else {
                    $ruleName = $rule;
                }

                $result = match ($ruleName) {
                    'required'   => self::isNotEmpty($value),
                    'email'      => is_string($value) && self::isEmail($value),
                    'mobile'     => is_string($value) && self::isMobile($value),
                    'url'        => is_string($value) && self::isUrl($value),
                    'ip'         => is_string($value) && self::isIp($value),
                    'ipv4'       => is_string($value) && self::isIpv4($value),
                    'ipv6'       => is_string($value) && self::isIpv6($value),
                    'idcard'     => is_string($value) && self::isIdCard($value),
                    'bankcard'   => is_string($value) && self::isBankCard($value),
                    'zipcode'    => is_string($value) && self::isZipCode($value),
                    'numeric'    => is_string($value) && self::isNumeric($value),
                    'integer'    => is_string($value) && self::isInteger($value),
                    'float'      => is_string($value) && self::isFloat($value),
                    'date'       => is_string($value) && self::isDate($value),
                    'datetime'   => is_string($value) && self::isDateTime($value),
                    'json'       => is_string($value) && self::isJson($value),
                    'base64'     => is_string($value) && self::isBase64($value),
                    'uuid'       => is_string($value) && self::isUuid($value),
                    'mac'        => is_string($value) && self::isMac($value),
                    'hex'        => is_string($value) && self::isHex($value),
                    'min'        => is_string($value) && isset($params[0]) && mb_strlen($value) >= (int) $params[0],
                    'max'        => is_string($value) && isset($params[0]) && mb_strlen($value) <= (int) $params[0],
                    'between'    => is_string($value) && isset($params[0], $params[1]) && self::lengthBetween($value, (int) $params[0], (int) $params[1]),
                    'in'         => isset($params[0]) && self::in($value, $params),
                    'regex'      => is_string($value) && isset($params[0]) && self::regex($value, $params[0]),
                    'alpha_num'  => is_string($value) && ctype_alnum($value),
                    'alpha_dash' => is_string($value) && self::isAlphaNumDash($value),
                    default      => true,
                };

                if (!$result) {
                    $errors[$field][] = self::formatError($ruleName, $field, $params);
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * 将15位身份证号转换为18位
     *
     * @param string $idCard15 15位身份证号
     * @return string|null 转换后的18位身份证号，无效时返回 null
     */
    private static function idCard15To18(string $idCard15): ?string
    {
        if (!preg_match('/^\d{15}$/', $idCard15)) {
            return null;
        }

        $prefix = substr($idCard15, 0, 6);
        $birth = '19' . substr($idCard15, 6, 6);
        $suffix = substr($idCard15, 12, 3);
        $idCard17 = $prefix . $birth . $suffix;

        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += (int) $idCard17[$i] * self::IDCARD_WEIGHTS[$i];
        }

        return $idCard17 . self::IDCARD_CHECK_CODES[$sum % 11];
    }

    /**
     * 格式化验证错误信息
     *
     * @param string $rule   规则名称
     * @param string $field  字段名
     * @param array  $params 规则参数
     * @return string 中文错误描述
     */
    private static function formatError(string $rule, string $field, array $params): string
    {
        return match ($rule) {
            'required'   => "{$field} 不能为空",
            'email'      => "{$field} 必须是有效的邮箱地址",
            'mobile'     => "{$field} 必须是有效的手机号",
            'url'        => "{$field} 必须是有效的URL地址",
            'ip'         => "{$field} 必须是有效的IP地址",
            'ipv4'       => "{$field} 必须是有效的IPv4地址",
            'ipv6'       => "{$field} 必须是有效的IPv6地址",
            'idcard'     => "{$field} 必须是有效的身份证号",
            'bankcard'   => "{$field} 必须是有效的银行卡号",
            'zipcode'    => "{$field} 必须是有效的邮政编码",
            'numeric'    => "{$field} 必须是纯数字",
            'integer'    => "{$field} 必须是整数",
            'float'      => "{$field} 必须是浮点数",
            'date'       => "{$field} 必须是有效的日期",
            'datetime'   => "{$field} 必须是有效的日期时间",
            'json'       => "{$field} 必须是有效的JSON",
            'base64'     => "{$field} 必须是有效的Base64",
            'uuid'       => "{$field} 必须是有效的UUID",
            'mac'        => "{$field} 必须是有效的MAC地址",
            'hex'        => "{$field} 必须是有效的十六进制",
            'min'        => "{$field} 长度不能小于 {$params[0]}",
            'max'        => "{$field} 长度不能大于 {$params[0]}",
            'between'    => "{$field} 长度必须在 {$params[0]} 到 {$params[1]} 之间",
            'in'         => "{$field} 必须在指定范围内",
            'regex'      => "{$field} 格式不正确",
            'alpha_num'  => "{$field} 只能包含字母和数字",
            'alpha_dash' => "{$field} 只能包含字母、数字、下划线和横线",
            default      => "{$field} 验证失败",
        };
    }
}

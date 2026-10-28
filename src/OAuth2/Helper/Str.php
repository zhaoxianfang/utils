<?php
declare(strict_types=1);

namespace zxf\Utils\OAuth2\Helper;

/**
 * OAuth2 模块内部字符串工具。
 *
 * 提供首字母大写、查询串拼装、安全随机串生成与客户端 IP 读取等小工具，
 * 供各平台 Gateway 在构造授权请求、生成 state 时使用。
 *
 * @package zxf\Utils\OAuth2\Helper
 * @version 1.0.0
 * @license MIT
 */
class Str
{
    /**
     * 将字符串转换为「首字母大写、其余小写」的形式。
     *
     * @param  string  $str  原始字符串
     * @return string 格式化后的字符串
     *
     * @example
     * ```php
     * Str::uFirst('HELLO'); // 'Hello'
     * Str::uFirst('wOrLd'); // 'World'
     * ```
     */
    public static function uFirst($str): string
    {
        return ucfirst(strtolower($str));
    }

    /**
     * 将关联数组拼装为查询字符串。
     *
     * 常用于构造授权跳转 URL 的 query 部分。默认排除 sign 字段，
     * 避免把签名本身混入「待签名参数」而导致验签不一致。
     *
     * @param  array  $params     参数键值对
     * @param  bool   $urlencode  是否对值做 rawurlencode（值含中文或特殊字符时应传 true）
     * @param  array  $except     需要排除的参数名列表
     * @return string 形如 "a=1&b=2" 的查询字符串（无参数时为空字符串）
     *
     * @example
     * ```php
     * Str::buildParams(['code' => 'x', 'sign' => 'y']); // "code=x"（sign 被排除）
     * Str::buildParams(['q' => '中文'], true);          // "q=%E4%B8%AD%E6%96%87"
     * ```
     */
    public static function buildParams($params, $urlencode = false, $except = ['sign']): string
    {
        $param_str = '';
        foreach ($params as $k => $v) {
            if (in_array($k, $except)) {
                continue;
            }
            $param_str .= $k . '=';
            $param_str .= $urlencode ? rawurlencode($v) : $v;
            $param_str .= '&';
        }
        return rtrim($param_str, '&');
    }

    /**
     * 生成不可预测的随机字符串（用于 OAuth2 state 等安全敏感场景）。
     *
     * 必须使用 CSPRNG：随机串用作 CSRF 防伪的 state，若用 str_shuffle()/rand()
     * 这类可预测、熵有限的伪随机源，攻击者可推测 state 从而绕过校验。
     *
     * @param  int     $length  需要的字符数，小于 1 时按 1 处理
     * @return string 十六进制随机字符串
     *
     * @example
     * ```php
     * $state = Str::random(16); // 例如 'a3f0c1e9b2d47a58'
     * ```
     */
    public static function random($length = 16): string
    {
        // state 参数用于 CSRF 防伪，必须不可预测：
        // 原实现 str_shuffle()（底层 rand()）可预测且默认熵有限，改用 CSPRNG
        $length = max(1, (int) $length);
        $bytes  = random_bytes((int) ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }

    /**
     * 获取客户端 IP 地址。
     *
     * 安全说明：只读取 REMOTE_ADDR（由 TCP 连接决定，客户端无法伪造），
     * 不信任 X-Forwarded-For / Client-IP 等任何代理头，避免 IP 伪造。
     *
     * @return string 客户端 IP；两者都取不到时回退为 '127.0.0.1'
     */
    public static function getClientIP()
    {
        $ip = '127.0.0.1';
        if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else if (getenv('REMOTE_ADDR')) {
            $ip = getenv('REMOTE_ADDR');
        }
        return $ip;
    }
}

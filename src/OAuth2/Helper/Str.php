<?php
declare(strict_types=1);

namespace zxf\Utils\OAuth2\Helper;

class Str
{
    public static function uFirst($str): string
    {
        return ucfirst(strtolower($str));
    }

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

    public static function random($length = 16): string
    {
        // state 参数用于 CSRF 防伪，必须不可预测：
        // 原实现 str_shuffle()（底层 rand()）可预测且默认熵有限，改用 CSPRNG
        $length = max(1, (int) $length);
        $bytes  = random_bytes((int) ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }

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

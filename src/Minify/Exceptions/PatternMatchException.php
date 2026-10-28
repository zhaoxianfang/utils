<?php

declare(strict_types=1);

namespace zxf\Utils\Minify\Exceptions;

/**
 * 正则表达式匹配异常。
 *
 * 当 PCRE 正则执行失败（如模式非法、回溯超限）时抛出。
 *
 * @package zxf\Utils\Minify\Exceptions
 * @version 1.0.0
 * @license MIT
 */
class PatternMatchException extends BasicException
{
    /**
     * 根据 preg_last_error() 构造异常实例。
     *
     * 在 PHP 8+ 上会额外附带 preg_last_error_msg() 返回的详细描述，
     * 便于排查正则执行失败的具体原因。
     *
     * @param string $msg 错误描述信息（末尾会拼接 PCRE 错误码）
     *
     * @return self 携带 PCRE 错误码的异常实例
     */
    public static function fromLastError($msg)
    {
        $msg .= ': Error ' . preg_last_error();
        if (PHP_MAJOR_VERSION >= 8) {
            $msg .= ' - ' . preg_last_error_msg();
        }

        return new PatternMatchException($msg);
    }
}

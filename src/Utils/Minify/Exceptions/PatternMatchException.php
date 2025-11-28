<?php

/**
 * Pattern match exception.
 */

namespace zxf\Utils\Minify\Exceptions;

/**
 * Pattern Match Exception Class.
 */
class PatternMatchException extends BasicException
{
    /**
     * Create an exception from preg_last_error.
     *
     * @param string $msg Error message
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

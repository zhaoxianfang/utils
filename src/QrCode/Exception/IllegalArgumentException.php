<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

/**
 * 非法参数异常
 *
 * 当传递给方法的参数无效时抛出
 */
class IllegalArgumentException extends BaseInvalidArgumentException
{
}

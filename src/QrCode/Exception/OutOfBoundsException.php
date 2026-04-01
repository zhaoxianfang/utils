<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Exception;

use OutOfBoundsException as BaseOutOfBoundsException;

/**
 * 越界异常
 *
 * 当数值超出有效范围时抛出
 */
class OutOfBoundsException extends BaseOutOfBoundsException
{
}

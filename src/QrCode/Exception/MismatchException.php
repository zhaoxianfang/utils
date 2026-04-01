<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Exception;

use RuntimeException;

/**
 * 类型不匹配异常
 *
 * 当尝试将枚举与不同类型的枚举进行比较时抛出
 */
class MismatchException extends RuntimeException
{
}

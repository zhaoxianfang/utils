<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Exception;

use RuntimeException;

/**
 * 不支持克隆异常
 *
 * 当尝试克隆枚举实例时抛出
 */
class CloneNotSupportedException extends RuntimeException
{
}

<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Exception;

use RuntimeException;

/**
 * 不支持反序列化异常
 *
 * 当尝试反序列化枚举实例时抛出
 */
class UnserializeNotSupportedException extends RuntimeException
{
}

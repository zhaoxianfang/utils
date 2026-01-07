<?php

declare(strict_types=1);

namespace zxf\Utils\Dom\Exceptions;

use RuntimeException;

/**
 * 无效选择器异常类
 * 
 * 当提供的选择器表达式无效或无法解析时抛出
 * 
 * @package zxf\Utils\Dom\Exceptions
 */
class InvalidSelectorException extends RuntimeException
{
}

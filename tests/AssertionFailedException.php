<?php

declare(strict_types=1);

namespace zxf\Utils\Tests;

use RuntimeException;

/**
 * 断言失败异常
 *
 * 使用独立异常类型（而非 PHPUnit 风格）以便测试运行器区分
 * 「断言失败」与「被测代码抛出的意外异常」。
 */
final class AssertionFailedException extends RuntimeException
{
}

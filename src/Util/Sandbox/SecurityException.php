<?php

declare(strict_types=1);

namespace zxf\Util\Sandbox;

/**
 * 安全异常类
 *
 * 用于表示安全相关的异常情况
 *
 * @package PhpSandbox
 */
final class SecurityException extends \Exception
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("安全限制: " . $message, $code, $previous);
    }
}

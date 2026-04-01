<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Exceptions;

/**
 * 渲染异常
 * 
 * 当条形码渲染失败时抛出
 */
class RenderException extends BarcodeException
{
    /**
     * 构造函数
     * 
     * @param string $message 错误信息
     * @param int    $code    错误码
     */
    public function __construct(string $message = '条形码渲染失败', int $code = self::ERROR_RENDER_FAILED)
    {
        parent::__construct($message, $code);
    }
}

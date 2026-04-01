<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Exceptions;

/**
 * 无效数据异常
 * 
 * 当提供的条形码数据格式不正确或不符合要求时抛出
 */
class InvalidDataException extends BarcodeException
{
    /**
     * 构造函数
     * 
     * @param string $message 错误信息
     * @param int    $code    错误码
     */
    public function __construct(string $message = '条形码数据格式无效', int $code = self::ERROR_INVALID_DATA)
    {
        parent::__construct($message, $code);
    }
}

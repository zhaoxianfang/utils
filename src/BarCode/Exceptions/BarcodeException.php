<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Exceptions;

use Exception;

/**
 * 条形码异常基类
 * 
 * 所有条形码相关异常都继承自此基类
 * 便于统一捕获和处理条形码生成过程中的错误
 */
class BarcodeException extends Exception
{
    /**
     * 错误码定义
     */
    public const ERROR_INVALID_DATA = 1001;        // 数据格式无效
    public const ERROR_INVALID_CHECKSUM = 1002;    // 校验位错误
    public const ERROR_UNSUPPORTED_TYPE = 1003;    // 不支持的条码类型
    public const ERROR_RENDER_FAILED = 1004;       // 渲染失败
    public const ERROR_FILE_SAVE_FAILED = 1005;    // 文件保存失败
    public const ERROR_INVALID_CONFIG = 1006;      // 配置无效

    /**
     * 构造函数
     * 
     * @param string $message 错误信息
     * @param int    $code    错误码
     * @param Exception|null $previous 前一个异常
     */
    public function __construct(string $message, int $code = self::ERROR_INVALID_DATA, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

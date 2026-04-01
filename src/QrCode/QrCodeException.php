<?php

namespace zxf\Utils\QrCode;

use Exception;

/**
 * 二维码异常类
 */
class QrCodeException extends Exception
{
    /**
     * 创建编码异常
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function encoding(string $message): self
    {
        return new self('编码错误: ' . $message);
    }

    /**
     * 创建渲染异常
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function rendering(string $message): self
    {
        return new self('渲染错误: ' . $message);
    }

    /**
     * 创建保存异常
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function saving(string $message): self
    {
        return new self('保存错误: ' . $message);
    }

    /**
     * 创建配置异常
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function configuration(string $message): self
    {
        return new self('配置错误: ' . $message);
    }
}

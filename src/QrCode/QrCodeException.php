<?php

declare(strict_types=1);

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
     * 用于内容无法按所选模式/字符集编码、或数据量超出容量等场景，
     * 返回的实例消息会统一带上“编码错误: ”前缀。
     *
     * @param string $message 错误消息（不含前缀）
     * @return self 带编码前缀的异常实例
     */
    public static function encoding(string $message): self
    {
        return new self('编码错误: ' . $message);
    }

    /**
     * 创建渲染异常
     *
     * 用于 GD 绘图失败、图像资源无效等渲染阶段问题，返回的实例消息
     * 会统一带上“渲染错误: ”前缀。
     *
     * @param string $message 错误消息（不含前缀）
     * @return self 带渲染前缀的异常实例
     */
    public static function rendering(string $message): self
    {
        return new self('渲染错误: ' . $message);
    }

    /**
     * 创建保存异常
     *
     * 用于写文件失败、目录不可写等落盘问题，返回的实例消息会统一
     * 带上“保存错误: ”前缀。
     *
     * @param string $message 错误消息（不含前缀）
     * @return self 带保存前缀的异常实例
     */
    public static function saving(string $message): self
    {
        return new self('保存错误: ' . $message);
    }

    /**
     * 创建配置异常
     *
     * 用于尺寸、边距、颜色等参数取值非法的情况，返回的实例消息会统一
     * 带上“配置错误: ”前缀。
     *
     * @param string $message 错误消息（不含前缀）
     * @return self 带配置前缀的异常实例
     */
    public static function configuration(string $message): self
    {
        return new self('配置错误: ' . $message);
    }
}

<?php

namespace zxf\Utils\QrCode;

/**
 * 二维码输出格式枚举类
 */
final class Format
{
    /** @var string PNG格式 */
    public const PNG = 'png';

    /** @var string JPEG格式 */
    public const JPEG = 'jpeg';

    /** @var string GIF格式 */
    public const GIF = 'gif';

    /** @var string JPG格式（JPEG的别名） */
    public const JPG = 'jpg';

    /**
     * 获取所有支持的格式
     *
     * @return array
     */
    public static function getAll(): array
    {
        return [self::PNG, self::JPEG, self::GIF, self::JPG];
    }

    /**
     * 检查格式是否有效
     *
     * @param string $format 格式
     * @return bool
     */
    public static function isValid(string $format): bool
    {
        return in_array(strtolower($format), self::getAll());
    }

    /**
     * 获取格式的MIME类型
     *
     * @param string $format 格式
     * @return string
     * @throws Exception
     */
    public static function getMimeType(string $format): string
    {
        return match(strtolower($format)) {
            self::PNG => 'image/png',
            self::JPEG, self::JPG => 'image/jpeg',
            self::GIF => 'image/gif',
            default => throw new Exception('不支持的格式: ' . $format)
        };
    }
}

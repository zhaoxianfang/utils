<?php

namespace zxf\Utils\QrCode;

use Exception;
use GdImage;
use zxf\Utils\QrCode\Color\Color;

/**
 * 二维码高级功能类
 * 提供渐变背景、水印、特效、批处理等高级功能
 */
class AdvancedFeatures
{
    /**
     * 创建渐变背景色
     *
     * @param string $startColor 起始颜色（十六进制）
     * @param string $endColor 结束颜色（十六进制）
     * @param string $direction 渐变方向（horizontal, vertical, diagonal）
     * @return GdImage 渐变图像资源
     */
    public static function createGradientBackground(int $width, int $height, string $startColor, string $endColor, string $direction = 'vertical'): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        $start = Color::fromHex($startColor);
        $end = Color::fromHex($endColor);

        if ($direction === 'horizontal') {
            for ($x = 0; $x < $width; $x++) {
                $ratio = $x / $width;
                $r = (int)($start->getRed() + ($end->getRed() - $start->getRed()) * $ratio);
                $g = (int)($start->getGreen() + ($end->getGreen() - $start->getGreen()) * $ratio);
                $b = (int)($start->getBlue() + ($end->getBlue() - $start->getBlue()) * $ratio);
                $color = imagecolorallocate($image, $r, $g, $b);
                imageline($image, $x, 0, $x, $height, $color);
            }
        } elseif ($direction === 'vertical') {
            for ($y = 0; $y < $height; $y++) {
                $ratio = $y / $height;
                $r = (int)($start->getRed() + ($end->getRed() - $start->getRed()) * $ratio);
                $g = (int)($start->getGreen() + ($end->getGreen() - $start->getGreen()) * $ratio);
                $b = (int)($start->getBlue() + ($end->getBlue() - $start->getBlue()) * $ratio);
                $color = imagecolorallocate($image, $r, $g, $b);
                imageline($image, 0, $y, $width, $y, $color);
            }
        } else { // diagonal
            $maxDim = max($width, $height);
            for ($i = 0; $i < $maxDim; $i++) {
                $ratio = $i / $maxDim;
                $r = (int)($start->getRed() + ($end->getRed() - $start->getRed()) * $ratio);
                $g = (int)($start->getGreen() + ($end->getGreen() - $start->getGreen()) * $ratio);
                $b = (int)($start->getBlue() + ($end->getBlue() - $start->getBlue()) * $ratio);
                $color = imagecolorallocate($image, $r, $g, $b);
                imageline($image, 0, $i, min($i, $width), $i, $color);
            }
        }

        return $image;
    }

    /**
     * 添加水印到二维码
     *
     * @param GdImage $qrImage 二维码图像
     * @param string $watermarkPath 水印图片路径
     * @param string $position 水印位置（top-left, top-right, bottom-left, bottom-right, center）
     * @param int $opacity 不透明度（0-100）
     * @return GdImage 添加水印后的图像
     */
    public static function addWatermark(GdImage $qrImage, string $watermarkPath, string $position = 'bottom-right', int $opacity = 50): GdImage
    {
        $watermark = self::loadImage($watermarkPath);
        if ($watermark === false) {
            return $qrImage;
        }

        // 调整水印大小为二维码的20%
        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);
        $wmWidth = imagesx($watermark);
        $wmHeight = imagesy($watermark);
        $newWmWidth = (int)($qrWidth * 0.2);
        $newWmHeight = (int)($wmHeight * ($newWmWidth / $wmWidth));

        $resizedWatermark = imagecreatetruecolor($newWmWidth, $newWmHeight);
        imagealphablending($resizedWatermark, false);
        imagesavealpha($resizedWatermark, true);
        imagecopyresampled($resizedWatermark, $watermark, 0, 0, 0, 0, $newWmWidth, $newWmHeight, $wmWidth, $wmHeight);

        // 应用透明度
        if ($opacity < 100) {
            self::applyImageOpacity($resizedWatermark, $opacity);
        }

        // 计算水印位置
        $x = self::calculatePosition($qrWidth, $newWmWidth, $position, 'x');
        $y = self::calculatePosition($qrHeight, $newWmHeight, $position, 'y');

        // 合并水印
        imagealphablending($qrImage, true);
        imagecopy($qrImage, $resizedWatermark, $x, $y, 0, 0, $newWmWidth, $newWmHeight);

        imagedestroy($watermark);
        imagedestroy($resizedWatermark);

        return $qrImage;
    }

    /**
     * 添加文字水印
     *
     * @param GdImage $qrImage 二维码图像
     * @param string $text 水印文字
     * @param string $fontPath 字体路径
     * @param int $fontSize 字体大小
     * @param string $color 颜色（十六进制）
     * @param string $position 位置
     * @param int $angle 旋转角度
     * @return GdImage 添加文字水印后的图像
     */
    public static function addTextWatermark(
        GdImage $qrImage,
        string $text,
        string $fontPath,
        int $fontSize = 14,
        string $color = '#000000',
        string $position = 'bottom-right',
        int $angle = 0
    ): GdImage {
        if (!file_exists($fontPath)) {
            return $qrImage;
        }

        $colorObj = Color::fromHex($color);
        $textColor = $colorObj->toGdColor($qrImage);

        // 获取文本边界框
        $bbox = imagettfbbox($fontSize, $angle, $fontPath, $text);
        $textWidth = abs($bbox[2] - $bbox[0]);
        $textHeight = abs($bbox[7] - $bbox[1]);

        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);

        // 计算文本位置
        $x = self::calculatePosition($qrWidth, $textWidth, $position, 'x');
        $y = self::calculatePosition($qrHeight, $textHeight, $position, 'y');

        imagettftext($qrImage, $fontSize, $angle, $x, $y, $textColor, $fontPath, $text);

        return $qrImage;
    }

    /**
     * 批量生成二维码
     *
     * @param array $dataList 数据列表
     * @param string $outputDir 输出目录
     * @param int $size 二维码尺寸
     * @param string $prefix 文件名前缀
     * @return array 生成结果统计
     */
    public static function batchGenerate(array $dataList, string $outputDir, int $size = 300, string $prefix = 'qr_'): array
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $results = [
            'total' => count($dataList),
            'success' => 0,
            'failed' => 0,
            'files' => []
        ];

        foreach ($dataList as $index => $data) {
            try {
                $filename = $outputDir . '/' . $prefix . $index . '.png';
                QrCode::make($data)
                    ->size($size)
                    ->save($filename);

                $results['success']++;
                $results['files'][] = $filename;
            } catch (Exception $e) {
                $results['failed']++;
                $results['files'][] = "Error: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 从模板生成二维码
     *
     * @param string $templatePath 模板图片路径
     * @param string $data 二维码数据
     * @param string $outputPath 输出路径
     * @param array $qrPosition 二维码位置 [x, y, width, height]
     * @param array $options 二维码选项
     * @return bool 是否成功
     */
    public static function generateFromTemplate(
        string $templatePath,
        string $data,
        string $outputPath,
        array $qrPosition,
        array $options = []
    ): bool {
        $template = self::loadImage($templatePath);
        if ($template === false) {
            return false;
        }

        // 生成二维码
        $qrCode = QrCode::make($data)
            ->size($qrPosition[2]);

        foreach ($options as $key => $value) {
            $qrCode = $qrCode->$key($value);
        }

        $qrImage = $qrCode->render();

        // 合并到模板
        imagecopyresampled(
            $template,
            $qrImage,
            $qrPosition[0],
            $qrPosition[1],
            0,
            0,
            $qrPosition[2],
            $qrPosition[3],
            imagesx($qrImage),
            imagesy($qrImage)
        );

        // 保存结果
        $extension = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($template, $outputPath, 90);
                break;
            case 'png':
            default:
                imagepng($template, $outputPath);
                break;
        }

        imagedestroy($template);
        imagedestroy($qrImage);

        return true;
    }

    /**
     * 添加边框效果
     *
     * @param GdImage $image 图像
     * @param string $color 边框颜色
     * @param int $thickness 边框厚度
     * @param int $borderRadius 圆角半径
     * @return GdImage 添加边框后的图像
     */
    public static function addBorder(GdImage $image, string $color = '#000000', int $thickness = 2, int $borderRadius = 0): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $borderColor = Color::fromHex($color)->toGdColor($image);

        if ($borderRadius > 0) {
            // 绘制圆角边框
            for ($i = 0; $i < $thickness; $i++) {
                $x1 = $i;
                $y1 = $i;
                $x2 = $width - $i - 1;
                $y2 = $height - $i - 1;

                imagesetthickness($image, 1);
                imagerectangle($image, $x1, $y1, $x2, $y2, $borderColor);
            }
        } else {
            // 普通矩形边框
            imagesetthickness($image, $thickness);
            imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);
        }

        return $image;
    }

    /**
     * 加载图片
     *
     * @param string $path 图片路径
     * @return GdImage|false
     */
    private static function loadImage(string $path)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match($extension) {
            'jpeg', 'jpg' => imagecreatefromjpeg($path),
            'png' => imagecreatefrompng($path),
            'gif' => imagecreatefromgif($path),
            'webp' => imagecreatefromwebp($path),
            default => false
        };
    }

    /**
     * 应用图像透明度
     *
     * @param GdImage $image 图像
     * @param int $opacity 透明度（0-100）
     */
    private static function applyImageOpacity(GdImage $image, int $opacity): void
    {
        imagealphablending($image, true);
        imagesavealpha($image, true);

        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $newAlpha = (int)($alpha + (127 - $alpha) * (100 - $opacity) / 100);
                $newColor = ($rgba & 0xFFFFFF) | ($newAlpha << 24);
                imagesetpixel($image, $x, $y, $newColor);
            }
        }
    }

    /**
     * 计算位置坐标
     *
     * @param int $containerSize 容器尺寸
     * @param int $itemSize 项目尺寸
     * @param string $position 位置
     * @param string $axis 轴（x或y）
     * @return int 坐标
     */
    private static function calculatePosition(int $containerSize, int $itemSize, string $position, string $axis): int
    {
        $margin = (int)($containerSize * 0.02); // 2% 边距

        return match($position) {
            'top-left' => $margin,
            'top-right' => $containerSize - $itemSize - $margin,
            'bottom-left' => $margin,
            'bottom-right' => $containerSize - $itemSize - $margin,
            'center' => (int)(($containerSize - $itemSize) / 2),
            default => $margin
        };
    }
}

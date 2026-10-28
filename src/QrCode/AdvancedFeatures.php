<?php

declare(strict_types=1);

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
     * 创建渐变背景色（优化性能版本）
     *
     * @param int $width 宽度
     * @param int $height 高度
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

        // 优化：使用更高效的插值算法
        if ($direction === 'horizontal') {
            for ($x = 0; $x < $width; $x++) {
                $ratio = $x / max(1, $width - 1);
                $r = (int)round($start->getRed() + ($end->getRed() - $start->getRed()) * $ratio);
                $g = (int)round($start->getGreen() + ($end->getGreen() - $start->getGreen()) * $ratio);
                $b = (int)round($start->getBlue() + ($end->getBlue() - $start->getBlue()) * $ratio);
                $color = imagecolorallocate($image, $r, $g, $b);
                imageline($image, $x, 0, $x, $height - 1, $color);
            }
        } elseif ($direction === 'vertical') {
            for ($y = 0; $y < $height; $y++) {
                $ratio = $y / max(1, $height - 1);
                $r = (int)round($start->getRed() + ($end->getRed() - $start->getRed()) * $ratio);
                $g = (int)round($start->getGreen() + ($end->getGreen() - $start->getGreen()) * $ratio);
                $b = (int)round($start->getBlue() + ($end->getBlue() - $start->getBlue()) * $ratio);
                $color = imagecolorallocate($image, $r, $g, $b);
                imageline($image, 0, $y, $width - 1, $y, $color);
            }
        } else { // diagonal
            $maxDim = max($width, $height);
            for ($i = 0; $i < $maxDim; $i++) {
                $ratio = $i / max(1, $maxDim - 1);
                $r = (int)round($start->getRed() + ($end->getRed() - $start->getRed()) * $ratio);
                $g = (int)round($start->getGreen() + ($end->getGreen() - $start->getGreen()) * $ratio);
                $b = (int)round($start->getBlue() + ($end->getBlue() - $start->getBlue()) * $ratio);
                $color = imagecolorallocate($image, $r, $g, $b);
                imageline($image, 0, $i, min($i, $width - 1), $i, $color);
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

        return $qrImage;
    }

    /**
     * 添加文字水印
     *
     * 使用 GD 的 TTF 接口把文字直接绘制到给定图像上，位置由 $position 决定，
     * 四周自动留出容器尺寸 2% 的边距。字体文件不存在时不做任何绘制、原样
     * 返回传入的图像；颜色字符串支持 Color::fromHex() 认可的十六进制格式。
     *
     * @param GdImage $qrImage 二维码图像
     * @param string $text 水印文字
     * @param string $fontPath 字体路径（需为 GD 可读取的 TTF/OTF 文件）
     * @param int $fontSize 字体大小（磅值）
     * @param string $color 颜色（十六进制，如 #000000）
     * @param string $position 水印位置，支持 top-left/top-right/bottom-left/bottom-right/center
     * @param int $angle 旋转角度（度，逆时针为正）
     * @return GdImage 添加文字水印后的图像（与传入的是同一资源）
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
     * 批量生成二维码（优化内存管理版本）
     *
     * @param array $dataList 数据列表
     * @param string $outputDir 输出目录
     * @param int $size 二维码尺寸
     * @param string $prefix 文件名前缀
     * @param array $options 二维码选项
     * @return array 生成结果统计
     */
    public static function batchGenerate(array $dataList, string $outputDir, int $size = 300, string $prefix = 'qr_', array $options = []): array
    {
        // 验证参数
        if (empty($dataList)) {
            throw new Exception('数据列表不能为空');
        }

        if ($size < 50 || $size > 5000) {
            throw new Exception('二维码尺寸必须在50-5000像素之间');
        }

        // 创建输出目录
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
                throw new Exception('无法创建输出目录: ' . $outputDir);
            }
        }

        if (!is_writable($outputDir)) {
            throw new Exception('输出目录不可写: ' . $outputDir);
        }

        $results = [
            'total' => count($dataList),
            'success' => 0,
            'failed' => 0,
            'files' => []
        ];

        // 限制批量数量，防止内存溢出
        $batchSize = 100;
        $batches = array_chunk($dataList, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            foreach ($batch as $index => $data) {
                $realIndex = $batchIndex * $batchSize + $index;

                try {
                    if (empty($data)) {
                        throw new Exception('数据内容不能为空');
                    }

                    $filename = $outputDir . '/' . $prefix . $realIndex . '.png';
                    $qrCode = QrCode::make($data)->size($size);

                    // 应用选项
                    foreach ($options as $method => $value) {
                        if (method_exists($qrCode, $method)) {
                            $qrCode = $qrCode->$method($value);
                        }
                    }

                    $qrCode->save($filename);

                    $results['success']++;
                    $results['files'][] = $filename;

                    // 显式释放内存
                    unset($qrCode);
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['files'][] = "Index {$realIndex}: " . $e->getMessage();
                }
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
     * @throws Exception 如果模板文件不存在或无法处理
     */
    public static function generateFromTemplate(
        string $templatePath,
        string $data,
        string $outputPath,
        array $qrPosition,
        array $options = []
    ): bool {
        // 验证模板文件
        if (!file_exists($templatePath)) {
            throw new Exception('模板文件不存在: ' . $templatePath);
        }

        $template = self::loadImage($templatePath);
        if ($template === false) {
            throw new Exception('无法加载模板图片: ' . $templatePath);
        }

        // 验证二维码位置参数
        if (count($qrPosition) !== 4) {
            throw new Exception('二维码位置参数必须包含4个元素: [x, y, width, height]');
        }

        // 生成二维码
        try {
            $qrCode = QrCode::make($data)
                ->size($qrPosition[2]);

            foreach ($options as $key => $value) {
                $qrCode = $qrCode->$key($value);
            }

            $qrImage = $qrCode->render();

            // 合并到模板
            $result = imagecopyresampled(
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
            $saveResult = match($extension) {
                'jpeg', 'jpg' => imagejpeg($template, $outputPath, 90),
                'png' => imagepng($template, $outputPath, 9),
                'gif' => imagegif($template, $outputPath),
                'webp' => imagewebp($template, $outputPath, 90),
                default => throw new Exception('不支持的输出格式: ' . $extension)
            };

            return $result !== false && $saveResult !== false;
        } catch (Exception $e) {
            throw new Exception('生成二维码失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 添加边框效果
     *
     * 在图像最外沿绘制矩形描边：$borderRadius 为 0 时用一条指定粗细的
     * 矩形框；大于 0 时改为逐层内缩、每层 1 像素的 $thickness 层描边
     * （当前实现只做内缩，并不绘制圆角）。描边采用叠加绘制，会覆盖边缘
     * 原有像素。
     *
     * @param GdImage $image 图像
     * @param string $color 边框颜色（十六进制，如 #000000）
     * @param int $thickness 边框厚度（像素）
     * @param int $borderRadius 圆角半径，0 表示直角边框；大于 0 仅切换描边方式
     * @return GdImage 添加边框后的图像（与传入的是同一资源）
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
     * 按扩展名加载图片
     *
     * 仅根据路径后缀（jpeg/jpg、png、gif、webp，大小写不敏感）选择对应的
     * GD 解码函数，不校验文件内容；后缀不在支持范围内时直接返回 false。
     * 文件存在但内容损坏时，由对应 imagecreatefrom* 返回 false。
     *
     * @param string $path 图片路径
     * @return GdImage|false 解码成功返回图像资源，格式不支持或解码失败返回 false
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
     * 逐像素读取 GD 的 alpha 通道（0 完全不透明 ~ 127 完全透明），按
     * $opacity 比例把原 alpha 向 127 推进后再写回，因此 $opacity 越小
     * 图像越透明，等于 100 时 alpha 保持不变。处理前会开启 alpha
     * 混合与保存，适用于真彩色图像；逐像素遍历，大图开销较高。
     *
     * @param GdImage $image 图像
     * @param int $opacity 不透明度百分比，0 表示全透明、100 表示保持原样
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
     * 计算水印在容器中的落点坐标
     *
     * 先取容器尺寸 2% 作为内边距，再按 $position 返回对应的起点：
     * left 系列贴左边距，right 系列贴右并用容器尺寸减去项目尺寸，
     * center 居中，无法识别的位置按左上角处理。实现中 $axis 仅用于
     * 语义区分，返回值与之无关。
     *
     * @param int $containerSize 容器尺寸（对应轴上的总长度）
     * @param int $itemSize 项目尺寸（水印自身的宽度或高度）
     * @param string $position 位置标识，支持 top-left/top-right/bottom-left/bottom-right/center
     * @param string $axis 轴标识（'x' 或 'y'），仅作语义说明
     * @return int 该轴上的起始坐标（像素）
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

<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Renderer;

use zxf\Utils\BarCode\DTO\BarcodeConfig;
use zxf\Utils\BarCode\Exceptions\RenderException;

/**
 * PNG 渲染器（动态文字定位版）
 *
 * 【核心特性】
 * - 动态检测长竖线（保护符）实际像素位置
 * - 根据条码内容和结构自动分段显示数字
 * - 动态计算字号大小适配条码宽度
 * - 动态计算上下偏移距离
 * - 每段数字在各自区域内均匀分布
 *
 * 【支持的长竖线特征条码】
 * - EAN-13: 分段 1+6+6（第1位左静区，2-7位左数据区，8-13位右数据区）
 * - ISSN: 基于EAN-13，显示格式不同
 * - UPC-A: 分段 1+5+5+1（第1位左静区，2-6位左数据区，7-11位右数据区，12位右静区）
 * - EAN-8: 分段 4+4（1-4位左数据区，5-8位右数据区）
 */
class PngRenderer extends BaseRenderer
{
    protected const TYPE = 'png';

    /** @var \GdImage|null GD图像资源 */
    protected ?\GdImage $image = null;

    /** @var int 动态计算的字号，用于长竖线条码的自动适配（GD 内置字体 1-5） */
    protected int $dynamicFontSize = 5;

    /** @var int 动态计算的文本Y轴偏移量，确保文字与长竖线不重叠 */
    protected int $dynamicTextOffset = 3;

    public function __construct(?BarcodeConfig $config = null)
    {
        parent::__construct($config);
    }

    /**
     * 渲染条形码为PNG
     */
    public function render(array $barcodeData, string $data, array $config = []): string
    {
        $this->config = BarcodeConfig::fromArray(array_merge($this->config->toArray(), $config));
        $this->barcodeStructure = []; // 重置结构分析
        $this->renderModuleSize = $this->resolveModuleSize($barcodeData);

        $this->createImage($barcodeData);
        $this->drawBarcode($barcodeData);
        
        if ($this->bearerBarEnabled) {
            $this->drawBearerBar();
        }
        
        if ($this->config->showText) {
            $this->drawText($data);
        }
        
        if ($this->watermarkText !== null) {
            $this->drawWatermark();
        }

        if ($this->borderWidth > 0) {
            $this->drawBorder();
        }

        return $this->outputPng();
    }

    /**
     * 直接输出到浏览器
     */
    public function outputToBrowser(array $barcodeData, string $data, array $config = []): void
    {
        $pngData = $this->render($barcodeData, $data, $config);
        
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($pngData));
        header('Cache-Control: no-cache, must-revalidate');
        
        echo $pngData;
    }

    public function saveToFile(array $barcodeData, string $data, string $filename, array $config = []): bool
    {
        $pngData = $this->render($barcodeData, $data, $config);
        
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($filename, $pngData) !== false;
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 添加水印（PNG 适配版）
     *
     * GD 内置字体仅支持 1-5 级字号，因此传入的字号会被映射到该范围。
     *
     * @param string $text 水印文本
     * @param int $opacity 透明度（0-100，值越大越不透明）
     * @param int $fontSize 字号（会被映射到 GD 内置字体 1-5）
     * @param string $color 颜色（十六进制格式）
     * @param int $angle 旋转角度（-180到180度）
     * @return self
     */
    public function setWatermark(
        string $text,
        int $opacity = 70,
        int $fontSize = 16,
        string $color = '#CCCCCC',
        int $angle = 0
    ): self {
        $this->watermarkText = $text;
        $this->watermarkOpacity = max(0, min(100, $opacity));
        // GD 内置字体有效范围为 1-5，超过此范围的值按 3 像素/级进行映射
        if ($fontSize <= 5) {
            $this->watermarkFontSize = max(1, min(5, $fontSize));
        } else {
            $mapped = (int) round($fontSize / 3);
            $this->watermarkFontSize = max(1, min(5, $mapped));
        }
        $this->watermarkColor = $color;
        $this->watermarkAngle = max(-180, min(180, $angle));
        return $this;
    }

    /**
     * 创建图像资源
     */
    protected function createImage(array $barcodeData): void
    {
        $totalWidth = 0;
        foreach ($barcodeData as $element) {
            $totalWidth += abs($element);
        }
        
        $width = $totalWidth * $this->renderModuleSize +
                 $this->config->marginLeft +
                 $this->config->marginRight;
        
        $height = $this->config->height +
                  $this->config->marginTop +
                  $this->config->marginBottom;

        if ($this->config->showText) {
            $height += $this->config->fontSize + $this->config->textOffset;
        }

        if ($this->bearerBarEnabled) {
            $height += $this->bearerBarWidth * 2;
        }

        $this->image = imagecreatetruecolor($width, $height);
        
        if ($this->image === false) {
            throw new RenderException('创建图像失败');
        }

        $bgColorHex = $this->config->bgColor;
        $barColorHex = $this->config->barColor;

        if ($bgColorHex !== 'transparent' && $bgColorHex !== null && !$this->validateContrast($bgColorHex, $barColorHex)) {
            $bgColorHex = '#FFFFFF';
            $barColorHex = '#000000';
        }

        if ($bgColorHex === 'transparent' || $bgColorHex === null) {
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
            $transparent = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
            imagefill($this->image, 0, 0, $transparent);
        } else {
            $bgColor = $this->hexToColor($bgColorHex);
            imagefill($this->image, 0, 0, $bgColor);
        }
    }

    /**
     * 绘制条形码并分析结构
     * 
     * 【关键】在绘制过程中分析条码结构，记录关键位置
     */
    protected function drawBarcode(array $barcodeData): void
    {
        if ($this->image === null) {
            return;
        }

        $barColor = $this->hexToColor($this->config->barColor);
        $x = $this->config->marginLeft;
        
        // 计算长竖线位置
        $longBarPositions = $this->calculateLongBarPositions();
        
        $barY = $this->config->marginTop;
        
        // 记录所有条的位置和宽度
        $bars = [];
        $elementIndex = 0;
        
        foreach ($barcodeData as $elementIndex => $element) {
            $isBar = $element > 0;
            $width = abs($element) * $this->renderModuleSize;

            if ($isBar) {
                $isLongBar = in_array($elementIndex, $longBarPositions, true) && 
                             $this->config->rotateLongBars;
                
                $bars[] = [
                    'x' => $x,
                    'width' => $width,
                    'isLongBar' => $isLongBar,
                    'elementIndex' => $elementIndex,
                ];
                
                $barHeight = $this->config->height;
                if ($isLongBar) {
                    $barHeight = (int)($barHeight * $this->longBarHeightRatio);
                }
                
                if ($this->enableGradient) {
                    $this->drawGradientBar($x, $barY, $width, $barHeight);
                } else {
                    if ($this->enableRoundedBars && $width > $this->cornerRadius * 2) {
                        $this->drawRoundedRect($x, $barY, $width, $barHeight, $this->cornerRadius, $barColor);
                    } else {
                        imagefilledrectangle(
                            $this->image,
                            $x,
                            $barY,
                            $x + $width - 1,
                            $barY + $barHeight - 1,
                            $barColor
                        );
                    }
                }
            }
            
            $x += $width;
        }
        
        // 分析条码结构
        $this->analyzeBarcodeStructure($bars, $x, $this->config->marginLeft);
    }


    /**
     * 绘制文字（动态定位）
     */
    protected function drawText(string $data): void
    {
        if ($this->image === null) {
            return;
        }

        $textColor = $this->hexToColor($this->config->barColor);
        
        // 判断是否为长竖线特征条码
        if (!$this->barcodeStructure['hasLongBars']) {
            // 无长竖线条码使用普通对齐方式
            switch ($this->barcodeType) {
                case 'Code 39':
                    $this->drawAlignedText($this->removeDelimiters($data), $textColor);
                    break;
                default:
                    $this->drawAlignedText($data, $textColor);
                    break;
            }
            return;
        }
        
        // 长竖线特征条码使用动态分段显示
        $this->drawSegmentedText($data, $textColor);
    }

    /**
     * 分段显示文字（核心方法）
     * 
     * 【动态计算逻辑】
     * 1. 根据条码类型确定分段方式
     * 2. 根据数据区域宽度动态计算字号
     * 3. 每段数字在各自区域内均匀分布
     * 4. 文字紧贴长竖线底部
     */
    protected function drawSegmentedText(string $data, int $textColor): void
    {
        $regions = $this->barcodeStructure['regions'] ?? null;
        if ($regions === null) {
            $this->drawAlignedText($data, $textColor);
            return;
        }
        
        // 根据条码类型确定分段策略
        $segments = $this->determineSegments($data);
        if (empty($segments)) {
            $this->drawAlignedText($data, $textColor);
            return;
        }
        
        // 计算动态参数
        $this->calculateDynamicParameters($segments, $regions);
        
        // 文字Y位置：长竖线底部下方
        $barY = $this->config->marginTop;
        $textY = $barY + (int)($this->config->height * $this->longBarHeightRatio) + $this->dynamicTextOffset;
        
        // 绘制每个分段
        foreach ($segments as $segment) {
            $this->drawSegment($segment, $regions, $textY, $textColor);
        }
    }

    /**
     * 确定分段策略
     * 
     * 【分段规则】
     * - EAN-13: [第1位, 左6位, 右6位]
     * - UPC-A: [第1位, 左5位, 右5位, 校验位]
     * - EAN-8: [左4位, 右4位]
     * - ISSN: 同EAN-13
     */
    protected function determineSegments(string $data): array
    {
        $length = strlen($data);
        $type = $this->barcodeType;
        
        // 根据条码类型和数据长度确定分段
        switch ($type) {
            case 'EAN-13':
                if ($length === 13) {
                    return [
                        ['text' => $data[0], 'region' => 'leftQuietZone', 'type' => 'single'],
                        ['text' => substr($data, 1, 6), 'region' => 'leftData', 'type' => 'multi'],
                        ['text' => substr($data, 7, 6), 'region' => 'rightData', 'type' => 'multi'],
                    ];
                }
                break;
                
            case 'UPC-A':
                if ($length === 12) {
                    return [
                        ['text' => $data[0], 'region' => 'leftQuietZone', 'type' => 'single'],
                        ['text' => substr($data, 1, 5), 'region' => 'leftData', 'type' => 'multi'],
                        ['text' => substr($data, 6, 5), 'region' => 'rightData', 'type' => 'multi'],
                        ['text' => $data[11], 'region' => 'rightQuietZone', 'type' => 'single'],
                    ];
                }
                break;
                
            case 'EAN-8':
                if ($length === 8) {
                    return [
                        ['text' => substr($data, 0, 4), 'region' => 'leftData', 'type' => 'multi'],
                        ['text' => substr($data, 4, 4), 'region' => 'rightData', 'type' => 'multi'],
                    ];
                }
                break;
                
            case 'ISSN':
                // ISSN基于EAN-13，但显示格式不同
                if ($length === 13) {
                    return [
                        ['text' => $data[0], 'region' => 'leftQuietZone', 'type' => 'single'],
                        ['text' => substr($data, 1, 6), 'region' => 'leftData', 'type' => 'multi'],
                        ['text' => substr($data, 7, 6), 'region' => 'rightData', 'type' => 'multi'],
                    ];
                }
                break;
        }
        
        return [];
    }

    /**
     * 计算动态参数（字号、偏移等）
     */
    protected function calculateDynamicParameters(array $segments, array $regions): void
    {
        // 计算最小数据区域宽度
        $minRegionWidth = PHP_INT_MAX;
        foreach ($segments as $segment) {
            if ($segment['type'] === 'multi') {
                $regionName = $segment['region'];
                if (isset($regions[$regionName])) {
                    $width = $regions[$regionName]['right'] - $regions[$regionName]['left'];
                    $minRegionWidth = min($minRegionWidth, $width);
                }
            }
        }
        
        // 根据最小区域宽度计算最大字号
        // 每个数字需要一定宽度，根据区域宽度和数字数量计算
        $baseFontSize = $this->config->fontSize;
        
        // 计算数据区域的平均宽度,用于更精确的字号调整
        $avgRegionWidth = 0;
        $multiCount = 0;
        foreach ($segments as $segment) {
            if ($segment['type'] === 'multi' && isset($regions[$segment['region']])) {
                $region = $regions[$segment['region']];
                $avgRegionWidth += ($region['right'] - $region['left']) / strlen($segment['text']);
                $multiCount++;
            }
        }
        
        if ($multiCount > 0) {
            $avgRegionWidth = $avgRegionWidth / $multiCount;
            // 根据平均每个数字的宽度调整字号 (GD库字号范围1-5)
            if ($avgRegionWidth < 6) {
                $this->dynamicFontSize = max(2, $baseFontSize - 1);
            } elseif ($avgRegionWidth > 10) {
                $this->dynamicFontSize = min(5, $baseFontSize + 1);
            } else {
                $this->dynamicFontSize = $baseFontSize;
            }
        } else {
            // 回退到基于总宽度的调整
            $barcodeWidth = $this->barcodeStructure['totalWidth'] ?? 200;
            if ($barcodeWidth < 150) {
                $this->dynamicFontSize = max(3, $baseFontSize - 1);
            } elseif ($barcodeWidth > 400) {
                $this->dynamicFontSize = min(6, $baseFontSize + 1);
            } else {
                $this->dynamicFontSize = $baseFontSize;
            }
        }
        
        // 动态计算文本偏移（根据长竖线高度和字号）
        $longBarHeight = $this->config->height * $this->longBarHeightRatio;
        $normalHeight = $this->config->height;
        $extraHeight = $longBarHeight - $normalHeight;
        
        // 偏移量基于额外高度和字号，确保文字不会太贴近长竖线
        $fontBasedOffset = (int)($this->dynamicFontSize * 0.5);
        $this->dynamicTextOffset = max(3, max($fontBasedOffset, (int)($extraHeight * 0.35)));
    }

    /**
     * 绘制单个分段
     */
    protected function drawSegment(array $segment, array $regions, int $textY, int $textColor): void
    {
        $regionName = $segment['region'];
        if (!isset($regions[$regionName])) {
            return;
        }
        
        $region = $regions[$regionName];
        $text = $segment['text'];
        $length = strlen($text);
        
        if ($length === 0) {
            return;
        }
        
        if ($segment['type'] === 'single' || $length === 1) {
            // 单个数字：居中在区域内
            $centerX = $region['center'] ?? (int)(($region['left'] + $region['right']) / 2);
            $this->drawChar($text, $centerX, $textY, $textColor, $this->dynamicFontSize);
        } else {
            // 多个数字：在区域内均匀分布
            $regionWidth = $region['right'] - $region['left'];
            $spacing = $regionWidth / $length;
            
            for ($i = 0; $i < $length; $i++) {
                // 每个数字的中心位置
                $x = (int)($region['left'] + $spacing * $i + $spacing / 2);
                $this->drawChar($text[$i], $x, $textY, $textColor, $this->dynamicFontSize);
            }
        }
    }

    /**
     * 移除分隔符
     */
    protected function removeDelimiters(string $data): string
    {
        return trim($data, '*');
    }

    /**
     * 绘制对齐的文本
     */
    protected function drawAlignedText(string $data, int $textColor): void
    {
        $barY = $this->config->marginTop;
        $y = $barY + $this->config->height + $this->config->textOffset + $this->config->fontSize;
        
        $imgWidth = imagesx($this->image);
        $textWidth = imagefontwidth(5) * strlen($data);
        
        switch ($this->textAlign) {
            case 'left':
                $x = $this->config->marginLeft;
                break;
            case 'right':
                $x = $imgWidth - $this->config->marginRight - $textWidth;
                break;
            case 'center':
            default:
                $x = (int)(($imgWidth - $textWidth) / 2);
                break;
        }
        
        imagestring($this->image, 5, $x, $y - 12, $data, $textColor);
    }

    /**
     * 绘制单个字符
     */
    protected function drawChar(string $char, int $x, int $y, int $textColor, int $fontSize = 5): void
    {
        if ($this->image === null) {
            return;
        }

        $charWidth = imagefontwidth($fontSize);
        $charHeight = imagefontheight($fontSize);
        
        // 居中显示 - 优化定位精度
        $centeredX = $x - (int)($charWidth / 2);
        
        // 确保不超出图像边界
        $imgWidth = imagesx($this->image);
        $centeredX = max(0, min($centeredX, $imgWidth - $charWidth));
        
        // 使用 imagestring 确保字符清晰显示
        imagestring($this->image, $fontSize, $centeredX, $y - $charHeight + 3, $char, $textColor);
    }

    /**
     * 绘制渐变条
     */
    protected function drawGradientBar(int $x, int $y, int $width, int $height): void
    {
        $startRgb = $this->hexToRgb($this->gradientStartColor);
        $endRgb = $this->hexToRgb($this->gradientEndColor);
        
        for ($i = 0; $i < $width; $i++) {
            $ratio = $i / $width;
            $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $ratio);
            $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $ratio);
            $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $ratio);
            
            $color = imagecolorallocate($this->image, $r, $g, $b);
            imageline($this->image, $x + $i, $y, $x + $i, $y + $height - 1, $color);
        }
    }

    /**
     * 绘制圆角矩形
     */
    protected function drawRoundedRect(int $x, int $y, int $width, int $height, int $radius, int $color): void
    {
        imagefilledrectangle($this->image, $x + $radius, $y, $x + $width - $radius - 1, $y + $height - 1, $color);
        imagefilledrectangle($this->image, $x, $y + $radius, $x + $width - 1, $y + $height - $radius - 1, $color);
        
        imagefilledellipse($this->image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($this->image, $x + $width - $radius - 1, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($this->image, $x + $radius, $y + $height - $radius - 1, $radius * 2, $radius * 2, $color);
        imagefilledellipse($this->image, $x + $width - $radius - 1, $y + $height - $radius - 1, $radius * 2, $radius * 2, $color);
    }

    /**
     * 绘制 Bearer Bar（ITF-14 标准框型）
     *
     * 绘制完整的矩形框，四边紧贴条码内容区域（含静区），
     * 避免水平横条覆盖整个图像导致的“超长”视觉异常。
     */
    protected function drawBearerBar(): void
    {
        if ($this->image === null) {
            return;
        }

        $barColor = $this->hexToColor($this->config->barColor);
        $thickness = $this->bearerBarWidth;
        $barTop = $this->config->marginTop;
        $barBottom = $barTop + $this->config->height - 1;

        // 内容区域左右边界（与条码静区对齐，不覆盖图像边缘额外 padding）
        $contentLeft = $this->config->marginLeft;
        $contentRight = imagesx($this->image) - $this->config->marginRight - 1;
        if ($contentLeft >= $contentRight) {
            $contentLeft = 0;
            $contentRight = imagesx($this->image) - 1;
        }

        // 顶部横线
        $topY1 = max(0, $barTop - $thickness);
        $topY2 = $barTop - 1;
        if ($topY2 >= 0) {
            imagefilledrectangle($this->image, $contentLeft, $topY1, $contentRight, $topY2, $barColor);
        }

        // 底部横线
        $bottomY1 = $barBottom + 1;
        $bottomY2 = $barBottom + $thickness;
        imagefilledrectangle($this->image, $contentLeft, $bottomY1, $contentRight, $bottomY2, $barColor);

        // 左右垂直条（连接顶部和底部，形成完整框型 Bearer Bar）
        $boxTop = $topY1;
        $boxBottom = $bottomY2;
        imagefilledrectangle($this->image, $contentLeft, $boxTop, $contentLeft + $thickness - 1, $boxBottom, $barColor);
        imagefilledrectangle($this->image, $contentRight - $thickness + 1, $boxTop, $contentRight, $boxBottom, $barColor);
    }

    /**
     * 绘制水印（增强版，支持旋转、斜向平铺、位置定位和TTF字体）
     */
    protected function drawWatermark(): void
    {
        if ($this->image === null || $this->watermarkText === null) {
            return;
        }

        $width = imagesx($this->image);
        $height = imagesy($this->image);

        // 解析水印颜色
        $rgb = $this->parseColor($this->watermarkColor);

        // 计算透明度（GD库alpha值：0不透明，127完全透明）
        $alpha = (int)(127 * (1 - $this->watermarkOpacity / 100));
        $watermarkColor = imagecolorallocatealpha($this->image, $rgb['r'], $rgb['g'], $rgb['b'], $alpha);

        // 使用TTF字体或内置GD字体
        if ($this->watermarkFontPath !== null && $this->watermarkAngle === 0) {
            $this->drawTtfWatermark($width, $height, $watermarkColor);
            return;
        }

        $fontSize = $this->watermarkFontSize;
        $textWidth = imagefontwidth($fontSize) * strlen($this->watermarkText);
        $textHeight = imagefontheight($fontSize);

        // 如果角度为0，根据位置定位绘制
        if ($this->watermarkAngle === 0) {
            [$x, $y] = $this->resolveWatermarkPosition($width, $height, $textWidth, $textHeight, 0);
            imagestring($this->image, $fontSize, $x, $y, $this->watermarkText, $watermarkColor);
        } else {
            // 有旋转角度，绘制斜向平铺的水印
            $this->drawRotatedWatermark($width, $height, $watermarkColor);
        }
    }

    /**
     * 使用TTF字体绘制水印
     */
    protected function drawTtfWatermark(int $imgWidth, int $imgHeight, int $color): void
    {
        if ($this->watermarkFontPath === null) {
            return;
        }

        $fontSize = max(8, $this->watermarkFontSize * 4);
        $bbox = imagettfbbox($fontSize, 0, $this->watermarkFontPath, $this->watermarkText);
        if ($bbox === false) {
            return;
        }

        $textWidth = (int) abs($bbox[4] - $bbox[0]);
        $textHeight = (int) abs($bbox[1] - $bbox[5]);

        [$x, $y] = $this->resolveWatermarkPosition($imgWidth, $imgHeight, $textWidth, $textHeight, $textHeight);

        imagettftext(
            $this->image,
            $fontSize,
            0,
            $x,
            $y,
            $color,
            $this->watermarkFontPath,
            $this->watermarkText
        );
    }

    /**
     * 根据位置解析水印坐标
     *
     * @return array{0:int,1:int} [x, y]
     */
    protected function resolveWatermarkPosition(int $imgWidth, int $imgHeight, int $textWidth, int $textHeight, int $baselineOffset): array
    {
        $margin = 10;
        $x = (int)(($imgWidth - $textWidth) / 2);
        $y = (int)(($imgHeight - $textHeight) / 2) + $baselineOffset;

        switch ($this->watermarkPosition) {
            case 'top-left':
                $x = $margin;
                $y = $margin + $baselineOffset;
                break;
            case 'top-right':
                $x = $imgWidth - $textWidth - $margin;
                $y = $margin + $baselineOffset;
                break;
            case 'bottom-left':
                $x = $margin;
                $y = $imgHeight - $textHeight - $margin + $baselineOffset;
                break;
            case 'bottom-right':
                $x = $imgWidth - $textWidth - $margin;
                $y = $imgHeight - $textHeight - $margin + $baselineOffset;
                break;
            case 'top':
                $x = (int)(($imgWidth - $textWidth) / 2);
                $y = $margin + $baselineOffset;
                break;
            case 'bottom':
                $x = (int)(($imgWidth - $textWidth) / 2);
                $y = $imgHeight - $textHeight - $margin + $baselineOffset;
                break;
            case 'left':
                $x = $margin;
                $y = (int)(($imgHeight - $textHeight) / 2) + $baselineOffset;
                break;
            case 'right':
                $x = $imgWidth - $textWidth - $margin;
                $y = (int)(($imgHeight - $textHeight) / 2) + $baselineOffset;
                break;
            case 'center':
            default:
                $x = (int)(($imgWidth - $textWidth) / 2);
                $y = (int)(($imgHeight - $textHeight) / 2) + $baselineOffset;
                break;
        }

        return [max(0, $x), max(0, $y)];
    }
    
    /**
     * 绘制旋转和平铺的水印
     */
    protected function drawRotatedWatermark(int $imgWidth, int $imgHeight, int $color): void
    {
        if ($this->image === null || $this->watermarkText === null) {
            return;
        }

        // 计算水印间距（根据字号调整）
        $fontSize = $this->watermarkFontSize;
        $textWidth = imagefontwidth($fontSize) * strlen($this->watermarkText);
        $textHeight = imagefontheight($fontSize);
        
        // 斜向水印的间距（水平和垂直）
        $spacingX = $textWidth + 60;  // 水平间距
        $spacingY = $textHeight + 40; // 垂直间距
        
        // 角度转换为弧度
        $angleRad = deg2rad($this->watermarkAngle);
        
        // 扩大绘制范围，确保旋转后覆盖整个图像
        $diagonal = (int)sqrt($imgWidth * $imgWidth + $imgHeight * $imgHeight);
        $startX = -$diagonal;
        $startY = -$diagonal;
        $endX = $diagonal + $imgWidth;
        $endY = $diagonal + $imgHeight;
        
        // 斜向平铺水印
        for ($row = $startY; $row < $endY; $row += $spacingY) {
            for ($col = $startX; $col < $endX; $col += $spacingX) {
                // 计算旋转后的位置
                $x = (int)($col * cos($angleRad) - $row * sin($angleRad) + $imgWidth / 2);
                $y = (int)($col * sin($angleRad) + $row * cos($angleRad) + $imgHeight / 2);
                
                // 只绘制在图像范围内的水印
                if ($x >= -$textWidth && $x <= $imgWidth && $y >= -$textHeight && $y <= $imgHeight) {
                    // 使用 imagettftext 需要字体文件，这里用旋转图像的方式
                    // 创建临时图像用于旋转
                    $tempImg = imagecreatetruecolor($textWidth + 10, $textHeight + 10);
                    imagesavealpha($tempImg, true);
                    $transparent = imagecolorallocatealpha($tempImg, 255, 255, 255, 127);
                    imagefill($tempImg, 0, 0, $transparent);
                    
                    // 在临时图像上绘制文本
                    $tempColor = imagecolorallocatealpha($tempImg, 
                        ($color >> 16) & 0xFF, 
                        ($color >> 8) & 0xFF, 
                        $color & 0xFF, 
                        ($color >> 24) & 0x7F
                    );
                    imagestring($tempImg, $fontSize, 5, 5, $this->watermarkText, $tempColor);
                    
                    // 旋转临时图像
                    $rotatedImg = imagerotate($tempImg, -$this->watermarkAngle, $transparent);
                    
                    // 合并到主图像
                    $rotatedWidth = imagesx($rotatedImg);
                    $rotatedHeight = imagesy($rotatedImg);
                    $destX = $x - (int)($rotatedWidth / 2);
                    $destY = $y - (int)($rotatedHeight / 2);
                    
                    imagecopy($this->image, $rotatedImg, $destX, $destY, 0, 0, $rotatedWidth, $rotatedHeight);
                }
            }
        }
    }
    
    /**
     * 解析颜色字符串
     */
    protected function parseColor(string $color): array
    {
        $color = ltrim($color, '#');
        
        if (strlen($color) === 6) {
            return [
                'r' => hexdec(substr($color, 0, 2)),
                'g' => hexdec(substr($color, 2, 2)),
                'b' => hexdec(substr($color, 4, 2)),
            ];
        }
        
        // 默认灰色
        return ['r' => 204, 'g' => 204, 'b' => 204];
    }

    /**
     * 绘制边框
     */
    protected function drawBorder(): void
    {
        if ($this->image === null || $this->borderWidth <= 0) {
            return;
        }

        $color = $this->hexToColor($this->borderColor);
        $imgWidth = imagesx($this->image);
        $imgHeight = imagesy($this->image);
        $w = $this->borderWidth;

        // 上边框
        imagefilledrectangle($this->image, 0, 0, $imgWidth - 1, $w - 1, $color);
        // 下边框
        imagefilledrectangle($this->image, 0, $imgHeight - $w, $imgWidth - 1, $imgHeight - 1, $color);
        // 左边框
        imagefilledrectangle($this->image, 0, 0, $w - 1, $imgHeight - 1, $color);
        // 右边框
        imagefilledrectangle($this->image, $imgWidth - $w, 0, $imgWidth - 1, $imgHeight - 1, $color);
    }

    /**
     * 输出PNG数据
     */
    protected function outputPng(): string
    {
        if ($this->image === null) {
            throw new RenderException('图像未创建');
        }

        ob_start();
        imagepng($this->image);
        $data = ob_get_clean();

        $this->image = null;

        if ($data === false) {
            throw new RenderException('生成PNG失败');
        }

        return $data;
    }

    /**
     * 十六进制颜色转GD颜色
     */
    protected function hexToColor(string $hex): int
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        if ($this->image === null) {
            return 0;
        }

        return imagecolorallocate($this->image, $r, $g, $b);
    }

}

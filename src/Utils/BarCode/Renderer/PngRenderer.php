<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Renderer;

use zxf\Utils\BarCode\Contracts\RendererInterface;
use zxf\Utils\BarCode\DTO\BarcodeConfig;
use zxf\Utils\BarCode\Exceptions\RenderException;

/**
 * PNG 渲染器（完整增强版）
 * 
 * 功能特性：
 * - 支持长竖线渲染（EAN-13/ISBN/UPC-A/EAN-8）
 * - 支持数字显示（根据条码类型自动调整位置）
 * - 支持自定义颜色、渐变、圆角
 * - 支持水印
 * - 支持文本对齐（左/中/右）
 * - 支持直接输出到浏览器
 * - 支持Bearer Bar（ITF-14上下边框）
 */
class PngRenderer implements RendererInterface
{
    /** @var string 渲染器类型 */
    protected const TYPE = 'png';
    
    /** @var BarcodeConfig 配置对象 */
    protected BarcodeConfig $config;
    
    /** @var resource|null GD图像资源 */
    protected $image = null;
    
    /** @var string 条码类型 */
    protected string $barcodeType = '';
    
    // 个性化配置
    protected bool $enableGradient = false;
    protected string $gradientStartColor = '#000000';
    protected string $gradientEndColor = '#333333';
    protected bool $enableRoundedBars = false;
    protected int $cornerRadius = 2;
    protected ?string $watermarkText = null;
    protected int $watermarkOpacity = 50;
    protected string $textAlign = 'center'; // left, center, right
    protected bool $enableBearerBar = false; // ITF-14上下边框
    protected int $bearerBarWidth = 2;

    public function __construct(?BarcodeConfig $config = null)
    {
        $this->config = $config ?? new BarcodeConfig();
    }

    /**
     * 渲染条形码为PNG
     * 
     * @param array  $barcodeData 条形码条空模式数组
     * @param string $data         原始数据
     * @param array  $config       渲染配置选项
     * @return string PNG二进制数据
     */
    public function render(array $barcodeData, string $data, array $config = []): string
    {
        $this->config = BarcodeConfig::fromArray(array_merge($this->config->toArray(), $config));
        
        $this->createImage($barcodeData);
        $this->drawBarcode($barcodeData);
        
        if ($this->config->showText) {
            $this->drawText($data);
        }
        
        if ($this->watermarkText !== null) {
            $this->drawWatermark();
        }

        return $this->outputPng();
    }

    /**
     * 直接输出到浏览器
     * 
     * @param array  $barcodeData 条形码条空模式数组
     * @param string $data         原始数据
     * @param array  $config       渲染配置选项
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

    public function setBarcodeType(string $type): self
    {
        $this->barcodeType = $type;
        return $this;
    }

    /**
     * 设置文本对齐方式
     * 
     * @param string $align 对齐方式：left, center, right
     * @return self 支持链式调用
     */
    public function setTextAlign(string $align): self
    {
        $this->textAlign = in_array($align, ['left', 'center', 'right']) ? $align : 'center';
        return $this;
    }

    /**
     * 启用渐变效果
     * 
     * @param string $startColor 起始颜色
     * @param string $endColor   结束颜色
     * @return self 支持链式调用
     */
    public function enableGradient(string $startColor, string $endColor): self
    {
        $this->enableGradient = true;
        $this->gradientStartColor = $startColor;
        $this->gradientEndColor = $endColor;
        return $this;
    }

    /**
     * 启用圆角条
     * 
     * @param int $radius 圆角半径
     * @return self 支持链式调用
     */
    public function enableRoundedBars(int $radius = 2): self
    {
        $this->enableRoundedBars = true;
        $this->cornerRadius = $radius;
        return $this;
    }

    /**
     * 添加水印
     * 
     * @param string $text    水印文字
     * @param int    $opacity 透明度（0-100）
     * @return self 支持链式调用
     */
    public function setWatermark(string $text, int $opacity = 50): self
    {
        $this->watermarkText = $text;
        $this->watermarkOpacity = max(0, min(100, $opacity));
        return $this;
    }

    /**
     * 启用Bearer Bar（ITF-14上下边框）
     * 
     * @param int $width 边框线宽
     * @return self 支持链式调用
     */
    public function enableBearerBar(int $width = 2): self
    {
        $this->enableBearerBar = true;
        $this->bearerBarWidth = $width;
        return $this;
    }

    /**
     * 创建图像资源
     * 
     * @param array<int> $barcodeData 条空模式数组
     */
    protected function createImage(array $barcodeData): void
    {
        $totalWidth = 0;
        foreach ($barcodeData as $element) {
            $totalWidth += abs($element);
        }
        
        $width = $totalWidth * $this->config->width + 
                 $this->config->marginLeft + 
                 $this->config->marginRight;
        
        $height = $this->config->height + 
                  $this->config->marginTop + 
                  $this->config->marginBottom;
        
        // Bearer bar额外高度
        if ($this->enableBearerBar) {
            $height += $this->bearerBarWidth * 2;
        }
        
        if ($this->config->showText) {
            $height += $this->config->fontSize + $this->config->textOffset;
        }

        $this->image = imagecreatetruecolor($width, $height);
        
        if ($this->image === false) {
            throw new RenderException('创建图像失败');
        }

        // 验证颜色对比度
        $bgColorHex = $this->config->bgColor;
        $barColorHex = $this->config->barColor;
        
        if (!$this->validateContrast($bgColorHex, $barColorHex)) {
            // 对比度不足，使用默认黑白配色
            $bgColorHex = '#FFFFFF';
            $barColorHex = '#000000';
        }

        $bgColor = $this->hexToColor($bgColorHex);
        imagefill($this->image, 0, 0, $bgColor);
    }

    /**
     * 绘制条形码
     * 
     * @param array<int> $barcodeData 条空模式数组
     */
    protected function drawBarcode(array $barcodeData): void
    {
        if ($this->image === null) {
            return;
        }

        $barColor = $this->hexToColor($this->config->barColor);
        $x = $this->config->marginLeft;
        
        // Bearer bar顶部
        if ($this->enableBearerBar) {
            imagefilledrectangle(
                $this->image,
                $x,
                $this->config->marginTop,
                $x + $this->getBarcodeWidth($barcodeData) - 1,
                $this->config->marginTop + $this->bearerBarWidth - 1,
                $barColor
            );
        }
        
        $longBarPositions = $this->calculateLongBarPositions($barcodeData);
        
        $currentModule = 0;
        $barY = $this->config->marginTop + ($this->enableBearerBar ? $this->bearerBarWidth : 0);
        
        foreach ($barcodeData as $element) {
            $isBar = $element > 0;
            $width = abs($element) * $this->config->width;
            
            if ($isBar) {
                $isLongBar = in_array($currentModule, $longBarPositions, true) && 
                             $this->config->rotateLongBars;
                
                $barHeight = $this->config->height;
                if ($isLongBar) {
                    $barHeight = (int)($barHeight * $this->config->longBarRatio);
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
            $currentModule += abs($element);
        }
        
        // Bearer bar底部
        if ($this->enableBearerBar) {
            $bottomY = $barY + $this->config->height;
            imagefilledrectangle(
                $this->image,
                $this->config->marginLeft,
                $bottomY,
                $this->config->marginLeft + $this->getBarcodeWidth($barcodeData) - 1,
                $bottomY + $this->bearerBarWidth - 1,
                $barColor
            );
        }
    }

    /**
     * 计算条码宽度
     * 
     * @param array<int> $barcodeData 条空模式数组
     * @return int 宽度（像素）
     */
    protected function getBarcodeWidth(array $barcodeData): int
    {
        $width = 0;
        foreach ($barcodeData as $element) {
            $width += abs($element) * $this->config->width;
        }
        return $width;
    }

    /**
     * 计算长竖线位置
     * 
     * 【长竖线位置说明】：
     * EAN-13/ISBN（117模块）：起始符(11,13)、分隔符(56,58)、终止符(101,103)
     * EAN-8（85模块）：起始符(7,9)、分隔符(40,42)
     * UPC-A（113模块）：起始符(9,11)、分隔符(54,56)、终止符(99,101)
     * 
     * @param array<int> $barcodeData 条空模式数组
     * @return array<int> 长竖线模块位置
     */
    protected function calculateLongBarPositions(array $barcodeData): array
    {
        // 长竖线位置基于模块索引（0-based）
        // 对应保护符（起始符/中间分隔符/终止符）中的条(1)位置
        return match ($this->barcodeType) {
            'EAN-13', 'ISBN' => [11, 13, 56, 58, 101, 103],  // 静区11+起始符/中间/终止符
            'EAN-8' => [7, 9, 38, 40, 69, 71],               // 静区7+起始符/中间/终止符
            'UPC-A' => [9, 11, 54, 56, 99, 101],             // 静区9+起始符/中间/终止符
            default => [],
        };
    }

    /**
     * 绘制文字
     * 
     * 根据条码类型选择不同的文字绘制方式
     * 
     * @param string $data 原始数据
     */
    protected function drawText(string $data): void
    {
        if ($this->image === null) {
            return;
        }

        $textColor = $this->hexToColor($this->config->barColor);
        
        switch ($this->barcodeType) {
            case 'EAN-13':
                $this->drawEAN13Text($data, $textColor);
                break;
            case 'ISBN':
                $this->drawISBNText($data, $textColor);
                break;
            case 'EAN-8':
                $this->drawEAN8Text($data, $textColor);
                break;
            case 'UPC-A':
                $this->drawUPCAText($data, $textColor);
                break;
            case 'Code 39':
                // Code 39不显示*分隔符
                $this->drawAlignedText($this->removeDelimiters($data), $textColor);
                break;
            default:
                $this->drawAlignedText($data, $textColor);
                break;
        }
    }

    /**
     * 移除分隔符
     * 
     * @param string $data 原始数据
     * @return string 移除*后的数据
     */
    protected function removeDelimiters(string $data): string
    {
        return trim($data, '*');
    }

    /**
     * 绘制对齐的文本（用于无长竖线的条码）
     * 
     * @param string $data      要显示的数据
     * @param int    $textColor 文字颜色
     */
    protected function drawAlignedText(string $data, int $textColor): void
    {
        $barY = $this->config->marginTop + ($this->enableBearerBar ? $this->bearerBarWidth : 0);
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
     * 绘制EAN-13文字（GS1标准位置优化版）
     * 
     * 【EAN-13数字显示位置】：
     * - 第1位数字：最左侧，静区左侧（约模块位置5-7），避免太靠左
     * - 第2-7位数字：起始保护符和中间分隔符之间
     * - 第8-13位数字：中间分隔符和终止保护符之间
     * 
     * 【优化点】：
     * - 第1位数字使用较大偏移，避免与图像左边缘重叠
     * - 数字之间间隔均匀（每7模块一个数字）
     * - 文字位置在长竖线下方，避免重叠
     * 
     * @param string $data      完整13位数据
     * @param int    $textColor 文字颜色
     */
    protected function drawEAN13Text(string $data, int $textColor): void
    {
        if (strlen($data) !== 13) {
            $this->drawAlignedText($data, $textColor);
            return;
        }

        $firstDigit = $data[0];
        $leftDigits = substr($data, 1, 6);
        $rightDigits = substr($data, 7, 6);

        $barY = $this->config->marginTop + ($this->enableBearerBar ? $this->bearerBarWidth : 0);
        // 增加文字与长竖线之间的距离，避免重叠
        $textY = $barY + $this->config->height + $this->config->textOffset + 2;

        // 第1位数字（最左侧）- 显示在起始保护符左侧静区中
        // 优化：使用较大偏移(9模块)，避免太靠左或重叠
        $firstX = $this->config->marginLeft + (int)(9 * $this->config->width);
        $this->drawChar($firstDigit, $firstX, $textY, $textColor);

        // 左侧6位数字（第2-7位）
        // 起始位置：静区11 + 起始符3 + 模块中心偏移(3.5) = 模块17.5
        // 每个数字占7个模块，居中显示
        $leftStart = $this->config->marginLeft + (int)(17.5 * $this->config->width);
        for ($i = 0; $i < 6; $i++) {
            $x = $leftStart + $i * 7 * $this->config->width;
            $this->drawChar($leftDigits[$i], $x, $textY, $textColor);
        }

        // 右侧6位数字（第8-13位）
        // 起始位置：静区11 + 起始符3 + 左侧42 + 分隔符5 + 偏移(3.5) = 模块64.5
        $rightStart = $this->config->marginLeft + (int)(64.5 * $this->config->width);
        for ($i = 0; $i < 6; $i++) {
            $x = $rightStart + $i * 7 * $this->config->width;
            $this->drawChar($rightDigits[$i], $x, $textY, $textColor);
        }
    }

    /**
     * 绘制ISBN文字
     * 
     * ISBN与EAN-13数字显示位置相同
     * 但通常显示格式化的ISBN（带分隔符）
     * 
     * @param string $data      完整13位ISBN
     * @param int    $textColor 文字颜色
     */
    protected function drawISBNText(string $data, int $textColor): void
    {
        // ISBN与EAN-13数字显示位置相同
        $this->drawEAN13Text($data, $textColor);
    }

    /**
     * 绘制EAN-8文字（GS1标准位置优化版）
     * 
     * 【EAN-8数字显示位置】：
     * - 第1-4位数字：起始保护符和中间分隔符之间
     * - 第5-8位数字：中间分隔符和终止保护符之间
     * 
     * 【优化点】：
     * - 数字位置与条码区域精确对齐
     * - 增加文字与条码间距，避免与长竖线重叠
     * 
     * @param string $data      完整8位数据
     * @param int    $textColor 文字颜色
     */
    protected function drawEAN8Text(string $data, int $textColor): void
    {
        if (strlen($data) !== 8) {
            $this->drawAlignedText($data, $textColor);
            return;
        }

        $leftDigits = substr($data, 0, 4);
        $rightDigits = substr($data, 4, 4);

        $barY = $this->config->marginTop + ($this->enableBearerBar ? $this->bearerBarWidth : 0);
        // 增加文字与长竖线之间的距离
        $textY = $barY + $this->config->height + $this->config->textOffset + 2;

        // 左侧4位数字
        // 起始位置：静区7 + 起始符3 + 模块中心偏移(3.5) = 模块13.5
        $leftStart = $this->config->marginLeft + (int)(13.5 * $this->config->width);
        for ($i = 0; $i < 4; $i++) {
            $x = $leftStart + $i * 7 * $this->config->width;
            $this->drawChar($leftDigits[$i], $x, $textY, $textColor);
        }

        // 右侧4位数字
        // 起始位置：静区7 + 起始符3 + 左侧28 + 分隔符5 + 偏移(3.5) = 模块46.5
        $rightStart = $this->config->marginLeft + (int)(46.5 * $this->config->width);
        for ($i = 0; $i < 4; $i++) {
            $x = $rightStart + $i * 7 * $this->config->width;
            $this->drawChar($rightDigits[$i], $x, $textY, $textColor);
        }
    }

    /**
     * 绘制UPC-A文字（GS1标准位置优化版）
     * 
     * 【UPC-A数字显示位置】：
     * - 第1位数字（系统字符）：最左侧，静区左侧
     * - 第2-6位数字（厂商代码）：起始保护符和中间分隔符之间
     * - 第7-11位数字（产品代码）：中间分隔符和终止保护符之间
     * - 第12位数字（校验位）：最右侧，静区右侧
     * 
     * 【优化点】：
     * - 系统字符位置优化，避免太靠左
     * - 校验位位置优化，避免太靠右
     * - 增加文字与条码间距，避免与长竖线重叠
     * 
     * @param string $data      完整12位数据
     * @param int    $textColor 文字颜色
     */
    protected function drawUPCAText(string $data, int $textColor): void
    {
        if (strlen($data) !== 12) {
            $this->drawAlignedText($data, $textColor);
            return;
        }

        $numberSystem = $data[0];
        $manufacturer = substr($data, 1, 5);
        $product = substr($data, 6, 5);
        $checkDigit = $data[11];

        $barY = $this->config->marginTop + ($this->enableBearerBar ? $this->bearerBarWidth : 0);
        // 增加文字与长竖线之间的距离
        $textY = $barY + $this->config->height + $this->config->textOffset + 2;

        // 系统字符（第1位）- 优化位置避免太靠左
        $firstX = $this->config->marginLeft + (int)(7 * $this->config->width);
        $this->drawChar($numberSystem, $firstX, $textY, $textColor);

        // 厂商代码（第2-6位）
        // 起始位置：静区9 + 起始符3 + 模块中心偏移(3.5) = 模块15.5
        $leftStart = $this->config->marginLeft + (int)(15.5 * $this->config->width);
        for ($i = 0; $i < 5; $i++) {
            $x = $leftStart + $i * 7 * $this->config->width;
            $this->drawChar($manufacturer[$i], $x, $textY, $textColor);
        }

        // 产品代码（第7-11位）
        // 起始位置：静区9 + 起始符3 + 左侧35 + 分隔符5 + 偏移(3.5) = 模块57.5
        $rightStart = $this->config->marginLeft + (int)(57.5 * $this->config->width);
        for ($i = 0; $i < 5; $i++) {
            $x = $rightStart + $i * 7 * $this->config->width;
            $this->drawChar($product[$i], $x, $textY, $textColor);
        }

        // 校验位（第12位）- 优化位置避免太靠右
        // 位置：静区9 + 条码95 + 静区偏移(7) = 模块111
        $checkX = $this->config->marginLeft + (int)(103 * $this->config->width);
        $this->drawChar($checkDigit, $checkX, $textY, $textColor);
    }

    /**
     * 绘制单个字符
     * 
     * @param string $char      要绘制的字符
     * @param int    $x         X坐标
     * @param int    $y         Y坐标
     * @param int    $textColor 文字颜色
     */
    protected function drawChar(string $char, int $x, int $y, int $textColor): void
    {
        if ($this->image === null) {
            return;
        }

        // 居中显示字符
        $charWidth = imagefontwidth(5);
        $centeredX = $x - (int)($charWidth / 2);
        imagestring($this->image, 5, $centeredX, $y, $char, $textColor);
    }

    /**
     * 绘制渐变条
     * 
     * @param int $x      X坐标
     * @param int $y      Y坐标
     * @param int $width  宽度
     * @param int $height 高度
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
     * 
     * @param int $x      X坐标
     * @param int $y      Y坐标
     * @param int $width  宽度
     * @param int $height 高度
     * @param int $radius 圆角半径
     * @param int $color   颜色
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
     * 绘制水印
     */
    protected function drawWatermark(): void
    {
        if ($this->image === null || $this->watermarkText === null) {
            return;
        }

        $width = imagesx($this->image);
        $height = imagesy($this->image);
        
        $opacity = (int)(127 * (1 - $this->watermarkOpacity / 100));
        $watermarkColor = imagecolorallocatealpha($this->image, 128, 128, 128, $opacity);
        
        $fontSize = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($this->watermarkText);
        $textHeight = imagefontheight($fontSize);
        
        $x = (int)(($width - $textWidth) / 2);
        $y = (int)(($height - $textHeight) / 2);
        
        imagestring($this->image, $fontSize, $x, $y, $this->watermarkText, $watermarkColor);
    }

    /**
     * 输出PNG数据
     * 
     * @return string PNG二进制数据
     * @throws RenderException 生成失败时抛出
     */
    protected function outputPng(): string
    {
        if ($this->image === null) {
            throw new RenderException('图像未创建');
        }

        ob_start();
        imagepng($this->image);
        $data = ob_get_clean();
        
        imagedestroy($this->image);
        $this->image = null;

        if ($data === false) {
            throw new RenderException('生成PNG失败');
        }

        return $data;
    }

    /**
     * 十六进制颜色转GD颜色
     * 
     * @param string $hex 十六进制颜色值
     * @return int GD颜色索引
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

    /**
     * 十六进制颜色转RGB数组
     * 
     * @param string $hex 十六进制颜色值
     * @return array<int> RGB数组 [R, G, B]
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * 验证颜色对比度是否足够（WCAG标准）
     * 
     * @param string $bgColor 背景色
     * @param string $barColor 条码色
     * @return bool 对比度足够返回true
     */
    protected function validateContrast(string $bgColor, string $barColor): bool
    {
        // 计算相对亮度
        $bgLuminance = $this->getRelativeLuminance($bgColor);
        $barLuminance = $this->getRelativeLuminance($barColor);
        
        // 计算对比度
        $lightest = max($bgLuminance, $barLuminance);
        $darkest = min($bgLuminance, $barLuminance);
        $contrast = ($lightest + 0.05) / ($darkest + 0.05);
        
        // 条形码要求对比度至少为3:1
        return $contrast >= 3.0;
    }

    /**
     * 获取颜色的相对亮度（WCAG标准）
     * 
     * @param string $color 十六进制颜色
     * @return float 相对亮度（0-1）
     */
    protected function getRelativeLuminance(string $color): float
    {
        $rgb = $this->hexToRgb($color);
        
        // 转换为sRGB
        $rsRGB = $rgb[0] / 255;
        $gsRGB = $rgb[1] / 255;
        $bsRGB = $rgb[2] / 255;
        
        // 应用gamma校正
        $r = $rsRGB <= 0.03928 ? $rsRGB / 12.92 : pow(($rsRGB + 0.055) / 1.055, 2.4);
        $g = $gsRGB <= 0.03928 ? $gsRGB / 12.92 : pow(($gsRGB + 0.055) / 1.055, 2.4);
        $b = $bsRGB <= 0.03928 ? $bsRGB / 12.92 : pow(($bsRGB + 0.055) / 1.055, 2.4);
        
        // 计算相对亮度
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}

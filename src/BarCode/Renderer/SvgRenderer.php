<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Renderer;

use zxf\Utils\BarCode\Contracts\RendererInterface;
use zxf\Utils\BarCode\DTO\BarcodeConfig;
use zxf\Utils\BarCode\Exceptions\RenderException;

/**
 * SVG 渲染器（修复版）
 * 
 * 支持长竖线、数字显示、自定义颜色、渐变、圆角等功能
 */
class SvgRenderer implements RendererInterface
{
    protected const TYPE = 'svg';
    protected BarcodeConfig $config;
    protected string $barcodeType = '';
    protected array $digitLayout = [];
    
    // 个性化配置
    protected bool $enableGradient = false;
    protected string $gradientStartColor = '#000000';
    protected string $gradientEndColor = '#333333';
    protected bool $enableRoundedBars = false;
    protected int $cornerRadius = 2;

    public function __construct(?BarcodeConfig $config = null)
    {
        $this->config = $config ?? new BarcodeConfig();
    }

    public function render(array $barcodeData, string $data, array $config = []): string
    {
        $this->config = BarcodeConfig::fromArray(array_merge($this->config->toArray(), $config));

        $totalWidth = 0;
        foreach ($barcodeData as $element) {
            $totalWidth += abs($element);
        }

        $barcodeWidth = $totalWidth * $this->config->width;
        
        // 确保有足够的静区宽度（GS1标准要求至少11个模块）
        $marginLeft = $this->config->marginLeft;
        $marginRight = $this->config->marginRight;
        if ($this->config->showQuietZone) {
            $minQuietZone = 11 * $this->config->width;
            if ($marginLeft < $minQuietZone) {
                $marginLeft = $minQuietZone;
            }
            if ($marginRight < $minQuietZone) {
                $marginRight = $minQuietZone;
            }
        }
        
        $width = $barcodeWidth + $marginLeft + $marginRight;
        $height = $this->config->height + $this->config->marginTop + $this->config->marginBottom;

        if ($this->config->showText) {
            $height += $this->config->fontSize + $this->config->textOffset;
        }

        $longBarPositions = $this->calculateLongBarPositions($barcodeData);

        return $this->buildSvg($barcodeData, $data, $width, $height, $longBarPositions, $marginLeft, $marginRight);
    }

    public function saveToFile(array $barcodeData, string $data, string $filename, array $config = []): bool
    {
        $svgData = $this->render($barcodeData, $data, $config);

        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($filename, $svgData) !== false;
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
     * 启用渐变效果
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
     */
    public function enableRoundedBars(int $radius = 2): self
    {
        $this->enableRoundedBars = true;
        $this->cornerRadius = $radius;
        return $this;
    }

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

    protected function buildSvg(array $barcodeData, string $data, int $width, int $height, array $longBarPositions, int $marginLeft, int $marginRight): string
    {
        $bgColor = $this->config->bgColor;
        $barColor = $this->config->barColor;

        // 验证颜色对比度，确保可被扫描
        if (!$this->validateContrast($bgColor, $barColor)) {
            // 如果对比度不足，使用默认黑白配色
            $bgColor = '#FFFFFF';
            $barColor = '#000000';
        }

        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">' . "\n";
        
        // 定义渐变
        if ($this->enableGradient) {
            $svg .= '  <defs>' . "\n";
            $svg .= '    <linearGradient id="barGradient" x1="0%" y1="0%" x2="0%" y2="100%">' . "\n";
            $svg .= '      <stop offset="0%" style="stop-color:' . $this->gradientStartColor . '"/>' . "\n";
            $svg .= '      <stop offset="100%" style="stop-color:' . $this->gradientEndColor . '"/>' . "\n";
            $svg .= '    </linearGradient>' . "\n";
            $svg .= '  </defs>' . "\n";
        }
        
        $svg .= '  <rect width="100%" height="100%" fill="' . $bgColor . '"/>' . "\n";

        // 绘制条码条
        $x = $marginLeft;
        $currentModule = 0;
        
        $fillColor = $this->enableGradient ? 'url(#barGradient)' : $barColor;

        foreach ($barcodeData as $element) {
            $isBar = $element > 0;
            $barWidth = abs($element) * $this->config->width;

            if ($isBar) {
                $isLongBar = in_array($currentModule, $longBarPositions, true) &&
                    $this->config->rotateLongBars;

                $barHeight = $this->config->height;
                if ($isLongBar) {
                    $barHeight = (int)($barHeight * $this->config->longBarRatio);
                }
                
                $rx = $this->enableRoundedBars ? $this->cornerRadius : 0;
                
                $svg .= '  <rect x="' . $x . '" y="' . $this->config->marginTop . '" width="' . $barWidth . '" height="' . $barHeight . '" fill="' . $fillColor . '" rx="' . $rx . '"/>' . "\n";
            }
            
            $x += $barWidth;
            $currentModule += abs($element);
        }

        // 绘制文字
        if ($this->config->showText) {
            $svg .= $this->buildTextElements($data, $marginLeft);
        }

        $svg .= '</svg>';

        return $svg;
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
        
        // 条形码要求对比度至少为3:1（实际应更高，推荐4.5:1以上）
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

    /**
     * 十六进制颜色转换为RGB数组
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

    protected function buildTextElements(string $data, int $marginLeft): string
    {
        // 增加文字与条码间距，避免与长竖线重叠
        $textY = $this->config->marginTop + $this->config->height + $this->config->textOffset + $this->config->fontSize + 2;
        $barColor = $this->config->barColor;
        $fontSize = $this->config->fontSize;

        $textElements = '';

        switch ($this->barcodeType) {
            case 'EAN-13':
            case 'ISBN':
                if (strlen($data) === 13) {
                    $firstDigit = $data[0];
                    $leftDigits = substr($data, 1, 6);
                    $rightDigits = substr($data, 7, 6);

                    // 第1位数字（最左侧，静区左侧）- 优化位置避免太靠左
                    $firstX = $marginLeft + 9 * $this->config->width;
                    $textElements .= '  <text x="' . $firstX . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $firstDigit . '</text>' . "\n";

                    // 左侧6位数字（第2-7位，在起始和中间保护符之间）
                    // 优化起始位置：静区11 + 起始符3 + 模块中心偏移(3.5) = 模块17.5
                    $leftStart = $marginLeft + 17.5 * $this->config->width;
                    for ($i = 0; $i < 6; $i++) {
                        $x = $leftStart + $i * 7 * $this->config->width;
                        $textElements .= '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $leftDigits[$i] . '</text>' . "\n";
                    }

                    // 右侧6位数字（第8-13位，在中间和终止保护符之间）
                    // 优化起始位置：静区11 + 起始符3 + 左侧42 + 分隔符5 + 偏移(3.5) = 模块64.5
                    $rightStart = $marginLeft + 64.5 * $this->config->width;
                    for ($i = 0; $i < 6; $i++) {
                        $x = $rightStart + $i * 7 * $this->config->width;
                        $textElements .= '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $rightDigits[$i] . '</text>' . "\n";
                    }
                }
                break;

            case 'EAN-8':
                if (strlen($data) === 8) {
                    $leftDigits = substr($data, 0, 4);
                    $rightDigits = substr($data, 4, 4);

                    // 左侧4位数字
                    // 优化起始位置：静区7 + 起始符3 + 模块中心偏移(3.5) = 模块13.5
                    $leftStart = $marginLeft + 13.5 * $this->config->width;
                    for ($i = 0; $i < 4; $i++) {
                        $x = $leftStart + $i * 7 * $this->config->width;
                        $textElements .= '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $leftDigits[$i] . '</text>' . "\n";
                    }

                    // 右侧4位数字
                    // 优化起始位置：静区7 + 起始符3 + 左侧28 + 分隔符5 + 偏移(3.5) = 模块46.5
                    $rightStart = $marginLeft + 46.5 * $this->config->width;
                    for ($i = 0; $i < 4; $i++) {
                        $x = $rightStart + $i * 7 * $this->config->width;
                        $textElements .= '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $rightDigits[$i] . '</text>' . "\n";
                    }
                }
                break;

            case 'UPC-A':
                if (strlen($data) === 12) {
                    $numberSystem = $data[0];
                    $manufacturer = substr($data, 1, 5);
                    $product = substr($data, 6, 5);
                    $checkDigit = $data[11];

                    // 系统字符（第1位）- 优化位置避免太靠左
                    $firstX = $marginLeft + 7 * $this->config->width;
                    $textElements .= '  <text x="' . $firstX . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $numberSystem . '</text>' . "\n";

                    // 厂商代码（第2-6位）
                    // 优化起始位置：静区9 + 起始符3 + 模块中心偏移(3.5) = 模块15.5
                    $leftStart = $marginLeft + 15.5 * $this->config->width;
                    for ($i = 0; $i < 5; $i++) {
                        $x = $leftStart + $i * 7 * $this->config->width;
                        $textElements .= '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $manufacturer[$i] . '</text>' . "\n";
                    }

                    // 产品代码（第7-11位）
                    // 优化起始位置：静区9 + 起始符3 + 左侧35 + 分隔符5 + 偏移(3.5) = 模块57.5
                    $rightStart = $marginLeft + 57.5 * $this->config->width;
                    for ($i = 0; $i < 5; $i++) {
                        $x = $rightStart + $i * 7 * $this->config->width;
                        $textElements .= '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $product[$i] . '</text>' . "\n";
                    }

                    // 校验位（第12位）- 优化位置避免太靠右
                    $checkX = $marginLeft + 103 * $this->config->width;
                    $textElements .= '  <text x="' . $checkX . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $checkDigit . '</text>' . "\n";
                }
                break;

            default:
                $textX = $marginLeft;
                $textElements .= '  <text x="' . $textX . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '">' . htmlspecialchars($data) . '</text>' . "\n";
                break;
        }

        return $textElements;
    }
}

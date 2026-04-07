<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Renderer;

use zxf\Utils\BarCode\Contracts\RendererInterface;
use zxf\Utils\BarCode\DTO\BarcodeConfig;
use zxf\Utils\BarCode\Exceptions\RenderException;

/**
 * SVG 渲染器（动态文字定位版）
 * 
 * 【核心特性】
 * - 动态检测长竖线（保护符）实际像素位置
 * - 根据条码内容和结构自动分段显示数字
 * - 动态计算字号大小适配条码宽度
 * - 动态计算上下偏移距离
 * - 每段数字在各自区域内均匀分布
 */
class SvgRenderer implements RendererInterface
{
    protected const TYPE = 'svg';
    protected BarcodeConfig $config;
    protected string $barcodeType = '';
    
    // 个性化配置
    protected bool $enableGradient = false;
    protected string $gradientStartColor = '#000000';
    protected string $gradientEndColor = '#333333';
    protected bool $enableRoundedBars = false;
    protected int $cornerRadius = 2;
    
    /** @var array<int> 长竖线位置索引 */
    protected array $longBarPositions = [];
    
    /** @var float 长竖线高度比例 */
    protected float $longBarHeightRatio = 1.15;
    
    /** @var array<string, mixed> 条码结构分析结果 */
    protected array $barcodeStructure = [];
    
    /** @var int 动态计算的字号 */
    protected int $dynamicFontSize = 12;
    
    /** @var int 动态计算的文本Y偏移 */
    protected int $dynamicTextOffset = 3;

    public function __construct(?BarcodeConfig $config = null)
    {
        $this->config = $config ?? new BarcodeConfig();
    }

    public function render(array $barcodeData, string $data, array $config = []): string
    {
        $this->config = BarcodeConfig::fromArray(array_merge($this->config->toArray(), $config));
        $this->barcodeStructure = []; // 重置结构分析

        $totalWidth = 0;
        foreach ($barcodeData as $element) {
            $totalWidth += abs($element);
        }

        $barcodeWidth = $totalWidth * $this->config->width;
        
        // 确保有足够的静区宽度
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

    public function setLongBarPositions(array $positions): self
    {
        $this->longBarPositions = $positions;
        return $this;
    }

    public function enableGradient(string $startColor, string $endColor): self
    {
        $this->enableGradient = true;
        $this->gradientStartColor = $startColor;
        $this->gradientEndColor = $endColor;
        return $this;
    }

    public function enableRoundedBars(int $radius = 2): self
    {
        $this->enableRoundedBars = true;
        $this->cornerRadius = $radius;
        return $this;
    }

    protected function calculateLongBarPositions(array $barcodeData): array
    {
        if (!empty($this->longBarPositions)) {
            return $this->longBarPositions;
        }
        return [];
    }

    /**
     * 构建SVG并分析条码结构
     */
    protected function buildSvg(array $barcodeData, string $data, int $width, int $height, array $longBarPositions, int $marginLeft, int $marginRight): string
    {
        $bgColor = $this->config->bgColor;
        $barColor = $this->config->barColor;

        if (!$this->validateContrast($bgColor, $barColor)) {
            $bgColor = '#FFFFFF';
            $barColor = '#000000';
        }

        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">' . "\n";
        
        if ($this->enableGradient) {
            $svg .= '  <defs>' . "\n";
            $svg .= '    <linearGradient id="barGradient" x1="0%" y1="0%" x2="0%" y2="100%">' . "\n";
            $svg .= '      <stop offset="0%" style="stop-color:' . $this->gradientStartColor . '"/>' . "\n";
            $svg .= '      <stop offset="100%" style="stop-color:' . $this->gradientEndColor . '"/>' . "\n";
            $svg .= '    </linearGradient>' . "\n";
            $svg .= '  </defs>' . "\n";
        }
        
        $svg .= '  <rect width="100%" height="100%" fill="' . $bgColor . '"/>' . "\n";

        // 绘制条码条并分析结构
        $x = $marginLeft;
        $fillColor = $this->enableGradient ? 'url(#barGradient)' : $barColor;
        
        // 记录所有条的位置
        $bars = [];
        
        foreach ($barcodeData as $elementIndex => $element) {
            $isBar = $element > 0;
            $barWidth = abs($element) * $this->config->width;

            if ($isBar) {
                $isLongBar = in_array($elementIndex, $longBarPositions, true) &&
                    $this->config->rotateLongBars;

                $bars[] = [
                    'x' => $x,
                    'width' => $barWidth,
                    'isLongBar' => $isLongBar,
                    'elementIndex' => $elementIndex,
                ];

                $barHeight = $this->config->height;
                if ($isLongBar) {
                    $barHeight = (int)($barHeight * $this->longBarHeightRatio);
                }
                
                $rx = $this->enableRoundedBars ? $this->cornerRadius : 0;
                
                $svg .= '  <rect x="' . $x . '" y="' . $this->config->marginTop . '" width="' . $barWidth . '" height="' . $barHeight . '" fill="' . $fillColor . '" rx="' . $rx . '"/>' . "\n";
            }
            
            $x += $barWidth;
        }

        // 分析条码结构
        $this->analyzeBarcodeStructure($bars, $x, $marginLeft);

        // 绘制文字
        if ($this->config->showText) {
            $svg .= $this->buildTextElements($data, $marginLeft);
        }

        $svg .= '</svg>';

        return $svg;
    }

    /**
     * 分析条码结构
     */
    protected function analyzeBarcodeStructure(array $bars, int $totalWidth, int $marginLeft): void
    {
        $longBars = array_filter($bars, fn($bar) => $bar['isLongBar']);
        $longBars = array_values($longBars);
        
        $this->barcodeStructure = [
            'totalWidth' => $totalWidth - $marginLeft,
            'longBars' => $longBars,
            'allBars' => $bars,
            'hasLongBars' => !empty($longBars),
            'marginLeft' => $marginLeft,
        ];
        
        if (empty($longBars)) {
            return;
        }
        
        $longBarCount = count($longBars);
        
        if ($longBarCount >= 6) {
            $this->analyzeEANStructure($longBars);
        }
    }

    /**
     * 分析 EAN/UPC 系列条码结构
     */
    protected function analyzeEANStructure(array $longBars): void
    {
        usort($longBars, fn($a, $b) => $a['x'] <=> $b['x']);
        
        $startGuardLeft = $longBars[0];
        $startGuardRight = $longBars[1];
        
        $endGuardLeft = $longBars[count($longBars) - 2];
        $endGuardRight = $longBars[count($longBars) - 1];
        
        $middleIdx = (int)(count($longBars) / 2);
        $middleGuardLeft = $longBars[$middleIdx - 1];
        $middleGuardRight = $longBars[$middleIdx];
        
        $marginLeft = $this->barcodeStructure['marginLeft'];
        
        $this->barcodeStructure['regions'] = [
            'startGuard' => [
                'left' => $startGuardLeft['x'],
                'right' => $startGuardRight['x'] + $startGuardRight['width'],
            ],
            'middleGuard' => [
                'left' => $middleGuardLeft['x'],
                'right' => $middleGuardRight['x'] + $middleGuardRight['width'],
            ],
            'endGuard' => [
                'left' => $endGuardLeft['x'],
                'right' => $endGuardRight['x'] + $endGuardRight['width'],
            ],
            'leftData' => [
                'left' => $startGuardRight['x'] + $startGuardRight['width'],
                'right' => $middleGuardLeft['x'],
            ],
            'rightData' => [
                'left' => $middleGuardRight['x'] + $middleGuardRight['width'],
                'right' => $endGuardLeft['x'],
            ],
            'leftQuietZone' => [
                'left' => $marginLeft,
                'right' => $startGuardLeft['x'],
                'center' => (int)(($marginLeft + $startGuardLeft['x']) / 2),
            ],
            'rightQuietZone' => [
                'left' => $endGuardRight['x'] + $endGuardRight['width'],
                'right' => $this->barcodeStructure['totalWidth'] + $marginLeft,
                'center' => (int)((($endGuardRight['x'] + $endGuardRight['width']) + 
                    ($this->barcodeStructure['totalWidth'] + $marginLeft)) / 2),
            ],
        ];
    }

    /**
     * 构建文字元素
     */
    protected function buildTextElements(string $data, int $marginLeft): string
    {
        if (!$this->barcodeStructure['hasLongBars']) {
            return $this->buildSimpleText($data, $marginLeft);
        }
        
        return $this->buildSegmentedText($data);
    }

    /**
     * 简单文本（无长竖线）
     */
    protected function buildSimpleText(string $data, int $marginLeft): string
    {
        $textY = $this->config->marginTop + $this->config->height + $this->config->textOffset + $this->config->fontSize;
        $barColor = $this->config->barColor;
        $fontSize = $this->config->fontSize;
        
        return '  <text x="' . $marginLeft . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '">' . htmlspecialchars($data) . '</text>' . "\n";
    }

    /**
     * 分段文本（长竖线特征条码）
     */
    protected function buildSegmentedText(string $data): string
    {
        $regions = $this->barcodeStructure['regions'] ?? null;
        if ($regions === null) {
            return '';
        }
        
        $segments = $this->determineSegments($data);
        if (empty($segments)) {
            return '';
        }
        
        $this->calculateDynamicParameters($segments, $regions);
        
        $barY = $this->config->marginTop;
        $textY = $barY + (int)($this->config->height * $this->longBarHeightRatio) + $this->dynamicTextOffset;
        
        $textElements = '';
        foreach ($segments as $segment) {
            $textElements .= $this->buildSegment($segment, $regions, $textY);
        }
        
        return $textElements;
    }

    /**
     * 确定分段策略
     */
    protected function determineSegments(string $data): array
    {
        $length = strlen($data);
        $type = $this->barcodeType;
        
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
     * 计算动态参数
     */
    protected function calculateDynamicParameters(array $segments, array $regions): void
    {
        $barcodeWidth = $this->barcodeStructure['totalWidth'] ?? 200;
        $baseFontSize = $this->config->fontSize;
        
        // 动态调整字号 - 根据条码宽度和数据密度
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
            // 根据平均每个数字的宽度调整字号
            if ($avgRegionWidth < 8) {
                $this->dynamicFontSize = max(10, $baseFontSize - 2);
            } elseif ($avgRegionWidth > 12) {
                $this->dynamicFontSize = min(16, $baseFontSize + 2);
            } else {
                $this->dynamicFontSize = $baseFontSize;
            }
        } else {
            // 回退到基于总宽度的调整
            if ($barcodeWidth < 150) {
                $this->dynamicFontSize = max(10, $baseFontSize - 2);
            } elseif ($barcodeWidth > 400) {
                $this->dynamicFontSize = min(16, $baseFontSize + 2);
            } else {
                $this->dynamicFontSize = $baseFontSize;
            }
        }
        
        // 动态计算偏移 - 确保文字与长竖线有足够距离
        $longBarHeight = $this->config->height * $this->longBarHeightRatio;
        $normalHeight = $this->config->height;
        $extraHeight = $longBarHeight - $normalHeight;
        
        // 根据字号调整偏移量,确保文字不会太贴近长竖线
        $fontBasedOffset = (int)($this->dynamicFontSize * 0.2);
        $this->dynamicTextOffset = max(3, max($fontBasedOffset, (int)($extraHeight * 0.35)));
    }

    /**
     * 构建单个分段
     */
    protected function buildSegment(array $segment, array $regions, int $textY): string
    {
        $regionName = $segment['region'];
        if (!isset($regions[$regionName])) {
            return '';
        }
        
        $region = $regions[$regionName];
        $text = $segment['text'];
        $length = strlen($text);
        
        if ($length === 0) {
            return '';
        }
        
        $barColor = $this->config->barColor;
        $fontSize = $this->dynamicFontSize;
        
        $textElements = '';
        
        if ($segment['type'] === 'single' || $length === 1) {
            $centerX = $region['center'] ?? (int)(($region['left'] + $region['right']) / 2);
            $textElements .= '  <text x="' . $centerX . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . htmlspecialchars($text) . '</text>' . "\n";
        } else {
            $regionWidth = $region['right'] - $region['left'];
            $spacing = $regionWidth / $length;
            
            for ($i = 0; $i < $length; $i++) {
                $x = (int)($region['left'] + $spacing * $i + $spacing / 2);
                $textElements .= '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . htmlspecialchars($text[$i]) . '</text>' . "\n";
            }
        }
        
        return $textElements;
    }

    /**
     * 验证颜色对比度
     */
    protected function validateContrast(string $bgColor, string $barColor): bool
    {
        $bgLuminance = $this->getRelativeLuminance($bgColor);
        $barLuminance = $this->getRelativeLuminance($barColor);
        
        $lightest = max($bgLuminance, $barLuminance);
        $darkest = min($bgLuminance, $barLuminance);
        $contrast = ($lightest + 0.05) / ($darkest + 0.05);
        
        return $contrast >= 3.0;
    }

    /**
     * 获取颜色的相对亮度
     */
    protected function getRelativeLuminance(string $color): float
    {
        $rgb = $this->hexToRgb($color);
        
        $rsRGB = $rgb[0] / 255;
        $gsRGB = $rgb[1] / 255;
        $bsRGB = $rgb[2] / 255;
        
        $r = $rsRGB <= 0.03928 ? $rsRGB / 12.92 : pow(($rsRGB + 0.055) / 1.055, 2.4);
        $g = $gsRGB <= 0.03928 ? $gsRGB / 12.92 : pow(($gsRGB + 0.055) / 1.055, 2.4);
        $b = $bsRGB <= 0.03928 ? $bsRGB / 12.92 : pow(($bsRGB + 0.055) / 1.055, 2.4);
        
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * 十六进制颜色转换为RGB数组
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
}

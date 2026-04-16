<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Renderer;

use zxf\Utils\BarCode\DTO\BarcodeConfig;

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
class SvgRenderer extends BaseRenderer
{
    protected const TYPE = 'svg';

    /** @var int 动态计算的字号，用于长竖线条码的自动适配（SVG px） */
    protected int $dynamicFontSize = 12;

    /** @var int 动态计算的文本Y轴偏移量 */
    protected int $dynamicTextOffset = 3;

    public function __construct(?BarcodeConfig $config = null)
    {
        parent::__construct($config);
    }

    public function render(array $barcodeData, string $data, array $config = []): string
    {
        $this->config = BarcodeConfig::fromArray(array_merge($this->config->toArray(), $config));
        $this->barcodeStructure = []; // 重置结构分析
        $this->renderModuleSize = $this->resolveModuleSize($barcodeData);

        $totalWidth = 0;
        foreach ($barcodeData as $element) {
            $totalWidth += abs($element);
        }

        $moduleSize = $this->renderModuleSize;
        $barcodeWidth = $totalWidth * $moduleSize;

        // 确保有足够的静区宽度
        $marginLeft = $this->config->marginLeft;
        $marginRight = $this->config->marginRight;
        if ($this->config->showQuietZone) {
            $minQuietZone = 11 * $moduleSize;
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

        if ($this->bearerBarEnabled) {
            $height += $this->bearerBarWidth * 2;
        }

        $longBarPositions = $this->calculateLongBarPositions();

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

    /**
     * 直接输出SVG到浏览器
     */
    public function outputToBrowser(array $barcodeData, string $data, array $config = []): void
    {
        $svgData = $this->render($barcodeData, $data, $config);

        header('Content-Type: image/svg+xml');
        header('Content-Length: ' . strlen($svgData));
        header('Cache-Control: no-cache, must-revalidate');

        echo $svgData;
    }

    /**
     * 获取Base64编码的SVG
     */
    public function toBase64(array $barcodeData, string $data, array $config = []): string
    {
        $svgData = $this->render($barcodeData, $data, $config);
        return 'data:image/svg+xml;base64,' . base64_encode($svgData);
    }

    /**
     * 构建SVG并分析条码结构
     */
    protected function buildSvg(array $barcodeData, string $data, int $width, int $height, array $longBarPositions, int $marginLeft, int $marginRight): string
    {
        $bgColor = $this->config->bgColor;
        $barColor = $this->config->barColor;

        $isTransparent = ($bgColor === 'transparent' || $bgColor === null);

        if (!$isTransparent && !$this->validateContrast($bgColor, $barColor)) {
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

        if (!$isTransparent) {
            $svg .= '  <rect width="100%" height="100%" fill="' . $bgColor . '"/>' . "\n";
        }

        if ($this->borderWidth > 0) {
            $borderColor = htmlspecialchars($this->borderColor, ENT_XML1, 'UTF-8');
            $svg .= '  <rect x="0" y="0" width="' . $width . '" height="' . $height . '" fill="none" stroke="' . $borderColor . '" stroke-width="' . ($this->borderWidth * 2) . '"/>' . "\n";
        }

        // 绘制条码条并分析结构
        $x = $marginLeft;
        $fillColor = $this->enableGradient ? 'url(#barGradient)' : $barColor;
        
        // 记录所有条的位置
        $bars = [];
        
        foreach ($barcodeData as $elementIndex => $element) {
            $isBar = $element > 0;
            $barWidth = abs($element) * $this->renderModuleSize;

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

        // 绘制 Bearer Bar（ITF-14 标准框型）
        if ($this->bearerBarEnabled) {
            $svg .= $this->buildBearerBar($marginLeft, $width - $marginRight);
        }

        // 绘制文字
        if ($this->config->showText) {
            $svg .= $this->buildTextElements($data, $marginLeft);
        }

        // 绘制水印
        if ($this->watermarkText !== null) {
            $svg .= $this->buildWatermark($width, $height);
        }

        $svg .= '</svg>';

        return $svg;
    }

    /**
     * 构建 Bearer Bar SVG 元素（ITF-14 标准框型）
     */
    protected function buildBearerBar(int $contentLeft, int $contentRight): string
    {
        $thickness = $this->bearerBarWidth;
        $barTop = $this->config->marginTop;
        $barBottom = $barTop + $this->config->height - 1;
        $barColor = htmlspecialchars($this->config->barColor, ENT_XML1, 'UTF-8');
        $boxTop = max(0, $barTop - $thickness);
        $boxBottom = $barBottom + $thickness;
        $boxHeight = $boxBottom - $boxTop + 1;
        $contentWidth = max(1, $contentRight - $contentLeft);

        $svg = '';
        // 顶部横线
        $svg .= '  <rect x="' . $contentLeft . '" y="' . $boxTop . '" width="' . $contentWidth . '" height="' . $thickness . '" fill="' . $barColor . '"/>' . "\n";
        // 底部横线
        $svg .= '  <rect x="' . $contentLeft . '" y="' . ($barBottom + 1) . '" width="' . $contentWidth . '" height="' . $thickness . '" fill="' . $barColor . '"/>' . "\n";
        // 左侧垂直条
        $svg .= '  <rect x="' . $contentLeft . '" y="' . $boxTop . '" width="' . $thickness . '" height="' . $boxHeight . '" fill="' . $barColor . '"/>' . "\n";
        // 右侧垂直条
        $svg .= '  <rect x="' . ($contentRight - $thickness) . '" y="' . $boxTop . '" width="' . $thickness . '" height="' . $boxHeight . '" fill="' . $barColor . '"/>' . "\n";

        return $svg;
    }

    /**
     * 构建水印元素
     */
    protected function buildWatermark(int $width, int $height): string
    {
        $opacity = round($this->watermarkOpacity / 100, 2);
        $fontSize = $this->watermarkFontSize;
        $text = htmlspecialchars($this->watermarkText ?? '', ENT_XML1, 'UTF-8');

        // 估算文本宽度（按字符宽度为字号的0.6倍）
        $textWidth = (int) (strlen($text) * $fontSize * 0.6);
        $textHeight = $fontSize;

        $x = (int) (($width - $textWidth) / 2);
        $y = (int) (($height + $textHeight) / 2);

        switch ($this->watermarkPosition) {
            case 'top-left':
                $x = 10;
                $y = 10 + $fontSize;
                break;
            case 'top-right':
                $x = $width - $textWidth - 10;
                $y = 10 + $fontSize;
                break;
            case 'bottom-left':
                $x = 10;
                $y = $height - 10;
                break;
            case 'bottom-right':
                $x = $width - $textWidth - 10;
                $y = $height - 10;
                break;
            case 'top':
                $x = (int) (($width - $textWidth) / 2);
                $y = 10 + $fontSize;
                break;
            case 'bottom':
                $x = (int) (($width - $textWidth) / 2);
                $y = $height - 10;
                break;
            case 'left':
                $x = 10;
                $y = (int) (($height + $textHeight) / 2);
                break;
            case 'right':
                $x = $width - $textWidth - 10;
                $y = (int) (($height + $textHeight) / 2);
                break;
            case 'center':
            default:
                $x = (int) (($width - $textWidth) / 2);
                $y = (int) (($height + $textHeight) / 2);
                break;
        }

        $transform = '';
        if ($this->watermarkAngle !== 0) {
            $transform = sprintf(' transform="rotate(%d,%d,%d)"', $this->watermarkAngle, $x + (int)($textWidth / 2), $y - (int)($fontSize / 2));
        }

        return sprintf(
            '  <text x="%d" y="%d" font-family="Arial, sans-serif" font-size="%d" fill="%s" opacity="%.2f" text-anchor="start"%s>%s</text>' . "\n",
            $x,
            $y,
            $fontSize,
            $this->watermarkColor,
            $opacity,
            $transform,
            $text
        );
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
        $barColor = $this->config->barColor;
        $fontSize = $this->config->fontSize;
        $imgWidth = $this->barcodeStructure['totalWidth'] + $marginLeft + $this->config->marginRight;

        if ($this->config->textPosition === 'top') {
            $textY = $this->config->marginTop - $this->config->textOffset;
            $textY = max($fontSize, $textY);
        } else {
            $textY = $this->config->marginTop + $this->config->height + $this->config->textOffset + $fontSize;
        }

        switch ($this->textAlign) {
            case 'left':
                $x = $marginLeft;
                $anchor = 'start';
                break;
            case 'right':
                $x = $imgWidth - $this->config->marginRight;
                $anchor = 'end';
                break;
            case 'center':
            default:
                $x = (int)($imgWidth / 2);
                $anchor = 'middle';
                break;
        }

        return '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="' . $anchor . '">' . htmlspecialchars($data) . '</text>' . "\n";
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

}

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

    /**
     * 构造 SVG 渲染器
     *
     * @param BarcodeConfig|null $config 条码配置对象；为 null 时使用默认配置
     */
    public function __construct(?BarcodeConfig $config = null)
    {
        parent::__construct($config);
    }

    /**
     * 将条空模式渲染为 SVG 字符串
     *
     * 解析模块大小与静区，动态计算长竖线位置，并以 viewBox 容纳其向下延长量，
     * 委托 buildSvg 绘制条、Bearer Bar、文字与水印。
     *
     * @param array<int> $barcodeData 条空模式数组（正数=条，负数=空，绝对值为模块数）
     * @param string $data 要在条码下方显示的文字（通常为完整编码数据）
     * @param array<string, mixed> $config 渲染配置项，可选，会与已有配置合并
     * @return string 完整 SVG 文档字符串
     */
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
            $minQuietZone = $this->quietZoneWidthPx();
            $marginLeft = max($marginLeft, $minQuietZone);
            $marginRight = max($marginRight, $minQuietZone);
        }
        
        $width = $barcodeWidth + $marginLeft + $marginRight;
        $height = $this->config->height + $this->config->marginTop + $this->config->marginBottom;

        if ($this->config->showText) {
            $height += $this->config->fontSize + $this->config->textOffset;
        }

        if ($this->bearerBarEnabled) {
            $height += $this->bearerBarWidth * 2;
        }

        // 长竖线（保护符）从 marginTop 起绘 height×ratio 的高度：
        // viewBox 高度必须容纳向下的延长量，否则 SVG 矢量会被视口裁剪
        if ($this->longBarsEnabled()) {
            $height += (int) ceil($this->config->height * ($this->longBarHeightRatio - 1));
        }

        $longBarPositions = $this->calculateLongBarPositions();

        return $this->buildSvg($barcodeData, $data, $width, $height, $longBarPositions, $marginLeft, $marginRight);
    }

    /**
     * 渲染并将 SVG 写入文件
     *
     * 目标目录不存在时自动递归创建。写入失败（如目录不可写）返回 false。
     *
     * @param array<int> $barcodeData 条空模式数组
     * @param string $data 显示文字
     * @param string $filename 目标 SVG 文件路径
     * @param array<string, mixed> $config 渲染配置项，可选
     * @return bool 写入成功返回 true，失败返回 false
     */
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
     *
     * 先渲染出 SVG 字符串，再以 image/svg+xml 类型及正确 Content-Length
     * 输出到标准输出，并附带禁止缓存的响应头。
     *
     * @param array<int> $barcodeData 条空模式数组（正数为条、负数为空）
     * @param string $data 原始数据（用于下方文字）
     * @param array $config 渲染配置覆盖项
     * @return void
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
     * 构建SVG并分析条码结构
     *
     * 依次输出背景（透明背景下跳过）、可选渐变定义、边框、每条矩形、
     * Bearer Bar、文字与水印。背景与条色对比度不足 3:1 时自动改写为
     * 黑白配色，transparent 背景则跳过对比度校验。
     *
     * @param array<int> $barcodeData 条空模式数组（正数为条、负数为空）
     * @param string $data 原始数据（用于下方文字）
     * @param int $width 画布总宽度（像素）
     * @param int $height 画布总高度（像素）
     * @param array<int> $longBarPositions 需要加长的条下标
     * @param int $marginLeft 左边距（像素）
     * @param int $marginRight 右边距（像素）
     * @return string 完整的 SVG XML 字符串
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

        if ($this->gradientUsable()) {
            $svg .= '  <defs>' . "\n";
            // 渐变统一为水平方向（与 PNG 逐列渐变、HTML linear-gradient(to right) 一致）
            $svg .= '    <linearGradient id="barGradient" x1="0%" y1="0%" x2="100%" y2="0%">' . "\n";
            $svg .= '      <stop offset="0%" style="stop-color:' . $this->escapeXmlAttr($this->gradientStartColor) . '"/>' . "\n";
            $svg .= '      <stop offset="100%" style="stop-color:' . $this->escapeXmlAttr($this->gradientEndColor) . '"/>' . "\n";
            $svg .= '    </linearGradient>' . "\n";
            $svg .= '  </defs>' . "\n";
        }

        if (!$isTransparent) {
            $svg .= '  <rect width="100%" height="100%" fill="' . $this->escapeXmlAttr($bgColor) . '"/>' . "\n";
        }

        if ($this->borderWidth > 0) {
            $borderColor = $this->escapeXmlAttr($this->borderColor);
            $svg .= '  <rect x="0" y="0" width="' . $width . '" height="' . $height . '" fill="none" stroke="' . $borderColor . '" stroke-width="' . ($this->borderWidth * 2) . '"/>' . "\n";
        }

        // 绘制条码条并分析结构
        $x = $marginLeft;
        $fillColor = $this->gradientUsable() ? 'url(#barGradient)' : $this->escapeXmlAttr($barColor);
        
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
                
                // 与 PNG 一致：仅当条宽足够容纳两倍圆角半径时才应用圆角
                $rx = ($this->enableRoundedBars && $barWidth > $this->cornerRadius * 2) ? $this->cornerRadius : 0;
                
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
     *
     * 于条区上下各绘制一条贯通左右的粗线，并在内容区左右两端补竖线，
     * 形成闭合外框，厚度取 bearerBarWidth。
     *
     * @param int $contentLeft 内容区左边界 x 坐标（像素）
     * @param int $contentRight 内容区右边界 x 坐标（像素）
     * @return string 组成外框的 rect 片段
     */
    protected function buildBearerBar(int $contentLeft, int $contentRight): string
    {
        $thickness = $this->bearerBarWidth;
        $barTop = $this->config->marginTop;
        $barBottom = $barTop + $this->config->height - 1;
        $barColor = $this->escapeXmlAttr($this->config->barColor);
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
     *
     * 按「字符数 × 字号 × 0.6」估算文本宽度，再依 watermarkPosition 计算
     * 九宫格锚点坐标；角度非 0 时以文本中心为轴追加 rotate 变换。
     * 文本经 XML 转义，避免破坏 SVG 结构。
     *
     * @param int $width 画布宽度（像素），用于右侧/居中定位
     * @param int $height 画布高度（像素），用于底部/居中定位
     * @return string 水印 text 元素片段
     */
    protected function buildWatermark(int $width, int $height): string
    {
        $opacity = round($this->watermarkOpacity / 100, 2);
        $fontSize = $this->watermarkFontSize;
        // 文本宽度按原始文本估算，避免实体化（&amp; 等）导致长度虚高
        $rawText = $this->watermarkText ?? '';
        $text = $this->escapeXmlAttr($rawText);

        // 估算文本宽度（按字符宽度为字号的0.6倍）
        $textWidth = (int) (strlen($rawText) * $fontSize * 0.6);
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
            $this->escapeXmlAttr($this->watermarkColor),
            $opacity,
            $transform,
            $text
        );
    }

    /**
     * 构建文字元素
     *
     * 条码结构含长条（EAN/UPC 等）时分段逐字符定位，否则整段按对齐方式绘制，
     * 两条路径在无区域信息时均安全回退。
     *
     * @param string $data 要显示的完整条码数据
     * @param int $marginLeft 左边距（像素），用于无长条时计算文字起点
     * @return string 文字 text 元素片段（可能为空串）
     */
    protected function buildTextElements(string $data, int $marginLeft): string
    {
        if (!$this->barcodeStructure['hasLongBars']) {
            return $this->buildSimpleText($data, $marginLeft);
        }
        
        return $this->buildSegmentedText($data, $marginLeft);
    }

    /**
     * 简单文本（无长竖线）
     *
     * 整段文字作为一个 text 元素绘制，纵向位置受 textPosition 影响：
     * top 时置于条区上方且不高于字号高度，否则置于条区下方。
     *
     * @param string $data 要显示的完整条码数据
     * @param int $marginLeft 左边距（像素），作为左对齐起点
     * @return string 单个 text 元素片段
     */
    protected function buildSimpleText(string $data, int $marginLeft): string
    {
        $barColor = $this->escapeXmlAttr($this->config->barColor);
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

        return '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="' . $anchor . '">' . $this->escapeXmlAttr($data) . '</text>' . "\n";
    }

    /**
     * 分段文本（长竖线特征条码）
     *
     * 先确认区域信息与分段结果可用，任一缺失都回退为整段文本，避免文字
     * 整体消失；随后按分段计算动态字号与偏移，逐段生成 text 元素。
     *
     * @param string $data 要显示的完整条码数据
     * @param int $marginLeft 左边距（像素），仅在回退时使用
     * @return string 各分段 text 元素拼接结果
     */
    protected function buildSegmentedText(string $data, int $marginLeft): string
    {
        $regions = $this->barcodeStructure['regions'] ?? null;
        if ($regions === null) {
            // 与 PNG/HTML 一致：无区域信息时回退为整段文本，避免文字整段消失
            return $this->buildSimpleText($data, $marginLeft);
        }
        
        $segments = $this->determineSegments($data);
        if (empty($segments)) {
            return $this->buildSimpleText($data, $marginLeft);
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
     * 确定分段策略（与 PNG/HTML 渲染器共用 BaseRenderer 的实现）
     *
     * 直接委托 splitDisplaySegments()，保证三种渲染器的分段规则一致。
     *
     * @param string $data 要显示的完整条码数据
     * @return list<array{text: string, region: string, type: string}> 分段列表，不适用时为空数组
     */
    protected function determineSegments(string $data): array
    {
        return $this->splitDisplaySegments($data);
    }

    /**
     * 计算动态参数
     *
     * 依据多字符分段的平均单字宽度（或回退到总宽度）在基础字号上下浮动 2px，
     * 并限制在 10-16 之间；同时按长条溢出高度算出文字偏移，最小 3px。
     * 结果写入 dynamicFontSize 与 dynamicTextOffset 属性。
     *
     * @param list<array{text: string, region: string, type: string}> $segments 文字分段列表
     * @param array<string, array<string, int>> $regions 各区域边界（left/right/center）
     * @return void
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
     *
     * 区域内只有 1 个字符时定位到区域中心（优先使用预置 center 坐标）；
     * 多字符时把区域宽度等分，逐字符居中排布。区域不存在或文本为空时
     * 返回空串。字号取 calculateDynamicParameters() 算出的动态字号。
     *
     * @param array{text: string, region: string, type: string} $segment 单个分段
     * @param array<string, array<string, int>> $regions 各区域边界（left/right/center）
     * @param int $textY 文字基线 y 坐标（像素）
     * @return string 该分段生成的 text 元素片段
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
        
        $barColor = $this->escapeXmlAttr($this->config->barColor);
        $fontSize = $this->dynamicFontSize;
        
        $textElements = '';
        
        if ($segment['type'] === 'single' || $length === 1) {
            $centerX = $region['center'] ?? (int)(($region['left'] + $region['right']) / 2);
            $textElements .= '  <text x="' . $centerX . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $this->escapeXmlAttr($text) . '</text>' . "\n";
        } else {
            $regionWidth = $region['right'] - $region['left'];
            $spacing = $regionWidth / $length;
            
            for ($i = 0; $i < $length; $i++) {
                $x = (int)($region['left'] + $spacing * $i + $spacing / 2);
                $textElements .= '  <text x="' . $x . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" fill="' . $barColor . '" text-anchor="middle">' . $this->escapeXmlAttr($text[$i]) . '</text>' . "\n";
            }
        }
        
        return $textElements;
    }

}

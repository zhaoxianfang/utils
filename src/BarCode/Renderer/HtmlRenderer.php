<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Renderer;

use zxf\Utils\BarCode\DTO\BarcodeConfig;

/**
 * HTML/CSS 渲染器
 *
 * 使用纯 HTML/CSS 渲染条形码，兼容所有现代浏览器。
 */
class HtmlRenderer extends BaseRenderer
{
    protected const TYPE = 'html';

    /** @var array<int> 最近一次渲染的条空模式（供文字分段结构分析使用） */
    protected array $barcodeData = [];

    public function __construct(?BarcodeConfig $config = null)
    {
        parent::__construct($config);
    }

    public function render(array $barcodeData, string $data, array $config = []): string
    {
        $cssClasses = [
            'wrapper'  => $config['wrapperClass'] ?? 'barcode-wrapper',
            'bars'     => $config['barsClass'] ?? 'barcode-bars',
            'bar'      => $config['barClass'] ?? 'barcode-bar',
            'space'    => $config['spaceClass'] ?? 'barcode-space',
            'text'     => $config['textClass'] ?? 'barcode-text',
            'bearer'   => $config['bearerClass'] ?? 'barcode-bearer',
        ];

        // 净化 CSS 类名，防止属性注入（仅保留字母数字、连字符、下划线与空格）
        foreach ($cssClasses as $k => $v) {
            $cssClasses[$k] = preg_replace('/[^A-Za-z0-9_\-\s]/', '', (string) $v) ?? '';
        }

        $barcodeConfig = $config;
        foreach (array_keys($cssClasses) as $k) {
            unset($barcodeConfig[$k . 'Class']);
        }

        $this->config = BarcodeConfig::fromArray(array_merge($this->config->toArray(), $barcodeConfig));
        $this->barcodeStructure = [];
        $this->renderModuleSize = $this->resolveModuleSize($barcodeData);
        $this->barcodeData = $barcodeData;

        $totalModules = 0;
        foreach ($barcodeData as $element) {
            $totalModules += abs($element);
        }

        $moduleSize = $this->renderModuleSize;
        $barcodeWidth = $totalModules * $moduleSize;

        $marginLeft = $this->config->marginLeft;
        $marginRight = $this->config->marginRight;
        if ($this->config->showQuietZone) {
            $minQuietZone = $this->quietZoneWidthPx();
            $marginLeft = max($marginLeft, $minQuietZone);
            $marginRight = max($marginRight, $minQuietZone);
        }

        $width = $barcodeWidth + $marginLeft + $marginRight;
        $height = $this->config->height + $this->config->marginTop + $this->config->marginBottom;
        $textHeight = 0;
        if ($this->config->showText) {
            $textHeight = $this->config->fontSize + $this->config->textOffset;
            $height += $textHeight;
        }

        $bearerHeight = 0;
        if ($this->bearerBarEnabled) {
            $bearerHeight = $this->bearerBarWidth * 2;
            $height += $bearerHeight;
        }

        // 长竖线（保护符）是否真的向下延长
        $longBarsEnabled = $this->longBarsEnabled();
        $longBarOverhang = 0;
        if ($longBarsEnabled) {
            // 与 PNG/SVG 的 ceil 取整保持一致，避免同参数下三种格式容器高度差 1px
            $longBarOverhang = (int) ceil($this->config->height * ($this->longBarHeightRatio - 1));
            $height += $longBarOverhang;
        }

        // 与 PNG/SVG 一致的对比度保护：前景/背景过于接近时回退为黑白
        $bgRaw = $this->config->bgColor;
        $barRaw = $this->config->barColor;
        if ($bgRaw !== null && $bgRaw !== 'transparent' && !$this->validateContrast($bgRaw, $barRaw)) {
            $bgRaw = '#FFFFFF';
            $barRaw = '#000000';
        }
        $barColor = $this->sanitizeCssValue($barRaw);
        $bgColor = $this->sanitizeCssValue($bgRaw);
        $borderColor = $this->sanitizeCssValue($this->borderColor);

        // 边框绘制在 wrapper 最外层（box-sizing:border-box），内部内容空间保持不变
        $borderPad = $this->borderWidth * 2;
        $width += $borderPad;
        $height += $borderPad;

        $html = '<div class="' . $cssClasses['wrapper'] . '" style="';
        $html .= 'display:inline-block;position:relative;box-sizing:border-box;';
        $html .= 'width:' . $width . 'px;height:' . $height . 'px;';
        if ($this->borderWidth > 0) {
            $html .= 'border:' . $this->borderWidth . 'px solid ' . $borderColor . ';';
        }
        if ($bgColor !== 'transparent') {
            $html .= 'background:' . $bgColor . ';';
        }
        $html .= '">' . "\n";

        if ($this->bearerBarEnabled) {
            // 顶横线（与条码静区对齐的内容区宽度）
            $html .= '  <div class="' . $cssClasses['bearer'] . '" style="';
            $html .= 'position:absolute;top:0;left:' . $marginLeft . 'px;right:' . $marginRight . 'px;';
            $html .= 'height:' . $this->bearerBarWidth . 'px;';
            $html .= 'background:' . $barColor . ';"></div>' . "\n";
        }

        $barsTop = $this->config->marginTop + ($this->bearerBarEnabled ? $this->bearerBarWidth : 0);
        $barHeight = $this->config->height;

        $html .= '  <div class="' . $cssClasses['bars'] . '" style="';
        $html .= 'display:flex;flex-direction:row;align-items:flex-start;';
        $html .= 'position:absolute;top:' . $barsTop . 'px;left:' . $marginLeft . 'px;';
        $html .= 'width:' . $barcodeWidth . 'px;height:' . $barHeight . 'px;';
        $html .= '">' . "\n";

        $currentIndex = 0;
        foreach ($barcodeData as $element) {
            $absWidth = abs($element) * $moduleSize;
            $isBar = $element > 0;

            if ($isBar) {
                $isLongBar = $longBarsEnabled && in_array($currentIndex, $this->longBarPositions, true);
                $h = $isLongBar ? (int) ($barHeight * $this->longBarHeightRatio) : $barHeight;
                // 渐变（水平方向）与 PNG 逐列渐变、SVG linearGradient 方向一致；
                // 经对比度守卫：两端与背景对比不足时回退纯色，保证可扫性
                $barFill = $this->gradientUsable()
                    ? 'linear-gradient(to right,' . $this->sanitizeCssValue($this->gradientStartColor) . ',' . $this->sanitizeCssValue($this->gradientEndColor) . ')'
                    : $barColor;
                $html .= '    <div class="' . $cssClasses['bar'] . '" style="';
                $html .= 'width:' . $absWidth . 'px;height:' . $h . 'px;';
                $html .= 'background:' . $barFill . ';flex-shrink:0;';
                // 圆角四角并带宽度保护（与 PNG/SVG 条件一致：条宽必须大于两倍半径）
                if ($this->enableRoundedBars && $absWidth > $this->cornerRadius * 2) {
                    $html .= 'border-radius:' . min($this->cornerRadius, (int) ($absWidth / 2)) . 'px;';
                }
                $html .= '"></div>' . "\n";
            } else {
                $html .= '    <div class="' . $cssClasses['space'] . '" style="';
                $html .= 'width:' . $absWidth . 'px;height:1px;flex-shrink:0;';
                $html .= '"></div>' . "\n";
            }
            $currentIndex++;
        }

        $html .= '  </div>' . "\n";

        if ($this->bearerBarEnabled) {
            // 左右竖线 + 底横线：闭合为与 PNG/SVG 一致的内容区矩形框
            $innerH = $height - $borderPad;
            $vBottom = max(0, $innerH - $this->config->marginBottom - $longBarOverhang - $this->bearerBarWidth);
            $html .= '  <div class="' . $cssClasses['bearer'] . '" style="';
            $html .= 'position:absolute;top:0;left:' . $marginLeft . 'px;';
            $html .= 'width:' . $this->bearerBarWidth . 'px;height:' . $vBottom . 'px;';
            $html .= 'background:' . $barColor . ';"></div>' . "\n";
            $html .= '  <div class="' . $cssClasses['bearer'] . '" style="';
            $html .= 'position:absolute;top:0;right:' . $marginRight . 'px;';
            $html .= 'width:' . $this->bearerBarWidth . 'px;height:' . $vBottom . 'px;';
            $html .= 'background:' . $barColor . ';"></div>' . "\n";
            $html .= '  <div class="' . $cssClasses['bearer'] . '" style="';
            $html .= 'position:absolute;bottom:' . ($this->config->marginBottom + $longBarOverhang) . 'px;';
            $html .= 'left:' . $marginLeft . 'px;right:' . $marginRight . 'px;height:' . $this->bearerBarWidth . 'px;';
            $html .= 'background:' . $barColor . ';"></div>' . "\n";
        }

        if ($this->config->showText && $data !== '') {
            $html .= $this->renderTextBlock($data, $cssClasses, $barColor, $width - $borderPad, $barcodeWidth, $marginLeft, $barsTop, $barHeight, $longBarsEnabled, $longBarOverhang);
        }

        if ($this->watermarkText !== null && $this->watermarkText !== '') {
            $html .= $this->renderWatermarkHtml();
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * 渲染水印（纯 HTML/CSS：九宫格定位 + 旋转 + 透明度）
     *
     * 与 PNG/SVG 水印语义一致（同 watermarkPosition/watermarkOpacity/watermarkAngle 配置）。
     */
    protected function renderWatermarkHtml(): string
    {
        $text = htmlspecialchars($this->watermarkText ?? '', ENT_QUOTES, 'UTF-8');
        $color = $this->sanitizeCssValue($this->watermarkColor);
        $opacity = round($this->watermarkOpacity / 100, 2);
        $fontSize = max(8, (int) $this->watermarkFontSize);
        $angle = (int) $this->watermarkAngle;

        $hAlign = match ($this->watermarkPosition) {
            'top-left', 'bottom-left', 'left' => 'flex-start',
            'top-right', 'bottom-right', 'right' => 'flex-end',
            default => 'center',
        };
        $vAlign = match ($this->watermarkPosition) {
            'top-left', 'top-right', 'top' => 'flex-start',
            'bottom-left', 'bottom-right', 'bottom' => 'flex-end',
            default => 'center',
        };

        $style = 'display:flex;align-items:' . $vAlign . ';justify-content:' . $hAlign . ';';
        $style .= 'position:absolute;top:0;left:0;right:0;bottom:0;';
        $style .= 'padding:10px;pointer-events:none;overflow:hidden;';
        $style .= 'color:' . $color . ';font-family:monospace;font-size:' . $fontSize . 'px;';
        $style .= 'opacity:' . $opacity . ';white-space:nowrap;';
        if ($angle !== 0) {
            $style .= 'transform:rotate(' . $angle . 'deg);';
        }

        return '  <div style="' . $style . '"><span>' . $text . '</span></div>' . "\n";
    }

    /**
     * 渲染条码下方文字
     *
     * EAN/UPC 系列走「分段定位」：每个字符按保护符区域中心精确摆放，
     * 与 PNG 渲染器的分段逻辑保持一致；其余条码走 textAlign 对齐。
     */
    protected function renderTextBlock(
        string $data,
        array $cssClasses,
        string $barColor,
        int $wrapperWidth,
        int $barcodeWidth,
        int $marginLeft,
        int $barsTop,
        int $barHeight,
        bool $longBarsEnabled,
        int $longBarOverhang
    ): string {
        // 文字紧贴长竖线底部，避免 overlap
        $textTop = $barsTop + $barHeight + $this->config->textOffset;
        if ($longBarsEnabled) {
            $textTop += $longBarOverhang;
        }

        $fontSize = $this->config->fontSize;
        $segments = $this->splitDisplaySegments($data);

        if ($segments === []) {
            // 无保护符分段需求 → 按 textAlign 对齐整段文字
            $justify = match ($this->textAlign) {
                'left' => 'flex-start',
                'right' => 'flex-end',
                default => 'center',
            };

            return '  <div class="' . $cssClasses['text'] . '" style="'
                . 'display:flex;justify-content:' . $justify . ';position:absolute;'
                . 'top:' . $textTop . 'px;left:' . $marginLeft . 'px;'
                . 'width:' . $barcodeWidth . 'px;'
                . 'font-family:monospace;font-size:' . $fontSize . 'px;'
                . 'color:' . $barColor . ';line-height:1;'
                . '">' . "\n"
                . '    <span>' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '</span>' . "\n"
                . '  </div>' . "\n";
        }

        // 结构分析（regions 为整个 wrapper 坐标系的绝对像素，条起点叠加 marginLeft）
        if (empty($this->barcodeStructure) || !isset($this->barcodeStructure['regions'])) {
            $bars = $this->buildBarGeometries($this->barcodeData, $marginLeft);
            $this->analyzeBarcodeStructure($bars, $barcodeWidth, $marginLeft);
        }

        $regions = $this->barcodeStructure['regions'] ?? [];
        $charWidth = (int) round($fontSize * 0.6); // monospace 数字字符宽度近似

        $html = '  <div class="' . $cssClasses['text'] . '" style="'
            . 'position:absolute;top:' . $textTop . 'px;left:0;'
            . 'width:' . $wrapperWidth . 'px;height:' . $fontSize . 'px;'
            . 'font-family:monospace;font-size:' . $fontSize . 'px;'
            . 'color:' . $barColor . ';line-height:1;'
            . '">' . "\n";

        foreach ($segments as $segment) {
            $region = $regions[$segment['region']] ?? null;
            if ($region === null || $segment['text'] === '') {
                continue;
            }

            $length = strlen($segment['text']);
            if ($segment['type'] === 'single' || $length === 1) {
                $centerX = (int) (($region['left'] + $region['right']) / 2);
                $html .= $this->textSpan($segment['text'], $centerX, $charWidth, $fontSize);
            } else {
                $regionWidth = $region['right'] - $region['left'];
                $spacing = $regionWidth / $length;
                for ($i = 0; $i < $length; $i++) {
                    $centerX = (int) ($region['left'] + $spacing * $i + $spacing / 2);
                    $html .= $this->textSpan($segment['text'][$i], $centerX, $charWidth, $fontSize);
                }
            }
        }

        return $html . '  </div>' . "\n";
    }

    /**
     * 生成单字符 span（按中心点定位）
     */
    protected function textSpan(string $char, int $centerX, int $charWidth, int $fontSize): string
    {
        $left = $centerX - (int) ($charWidth / 2);
        if ($left < 0) {
            $left = 0;
        }

        return '    <span style="position:absolute;left:' . $left . 'px;top:0;'
            . 'width:' . $charWidth . 'px;text-align:center;'
            . '">' . htmlspecialchars($char, ENT_QUOTES, 'UTF-8') . '</span>' . "\n";
    }

    public function saveToFile(array $barcodeData, string $data, string $filename, array $config = []): bool
    {
        $html = $this->render($barcodeData, $data, $config);
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($filename, $html) !== false;
    }

    public function outputToBrowser(array $barcodeData, string $data, array $config = []): void
    {
        $html = $this->render($barcodeData, $data, $config);
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Length: ' . strlen($html));
        header('Cache-Control: no-cache, must-revalidate');
        echo $html;
    }
}

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

        $totalModules = 0;
        foreach ($barcodeData as $element) {
            $totalModules += abs($element);
        }

        $moduleSize = $this->renderModuleSize;
        $barcodeWidth = $totalModules * $moduleSize;

        $marginLeft = $this->config->marginLeft;
        $marginRight = $this->config->marginRight;
        if ($this->config->showQuietZone) {
            $minQuietZone = 11 * $moduleSize;
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

        $longBarPositions = $this->calculateLongBarPositions();

        $barColor = $this->sanitizeCssValue($this->config->barColor);
        $bgColor = $this->sanitizeCssValue($this->config->bgColor);

        $html = '<div class="' . $cssClasses['wrapper'] . '" style="';
        $html .= 'display:inline-block;position:relative;';
        $html .= 'width:' . $width . 'px;height:' . $height . 'px;';
        if ($bgColor !== 'transparent') {
            $html .= 'background:' . $bgColor . ';';
        }
        $html .= '">' . "\n";

        if ($this->bearerBarEnabled) {
            $html .= '  <div class="' . $cssClasses['bearer'] . '" style="';
            $html .= 'position:absolute;top:0;left:0;right:0;height:' . $this->bearerBarWidth . 'px;';
            $html .= 'background:' . $barColor . ';"></div>' . "\n";
        }

        $barsTop = $this->config->marginTop + ($this->bearerBarEnabled ? $this->bearerBarWidth : 0);
        $barHeight = $this->config->height;

        $html .= '  <div class="' . $cssClasses['bars'] . '" style="';
        $html .= 'display:flex;flex-direction:row;align-items:flex-end;';
        $html .= 'position:absolute;top:' . $barsTop . 'px;left:' . $marginLeft . 'px;';
        $html .= 'width:' . $barcodeWidth . 'px;height:' . $barHeight . 'px;';
        $html .= '">' . "\n";

        $currentIndex = 0;
        foreach ($barcodeData as $element) {
            $absWidth = abs($element) * $moduleSize;
            $isBar = $element > 0;
            $isLongBar = in_array($currentIndex, $longBarPositions, true);

            if ($isBar) {
                $h = $isLongBar ? (int)($barHeight * $this->longBarHeightRatio) : $barHeight;
                $html .= '    <div class="' . $cssClasses['bar'] . '" style="';
                $html .= 'width:' . $absWidth . 'px;height:' . $h . 'px;';
                $html .= 'background:' . $barColor . ';flex-shrink:0;';
                if ($this->enableRoundedBars && $absWidth > 2) {
                    $html .= 'border-radius:' . min($this->cornerRadius, (int)($absWidth / 2)) . 'px ';
                    $html .= min($this->cornerRadius, (int)($absWidth / 2)) . 'px 0 0;';
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
            $html .= '  <div class="' . $cssClasses['bearer'] . '" style="';
            $html .= 'position:absolute;bottom:' . ($textHeight + $this->config->marginBottom) . 'px;';
            $html .= 'left:0;right:0;height:' . $this->bearerBarWidth . 'px;';
            $html .= 'background:' . $barColor . ';"></div>' . "\n";
        }

        if ($this->config->showText && $data !== '') {
            $textTop = $barsTop + $barHeight + $this->config->textOffset;
            $segments = $this->getTextSegments($data);
            $html .= '  <div class="' . $cssClasses['text'] . '" style="';
            $html .= 'display:flex;justify-content:space-between;position:absolute;';
            $html .= 'top:' . $textTop . 'px;left:' . $marginLeft . 'px;';
            $html .= 'width:' . $barcodeWidth . 'px;';
            $html .= 'font-family:monospace;font-size:' . $this->config->fontSize . 'px;';
            $html .= 'color:' . $barColor . ';line-height:1;';
            $html .= '">' . "\n";
            foreach ($segments as $segment) {
                $html .= '    <span>' . htmlspecialchars($segment) . '</span>' . "\n";
            }
            $html .= '  </div>' . "\n";
        }

        $html .= '</div>';
        return $html;
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

    protected function getTextSegments(string $data): array
    {
        if (empty($this->barcodeStructure) || !isset($this->barcodeStructure['textSegments'])) {
            return [$data];
        }
        return $this->barcodeStructure['textSegments'];
    }
}

<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Renderer;

use zxf\Utils\BarCode\Contracts\RendererInterface;
use zxf\Utils\BarCode\DTO\BarcodeConfig;

/**
 * 渲染器抽象基类
 *
 * 统一 PNG/SVG 等渲染器的公共属性、默认参数和工具方法，
 * 确保不同渲染器在相同配置下具有一致的外观和行为。
 */
abstract class BaseRenderer implements RendererInterface
{
    /** @var string 渲染器类型标识 */
    protected const TYPE = 'base';

    /** @var BarcodeConfig 条码配置对象，控制尺寸、颜色、边距等 */
    protected BarcodeConfig $config;

    /** @var string 当前条码类型名称（如 EAN-13、Code 128） */
    protected string $barcodeType = '';

    /** @var bool 是否启用渐变条效果 */
    protected bool $enableGradient = false;

    /** @var string 渐变起始颜色（十六进制） */
    protected string $gradientStartColor = '#000000';

    /** @var string 渐变结束颜色（十六进制） */
    protected string $gradientEndColor = '#333333';

    /** @var bool 是否启用圆角条 */
    protected bool $enableRoundedBars = false;

    /** @var int 圆角半径（像素） */
    protected int $cornerRadius = 2;

    /** @var array<int> 长竖线（保护符）在条空模式中的索引位置 */
    protected array $longBarPositions = [];

    /** @var float 长竖线相对于普通条的高度比例（默认 1.15） */
    protected float $longBarHeightRatio = 1.15;

    /** @var array<string, mixed> 条码结构分析结果，包含各区域边界 */
    protected array $barcodeStructure = [];

    /** @var string 普通条码文本对齐方式（left/center/right） */
    protected string $textAlign = 'center';

    /** @var string|null 水印文本内容 */
    protected ?string $watermarkText = null;

    /** @var int 水印透明度（0-100，值越大越不透明） */
    protected int $watermarkOpacity = 50;

    /** @var int 水印字号（像素或 GD 内置字体级别，默认 16） */
    protected int $watermarkFontSize = 16;

    /** @var string 水印颜色（十六进制） */
    protected string $watermarkColor = '#CCCCCC';

    /** @var int 水印旋转角度（-180 到 180 度，0 表示不旋转） */
    protected int $watermarkAngle = 0;

    /** @var string 水印位置（center/top/bottom/left/right/top-left/top-right/bottom-left/bottom-right） */
    protected string $watermarkPosition = 'center';

    /** @var string|null TTF 字体文件路径，用于高质量水印文字 */
    protected ?string $watermarkFontPath = null;

    /** @var bool 是否启用 Bearer Bar（ITF-14 上下边框） */
    protected bool $bearerBarEnabled = false;

    /** @var int Bearer Bar 线条宽度（像素） */
    protected int $bearerBarWidth = 2;

    /** @var int 当前渲染使用的模块大小（像素） */
    protected int $renderModuleSize = 2;

    /** @var int 边框宽度（像素），0 表示无边框 */
    protected int $borderWidth = 0;

    /** @var string 边框颜色（十六进制） */
    protected string $borderColor = '#000000';

    public function __construct(?BarcodeConfig $config = null)
    {
        $this->config = $config ?? new BarcodeConfig();
    }

    public function getType(): string
    {
        return static::TYPE;
    }

    /**
     * 设置条码类型
     */
    public function setBarcodeType(string $type): self
    {
        $this->barcodeType = $type;
        return $this;
    }

    /**
     * 设置长竖线位置
     */
    public function setLongBarPositions(array $positions): self
    {
        $this->longBarPositions = $positions;
        return $this;
    }

    /**
     * 设置文本对齐方式
     */
    public function setTextAlign(string $align): self
    {
        $this->textAlign = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
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
     * 禁用渐变效果
     */
    public function disableGradient(): self
    {
        $this->enableGradient = false;
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

    /**
     * 设置水印（增强版）
     *
     * 子类可根据自身渲染引擎特性覆盖此方法，对字号等参数进行适配。
     *
     * @param string $text 水印文本
     * @param int $opacity 透明度（0-100，值越大越不透明）
     * @param int $fontSize 字号（默认 16）
     * @param string $color 颜色（十六进制格式）
     * @param int $angle 旋转角度（-180 到 180 度）
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
        $this->watermarkFontSize = max(1, $fontSize);
        $this->watermarkColor = $color;
        $this->watermarkAngle = max(-180, min(180, $angle));
        return $this;
    }

    /**
     * 设置水印位置
     */
    public function setWatermarkPosition(string $position): self
    {
        $validPositions = ['center', 'top', 'bottom', 'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right'];
        $this->watermarkPosition = in_array($position, $validPositions, true) ? $position : 'center';
        return $this;
    }

    /**
     * 设置水印字体路径（TTF 字体）
     */
    public function setWatermarkFontPath(?string $fontPath): self
    {
        $this->watermarkFontPath = ($fontPath !== null && file_exists($fontPath)) ? $fontPath : null;
        return $this;
    }

    /**
     * 启用 Bearer Bar（ITF-14 上下边框）
     */
    public function enableBearerBar(int $width = 2): self
    {
        $this->bearerBarEnabled = true;
        $this->bearerBarWidth = max(1, $width);
        return $this;
    }

    /**
     * 禁用 Bearer Bar
     */
    public function disableBearerBar(): self
    {
        $this->bearerBarEnabled = false;
        return $this;
    }

    /**
     * 设置边框
     *
     * @param int $width 边框宽度（像素）
     * @param string $color 边框颜色（十六进制）
     * @return self
     */
    public function setBorder(int $width, string $color = '#000000'): self
    {
        $this->borderWidth = max(0, $width);
        $this->borderColor = $color;
        return $this;
    }

    /**
     * 根据条码数据和配置解析出实际使用的模块大小（像素）
     *
     * 优先级：
     * 1. config->moduleSize（内部已计算）
     * 2. config->width / totalModules（内容总宽度 / 总模块数）
     * 3. config->width 本身（旧版兼容，当 width 是模块宽度时）
     * 4. 默认值 2
     *
     * @param array<int> $barcodeData 条空模式数组
     * @return int 模块大小（像素）
     */
    protected function resolveModuleSize(array $barcodeData): int
    {
        if ($this->config->moduleSize > 0) {
            return $this->config->moduleSize;
        }

        $totalModules = 0;
        foreach ($barcodeData as $element) {
            $totalModules += abs($element);
        }

        if ($totalModules > 0 && $this->config->width > 0) {
            return max(1, (int) floor($this->config->width / $totalModules));
        }

        // 旧版兼容：当 width 是模块宽度时（通常为小值）
        if ($this->config->width > 0) {
            return $this->config->width;
        }

        return 2;
    }

    /**
     * 计算长竖线位置
     *
     * @param array<int> $barcodeData 条空模式数组
     * @return array<int> 长竖线索引数组
     */
    protected function calculateLongBarPositions(): array
    {
        if (!empty($this->longBarPositions)) {
            return $this->longBarPositions;
        }
        return [];
    }

    /**
     * 分析条码结构，识别保护符位置和数据区域
     */
    protected function analyzeBarcodeStructure(array $bars, int $totalWidth, int $marginLeft = 0): void
    {
        $marginLeft = $marginLeft ?: $this->config->marginLeft;
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
     * 十六进制颜色转换为 RGB 数组
     *
     * @return array<int> RGB 数组 [R, G, B]
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

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

    /** @var float 长竖线相对于普通条的高度比例（基类兜底 1.15；实际以 config.longBarRatio 为准，其默认 1.1） */
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
    protected int $renderModuleSize = 4;

    /** @var int 边框宽度（像素），0 表示无边框 */
    protected int $borderWidth = 0;

    /** @var string 边框颜色（十六进制） */
    protected string $borderColor = '#000000';

    public function __construct(?BarcodeConfig $config = null)
    {
        $this->config = $config ?? new BarcodeConfig();
        // 长竖线比例以配置为准（config 默认 1.1），无配置时保持基类默认值
        if ($this->config->longBarRatio > 0) {
            $this->longBarHeightRatio = $this->config->longBarRatio;
        }
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
     * 4. 默认值 4（4px/模块，兼顾图片体积与手机相机直扫屏幕的稳定性）
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

        // 默认 4px/模块：95 模块的 EAN-13 内容区输出 380px（加左右静区整图 468px）。
        // 历史沿革：2px → 3px（2016-07，消除缩放/压缩后的 EAN 镜像码对误读）→ 4px（本版，
        // 手机相机对屏幕直接扫码时 3px/模块仍处于对焦/采样临界区，会出现校验恰好通过的
        // 伪解码且多帧结果不同；4px/模块下各码制留出更宽裕的解码余量）。
        return 4;
    }

    /**
     * 计算长竖线位置
     *
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
     * 是否应把保护符渲染成长竖线
     *
     * PNG/SVG 渲染器用 config->rotateLongBars 控制，HTML 渲染器此前忽略了该开关，
     * 此处统一判定，保证三种渲染器行为一致。
     */
    protected function longBarsEnabled(): bool
    {
        return $this->config->rotateLongBars && !empty($this->longBarPositions);
    }

    /**
     * 把条码数据切分为按保护符区域分组的显示段（供文本渲染使用）
     *
     * 返回形如 [ ['text' => '6', 'region' => 'leftQuietZone', 'type' => 'single'], ... ]
     * 的列表；不适用分段的条码类型返回空数组，由调用方回退为整段文本。
     *
     * 【注意】data 可能是补齐校验位后的完整条码（如 EAN-13 的 13 位），
     * 这里只负责「按位拆分 + 分组」，不负责显示格式的变换。
     *
     * @return list<array{text: string, region: string, type: string}>
     */
    protected function splitDisplaySegments(string $data): array
    {
        $length = strlen($data);

        switch ($this->barcodeType) {
            case 'EAN-13':
            case 'ISBN':
            case 'ISSN':
                // ISBN 的码面是 13 位 EAN-13 结构（含 978 前缀与 EAN-13 校验位），
                // 显示分段与 EAN-13 一致（1+6+6），否则三种渲染器会丢字/布局错位。
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
        }

        return [];
    }

    /**
     * 由条空模式数组构建各条/空元素的几何信息（供结构分析共用）
     *
     * @param array<int> $barcodeData
     * @param int $startX 首条 x 坐标的偏移量（PNG 直接以画布坐标为基准时为 marginLeft）
     * @return list<array{x:int,width:int,isLongBar:bool,elementIndex:int}>
     */
    protected function buildBarGeometries(array $barcodeData, int $startX = 0): array
    {
        $longBarPositions = $this->calculateLongBarPositions();
        $moduleSize = $this->renderModuleSize ?: 1;
        $x = $startX;
        $bars = [];

        foreach ($barcodeData as $elementIndex => $element) {
            $isBar = $element > 0;
            $width = abs($element) * $moduleSize;

            if ($isBar) {
                $bars[] = [
                    'x' => $x,
                    'width' => $width,
                    'isLongBar' => in_array($elementIndex, $longBarPositions, true),
                    'elementIndex' => $elementIndex,
                ];
            }

            $x += $width;
        }

        return $bars;
    }

    /**
     * 静区最小宽度（像素）
     *
     * 以配置的 quietZoneWidth（模块倍数）为准，但保证不低于
     * EAN/UPC 规范要求的 11 个模块宽度。
     */
    protected function quietZoneWidthPx(): int
    {
        $modules = max(11, max(1, (int) $this->config->quietZoneWidth));

        return $modules * max(1, $this->renderModuleSize);
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
     * 渐变是否可安全使用（扫码守卫）
     *
     * 渐变把条带从 startColor 渐变到 endColor：若任一端与背景对比不足（WCAG < 3:1），
     * 条形浅色段会被解码器当作空白（起始符/终止符丢失），整码拒读。
     * 两端任一非合法 hex 或对比不足时自动回退纯色条，保证可扫性优先。
     */
    protected function gradientUsable(): bool
    {
        if (!$this->enableGradient) {
            return false;
        }
        $bg = $this->config->bgColor;
        if ($bg === 'transparent') {
            $bg = '#FFFFFF';
        }
        $hexPattern = '/^#?[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/';
        if (!preg_match($hexPattern, $bg)
            || !preg_match($hexPattern, $this->gradientStartColor)
            || !preg_match($hexPattern, $this->gradientEndColor)) {
            return false;
        }
        return $this->validateContrast($bg, $this->gradientStartColor)
            && $this->validateContrast($bg, $this->gradientEndColor);
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
     * 转义用于 SVG/XML 属性值的字符串，防止属性注入
     *
     * @param string|null $value 原始值
     * @return string 已转义的值
     */
    protected function escapeXmlAttr(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * 转义用于 HTML/CSS 上下文的字符串
     *
     * 仅允许颜色 / 长度等安全字符（字母数字、# . , % ( ) 空格 - +），
     * 其余字符一律移除，避免 style/class 属性被注入恶意内容。
     *
     * @param string|null $value 原始值
     * @return string 已净化的值
     */
    protected function sanitizeCssValue(?string $value): string
    {
        return preg_replace('/[^A-Za-z0-9#\.\,\%\(\)\s\-\+]/', '', (string) $value) ?? '';
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

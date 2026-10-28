<?php

declare(strict_types=1);

namespace zxf\Utils\QrCode;

use InvalidArgumentException;
use zxf\res\AssetLocator;
use zxf\Utils\QrCode\Color\Color;

/**
 * 二维码标签配置类
 * 用于配置二维码下方的文本标签
 */
class LabelOptions
{
    /** @var string|null 标签文本内容 */
    private ?string $text = null;

    /** @var string|null 字体文件路径 */
    private ?string $fontPath = null;

    /** @var int 字体大小 */
    private int $fontSize = 22;

    /** @var Color 文本颜色 */
    private Color $color;

    /** @var Color|null 背景色 */
    private ?Color $backgroundColor = null;

    /** @var Color|null 边框颜色 */
    private ?Color $borderColor = null;

    /** @var int 边框宽度 */
    private int $borderWidth = 0;

    /** @var int 文本内边距-上（像素） */
    private int $paddingTop = 5;

    /** @var int 文本内边距-下（像素） */
    private int $paddingBottom = 5;

    /** @var int 文本内边距-左（像素） */
    private int $paddingLeft = 10;

    /** @var int 文本内边距-右（像素） */
    private int $paddingRight = 10;

    /** @var int 文本外边距-上（像素） */
    private int $marginTop = 10;

    /** @var int 文本外边距-下（像素） */
    private int $marginBottom = 10;

    /** @var int 文本外边距-左（像素） */
    private int $marginLeft = 0;

    /** @var int 文本外边距-右（像素） */
    private int $marginRight = 0;

    /** @var int 行高（像素） */
    private int $lineHeight = 20;

    /** @var string 文本对齐方式 */
    private string $alignment = 'center';

    /** @var float 圆角半径（0-1，相对于高度） */
    private float $borderRadius = 0;

    /** @var bool 是否启用文本阴影 */
    private bool $textShadow = false;

    /** @var Color|null 文本阴影颜色 */
    private ?Color $shadowColor = null;

    /** @var int 阴影偏移X */
    private int $shadowOffsetX = 1;

    /** @var int 阴影偏移Y */
    private int $shadowOffsetY = 1;

    /** @var bool 是否启用文本描边 */
    private bool $textStroke = false;

    /** @var Color|null 描边颜色 */
    private ?Color $strokeColor = null;

    /** @var int 描边宽度 */
    private int $strokeWidth = 1;

    /** @var string|null 默认字体路径 */
    private static ?string $defaultFontPath = null;

    /**
     * 构造函数
     *
     * @param string|null $text 标签文本
     * @param string|null $fontPath 字体路径 或者内置字体的名称，例如：/your/path/aaa.ttf、 'lishu'
     */
    public function __construct(?string $text = null, ?string $fontPath = null)
    {
        $this->text = $text;
        $this->color = Color::black();
        if ($fontPath === null && self::$defaultFontPath !== null) {
            $fontPath = self::$defaultFontPath;
        }
        $this->fontPath($fontPath);
    }

    /**
     * 设置默认字体路径
     *
     * @param string $fontPath 字体文件路径或者内置字体的名称，例如：/your/path/aaa.ttf、 'lishu'
     * @throws InvalidArgumentException 如果字体文件不存在
     */
    public static function setDefaultFontPath(string $fontPath): void
    {
        // 首先尝试直接作为完整路径使用
        if (is_file($fontPath)) {
            self::$defaultFontPath = $fontPath;
            return;
        }

        // 尝试作为字体名称查找
        $fontByName = AssetLocator::resolve('fonts', $fontPath.'.ttf');
        if (is_file($fontByName)) {
            self::$defaultFontPath = $fontByName;
            return;
        }

        throw new InvalidArgumentException('默认字体文件不存在: ' . $fontPath);
    }

    /**
     * 创建标签配置
     *
     * 构造并返回一个新的标签配置对象，未指定字体时由构造函数回退到
     * 默认字体（内置 xingkai），文本为空表示不启用标签。
     *
     * @param string $text 标签文本
     * @param string|null $fontPath 字体路径或内置字体名（如 lishu）；null 表示使用默认字体
     * @return self 新建的标签配置对象
     * @throws InvalidArgumentException 当字体无法解析时抛出
     */
    public static function create(string $text, ?string $fontPath = null): self
    {
        return new self($text, $fontPath);
    }

    /**
     * 设置标签文本
     *
     * 直接覆盖原文本，支持用 \n 换行；传入空串时 isEnabled() 会返回
     * false，相当于关闭标签显示。
     *
     * @param string $text 文本内容
     * @return self 当前对象，便于链式调用
     */
    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    /**
     * 设置字体路径
     *
     * 先按完整文件路径判断，再按内置字体名在 resource/font 下拼 .ttf 查找；
     * 传入空值时回退到内置默认字体 xingkai，两者都找不到则抛异常。
     *
     * @param string|null $fontPath 字体文件路径或者内置字体的名称（如：lishu）
     * @return self 当前对象，便于链式调用
     * @throws InvalidArgumentException 如果字体文件不存在
     */
    public function fontPath(?string $fontPath): self
    {
        if (empty($fontPath)) {
            $fontPath = 'lishu';
        }

        $fontByName = AssetLocator::resolve('fonts', $fontPath.'.ttf');
        if (is_file($fontByName)) {
            $this->fontPath = $fontByName;
            return $this;
        }

        throw new InvalidArgumentException('字体文件不存在: ' . $fontPath);
    }

    /**
     * 设置字体大小
     *
     * 数值会被钳制为最小 1，避免传入 0 或负数导致后续文本测量异常；
     * 若已设置的行高小于新字号，行高不会自动跟随（lineHeight() 才会处理）。
     *
     * @param int $fontSize 字体大小（磅值）
     * @return self 当前对象，便于链式调用
     */
    public function fontSize(int $fontSize): self
    {
        $this->fontSize = max(1, $fontSize);
        return $this;
    }

    /**
     * 设置文本颜色
     *
     * 传入字符串时按十六进制解析（支持 #RGB/#RGBA/#RRGGBB/#RRGGBBAA），
     * 传入 Color 对象则直接使用；非法十六进制由 Color::fromHex() 抛异常。
     *
     * @param Color|string $color 颜色对象或十六进制颜色值
     * @return self 当前对象，便于链式调用
     */
    public function color($color): self
    {
        if (is_string($color)) {
            $this->color = Color::fromHex($color);
        } else {
            $this->color = $color;
        }
        return $this;
    }

    /**
     * 设置背景色
     *
     * 字符串会按十六进制解析为 Color；未设置时标签区域背景透明，
     * 渲染时不会额外填充底色。
     *
     * @param Color|string $color 颜色对象或十六进制颜色值
     * @return self 当前对象，便于链式调用
     */
    public function backgroundColor($color): self
    {
        if (is_string($color)) {
            $this->backgroundColor = Color::fromHex($color);
        } else {
            $this->backgroundColor = $color;
        }
        return $this;
    }

    /**
     * 设置边框颜色
     *
     * 字符串会按十六进制解析为 Color；仅在 borderWidth() 大于 0 时才会
     * 真正绘制边框。未设置时标签区域不描边。
     *
     * @param Color|string $color 颜色对象或十六进制颜色值
     * @return self 当前对象，便于链式调用
     */
    public function borderColor($color): self
    {
        if (is_string($color)) {
            $this->borderColor = Color::fromHex($color);
        } else {
            $this->borderColor = $color;
        }
        return $this;
    }

    /**
     * 设置边框宽度
     *
     * 负数会被钳制为 0（表示不绘制边框）；宽度按像素计，加在标签区域四周。
     *
     * @param int $width 边框宽度（像素）
     * @return self 当前对象，便于链式调用
     */
    public function borderWidth(int $width): self
    {
        $this->borderWidth = max(0, $width);
        return $this;
    }

    /**
     * 设置边框圆角半径
     *
     * @param float $radius 圆角半径（0-1，相对于高度）
     * @return self
     */
    public function borderRadius(float $radius): self
    {
        $this->borderRadius = max(0, min(1, $radius));
        return $this;
    }

    /**
     * 设置文本阴影效果
     *
     * @param Color|string $color 阴影颜色
     * @param int $offsetX X轴偏移量（默认1）
     * @param int $offsetY Y轴偏移量（默认1）
     * @return self
     */
    public function textShadow($color, int $offsetX = 1, int $offsetY = 1): self
    {
        if (is_string($color)) {
            $this->shadowColor = Color::fromHex($color);
        } else {
            $this->shadowColor = $color;
        }
        $this->textShadow = true;
        $this->shadowOffsetX = $offsetX;
        $this->shadowOffsetY = $offsetY;
        return $this;
    }

    /**
     * 设置文本描边效果
     *
     * @param Color|string $color 描边颜色
     * @param int $width 描边宽度（默认1）
     * @return self
     */
    public function textStroke($color, int $width = 1): self
    {
        if (is_string($color)) {
            $this->strokeColor = Color::fromHex($color);
        } else {
            $this->strokeColor = $color;
        }
        $this->textStroke = true;
        $this->strokeWidth = max(1, $width);
        return $this;
    }

    /**
     * 设置内边距（所有方向）
     *
     * @param int $padding 内边距值
     * @return self
     */
    public function padding(int $padding): self
    {
        $this->paddingTop = $this->paddingBottom = $this->paddingLeft = $this->paddingRight = max(0, $padding);
        return $this;
    }

    /**
     * 设置上内边距
     *
     * 内边距位于标签背景内部、文本之外；负数会被钳制为 0。
     *
     * @param int $padding 上内边距（像素）
     * @return self 当前对象，便于链式调用
     */
    public function paddingTop(int $padding): self
    {
        $this->paddingTop = max(0, $padding);
        return $this;
    }

    /**
     * 设置下内边距
     *
     * 内边距位于标签背景内部、文本之外；负数会被钳制为 0。
     *
     * @param int $padding 下内边距（像素）
     * @return self 当前对象，便于链式调用
     */
    public function paddingBottom(int $padding): self
    {
        $this->paddingBottom = max(0, $padding);
        return $this;
    }

    /**
     * 设置左内边距
     *
     * 内边距位于标签背景内部、文本之外；负数会被钳制为 0。
     *
     * @param int $padding 左内边距（像素）
     * @return self 当前对象，便于链式调用
     */
    public function paddingLeft(int $padding): self
    {
        $this->paddingLeft = max(0, $padding);
        return $this;
    }

    /**
     * 设置右内边距
     *
     * 内边距位于标签背景内部、文本之外；负数会被钳制为 0。
     *
     * @param int $padding 右内边距（像素）
     * @return self 当前对象，便于链式调用
     */
    public function paddingRight(int $padding): self
    {
        $this->paddingRight = max(0, $padding);
        return $this;
    }

    /**
     * 设置上边距
     *
     * 外边距位于标签区域之外，用于与二维码或其他元素留白；负数会被钳制为 0。
     *
     * @param int $marginTop 上边距（像素）
     * @return self 当前对象，便于链式调用
     */
    public function marginTop(int $marginTop): self
    {
        $this->marginTop = max(0, $marginTop);
        return $this;
    }

    /**
     * 设置下边距
     *
     * 外边距位于标签区域之外，用于与二维码或其他元素留白；负数会被钳制为 0。
     *
     * @param int $marginBottom 下边距（像素）
     * @return self 当前对象，便于链式调用
     */
    public function marginBottom(int $marginBottom): self
    {
        $this->marginBottom = max(0, $marginBottom);
        return $this;
    }

    /**
     * 设置左边距
     *
     * 外边距位于标签区域之外，用于与二维码或其他元素留白；负数会被钳制为 0。
     *
     * @param int $marginLeft 左边距（像素）
     * @return self 当前对象，便于链式调用
     */
    public function marginLeft(int $marginLeft): self
    {
        $this->marginLeft = max(0, $marginLeft);
        return $this;
    }

    /**
     * 设置右边距
     *
     * 外边距位于标签区域之外，用于与二维码或其他元素留白；负数会被钳制为 0。
     *
     * @param int $marginRight 右边距（像素）
     * @return self 当前对象，便于链式调用
     */
    public function marginRight(int $marginRight): self
    {
        $this->marginRight = max(0, $marginRight);
        return $this;
    }

    /**
     * 设置所有边距
     *
     * 把同一个值应用到上、下、左、右四个方向，内部逐个调用对应的
     * marginXxx()，因此同样会把负数钳制为 0。
     *
     * @param int $margin 四边统一的边距值（像素）
     * @return self 当前对象，便于链式调用
     */
    public function margin(int $margin): self
    {
        return $this->marginTop($margin)->marginBottom($margin)->marginLeft($margin)->marginRight($margin);
    }

    /**
     * 设置行高
     *
     * 行高不允许小于当前字号（取 fontSize 与传入值中的较大者），以保证
     * 多行文本不会上下重叠。
     *
     * @param int $lineHeight 行高（像素）
     * @return self 当前对象，便于链式调用
     */
    public function lineHeight(int $lineHeight): self
    {
        $this->lineHeight = max($this->fontSize, $lineHeight);
        return $this;
    }

    /**
     * 设置文本对齐方式
     *
     * @param string $alignment 对齐方式：left/center/right
     * @return self
     */
    public function alignment(string $alignment): self
    {
        $validAlignments = ['left', 'center', 'right'];
        if (!in_array($alignment, $validAlignments)) {
            throw new InvalidArgumentException('无效的对齐方式: ' . $alignment);
        }
        $this->alignment = $alignment;
        return $this;
    }

    /**
     * 获取标签文本
     *
     * 返回原始文本（可能含 \n 换行），未设置时为 null。
     *
     * @return string|null 标签文本；未设置返回 null
     */
    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * 获取字体路径
     *
     * 返回的是已被解析过的绝对路径（构造函数会回退到默认字体），
     * 因此正常情况下不会为 null。
     *
     * @return string|null 字体文件的绝对路径
     */
    public function getFontPath(): ?string
    {
        return $this->fontPath;
    }

    /**
     * 获取字体大小
     *
     * 返回当前生效的磅值，fontSize() 已将其钳制为不小于 1；行高不会随
     * 字号自动变化，需要更大的行距时应另行调用 lineHeight()。
     *
     * @return int 字体大小（磅值）
     */
    public function getFontSize(): int
    {
        return $this->fontSize;
    }

    /**
     * 获取文本颜色
     *
     * 构造函数中会把该值初始化为黑色，因此任何时刻都有值；color() 只做
     * 整体替换，不会返回 null。
     *
     * @return Color 当前文本颜色对象
     */
    public function getColor(): Color
    {
        return $this->color;
    }

    /**
     * 获取上边距
     *
     * 返回标签区域上方与二维码之间的留白像素数，由 marginTop() 设置，
     * 默认 10 像素，负数在设置时已被钳制为 0。
     *
     * @return int 上边距（像素）
     */
    public function getMarginTop(): int
    {
        return $this->marginTop;
    }

    /**
     * 获取下边距
     *
     * 返回标签区域下方的留白像素数，由 marginBottom() 设置，默认 10 像素，
     * 负数在设置时已被钳制为 0。
     *
     * @return int 下边距（像素）
     */
    public function getMarginBottom(): int
    {
        return $this->marginBottom;
    }

    /**
     * 获取左边距
     *
     * 返回标签区域左侧的留白像素数，由 marginLeft() 设置，默认为 0
     * （标签默认铺满二维码宽度）。
     *
     * @return int 左边距（像素）
     */
    public function getMarginLeft(): int
    {
        return $this->marginLeft;
    }

    /**
     * 获取右边距
     *
     * 返回标签区域右侧的留白像素数，由 marginRight() 设置，默认为 0
     * （标签默认铺满二维码宽度）。
     *
     * @return int 右边距（像素）
     */
    public function getMarginRight(): int
    {
        return $this->marginRight;
    }

    /**
     * 获取行高
     *
     * 返回多行文本每一行所占的垂直像素数，已保证不小于字号。
     *
     * @return int 行高（像素）
     */
    public function getLineHeight(): int
    {
        return $this->lineHeight;
    }

    /**
     * 获取对齐方式
     *
     * 返回文本在标签区域内的水平对齐方式，默认 center，仅可能是
     * left、center、right 三者之一。
     *
     * @return string 对齐方式标识
     */
    public function getAlignment(): string
    {
        return $this->alignment;
    }

    /**
     * 获取背景色
     *
     * 未调用 backgroundColor() 时返回 null，表示标签区域不填充底色。
     *
     * @return Color|null 背景色；未设置返回 null
     */
    public function getBackgroundColor(): ?Color
    {
        return $this->backgroundColor;
    }

    /**
     * 获取边框颜色
     *
     * 未调用 borderColor() 时返回 null；实际是否描边还需边框宽度大于 0。
     *
     * @return Color|null 边框颜色；未设置返回 null
     */
    public function getBorderColor(): ?Color
    {
        return $this->borderColor;
    }

    /**
     * 获取边框宽度
     *
     * 返回标签区域描边的像素宽度，0 表示不绘制边框；颜色是否设置不影响
     * 该值，但宽度为 0 时即使设置了边框颜色也不会描边。
     *
     * @return int 边框宽度（像素）
     */
    public function getBorderWidth(): int
    {
        return $this->borderWidth;
    }

    /**
     * 获取边框圆角半径
     *
     * @return float
     */
    public function getBorderRadius(): float
    {
        return $this->borderRadius;
    }

    /**
     * 获取上内边距
     *
     * 返回标签背景内部、文本上方的空白像素数，默认 5 像素，参与标签整体
     * 高度的计算。
     *
     * @return int 上内边距（像素）
     */
    public function getPaddingTop(): int
    {
        return $this->paddingTop;
    }

    /**
     * 获取下内边距
     *
     * 返回标签背景内部、文本下方的空白像素数，默认 5 像素，参与标签整体
     * 高度的计算。
     *
     * @return int 下内边距（像素）
     */
    public function getPaddingBottom(): int
    {
        return $this->paddingBottom;
    }

    /**
     * 获取左内边距
     *
     * 返回标签背景内部、文本左侧的空白像素数，默认 10 像素，用于避免文字
     * 紧贴边框。
     *
     * @return int 左内边距（像素）
     */
    public function getPaddingLeft(): int
    {
        return $this->paddingLeft;
    }

    /**
     * 获取右内边距
     *
     * 返回标签背景内部、文本右侧的空白像素数，默认 10 像素，用于避免文字
     * 紧贴边框。
     *
     * @return int 右内边距（像素）
     */
    public function getPaddingRight(): int
    {
        return $this->paddingRight;
    }

    /**
     * 获取是否启用文本阴影
     *
     * @return bool
     */
    public function hasTextShadow(): bool
    {
        return $this->textShadow && $this->shadowColor !== null;
    }

    /**
     * 获取阴影颜色
     *
     * 未调用 textShadow() 时返回 null；即使已设置颜色，仍需
     * hasTextShadow() 为 true 才会真正绘制阴影。
     *
     * @return Color|null 阴影颜色；未设置返回 null
     */
    public function getShadowColor(): ?Color
    {
        return $this->shadowColor;
    }

    /**
     * 获取阴影X轴偏移
     *
     * @return int
     */
    public function getShadowOffsetX(): int
    {
        return $this->shadowOffsetX;
    }

    /**
     * 获取阴影Y轴偏移
     *
     * @return int
     */
    public function getShadowOffsetY(): int
    {
        return $this->shadowOffsetY;
    }

    /**
     * 获取是否启用文本描边
     *
     * @return bool
     */
    public function hasTextStroke(): bool
    {
        return $this->textStroke && $this->strokeColor !== null;
    }

    /**
     * 获取描边颜色
     *
     * 未调用 textStroke() 时返回 null；即使已设置颜色，仍需
     * hasTextStroke() 为 true 才会真正绘制描边。
     *
     * @return Color|null 描边颜色；未设置返回 null
     */
    public function getStrokeColor(): ?Color
    {
        return $this->strokeColor;
    }

    /**
     * 获取描边宽度
     *
     * 返回文本描边的像素宽度，通过 textStroke() 设置时保证不小于 1。
     *
     * @return int 描边宽度（像素）
     */
    public function getStrokeWidth(): int
    {
        return $this->strokeWidth;
    }

    /**
     * 检查是否启用了标签
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->text !== null && trim($this->text) !== '';
    }

    /**
     * 计算文本所需高度
     * 包含内边距和外边距的计算
     * 支持动态字体大小调整，确保文本不会超出可用空间
     *
     * @param int $qrWidth 二维码宽度
     * @return array [总高度, 行数]
     */
    public function calculateTextHeight(int $qrWidth): array
    {
        if (!$this->isEnabled() || !$this->fontPath) {
            return [0, 0];
        }

        // 将文本按换行符分割
        $lines = explode("\n", $this->text);

        // 如果没有换行符，检查是否需要自动换行
        if (count($lines) === 1) {
            $lines = $this->wrapText($this->text, $qrWidth - $this->paddingLeft - $this->paddingRight - $this->marginLeft - $this->marginRight);
        }

        $lineCount = count($lines);
        // 计算总高度：外边距 + 内边距 + 文本行高
        $textHeight = $this->marginTop + $this->paddingTop + ($lineCount * $this->lineHeight) + $this->paddingBottom + $this->marginBottom;

        return [$textHeight, $lineCount];
    }

    /**
     * 智能调整字体大小以适应可用空间
     * 如果文本超出可用宽度，自动减小字体大小
     *
     * @param string $text 文本内容
     * @param int $availableWidth 可用宽度（像素）
     * @param int $minFontSize 最小字体大小（默认10）
     * @return int 调整后字体大小
     */
    public function adjustFontSizeToFit(string $text, int $availableWidth, int $minFontSize = 10): int
    {
        $currentFontSize = $this->fontSize;
        $maxWidth = $availableWidth - $this->paddingLeft - $this->paddingRight;

        // 如果字体文件不存在，返回原大小
        if (!$this->fontPath || !file_exists($this->fontPath)) {
            return $currentFontSize;
        }

        // 从当前字体大小开始，逐渐减小直到文本适应
        while ($currentFontSize >= $minFontSize) {
            $textWidth = $this->calculateExactTextWidth($text, $currentFontSize);

            if ($textWidth <= $maxWidth) {
                return $currentFontSize;
            }

            $currentFontSize -= 1;
        }

        return $minFontSize;
    }

    /**
     * 使用GD库精确计算文本宽度
     * 优先使用GD库的精确计算，回退到估算方法
     *
     * @param string $text 文本
     * @param int|null $fontSize 字体大小（null使用当前设置）
     * @return int 文本宽度（像素）
     */
    public function calculateExactTextWidth(string $text, ?int $fontSize = null): int
    {
        if ($text === '') {
            return 0;
        }

        $size = $fontSize ?? $this->fontSize;

        // 优先使用GD库的精确计算
        if ($this->fontPath && file_exists($this->fontPath)) {
            $box = imagettfbbox($size, 0, $this->fontPath, $text);
            if ($box !== false) {
                return abs($box[2] - $box[0]);
            }
        }

        // 回退到字符估算方法
        return $this->calculateTextWidth($text);
    }

    /**
     * 检查文本是否需要换行
     * 根据可用宽度和字体大小判断
     *
     * @param string $text 文本
     * @param int $availableWidth 可用宽度
     * @return bool 是否需要换行
     */
    public function needsWrapping(string $text, int $availableWidth): bool
    {
        $textWidth = $this->calculateExactTextWidth($text);
        $maxWidth = $availableWidth - $this->paddingLeft - $this->paddingRight;
        return $textWidth > $maxWidth;
    }

    /**
     * 获取文本的摘要信息（用于调试和日志）
     *
     * @param int $qrWidth 二维码宽度
     * @return array 包含行数、总高度、字体大小等信息
     */
    public function getTextSummary(int $qrWidth): array
    {
        [$totalHeight, $lineCount] = $this->calculateTextHeight($qrWidth);

        return [
            'lineCount' => $lineCount,
            'totalHeight' => $totalHeight,
            'fontSize' => $this->fontSize,
            'lineHeight' => $this->lineHeight,
            'textLength' => mb_strlen($this->text ?? ''),
            'needsWrapping' => $this->needsWrapping($this->text ?? '', $qrWidth)
        ];
    }

    /**
     * 设置字体大小并自动调整行高
     * 确保行高至少等于字体大小
     *
     * @param int $fontSize 字体大小
     * @return self
     */
    public function fontSizeAutoLineHeight(int $fontSize): self
    {
        $this->fontSize = max(1, $fontSize);
        $this->lineHeight = max($this->fontSize, (int)($this->fontSize * 1.2));
        return $this;
    }

    /**
     * 文本自动换行处理
     * 优化中文、英文混合文本的换行算法
     *
     * @param string $text 文本
     * @param int $maxWidth 最大宽度（像素）
     * @return array 分割后的行数组
     */
    private function wrapText(string $text, int $maxWidth): array
    {
        if ($maxWidth <= 0 || $text === '') {
            return [$text];
        }

        $lines = [];
        $textLength = mb_strlen($text, 'UTF-8');
        $currentLine = '';
        $currentWidth = 0;

        // 按字符遍历文本
        for ($i = 0; $i < $textLength; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');

            // 处理换行符
            if ($char === "\n") {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                    $currentLine = '';
                    $currentWidth = 0;
                }
                continue;
            }

            // 计算字符宽度（中文字符通常比英文字符宽）
            $charWidth = $this->calculateCharWidth($char);

            // 检查是否需要换行
            if ($currentWidth + $charWidth > $maxWidth && $currentLine !== '') {
                // 当前行已满，添加到行数组
                $lines[] = $currentLine;
                $currentLine = $char;
                $currentWidth = $charWidth;
            } else {
                // 继续当前行
                $currentLine .= $char;
                $currentWidth += $charWidth;
            }
        }

        // 添加最后一行
        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        // 如果没有任何行，返回原文本
        if (empty($lines)) {
            $lines[] = $text;
        }

        return $lines;
    }

    /**
     * 计算单个字符的宽度
     * 中文字符宽度约为字体大小的1.1倍，英文字符约为0.6倍
     *
     * @param string $char 字符
     * @return float 字符宽度（像素）
     */
    private function calculateCharWidth(string $char): float
    {
        if ($char === ' ' || $char === "\t") {
            // 空格宽度约为字体大小的一半
            return $this->fontSize * 0.5;
        }

        // 判断是否为ASCII字符（英文字符、数字、符号）
        if (strlen($char) === 1 && ord($char) < 128) {
            // ASCII字符宽度约为字体大小的0.6倍
            return $this->fontSize * 0.6;
        }

        // 中文字符宽度约为字体大小的1.1倍
        return $this->fontSize * 1.1;
    }

    /**
     * 计算文本宽度
     * 优先使用GD库精确计算，回退到估算方法
     *
     * @param string $text 文本
     * @return int 文本宽度（像素）
     * @deprecated 该方法保留用于向后兼容，建议使用更精确的字符宽度计算方法
     */
    private function calculateTextWidth(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        // 优先使用GD库的精确计算
        if ($this->fontPath && file_exists($this->fontPath)) {
            $box = imagettfbbox($this->fontSize, 0, $this->fontPath, $text);
            if ($box !== false) {
                return abs($box[2] - $box[0]);
            }
        }

        // 回退到字符估算方法
        $width = 0;
        $length = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $width += $this->calculateCharWidth($char);
        }

        return (int)$width;
    }

    /**
     * 获取分割后的文本行
     *
     * @param int $qrWidth 二维码宽度
     * @return array
     */
    public function getLines(int $qrWidth): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $lines = explode("\n", $this->text);
        if (count($lines) === 1) {
            $lines = $this->wrapText($this->text, $qrWidth);
        }

        return $lines;
    }
}

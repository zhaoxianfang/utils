<?php

namespace zxf\Utils\Image;

use Exception;
use GdImage;

/**
 * 文字生成图片类库
 *
 * 基于 PHP 8.2+ 和 GD 库实现的高性能文字图片生成工具，
 * 支持丰富的排版特效、水印、背景处理、滤镜及多种输出格式。
 *
 * @package   zxf\Utils\Image
 * @author    zxf
 * @version   1.0.0
 * @license   MIT
 * @requires  PHP >= 8.2, GD Extension
 *
 * ## 快速入门示例
 *
 * ```php
 * use zxf\Utils\Image\TextToImg;
 *
 * // 基础用法：生成文字图片
 * TextToImg::instance(1200, 600)
 *     ->setText('欢迎使用 TextToImg')
 *     ->setColor('333333')
 *     ->setBgColor('F5F5F5')
 *     ->render('output.png');
 *
 * // 高级排版：自动换行 + 阴影 + 圆角边框
 * TextToImg::instance()
 *     ->setText('这是一段支持自动换行和多种排版样式的文字内容...')
 *     ->setFontSize(36)
 *     ->setColor('FF5733')
 *     ->setBgColor('FFFFFF')
 *     ->setAutoWrap(true)
 *     ->setPadding(40)
 *     ->setAlign(TextToImg::ALIGN_LEFT, TextToImg::VALIGN_TOP)
 *     ->setTextShadow(['color' => '000000', 'alpha' => 60, 'offsetX' => 3, 'offsetY' => 3])
 *     ->setTextStroke(['color' => 'FFFFFF', 'width' => 2])
 *     ->setBorder(4, '333333', 16)
 *     ->render('advanced.png');
 *
 * // 渐变背景 + 水印
 * TextToImg::instance(800, 400)
 *     ->setText('渐变背景示例')
 *     ->setFontSize(60)
 *     ->setGradientBackground([
 *         ['color' => '667eea', 'position' => 0],
 *         ['color' => '764ba2', 'position' => 1],
 *     ])
 *     ->setGradientType(TextToImg::GRADIENT_LINEAR)
 *     ->setGradientAngle(135)
 *     ->addTextWatermark('内部资料', ['size' => 24, 'color' => 'FFFFFF', 'alpha' => 40])
 *     ->render('gradient.png');
 *
 * // 自适应画布尺寸
 * TextToImg::instance()
 *     ->setAutoSize(true)
 *     ->setPadding(30)
 *     ->setText('根据内容自动调整画布大小')
 *     ->setFontSize(48)
 *     ->render('autosize.png');
 *
 * // Base64 输出
 * $base64 = TextToImg::instance(400, 200)
 *     ->setText('Base64')
 *     ->toBase64();
 * echo '<img src="' . $base64 . '">';
 * ```
 *
 * ## 完整方法列表与功能说明
 *
 * ### 单例与生命周期
 * | 方法 | 说明 |
 * |------|------|
 * | `instance($w, $h)` | 获取单例实例（带状态重置复用） |
 * | `batch($handlers)` | 批量生成图片（复用单例，降低内存占用） |
 * | `__construct($w, $h)` | 构造函数，自动检测 GD 扩展 |
 * | `resetState()` | 重置所有绘制参数（单例复用必备） |
 * | `destroy()` | 立即释放 GD 资源 |
 * | `__destruct()` | 析构时自动释放资源 |
 *
 * ### 基础设置
 * | 方法 | 说明 |
 * |------|------|
 * | `setSize($w, $h)` | 设置画布尺寸（像素） |
 * | `setText($text)` | 设置文字内容（支持 \n 和 `<br>` 换行） |
 * | `setFontSize($size)` | 设置固定字号（pt），null 则自动计算 |
 * | `setFontSizeRange($min, $max)` | 限制自动计算字号的上下限 |
 * | `setAngle($angle)` | 设置文字旋转角度（0-360） |
 * | `setPadding($padding)` | 设置文字区域内边距 |
 * | `setLineHeight($height)` | 设置行高（0 则自动 1.5 倍字号） |
 * | `setScale($scale)` | 设置画布缩放比例（高 DPI 支持） |
 * | `setAntialias($enabled)` | 开启/关闭抗锯齿 |
 *
 * ### 字体管理
 * | 方法 | 说明 |
 * |------|------|
 * | `setFontFile($file)` | 加载外部 TTF/OTF 字体文件 |
 * | `setFontStyle($style)` | 使用内置字体（resource/font 目录） |
 * | `setFontDir($dir)` | 修改字体扫描目录 |
 * | `getAvailableFonts()` | 获取所有可用内置字体名称列表 |
 *
 * ### 颜色设置（支持 HEX/RGB/RGBA）
 * | 方法 | 说明 |
 * |------|------|
 * | `setColor($color, $alpha)` | 设置文字颜色 |
 * | `setTextAlpha($alpha)` | 设置文字透明度（0-127） |
 * | `setBgColor($color, $alpha)` | 设置背景颜色 |
 * | `setBgAlpha($alpha)` | 设置背景透明度（0-127） |
 *
 * ### 对齐与排版
 * | 方法 | 说明 |
 * |------|------|
 * | `setAlign($h, $v)` | 同时设置水平和垂直对齐 |
 * | `setHAlign($align)` | 水平对齐：left/center/right |
 * | `setVAlign($align)` | 垂直对齐：top/middle/bottom |
 * | `setAutoWrap($enabled)` | 按画布宽度自动换行 |
 * | `setAutoSize($enabled)` | 画布尺寸自适应文字内容 |
 *
 * ### 背景设置
 * | 方法 | 说明 |
 * |------|------|
 * | `setBackgroundImage($path)` | 设置背景图片（覆盖其他背景） |
 * | `setSolidBackground($color, $alpha)` | 设置纯色背景 |
 * | `setGradientBackground($colors)` | 设置渐变色数组（支持多色标） |
 * | `setGradientType($type)` | 渐变类型：linear/radial |
 * | `setGradientAngle($angle)` | 线性渐变角度（度） |
 *
 * ### 边框与圆角
 * | 方法 | 说明 |
 * |------|------|
 * | `setBorder($width, $color, $radius)` | 设置边框宽度、颜色、圆角 |
 * | `setBorderRadius($radius)` | 单独设置圆角半径 |
 *
 * ### 文字特效
 * | 方法 | 说明 |
 * |------|------|
 * | `setTextShadow($config)` | 阴影：color/alpha/offsetX/offsetY/blur |
 * | `clearTextShadow()` | 清除阴影 |
 * | `setTextStroke($config)` | 描边：color/alpha/width |
 * | `clearTextStroke()` | 清除描边 |
 * | `setTextGlow($config)` | 发光：color/alpha/radius/strength |
 * | `clearTextGlow()` | 清除发光 |
 * | `setTextHighlight($color, $alpha, $pad, $radius)` | 文字高亮底色 |
 * | `clearTextHighlight()` | 清除高亮 |
 *
 * ### 水印（支持多次叠加）
 * | 方法 | 说明 |
 * |------|------|
 * | `addImageWatermark($path, $config)` | 添加图片水印（支持位置/透明度/缩放） |
 * | `addTextWatermark($text, $config)` | 添加文字水印（支持旋转/透明度/字体） |
 * | `clearWatermarks()` | 清除所有水印 |
 *
 * ### 输出设置
 * | 方法 | 说明 |
 * |------|------|
 * | `setOutputFormat($format)` | 输出格式：png/jpeg/gif/webp/bmp |
 * | `setQuality($quality)` | JPEG/WebP 质量（0-100） |
 * | `setClearExif($clear)` | 是否清除元数据标记 |
 *
 * ### 渲染与输出
 * | 方法 | 说明 |
 * |------|------|
 * | `build()` | 执行绘制流程（创建画布→背景→边框→文字→水印） |
 * | `render($fileName)` | 渲染并输出：传路径则保存，null 则输出浏览器 |
 * | `getImage()` | 获取当前 GD 资源句柄 |
 * | `toBase64($prefix)` | 输出 Base64 编码字符串 |
 * | `toBinary()` | 输出二进制图片数据 |
 *
 * ### 滤镜（build 后调用）
 * | 方法 | 说明 |
 * |------|------|
 * | `filterGrayscale()` | 灰度 |
 * | `filterNegate()` | 反色 |
 * | `filterBrightness($level)` | 亮度调整（-255~255） |
 * | `filterContrast($level)` | 对比度调整（-100~100） |
 * | `filterBlur($times)` | 高斯模糊（次数） |
 * | `filterSmooth($level)` | 平滑处理 |
 * | `filterPixelate($size)` | 像素化 |
 * | `filterColorize($color, $alpha)` | 颜色叠加 |
 *
 * ### 高级图像处理（build 后调用）
 * | 方法 | 说明 |
 * |------|------|
 * | `rotateImage($angle, $bgColor)` | 旋转整张图片 |
 * | `cropImage($x, $y, $w, $h)` | 裁剪图片 |
 * | `resizeImage($w, $h, $crop)` | 缩放图片（支持裁剪填充） |
 * | `mergeImage($path, $x, $y, $opacity, $w, $h)` | 合并外部图片 |
 * | `stripMetadata()` | 清除图片元数据（EXIF 等） |
 */
class TextToImg
{
    // ==================== 图片格式常量 ====================

    /** PNG 图片格式，支持透明通道 */
    public const FORMAT_PNG = 'png';

    /** JPEG 图片格式，适合照片类内容 */
    public const FORMAT_JPEG = 'jpeg';

    /** JPG 图片格式（JPEG 的别名） */
    public const FORMAT_JPG = 'jpg';

    /** GIF 图片格式，支持动画（本类仅输出静态） */
    public const FORMAT_GIF = 'gif';

    /** WebP 图片格式，现代浏览器推荐 */
    public const FORMAT_WEBP = 'webp';

    /** BMP 图片格式，无压缩位图 */
    public const FORMAT_BMP = 'bmp';

    // ==================== 水平对齐常量 ====================

    /** 左对齐 */
    public const ALIGN_LEFT = 'left';

    /** 水平居中 */
    public const ALIGN_CENTER = 'center';

    /** 右对齐 */
    public const ALIGN_RIGHT = 'right';

    // ==================== 垂直对齐常量 ====================

    /** 顶部对齐 */
    public const VALIGN_TOP = 'top';

    /** 垂直居中 */
    public const VALIGN_MIDDLE = 'middle';

    /** 底部对齐 */
    public const VALIGN_BOTTOM = 'bottom';

    // ==================== 水印位置常量 ====================

    /** 左上角 */
    public const WATERMARK_TOP_LEFT = 'top-left';

    /** 顶部居中 */
    public const WATERMARK_TOP_CENTER = 'top-center';

    /** 右上角 */
    public const WATERMARK_TOP_RIGHT = 'top-right';

    /** 左侧居中 */
    public const WATERMARK_CENTER_LEFT = 'center-left';

    /** 正中心 */
    public const WATERMARK_CENTER = 'center';

    /** 右侧居中 */
    public const WATERMARK_CENTER_RIGHT = 'center-right';

    /** 左下角 */
    public const WATERMARK_BOTTOM_LEFT = 'bottom-left';

    /** 底部居中 */
    public const WATERMARK_BOTTOM_CENTER = 'bottom-center';

    /** 右下角 */
    public const WATERMARK_BOTTOM_RIGHT = 'bottom-right';

    // ==================== 渐变类型常量 ====================

    /** 线性渐变 */
    public const GRADIENT_LINEAR = 'linear';

    /** 径向渐变 */
    public const GRADIENT_RADIAL = 'radial';

    // ==================== 核心属性 ====================

    /** @var GdImage|null GD 图片资源句柄 */
    private ?GdImage $image = null;

    /** @var string 字体文件绝对路径 */
    private string $fontFile = '';

    /** @var int 文字旋转角度（0-360 度） */
    private int $angle = 0;

    /** @var string 文字内容，支持 \n 和 <br> 换行 */
    private string $text = 'hello';

    /** @var float|null 固定字号（pt），null 表示自动计算 */
    private ?float $size = null;

    /** @var float 最小字号限制，防止自动计算时过小 */
    private float $minSize = 8.0;

    /** @var float 最大字号限制，防止自动计算时过大 */
    private float $maxSize = 300.0;

    /** @var array<int> 文字颜色 [R, G, B]，每项范围 0-255 */
    private array $textColor = [0, 0, 0];

    /** @var int 文字透明度（GD 特性：0 为不透明，127 为全透明） */
    private int $textAlpha = 0;

    /** @var array<int> 背景颜色 [R, G, B]，每项范围 0-255 */
    private array $backgroundColor = [255, 255, 255];

    /** @var int 背景透明度（GD 特性：0 为不透明，127 为全透明） */
    private int $bgAlpha = 0;

    /** @var int 画布宽度（像素） */
    private int $width = 800;

    /** @var int 画布高度（像素） */
    private int $height = 600;

    /** @var bool 是否自动根据文字内容调整画布尺寸 */
    private bool $autoSize = false;

    /** @var int 内边距（像素），文字区域与画布边缘的距离 */
    private int $padding = 20;

    /** @var int 行高（像素），0 表示根据字号自动计算 */
    private int $lineHeight = 0;

    /** @var string 水平对齐方式：left / center / right */
    private string $hAlign = self::ALIGN_CENTER;

    /** @var string 垂直对齐方式：top / middle / bottom */
    private string $vAlign = self::VALIGN_MIDDLE;

    /** @var bool 是否按画布宽度自动换行 */
    private bool $autoWrap = false;

    /** @var string|null 背景图片路径 */
    private ?string $backgroundImage = null;

    /** @var array 渐变色配置数组，每项为 ['color' => [R,G,B]|string, 'position' => 0-1] */
    private array $gradientColors = [];

    /** @var string 渐变类型：linear / radial */
    private string $gradientType = self::GRADIENT_LINEAR;

    /** @var float 渐变角度（仅线性渐变有效，单位：度） */
    private float $gradientAngle = 0.0;

    /** @var int 边框宽度（像素），0 表示无边框 */
    private int $borderWidth = 0;

    /** @var array<int> 边框颜色 [R, G, B] */
    private array $borderColor = [0, 0, 0];

    /** @var int 边框圆角半径（像素），0 表示直角 */
    private int $borderRadius = 0;

    /** @var array 文字阴影配置 [
     *     'color'   => [R,G,B]|string,
     *     'alpha'   => int(0-127),
     *     'offsetX' => int,
     *     'offsetY' => int,
     *     'blur'    => int
     * ]
     */
    private array $textShadow = [];

    /** @var array 文字描边配置 [
     *     'color' => [R,G,B]|string,
     *     'alpha' => int(0-127),
     *     'width' => int
     * ]
     */
    private array $textStroke = [];

    /** @var array 文字发光配置 [
     *     'color'   => [R,G,B]|string,
     *     'alpha'   => int(0-127),
     *     'radius'  => int,
     *     'strength'=> int
     * ]
     */
    private array $textGlow = [];

    /** @var array 水印列表，支持图片和文字水印混合 */
    private array $watermarks = [];

    /** @var string 输出图片格式：png / jpeg / gif / webp / bmp */
    private string $outputFormat = self::FORMAT_PNG;

    /** @var int 图片质量（JPEG / WebP 有效，范围 0-100） */
    private int $quality = 85;

    /** @var bool 是否清除输出图片的元数据（EXIF 等） */
    private bool $clearExif = false;

    /** @var string 字体目录路径 */
    private string $fontDir = '';

    /** @var array 可用字体文件名缓存 */
    private array $availableFonts = [];

    /** @var float 画布缩放比例（用于高 DPI / Retina 显示，2.0 表示 2x 图） */
    private float $scale = 1.0;

    /** @var bool 是否开启抗锯齿（文字渲染更平滑） */
    private bool $antialias = true;

    /** @var array<int> 文字背景高亮色 [R, G, B]，用于实现文字底色效果 */
    private array $textHighlight = [];

    /** @var int 文字背景高亮透明度（0-127） */
    private int $textHighlightAlpha = 0;

    /** @var int 文字背景高亮的内边距（像素） */
    private int $textHighlightPadding = 4;

    /** @var int 文字背景高亮的圆角半径（像素） */
    private int $textHighlightRadius = 4;

    // ==================== 性能缓存属性 ====================

    /** @var array|null 文字尺寸计算结果缓存，避免重复调用 imagettfbbox */
    private ?array $textDimensionsCache = null;

    /** @var float|null 上次计算使用的字号（用于缓存失效判断） */
    private ?float $cachedSize = null;

    /** @var int|null 上次计算使用的角度（用于缓存失效判断） */
    private ?int $cachedAngle = null;

    /** @var array|null 上次处理后的文字行数组（用于缓存失效判断） */
    private ?array $cachedLines = null;

    // ==================== 单例属性 ====================

    /** @var self|null 单例实例缓存 */
    private static ?self $instance = null;

    /**
     * 构造函数
     *
     * 初始化画布尺寸、字体路径，并检测 GD 扩展是否可用。
     *
     * @param  int  $width   画布宽度（像素）
     * @param  int  $height  画布高度（像素）
     *
     * @throws Exception 当 GD 扩展未加载或字体目录不可读时抛出异常
     */
    public function __construct(int $width = 800, int $height = 600)
    {
        // 检测 PHP GD 扩展是否已加载，未加载则无法继续
        if (! extension_loaded('gd')) {
            throw new Exception('TextToImg 依赖 PHP GD 扩展，请先安装并启用 gd 扩展。');
        }

        $this->width  = max(1, $width);
        $this->height = max(1, $height);

        // 设置默认字体目录为本库自带的 resource/font 目录
        $this->fontDir = dirname(__FILE__, 2) . '/resource/font';

        // 初始化默认字体文件（庞门正道标题体）
        $defaultFont = $this->fontDir . '/pmzdxx.ttf';
        if (is_file($defaultFont)) {
            $this->fontFile = $defaultFont;
        }
    }

    /**
     * 获取单例实例
     *
     * 采用单例模式减少重复创建对象的开销。
     * 如需全新实例，请直接使用 new TextToImg()。
     *
     * @param  int  $width   画布宽度
     * @param  int  $height  画布高度
     * @return static
     *
     * @throws Exception
     */
    public static function instance(int $width = 800, int $height = 600): static
    {
        if (self::$instance === null) {
            self::$instance = new static($width, $height);
        } else {
            // 复用单例时重置状态并更新尺寸
            self::$instance->resetState();
            self::$instance->width  = $width;
            self::$instance->height = $height;
        }

        return self::$instance;
    }

    /**
     * 批量生成图片
     *
     * 通过复用单例实例，避免每次生成时重新创建对象和加载字体，
     * 在批量场景（如生成验证码、海报、缩略图等）下可显著降低内存占用和提升速度。
     *
     * 每个回调接收一个已重置状态的实例，可在回调内自由链式调用。
     *
     * ```php
     * $files = TextToImg::batch([
     *     fn ($img) => $img->setText('A')->setFontSize(48)->render('a.png'),
     *     fn ($img) => $img->setText('B')->setFontSize(48)->render('b.png'),
     *     fn ($img) => $img->setText('C')->setFontSize(48)->render('c.png'),
     * ]);
     * ```
     *
     * @param  array<callable(static): mixed>  $handlers  回调数组
     * @return array<mixed> 各回调的返回值
     *
     * @throws Exception
     */
    public static function batch(array $handlers): array
    {
        $results  = [];
        $instance = self::instance();
        foreach ($handlers as $handler) {
            if (! is_callable($handler)) {
                throw new Exception('TextToImg::batch() 中的每个任务必须是可调用对象。');
            }
            $instance->resetState();
            $results[] = $handler($instance);
        }

        return $results;
    }

    /**
     * 重置实例状态
     *
     * 将除核心配置外的所有绘制参数恢复为默认值，
     * 便于单例复用时不会残留上一次的设置。
     *
     * @return $this
     */
    public function resetState(): static
    {
        $this->angle              = 0;
        $this->text               = 'hello';
        $this->size               = null;
        $this->textColor          = [0, 0, 0];
        $this->textAlpha          = 0;
        $this->backgroundColor    = [255, 255, 255];
        $this->bgAlpha            = 0;
        $this->autoSize           = false;
        $this->padding            = 20;
        $this->lineHeight         = 0;
        $this->hAlign             = self::ALIGN_CENTER;
        $this->vAlign             = self::VALIGN_MIDDLE;
        $this->autoWrap           = false;
        $this->backgroundImage    = null;
        $this->gradientColors     = [];
        $this->gradientType       = self::GRADIENT_LINEAR;
        $this->gradientAngle      = 0.0;
        $this->borderWidth        = 0;
        $this->borderColor        = [0, 0, 0];
        $this->borderRadius       = 0;
        $this->textShadow         = [];
        $this->textStroke         = [];
        $this->textGlow           = [];
        $this->watermarks         = [];
        $this->outputFormat       = self::FORMAT_PNG;
        $this->quality            = 85;
        $this->clearExif          = false;
        $this->scale              = 1.0;
        $this->antialias          = true;
        $this->textHighlight      = [];
        $this->textHighlightAlpha = 0;
        $this->textHighlightPadding = 4;
        $this->textHighlightRadius  = 4;

        // 清空性能缓存，避免脏数据
        $this->textDimensionsCache = null;
        $this->cachedSize          = null;
        $this->cachedAngle         = null;
        $this->cachedLines         = null;

        // 释放已有的 GD 资源，避免内存泄漏
        if ($this->image !== null) {
            imagedestroy($this->image);
            $this->image = null;
        }

        return $this;
    }

    /**
     * 禁止克隆（单例模式保护）
     *
     * @throws Exception
     */
    private function __clone()
    {
        throw new Exception('TextToImg 单例模式不支持克隆操作。');
    }

    // ==================== 基础设置方法 ====================

    /**
     * 设置画布尺寸
     *
     * @param  int  $width   宽度（像素）
     * @param  int  $height  高度（像素）
     * @return $this
     */
    public function setSize(int $width, int $height): static
    {
        $this->width  = max(1, $width);
        $this->height = max(1, $height);

        return $this;
    }

    /**
     * 设置字体文件路径
     *
     * 支持传入系统任意可读的 TTF / OTF 字体文件。
     *
     * @param  string  $file  字体文件的绝对路径
     * @return $this
     *
     * @throws Exception 字体文件不存在或不可读时抛出异常
     */
    public function setFontFile(string $file): static
    {
        if ($file === '') {
            throw new Exception('字体文件路径不能为空。');
        }
        if (! is_file($file) || ! is_readable($file)) {
            throw new Exception('字体文件不存在或不可读: ' . $file);
        }
        $this->fontFile = $file;

        return $this;
    }

    /**
     * 选择本库内置字体
     *
     * 字体文件需存放于本库 resource/font/ 目录下，仅需传入文件名（不含扩展名）。
     *
     * @param  string  $style  字体名称，例如 'pmzdxx'（庞门正道标题体）
     * @return $this
     *
     * @throws Exception 字体不存在时抛出异常
     */
    public function setFontStyle(string $style = 'pmzdxx'): static
    {
        $file = $this->fontDir . '/' . $style . '.ttf';
        if (! is_file($file)) {
            // 尝试 otf 扩展名
            $file = $this->fontDir . '/' . $style . '.otf';
            if (! is_file($file)) {
                throw new Exception('不支持的字体样式: ' . $style . '，请确保字体文件存在于 ' . $this->fontDir);
            }
        }
        $this->fontFile = $file;

        return $this;
    }

    /**
     * 设置字体目录
     *
     * 用于修改本库默认字体扫描路径，设置后 setFontStyle 将在新目录中查找字体。
     *
     * @param  string  $dir  字体目录绝对路径
     * @return $this
     *
     * @throws Exception 目录不存在时抛出异常
     */
    public function setFontDir(string $dir): static
    {
        if (! is_dir($dir)) {
            throw new Exception('字体目录不存在: ' . $dir);
        }
        $this->fontDir = rtrim($dir, '/\\');

        return $this;
    }

    /**
     * 设置文字内容
     *
     * 支持使用 \n、\r\n 或 <br> 标签进行换行。
     *
     * @param  string  $text  文字内容
     * @return $this
     */
    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /**
     * 设置固定字号
     *
     * 设置后不再自动计算字体大小，强制使用指定值。
     *
     * @param  float  $size  字号（pt）
     * @return $this
     */
    public function setFontSize(float $size): static
    {
        $this->size = max($this->minSize, min($this->maxSize, $size));

        return $this;
    }

    /**
     * 设置字号上下限
     *
     * 用于限制自动计算字体大小时的范围，防止过大或过小。
     *
     * @param  float  $min  最小字号（默认 8）
     * @param  float  $max  最大字号（默认 300）
     * @return $this
     */
    public function setFontSizeRange(float $min = 8.0, float $max = 300.0): static
    {
        $this->minSize = max(1, $min);
        $this->maxSize = max($this->minSize, $max);
        if ($this->size !== null) {
            $this->size = max($this->minSize, min($this->maxSize, $this->size));
        }

        return $this;
    }

    /**
     * 设置文字旋转角度
     *
     * 角度范围会自动归一化到 0-360 度之间。
     *
     * @param  int  $angle  旋转角度（顺时针）
     * @return $this
     */
    public function setAngle(int $angle = 0): static
    {
        $angle = $angle % 360;
        if ($angle < 0) {
            $angle += 360;
        }
        $this->angle = $angle;

        return $this;
    }

    /**
     * 设置文字颜色
     *
     * 支持多种颜色格式：
     * - 16 进制字符串：'FF5733'、'F53'、'F'
     * - RGB 数组：[255, 87, 51]
     * - RGBA 数组：[255, 87, 51, 0]
     *
     * @param  string|array  $color  颜色值
     * @param  int|null      $alpha  透明度（0-127），null 表示不覆盖
     * @return $this
     *
     * @throws Exception 颜色格式错误时抛出异常
     */
    public function setColor(string|array $color, ?int $alpha = null): static
    {
        $this->textColor = $this->parseColor($color);
        if ($alpha !== null) {
            $this->textAlpha = max(0, min(127, $alpha));
        }

        return $this;
    }

    /**
     * 设置文字透明度
     *
     * @param  int  $alpha  透明度（0 为不透明，127 为全透明）
     * @return $this
     */
    public function setTextAlpha(int $alpha): static
    {
        $this->textAlpha = max(0, min(127, $alpha));

        return $this;
    }

    /**
     * 设置背景颜色
     *
     * 支持多种颜色格式：
     * - 16 进制字符串：'FFFFFF'、'FFF'、'F'
     * - RGB 数组：[255, 255, 255]
     * - RGBA 数组：[255, 255, 255, 0]
     *
     * @param  string|array  $color  颜色值
     * @param  int|null      $alpha  透明度（0-127），null 表示不覆盖
     * @return $this
     *
     * @throws Exception
     */
    public function setBgColor(string|array $color, ?int $alpha = null): static
    {
        $this->backgroundColor = $this->parseColor($color);
        if ($alpha !== null) {
            $this->bgAlpha = max(0, min(127, $alpha));
        }

        return $this;
    }

    /**
     * 设置背景透明度
     *
     * @param  int  $alpha  透明度（0 为不透明，127 为全透明）
     * @return $this
     */
    public function setBgAlpha(int $alpha): static
    {
        $this->bgAlpha = max(0, min(127, $alpha));

        return $this;
    }

    // ==================== 排版设置方法 ====================

    /**
     * 设置对齐方式
     *
     * 可同时设置水平和垂直对齐，也支持单独调用 setHAlign / setVAlign。
     *
     * @param  string  $horizontal  水平对齐：left / center / right
     * @param  string  $vertical    垂直对齐：top / middle / bottom
     * @return $this
     *
     * @throws Exception 对齐参数非法时抛出异常
     */
    public function setAlign(string $horizontal = self::ALIGN_CENTER, string $vertical = self::VALIGN_MIDDLE): static
    {
        $this->setHAlign($horizontal);
        $this->setVAlign($vertical);

        return $this;
    }

    /**
     * 设置水平对齐方式
     *
     * @param  string  $align  left / center / right
     * @return $this
     *
     * @throws Exception
     */
    public function setHAlign(string $align): static
    {
        $allowed = [self::ALIGN_LEFT, self::ALIGN_CENTER, self::ALIGN_RIGHT];
        if (! in_array($align, $allowed, true)) {
            throw new Exception('水平对齐方式必须是 left、center 或 right。');
        }
        $this->hAlign = $align;

        return $this;
    }

    /**
     * 设置垂直对齐方式
     *
     * @param  string  $align  top / middle / bottom
     * @return $this
     *
     * @throws Exception
     */
    public function setVAlign(string $align): static
    {
        $allowed = [self::VALIGN_TOP, self::VALIGN_MIDDLE, self::VALIGN_BOTTOM];
        if (! in_array($align, $allowed, true)) {
            throw new Exception('垂直对齐方式必须是 top、middle 或 bottom。');
        }
        $this->vAlign = $align;

        return $this;
    }

    /**
     * 设置内边距
     *
     * 文字区域与画布边缘的留白距离。
     *
     * @param  int  $padding  内边距（像素）
     * @return $this
     */
    public function setPadding(int $padding): static
    {
        $this->padding = max(0, $padding);

        return $this;
    }

    /**
     * 设置行高
     *
     * @param  int  $height  行高（像素），0 表示按字号自动计算（1.5 倍字号）
     * @return $this
     */
    public function setLineHeight(int $height = 0): static
    {
        $this->lineHeight = max(0, $height);

        return $this;
    }

    /**
     * 设置是否自动换行
     *
     * 开启后，文字在达到画布宽度（减去内边距）时自动换行。
     *
     * @param  bool  $enabled  是否开启
     * @return $this
     */
    public function setAutoWrap(bool $enabled = true): static
    {
        $this->autoWrap = $enabled;

        return $this;
    }

    /**
     * 设置画布尺寸自适应文字内容
     *
     * 开启后，render 前会自动根据文字尺寸调整画布大小（含内边距）。
     *
     * @param  bool  $enabled  是否开启
     * @return $this
     */
    public function setAutoSize(bool $enabled = true): static
    {
        $this->autoSize = $enabled;

        return $this;
    }

    /**
     * 设置画布缩放比例（高 DPI 支持）
     *
     * 例如设为 2.0 可生成 2x 高清图，配合 HTML img 标签 width/height 使用。
     *
     * @param  float  $scale  缩放比例（>= 1.0）
     * @return $this
     */
    public function setScale(float $scale): static
    {
        $this->scale = max(0.1, $scale);

        return $this;
    }

    /**
     * 设置是否开启抗锯齿
     *
     * @param  bool  $enabled  是否开启（默认 true）
     * @return $this
     */
    public function setAntialias(bool $enabled = true): static
    {
        $this->antialias = $enabled;

        return $this;
    }

    // ==================== 背景设置方法 ====================

    /**
     * 设置背景图片
     *
     * 背景图片会覆盖纯色/渐变背景设置。支持 JPG、PNG、GIF、WebP、BMP 格式。
     *
     * @param  string  $path  背景图片绝对路径
     * @return $this
     *
     * @throws Exception 图片不存在或格式不支持时抛出异常
     */
    public function setBackgroundImage(string $path): static
    {
        if (! is_file($path)) {
            throw new Exception('背景图片不存在: ' . $path);
        }
        $this->backgroundImage = $path;

        return $this;
    }

    /**
     * 设置纯色背景
     *
     * 此调用会清空背景图片和渐变背景设置。
     *
     * @param  string|array  $color  颜色值
     * @param  int|null      $alpha  透明度（0-127）
     * @return $this
     *
     * @throws Exception
     */
    public function setSolidBackground(string|array $color, ?int $alpha = null): static
    {
        $this->backgroundImage = null;
        $this->gradientColors  = [];
        $this->setBgColor($color, $alpha);

        return $this;
    }

    /**
     * 设置渐变背景
     *
     * 示例：
     * ```php
     * ->setGradientBackground([
     *     ['color' => '667eea', 'position' => 0],
     *     ['color' => '764ba2', 'position' => 1],
     * ])
     * ```
     *
     * @param  array  $colors  渐变色数组
     * @return $this
     *
     * @throws Exception 颜色配置非法时抛出异常
     */
    public function setGradientBackground(array $colors): static
    {
        if (count($colors) < 2) {
            throw new Exception('渐变色至少需要两种颜色。');
        }
        $this->backgroundImage = null;
        $this->gradientColors  = [];
        foreach ($colors as $item) {
            if (! isset($item['color'], $item['position'])) {
                throw new Exception('渐变色配置必须包含 color 和 position 键。');
            }
            $this->gradientColors[] = [
                'color'    => $this->parseColor($item['color']),
                'position' => max(0.0, min(1.0, (float) $item['position'])),
            ];
        }
        // 按 position 排序，确保渐变方向正确
        usort($this->gradientColors, fn ($a, $b) => $a['position'] <=> $b['position']);

        return $this;
    }

    /**
     * 设置渐变类型
     *
     * @param  string  $type  linear（线性）或 radial（径向）
     * @return $this
     *
     * @throws Exception
     */
    public function setGradientType(string $type = self::GRADIENT_LINEAR): static
    {
        $allowed = [self::GRADIENT_LINEAR, self::GRADIENT_RADIAL];
        if (! in_array($type, $allowed, true)) {
            throw new Exception('渐变类型必须是 linear 或 radial。');
        }
        $this->gradientType = $type;

        return $this;
    }

    /**
     * 设置渐变角度（仅线性渐变有效）
     *
     * @param  float  $angle  渐变角度（度）
     * @return $this
     */
    public function setGradientAngle(float $angle = 0.0): static
    {
        $this->gradientAngle = $angle;

        return $this;
    }

    // ==================== 边框设置方法 ====================

    /**
     * 设置边框
     *
     * @param  int           $width   边框宽度（像素），0 表示无边框
     * @param  string|array  $color   边框颜色
     * @param  int           $radius  圆角半径（像素），0 表示直角
     * @return $this
     *
     * @throws Exception
     */
    public function setBorder(int $width = 1, string|array $color = '000000', int $radius = 0): static
    {
        $this->borderWidth  = max(0, $width);
        $this->borderColor  = $this->parseColor($color);
        $this->borderRadius = max(0, $radius);

        return $this;
    }

    /**
     * 设置圆角半径
     *
     * 单独设置圆角，不影响边框宽度。
     *
     * @param  int  $radius  圆角半径（像素）
     * @return $this
     */
    public function setBorderRadius(int $radius = 0): static
    {
        $this->borderRadius = max(0, $radius);

        return $this;
    }

    // ==================== 文字特效设置方法 ====================

    /**
     * 设置文字阴影
     *
     * 示例：
     * ```php
     * ->setTextShadow([
     *     'color'   => '000000',
     *     'alpha'   => 60,
     *     'offsetX' => 3,
     *     'offsetY' => 3,
     *     'blur'    => 2,
     * ])
     * ```
     *
     * @param  array  $config  阴影配置数组
     * @return $this
     *
     * @throws Exception
     */
    public function setTextShadow(array $config): static
    {
        $this->textShadow = $this->parseTextEffectConfig($config, 'shadow');

        return $this;
    }

    /**
     * 清除文字阴影
     *
     * @return $this
     */
    public function clearTextShadow(): static
    {
        $this->textShadow = [];

        return $this;
    }

    /**
     * 设置文字描边
     *
     * 描边会在文字边缘绘制轮廓线，使文字在复杂背景下更清晰。
     *
     * 示例：
     * ```php
     * ->setTextStroke([
     *     'color' => 'FFFFFF',
     *     'alpha' => 100,
     *     'width' => 2,
     * ])
     * ```
     *
     * @param  array  $config  描边配置数组
     * @return $this
     *
     * @throws Exception
     */
    public function setTextStroke(array $config): static
    {
        $this->textStroke = $this->parseTextEffectConfig($config, 'stroke');

        return $this;
    }

    /**
     * 清除文字描边
     *
     * @return $this
     */
    public function clearTextStroke(): static
    {
        $this->textStroke = [];

        return $this;
    }

    /**
     * 设置文字发光效果
     *
     * 发光效果会在文字周围产生柔和的光晕。
     *
     * 示例：
     * ```php
     * ->setTextGlow([
     *     'color'    => 'FFD700',
     *     'alpha'    => 80,
     *     'radius'   => 8,
     *     'strength' => 3,
     * ])
     * ```
     *
     * @param  array  $config  发光配置数组
     * @return $this
     *
     * @throws Exception
     */
    public function setTextGlow(array $config): static
    {
        $this->textGlow = $this->parseTextEffectConfig($config, 'glow');

        return $this;
    }

    /**
     * 清除文字发光效果
     *
     * @return $this
     */
    public function clearTextGlow(): static
    {
        $this->textGlow = [];

        return $this;
    }

    /**
     * 设置文字高亮背景
     *
     * 在文字下方绘制一个带圆角的色块作为高亮背景。
     *
     * @param  string|array  $color    高亮色
     * @param  int           $alpha    透明度（0-127）
     * @param  int           $padding  高亮背景与文字的间距（像素）
     * @param  int           $radius   高亮背景圆角半径（像素）
     * @return $this
     *
     * @throws Exception
     */
    public function setTextHighlight(string|array $color, int $alpha = 0, int $padding = 4, int $radius = 4): static
    {
        $this->textHighlight        = $this->parseColor($color);
        $this->textHighlightAlpha   = max(0, min(127, $alpha));
        $this->textHighlightPadding = max(0, $padding);
        $this->textHighlightRadius  = max(0, $radius);

        return $this;
    }

    /**
     * 清除文字高亮背景
     *
     * @return $this
     */
    public function clearTextHighlight(): static
    {
        $this->textHighlight        = [];
        $this->textHighlightAlpha   = 0;
        $this->textHighlightPadding = 4;
        $this->textHighlightRadius  = 4;

        return $this;
    }

    // ==================== 水印设置方法 ====================

    /**
     * 添加图片水印
     *
     * 支持多次调用添加多个水印。
     *
     * 示例：
     * ```php
     * ->addImageWatermark('/path/to/logo.png', [
     *     'position' => TextToImg::WATERMARK_BOTTOM_RIGHT,
     *     'opacity'  => 50,
     *     'scale'    => 0.3,
     *     'margin'   => 20,
     * ])
     * ```
     *
     * @param  string  $path    水印图片路径
     * @param  array   $config  水印配置
     * @return $this
     *
     * @throws Exception
     */
    public function addImageWatermark(string $path, array $config = []): static
    {
        if (! is_file($path)) {
            throw new Exception('水印图片不存在: ' . $path);
        }
        $this->watermarks[] = [
            'type'     => 'image',
            'path'     => $path,
            'position' => $config['position'] ?? self::WATERMARK_BOTTOM_RIGHT,
            'opacity'  => max(0, min(100, $config['opacity'] ?? 100)),
            'scale'    => $config['scale'] ?? null,
            'width'    => $config['width'] ?? null,
            'height'   => $config['height'] ?? null,
            'margin'   => $config['margin'] ?? 20,
            'x'        => $config['x'] ?? 0,
            'y'        => $config['y'] ?? 0,
        ];

        return $this;
    }

    /**
     * 添加文字水印
     *
     * 支持多次调用添加多个文字水印。
     *
     * 示例：
     * ```php
     * ->addTextWatermark('机密文件', [
     *     'position' => TextToImg::WATERMARK_CENTER,
     *     'size'     => 48,
     *     'color'    => 'FF0000',
     *     'alpha'    => 30,
     *     'angle'    => 45,
     *     'font'     => '/path/to/font.ttf',
     * ])
     * ```
     *
     * @param  string  $text    水印文字
     * @param  array   $config  水印配置
     * @return $this
     *
     * @throws Exception
     */
    public function addTextWatermark(string $text, array $config = []): static
    {
        $color = $config['color'] ?? '000000';
        $this->watermarks[] = [
            'type'     => 'text',
            'text'     => $text,
            'position' => $config['position'] ?? self::WATERMARK_BOTTOM_RIGHT,
            'size'     => $config['size'] ?? 24,
            'color'    => is_array($color) ? $color : $this->parseHexColor($color),
            'alpha'    => max(0, min(127, $config['alpha'] ?? 50)),
            'angle'    => $config['angle'] ?? 0,
            'font'     => $config['font'] ?? $this->fontFile,
            'margin'   => $config['margin'] ?? 20,
            'x'        => $config['x'] ?? 0,
            'y'        => $config['y'] ?? 0,
        ];

        return $this;
    }

    /**
     * 清除所有水印
     *
     * @return $this
     */
    public function clearWatermarks(): static
    {
        $this->watermarks = [];

        return $this;
    }

    // ==================== 输出设置方法 ====================

    /**
     * 设置输出图片格式
     *
     * @param  string  $format  png / jpeg / jpg / gif / webp / bmp
     * @return $this
     *
     * @throws Exception 格式不支持时抛出异常
     */
    public function setOutputFormat(string $format = self::FORMAT_PNG): static
    {
        $allowed = [
            self::FORMAT_PNG, self::FORMAT_JPEG, self::FORMAT_JPG,
            self::FORMAT_GIF, self::FORMAT_WEBP, self::FORMAT_BMP,
        ];
        $format = strtolower($format);
        if (! in_array($format, $allowed, true)) {
            throw new Exception('不支持的图片输出格式: ' . $format);
        }
        $this->outputFormat = $format;

        return $this;
    }

    /**
     * 设置图片质量
     *
     * 仅对 JPEG / WebP 格式有效（范围 0-100，默认 85）。
     *
     * @param  int  $quality  图片质量
     * @return $this
     */
    public function setQuality(int $quality = 85): static
    {
        $this->quality = max(0, min(100, $quality));

        return $this;
    }

    /**
     * 设置是否清除图片元数据
     *
     * @param  bool  $clear  是否清除
     * @return $this
     */
    public function setClearExif(bool $clear = true): static
    {
        $this->clearExif = $clear;

        return $this;
    }

    // ==================== 核心工具方法 ====================

    /**
     * 解析颜色值
     *
     * 支持多种颜色格式统一转换为 [R, G, B] 数组：
     * - 16 进制字符串（6位/3位/1位）: 'FF5733'、'F53'、'F'
     * - RGB 数组: [255, 87, 51]
     * - RGBA 数组: [255, 87, 51, 0]（alpha 被忽略，仅提取 RGB）
     *
     * @param  string|array  $color  颜色值
     * @return array<int>  [R, G, B]
     *
     * @throws Exception 格式错误时抛出异常
     */
    private function parseColor(string|array $color): array
    {
        if (is_array($color)) {
            if (count($color) < 3) {
                throw new Exception('RGB 颜色数组至少需要包含 R、G、B 三个值。');
            }
            return [
                max(0, min(255, (int) $color[0])),
                max(0, min(255, (int) $color[1])),
                max(0, min(255, (int) $color[2])),
            ];
        }

        return $this->parseHexColor($color);
    }

    /**
     * 解析 16 进制颜色字符串
     *
     * 支持格式：#FF5733、FF5733、F53、F
     *
     * @param  string  $hex  16 进制颜色字符串
     * @return array<int>  [R, G, B]
     *
     * @throws Exception
     */
    private function parseHexColor(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $hex = strtolower($hex);

        if (! preg_match('/^[0-9a-f]+$/', $hex)) {
            throw new Exception('颜色值包含非法字符: ' . $hex);
        }

        $length = strlen($hex);

        return match ($length) {
            6 => [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
            ],
            3 => [
                hexdec(str_repeat(substr($hex, 0, 1), 2)),
                hexdec(str_repeat(substr($hex, 1, 1), 2)),
                hexdec(str_repeat(substr($hex, 2, 1), 2)),
            ],
            1 => [
                hexdec(str_repeat($hex, 2)),
                hexdec(str_repeat($hex, 2)),
                hexdec(str_repeat($hex, 2)),
            ],
            default => throw new Exception('颜色值格式错误，仅支持 1/3/6 位十六进制: ' . $hex),
        };
    }

    /**
     * 解析文字特效配置
     *
     * @param  array   $config  用户配置
     * @param  string  $type    特效类型：shadow / stroke / glow
     * @return array
     *
     * @throws Exception
     */
    private function parseTextEffectConfig(array $config, string $type): array
    {
        if (! isset($config['color'])) {
            throw new Exception($type . ' 特效必须指定 color 参数。');
        }

        $parsed = [
            'color' => $this->parseColor($config['color']),
            'alpha' => max(0, min(127, $config['alpha'] ?? 0)),
        ];

        return match ($type) {
            'shadow' => array_merge($parsed, [
                'offsetX' => $config['offsetX'] ?? 2,
                'offsetY' => $config['offsetY'] ?? 2,
                'blur'    => max(0, $config['blur'] ?? 0),
            ]),
            'stroke' => array_merge($parsed, [
                'width' => max(1, $config['width'] ?? 1),
            ]),
            'glow' => array_merge($parsed, [
                'radius'   => max(1, $config['radius'] ?? 5),
                'strength' => max(1, $config['strength'] ?? 2),
            ]),
            default => $parsed,
        };
    }

    /**
     * 创建 GD 图片资源
     *
     * 根据当前配置创建画布，并应用缩放比例。
     *
     * @return GdImage
     */
    private function createCanvas(): GdImage
    {
        // 若已存在旧画布，先释放以避免重复 build() 导致内存泄漏
        if ($this->image !== null) {
            imagedestroy($this->image);
            $this->image = null;
        }

        $w = (int) round($this->width * $this->scale);
        $h = (int) round($this->height * $this->scale);

        $this->image = imagecreatetruecolor($w, $h);

        if ($this->antialias && function_exists('imageantialias')) {
            imageantialias($this->image, true);
        }

        if (in_array($this->outputFormat, [self::FORMAT_PNG, self::FORMAT_WEBP, self::FORMAT_GIF], true)) {
            imagealphablending($this->image, true);
            imagesavealpha($this->image, true);
        }

        return $this->image;
    }

    /**
     * 获取可用字体列表
     *
     * 扫描字体目录下所有 .ttf 和 .otf 文件。
     *
     * @return array<string>  字体名称列表（不含扩展名）
     */
    public function getAvailableFonts(): array
    {
        if (! empty($this->availableFonts)) {
            return $this->availableFonts;
        }

        if (! is_dir($this->fontDir)) {
            return [];
        }

        $fonts = [];
        foreach (glob($this->fontDir . '/*.{ttf,otf}', GLOB_BRACE) as $file) {
            $fonts[] = pathinfo($file, PATHINFO_FILENAME);
        }
        $this->availableFonts = $fonts;

        return $fonts;
    }

    // ==================== 文字尺寸计算 ====================

    /**
     * 将文字按换行符拆分为多行数组
     *
     * 支持 \n、\r\n 和 <br> 标签作为换行符。
     *
     * @param  string  $text  原始文字
     * @return array<string>  分行后的数组
     */
    private function splitTextIntoLines(string $text): array
    {
        $text = str_replace(['\r\n', '\r', '<br>', '<br/>', '<br />'], "\n", $text);
        $lines = explode("\n", $text);

        return array_map('trim', $lines);
    }

    /**
     * 按画布宽度自动换行
     *
     * 根据当前字号和画布可用宽度，将长行文字自动折行。
     *
     * @param  string  $text  原始文字
     * @param  float   $size  字号
     * @param  int     $angle  旋转角度
     * @return array<string>  换行后的文字数组
     */
    private function wrapText(string $text, float $size, int $angle = 0): array
    {
        $lines = $this->splitTextIntoLines($text);
        $maxWidth = ($this->width - $this->padding * 2) * $this->scale;

        $result = [];
        foreach ($lines as $line) {
            if ($line === '') {
                $result[] = '';
                continue;
            }

            $bbox = imagettfbbox($size, $angle, $this->fontFile, $line);
            $lineWidth = abs($bbox[4] - $bbox[0]);

            if ($lineWidth <= $maxWidth) {
                $result[] = $line;
                continue;
            }

            // 使用二分查找 + 贪心策略快速确定换行点，减少 imagettfbbox 调用次数
            $remaining = $line;
            while ($remaining !== '') {
                $len = mb_strlen($remaining, 'UTF-8');
                if ($len === 0) {
                    break;
                }

                // 二分查找最长适合长度
                $low = 1;
                $high = $len;
                $best = 0;

                while ($low <= $high) {
                    $mid = (int) floor(($low + $high) / 2);
                    $sub = mb_substr($remaining, 0, $mid, 'UTF-8');
                    $subBbox = imagettfbbox($size, $angle, $this->fontFile, $sub);
                    $subWidth = abs($subBbox[4] - $subBbox[0]);

                    if ($subWidth <= $maxWidth) {
                        $best = $mid;
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                }

                if ($best === 0) {
                    // 即使一个字符也放不下，强制截断一个字符
                    $best = 1;
                }

                $result[] = mb_substr($remaining, 0, $best, 'UTF-8');
                $remaining = mb_substr($remaining, $best, null, 'UTF-8');
            }
        }

        return $result;
    }

    /**
     * 获取文字区域的宽度和高度信息
     *
     * 支持多行文字，返回整体包围盒信息。
     *
     * @param  array<string>  $lines  多行文字数组
     * @param  float          $size   字号
     * @param  int            $angle  旋转角度
     * @return array  包含 width、height、lineHeight、linesInfo 等键
     */
    private function getTextDimensions(array $lines, float $size, int $angle = 0): array
    {
        // 缓存命中检查：若输入参数与上次完全一致，直接返回缓存结果
        if (
            $this->textDimensionsCache !== null
            && $this->cachedSize === $size
            && $this->cachedAngle === $angle
            && $this->cachedLines === $lines
        ) {
            return $this->textDimensionsCache;
        }

        $lineHeightPx = $this->lineHeight > 0
            ? $this->lineHeight * $this->scale
            : $size * 1.5;

        $totalWidth  = 0;
        $totalHeight = 0;
        $linesInfo   = [];

        foreach ($lines as $index => $line) {
            if ($line === '') {
                $linesInfo[] = [
                    'text'   => '',
                    'width'  => 0,
                    'height' => $lineHeightPx,
                ];
                $totalHeight += $lineHeightPx;
                continue;
            }

            $bbox = imagettfbbox($size, $angle, $this->fontFile, $line);
            $minX = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
            $maxX = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
            $minY = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
            $maxY = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);

            $lineWidth  = abs($maxX - $minX);
            $lineHeight = abs($maxY - $minY);

            $linesInfo[] = [
                'text'   => $line,
                'width'  => $lineWidth,
                'height' => $lineHeight,
                'min_x'  => $minX,
                'min_y'  => $minY,
                'max_x'  => $maxX,
                'max_y'  => $maxY,
            ];

            $totalWidth = max($totalWidth, $lineWidth);
            $totalHeight += ($index === 0 ? $lineHeight : $lineHeightPx);
        }

        $result = [
            'width'      => $totalWidth,
            'height'     => $totalHeight,
            'lineHeight' => $lineHeightPx,
            'linesInfo'  => $linesInfo,
            'lineCount'  => count($lines),
        ];

        // 写入缓存
        $this->textDimensionsCache = $result;
        $this->cachedSize          = $size;
        $this->cachedAngle         = $angle;
        $this->cachedLines         = $lines;

        return $result;
    }

    /**
     * 自动计算最佳字号
     *
     * 根据画布尺寸、内边距和文字内容，通过二分查找法确定最佳字号。
     *
     * @param  array<string>  $lines  多行文字
     * @param  int            $angle  旋转角度
     * @return float  计算后的字号
     */
    private function calculateFontSize(array $lines, int $angle = 0): float
    {
        $availableWidth  = ($this->width - $this->padding * 2) * $this->scale;
        $availableHeight = ($this->height - $this->padding * 2) * $this->scale;

        $low  = $this->minSize;
        $high = $this->maxSize;
        $best = $low;

        for ($i = 0; $i < 20; $i++) {
            $mid = ($low + $high) / 2;
            $dims = $this->getTextDimensions($lines, $mid, $angle);

            if ($dims['width'] <= $availableWidth && $dims['height'] <= $availableHeight) {
                $best = $mid;
                $low  = $mid;
            } else {
                $high = $mid;
            }
        }

        return $best;
    }

    // ==================== 绘制方法 ====================

    /**
     * 绘制背景
     *
     * 根据优先级依次处理：背景图片 > 渐变背景 > 纯色背景。
     */
    private function drawBackground(): void
    {
        $w = (int) round($this->width * $this->scale);
        $h = (int) round($this->height * $this->scale);

        if ($this->backgroundImage !== null) {
            $this->drawBackgroundImage($w, $h);
            return;
        }

        if (! empty($this->gradientColors)) {
            $this->drawGradientBackground($w, $h);
            return;
        }

        $bgColor = imagecolorallocatealpha(
            $this->image,
            $this->backgroundColor[0],
            $this->backgroundColor[1],
            $this->backgroundColor[2],
            $this->bgAlpha
        );
        imagefilledrectangle($this->image, 0, 0, $w - 1, $h - 1, $bgColor);
    }

    /**
     * 绘制背景图片
     *
     * 将背景图片缩放并裁剪填充至整个画布。
     *
     * @param  int  $w  画布宽度
     * @param  int  $h  画布高度
     */
    private function drawBackgroundImage(int $w, int $h): void
    {
        $src = $this->loadImage($this->backgroundImage);
        if ($src === null) {
            return;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $scale = max($w / $srcW, $h / $srcH);
        $newW  = (int) round($srcW * $scale);
        $newH  = (int) round($srcH * $scale);
        $srcX  = (int) round(($newW - $w) / 2 / $scale);
        $srcY  = (int) round(($newH - $h) / 2 / $scale);

        imagecopyresampled(
            $this->image, $src, 0, 0, $srcX, $srcY,
            $w, $h, (int) round($w / $scale), (int) round($h / $scale)
        );
        imagedestroy($src);
    }

    /**
     * 绘制渐变背景
     *
     * 支持线性渐变和径向渐变。
     *
     * @param  int  $w  画布宽度
     * @param  int  $h  画布高度
     */
    private function drawGradientBackground(int $w, int $h): void
    {
        if ($this->gradientType === self::GRADIENT_RADIAL) {
            $this->drawRadialGradient($w, $h);
        } else {
            $this->drawLinearGradient($w, $h);
        }
    }

    /**
     * 绘制线性渐变背景
     *
     * 基于角度计算起点和终点，逐像素插值计算颜色。
     *
     * @param  int  $w  画布宽度
     * @param  int  $h  画布高度
     */
    private function drawLinearGradient(int $w, int $h): void
    {
        $angleRad = deg2rad($this->gradientAngle);
        $cos = cos($angleRad);
        $sin = sin($angleRad);
        $lineLen = abs($w * $cos) + abs($h * $sin);
        $centerX = $w / 2;
        $centerY = $h / 2;
        $colors = $this->gradientColors;
        $colorCount = count($colors);

        // 性能优化：使用分段矩形替代逐像素绘制，步长根据画布大小动态调整（2-4 像素）
        $step = max(2, (int) round(min($w, $h) / 200));

        for ($sx = 0; $sx < $w; $sx += $step) {
            for ($sy = 0; $sy < $h; $sy += $step) {
                // 取块中心计算颜色
                $cx = min($sx + $step / 2, $w - 1);
                $cy = min($sy + $step / 2, $h - 1);

                $proj = (($cx - $centerX) * $cos + ($cy - $centerY) * $sin) / ($lineLen / 2) + 0.5;
                $proj = max(0.0, min(1.0, $proj));

                $c1 = $colors[0];
                $c2 = $colors[$colorCount - 1];
                for ($i = 0; $i < $colorCount - 1; $i++) {
                    if ($proj >= $colors[$i]['position'] && $proj <= $colors[$i + 1]['position']) {
                        $c1 = $colors[$i];
                        $c2 = $colors[$i + 1];
                        break;
                    }
                }

                $range = $c2['position'] - $c1['position'];
                $t = $range <= 0 ? 0 : ($proj - $c1['position']) / $range;

                $r = (int) round($c1['color'][0] + ($c2['color'][0] - $c1['color'][0]) * $t);
                $g = (int) round($c1['color'][1] + ($c2['color'][1] - $c1['color'][1]) * $t);
                $b = (int) round($c1['color'][2] + ($c2['color'][2] - $c1['color'][2]) * $t);

                $color = imagecolorallocate($this->image, $r, $g, $b);
                imagefilledrectangle($this->image, $sx, $sy, min($sx + $step - 1, $w - 1), min($sy + $step - 1, $h - 1), $color);
            }
        }
    }

    /**
     * 绘制径向渐变背景
     *
     * 从中心向外辐射渐变。使用同心圆环替代逐像素绘制以提升性能。
     *
     * @param  int  $w  画布宽度
     * @param  int  $h  画布高度
     */
    private function drawRadialGradient(int $w, int $h): void
    {
        $centerX = $w / 2;
        $centerY = $h / 2;
        $maxDist = sqrt($centerX * $centerX + $centerY * $centerY);
        $colors = $this->gradientColors;
        $colorCount = count($colors);

        // 性能优化：使用同心圆环替代逐像素，步长根据画布大小动态调整
        $step = max(2, (int) round(min($w, $h) / 150));

        for ($r = 0; $r <= $maxDist + $step; $r += $step) {
            $proj = min(1.0, ($r + $step / 2) / $maxDist);

            $c1 = $colors[0];
            $c2 = $colors[$colorCount - 1];
            for ($i = 0; $i < $colorCount - 1; $i++) {
                if ($proj >= $colors[$i]['position'] && $proj <= $colors[$i + 1]['position']) {
                    $c1 = $colors[$i];
                    $c2 = $colors[$i + 1];
                    break;
                }
            }

            $range = $c2['position'] - $c1['position'];
            $t = $range <= 0 ? 0 : ($proj - $c1['position']) / $range;

            $red   = (int) round($c1['color'][0] + ($c2['color'][0] - $c1['color'][0]) * $t);
            $green = (int) round($c1['color'][1] + ($c2['color'][1] - $c1['color'][1]) * $t);
            $blue  = (int) round($c1['color'][2] + ($c2['color'][2] - $c1['color'][2]) * $t);

            $color = imagecolorallocate($this->image, $red, $green, $blue);
            $diameter = (int) round(($r + $step) * 2);
            imagefilledellipse($this->image, (int) $centerX, (int) $centerY, $diameter, $diameter, $color);
        }
    }

    /**
     * 绘制圆角矩形
     *
     * @param  int    $x       左上角 X
     * @param  int    $y       左上角 Y
     * @param  int    $w       宽度
     * @param  int    $h       高度
     * @param  int    $radius  圆角半径
     * @param  int    $color   GD 颜色索引
     * @param  bool   $filled  是否填充
     */
    private function drawRoundedRect(int $x, int $y, int $w, int $h, int $radius, int $color, bool $filled = true): void
    {
        $radius = min($radius, (int) floor($w / 2), (int) floor($h / 2));

        if ($filled) {
            imagefilledrectangle($this->image, $x + $radius, $y, $x + $w - $radius - 1, $y + $h - 1, $color);
            imagefilledrectangle($this->image, $x, $y + $radius, $x + $w - 1, $y + $h - $radius - 1, $color);
            imagefilledellipse($this->image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($this->image, $x + $w - $radius - 1, $y + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($this->image, $x + $radius, $y + $h - $radius - 1, $radius * 2, $radius * 2, $color);
            imagefilledellipse($this->image, $x + $w - $radius - 1, $y + $h - $radius - 1, $radius * 2, $radius * 2, $color);
        } else {
            imageline($this->image, $x + $radius, $y, $x + $w - $radius - 1, $y, $color);
            imageline($this->image, $x + $radius, $y + $h - 1, $x + $w - $radius - 1, $y + $h - 1, $color);
            imageline($this->image, $x, $y + $radius, $x, $y + $h - $radius - 1, $color);
            imageline($this->image, $x + $w - 1, $y + $radius, $x + $w - 1, $y + $h - $radius - 1, $color);
            imagearc($this->image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, 180, 270, $color);
            imagearc($this->image, $x + $w - $radius - 1, $y + $radius, $radius * 2, $radius * 2, 270, 360, $color);
            imagearc($this->image, $x + $radius, $y + $h - $radius - 1, $radius * 2, $radius * 2, 90, 180, $color);
            imagearc($this->image, $x + $w - $radius - 1, $y + $h - $radius - 1, $radius * 2, $radius * 2, 0, 90, $color);
        }
    }

    /**
     * 绘制边框
     *
     * 支持圆角边框，边框绘制在背景之上。
     */
    private function drawBorder(): void
    {
        if ($this->borderWidth <= 0) {
            return;
        }

        $w = (int) round($this->width * $this->scale);
        $h = (int) round($this->height * $this->scale);
        $borderW = (int) round($this->borderWidth * $this->scale);
        $radius  = (int) round($this->borderRadius * $this->scale);

        $borderColor = imagecolorallocate(
            $this->image,
            $this->borderColor[0],
            $this->borderColor[1],
            $this->borderColor[2]
        );

        $this->drawRoundedRect(0, 0, $w, $h, $radius, $borderColor, true);

        if ($w > $borderW * 2 && $h > $borderW * 2) {
            $innerColor = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
            $innerRadius = max(0, $radius - $borderW);
            $this->drawRoundedRect($borderW, $borderW, $w - $borderW * 2, $h - $borderW * 2, $innerRadius, $innerColor, true);
        }
    }

    /**
     * 绘制文字高亮背景
     *
     * @param  float  $x       文字基准 X
     * @param  float  $y       文字基准 Y
     * @param  float  $width   文字宽度
     * @param  float  $height  文字高度
     * @param  float  $size    字号
     * @param  int    $angle   旋转角度
     */
    private function drawTextHighlight(float $x, float $y, float $width, float $height, float $size, int $angle): void
    {
        if (empty($this->textHighlight)) {
            return;
        }

        $pad    = (int) round($this->textHighlightPadding * $this->scale);
        $radius = (int) round($this->textHighlightRadius * $this->scale);

        $hlColor = imagecolorallocatealpha(
            $this->image,
            $this->textHighlight[0],
            $this->textHighlight[1],
            $this->textHighlight[2],
            $this->textHighlightAlpha
        );

        $rectX = (int) round($x - $pad);
        $rectY = (int) round($y - $height - $pad);
        $rectW = (int) round($width + $pad * 2);
        $rectH = (int) round($height + $pad * 2);

        if ($angle === 0) {
            $this->drawRoundedRect($rectX, $rectY, $rectW, $rectH, $radius, $hlColor, true);
        } else {
            imagefilledrectangle($this->image, $rectX, $rectY, $rectX + $rectW, $rectY + $rectH, $hlColor);
        }
    }

    /**
     * 绘制文字阴影
     *
     * @param  string  $text   文字内容
     * @param  float   $size   字号
     * @param  int     $angle  旋转角度
     * @param  int     $x      基准 X
     * @param  int     $y      基准 Y
     */
    private function drawShadow(string $text, float $size, int $angle, int $x, int $y): void
    {
        $offsetX = (int) round($this->textShadow['offsetX'] * $this->scale);
        $offsetY = (int) round($this->textShadow['offsetY'] * $this->scale);
        $blur    = (int) round($this->textShadow['blur'] * $this->scale);

        $baseColor = $this->textShadow['color'];
        $baseAlpha = $this->textShadow['alpha'];

        // 无模糊时仅绘制一次偏移文字，避免进入循环
        if ($blur <= 1) {
            $c = imagecolorallocatealpha($this->image, $baseColor[0], $baseColor[1], $baseColor[2], $baseAlpha);
            imagettftext($this->image, $size, $angle, $x + $offsetX, $y + $offsetY, $c, $this->fontFile, $text);
            return;
        }

        // 性能优化：采用稀疏采样策略，将 imagettftext 调用次数从 O(n²) 降至 O((n/step)²)
        // 步长根据模糊半径动态计算，在视觉效果与渲染性能之间取得平衡
        $step = max(1, (int) round($blur / 3));
        if ($blur <= 3) {
            $step = 1; // 小半径保持最高质量
        }

        // 颜色缓存：按距离阶梯预分配颜色索引，避免在循环中重复调用 imagecolorallocatealpha
        $colorCache = [];

        for ($bx = -$blur; $bx <= $blur; $bx += $step) {
            for ($by = -$blur; $by <= $blur; $by += $step) {
                $dist = sqrt($bx * $bx + $by * $by);
                if ($dist > $blur) {
                    continue;
                }

                $distKey = (int) round($dist);
                if (! isset($colorCache[$distKey])) {
                    $alpha = (int) round($baseAlpha + (127 - $baseAlpha) * ($dist / ($blur + 1)));
                    $colorCache[$distKey] = imagecolorallocatealpha(
                        $this->image,
                        $baseColor[0],
                        $baseColor[1],
                        $baseColor[2],
                        min(127, $alpha)
                    );
                }

                imagettftext($this->image, $size, $angle, $x + $offsetX + $bx, $y + $offsetY + $by, $colorCache[$distKey], $this->fontFile, $text);
            }
        }
    }

    /**
     * 绘制文字描边
     *
     * @param  string  $text   文字内容
     * @param  float   $size   字号
     * @param  int     $angle  旋转角度
     * @param  int     $x      基准 X
     * @param  int     $y      基准 Y
     */
    private function drawStroke(string $text, float $size, int $angle, int $x, int $y): void
    {
        $strokeColor = imagecolorallocatealpha(
            $this->image,
            $this->textStroke['color'][0],
            $this->textStroke['color'][1],
            $this->textStroke['color'][2],
            $this->textStroke['alpha']
        );

        $width = (int) round($this->textStroke['width'] * $this->scale);

        // 性能优化：宽度为 1 时使用 8 方向采样；宽度大于 1 时仅在轮廓（外圈）绘制，
        // 将 imagettftext 调用次数从 O(w²) 降至 O(w)，大幅降低粗描边的渲染开销
        $directions = [
            [-1, -1], [0, -1], [1, -1],
            [-1,  0],          [1,  0],
            [-1,  1], [0,  1], [1,  1],
        ];

        if ($width === 1) {
            foreach ($directions as [$dx, $dy]) {
                imagettftext($this->image, $size, $angle, $x + $dx, $y + $dy, $strokeColor, $this->fontFile, $text);
            }
        } else {
            for ($w = 1; $w <= $width; $w++) {
                foreach ($directions as [$dx, $dy]) {
                    imagettftext($this->image, $size, $angle, $x + $dx * $w, $y + $dy * $w, $strokeColor, $this->fontFile, $text);
                }
            }
        }
    }

    /**
     * 绘制文字发光效果
     *
     * @param  string  $text   文字内容
     * @param  float   $size   字号
     * @param  int     $angle  旋转角度
     * @param  int     $x      基准 X
     * @param  int     $y      基准 Y
     */
    private function drawGlow(string $text, float $size, int $angle, int $x, int $y): void
    {
        $radius   = (int) round($this->textGlow['radius'] * $this->scale);
        $strength = $this->textGlow['strength'];

        // 性能优化：根据半径动态调整有效强度层数，减少冗余绘制
        $strengthEff = ($radius > 3) ? max(1, (int) round($strength / 2)) : $strength;

        // 稀疏采样步长：半径较大时使用步长跳跃，平衡质量与性能
        $step = ($radius > 3) ? max(1, (int) round($radius / 3)) : 1;

        for ($s = 0; $s < $strengthEff; $s++) {
            $ratio = ($s + 1) / $strengthEff;
            $alpha = (int) round($this->textGlow['alpha'] + (127 - $this->textGlow['alpha']) * $ratio);
            $c = imagecolorallocatealpha(
                $this->image,
                $this->textGlow['color'][0],
                $this->textGlow['color'][1],
                $this->textGlow['color'][2],
                min(127, $alpha)
            );

            $offset = (int) round($radius * $ratio);
            for ($dx = -$offset; $dx <= $offset; $dx += $step) {
                for ($dy = -$offset; $dy <= $offset; $dy += $step) {
                    $dist = sqrt($dx * $dx + $dy * $dy);
                    if ($dist > $offset) {
                        continue;
                    }
                    imagettftext($this->image, $size, $angle, $x + $dx, $y + $dy, $c, $this->fontFile, $text);
                }
            }
        }
    }

    /**
     * 绘制多行文字
     *
     * 支持阴影、描边、发光、高亮等特效，支持水平和垂直对齐。
     *
     * @param  array<string>  $lines  多行文字
     * @param  float          $size   字号
     * @param  int            $angle  旋转角度
     */
    private function drawTextLines(array $lines, float $size, int $angle = 0): void
    {
        $dims = $this->getTextDimensions($lines, $size, $angle);
        $w = (int) round($this->width * $this->scale);
        $h = (int) round($this->height * $this->scale);
        $pad = (int) round($this->padding * $this->scale);

        $blockY = match ($this->vAlign) {
            self::VALIGN_TOP    => $pad,
            self::VALIGN_BOTTOM => $h - $pad - $dims['height'],
            default             => ($h - $dims['height']) / 2,
        };

        $textColor = imagecolorallocatealpha(
            $this->image,
            $this->textColor[0],
            $this->textColor[1],
            $this->textColor[2],
            $this->textAlpha
        );

        foreach ($dims['linesInfo'] as $index => $lineInfo) {
            if ($lineInfo['text'] === '') {
                continue;
            }

            $lineX = match ($this->hAlign) {
                self::ALIGN_LEFT   => $pad,
                self::ALIGN_RIGHT  => $w - $pad - $lineInfo['width'],
                default            => ($w - $lineInfo['width']) / 2,
            };

            $lineY = $blockY;
            for ($j = 0; $j <= $index; $j++) {
                $lineY += ($j === 0 ? 0 : $dims['lineHeight']);
                if ($j === $index) {
                    $lineY += abs($lineInfo['min_y']);
                }
            }

            if (! empty($this->textHighlight)) {
                $this->drawTextHighlight($lineX, $lineY, $lineInfo['width'], $lineInfo['height'], $size, $angle);
            }
            if (! empty($this->textGlow)) {
                $this->drawGlow($lineInfo['text'], $size, $angle, (int) $lineX, (int) $lineY);
            }
            if (! empty($this->textShadow)) {
                $this->drawShadow($lineInfo['text'], $size, $angle, (int) $lineX, (int) $lineY);
            }
            if (! empty($this->textStroke)) {
                $this->drawStroke($lineInfo['text'], $size, $angle, (int) $lineX, (int) $lineY);
            }

            imagettftext($this->image, $size, $angle, (int) $lineX, (int) $lineY, $textColor, $this->fontFile, $lineInfo['text']);
        }
    }

    /**
     * 绘制水印
     *
     * 遍历所有已添加的水印并依次绘制。
     */
    private function drawWatermarks(): void
    {
        foreach ($this->watermarks as $watermark) {
            if ($watermark['type'] === 'image') {
                $this->drawImageWatermark($watermark);
            } else {
                $this->drawTextWatermark($watermark);
            }
        }
    }

    /**
     * 绘制图片水印
     *
     * @param  array  $config  水印配置
     */
    private function drawImageWatermark(array $config): void
    {
        $src = $this->loadImage($config['path']);
        if ($src === null) {
            return;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $cw = (int) round($this->width * $this->scale);
        $ch = (int) round($this->height * $this->scale);

        // 计算水印尺寸
        if ($config['scale'] !== null) {
            $targetW = (int) round($cw * $config['scale']);
            $targetH = (int) round($targetW * ($srcH / $srcW));
        } elseif ($config['width'] !== null || $config['height'] !== null) {
            $targetW = $config['width'] !== null ? (int) round($config['width'] * $this->scale) : (int) round(($config['height'] * $this->scale) * ($srcW / $srcH));
            $targetH = $config['height'] !== null ? (int) round($config['height'] * $this->scale) : (int) round(($config['width'] * $this->scale) * ($srcH / $srcW));
        } else {
            $targetW = $srcW;
            $targetH = $srcH;
        }

        $pos = $this->calculateWatermarkPosition($config['position'], $cw, $ch, $targetW, $targetH, $config['margin'], $config['x'], $config['y']);

        // 处理透明度
        if ($config['opacity'] < 100) {
            imagefilter($src, IMG_FILTER_COLORIZE, 0, 0, 0, (int) round(127 * (1 - $config['opacity'] / 100)));
        }

        imagecopyresampled($this->image, $src, $pos['x'], $pos['y'], 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($src);
    }

    /**
     * 绘制文字水印
     *
     * @param  array  $config  水印配置
     */
    private function drawTextWatermark(array $config): void
    {
        $cw = (int) round($this->width * $this->scale);
        $ch = (int) round($this->height * $this->scale);

        $bbox = imagettfbbox($config['size'], $config['angle'], $config['font'], $config['text']);
        $textW = abs($bbox[4] - $bbox[0]);
        $textH = abs($bbox[5] - $bbox[1]);

        $pos = $this->calculateWatermarkPosition($config['position'], $cw, $ch, $textW, $textH, $config['margin'], $config['x'], $config['y']);

        $color = imagecolorallocatealpha(
            $this->image,
            $config['color'][0],
            $config['color'][1],
            $config['color'][2],
            $config['alpha']
        );

        imagettftext(
            $this->image,
            $config['size'],
            $config['angle'],
            $pos['x'] + abs($bbox[0]),
            $pos['y'] + abs($bbox[5]),
            $color,
            $config['font'],
            $config['text']
        );
    }

    /**
     * 计算水印位置
     *
     * @param  string  $position  位置常量
     * @param  int     $cw        画布宽度
     * @param  int     $ch        画布高度
     * @param  int     $ww        水印宽度
     * @param  int     $wh        水印高度
     * @param  int     $margin    边距
     * @param  int     $offsetX   额外 X 偏移
     * @param  int     $offsetY   额外 Y 偏移
     * @return array{x: int, y: int}
     */
    private function calculateWatermarkPosition(string $position, int $cw, int $ch, int $ww, int $wh, int $margin, int $offsetX, int $offsetY): array
    {
        $m = (int) round($margin * $this->scale);

        $x = match ($position) {
            self::WATERMARK_TOP_LEFT, self::WATERMARK_CENTER_LEFT, self::WATERMARK_BOTTOM_LEFT => $m,
            self::WATERMARK_TOP_RIGHT, self::WATERMARK_CENTER_RIGHT, self::WATERMARK_BOTTOM_RIGHT => $cw - $ww - $m,
            default => (int) round(($cw - $ww) / 2),
        };

        $y = match ($position) {
            self::WATERMARK_TOP_LEFT, self::WATERMARK_TOP_CENTER, self::WATERMARK_TOP_RIGHT => $m,
            self::WATERMARK_BOTTOM_LEFT, self::WATERMARK_BOTTOM_CENTER, self::WATERMARK_BOTTOM_RIGHT => $ch - $wh - $m,
            default => (int) round(($ch - $wh) / 2),
        };

        return ['x' => $x + $offsetX, 'y' => $y + $offsetY];
    }

    /**
     * 加载外部图片为 GD 资源
     *
     * 支持 PNG、JPEG、GIF、WebP、BMP 格式。
     *
     * @param  string  $path  图片路径
     * @return GdImage|null
     */
    private function loadImage(string $path): ?GdImage
    {
        if (! is_file($path)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $image = match ($ext) {
            'png'  => imagecreatefrompng($path),
            'jpg', 'jpeg' => imagecreatefromjpeg($path),
            'gif'  => imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null,
            'bmp'  => function_exists('imagecreatefrombmp') ? imagecreatefrombmp($path) : null,
            default => null,
        };

        return $image instanceof GdImage ? $image : null;
    }

    // ==================== 滤镜与图像处理方法 ====================

    /**
     * 应用灰度滤镜
     *
     * 将整张图片转换为灰度图。
     *
     * @return $this
     */
    public function filterGrayscale(): static
    {
        if ($this->image !== null) {
            imagefilter($this->image, IMG_FILTER_GRAYSCALE);
        }

        return $this;
    }

    /**
     * 应用反色滤镜
     *
     * 将整张图片颜色反转。
     *
     * @return $this
     */
    public function filterNegate(): static
    {
        if ($this->image !== null) {
            imagefilter($this->image, IMG_FILTER_NEGATE);
        }

        return $this;
    }

    /**
     * 应用亮度调整
     *
     * @param  int  $level  亮度级别（-255 到 255，0 为不变）
     * @return $this
     */
    public function filterBrightness(int $level = 0): static
    {
        if ($this->image !== null) {
            imagefilter($this->image, IMG_FILTER_BRIGHTNESS, max(-255, min(255, $level)));
        }

        return $this;
    }

    /**
     * 应用对比度调整
     *
     * @param  int  $level  对比度级别（-100 到 100，0 为不变）
     * @return $this
     */
    public function filterContrast(int $level = 0): static
    {
        if ($this->image !== null) {
            imagefilter($this->image, IMG_FILTER_CONTRAST, max(-100, min(100, $level)));
        }

        return $this;
    }

    /**
     * 应用高斯模糊
     *
     * @param  int  $times  模糊次数（次数越多越模糊）
     * @return $this
     */
    public function filterBlur(int $times = 1): static
    {
        if ($this->image !== null) {
            for ($i = 0; $i < max(1, $times); $i++) {
                imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }

        return $this;
    }

    /**
     * 应用平滑处理
     *
     * @param  int  $level  平滑级别（> 0）
     * @return $this
     */
    public function filterSmooth(int $level = 1): static
    {
        if ($this->image !== null) {
            imagefilter($this->image, IMG_FILTER_SMOOTH, max(1, $level));
        }

        return $this;
    }

    /**
     * 应用像素化处理
     *
     * @param  int  $size  像素块大小
     * @return $this
     */
    public function filterPixelate(int $size = 10): static
    {
        if ($this->image !== null) {
            imagefilter($this->image, IMG_FILTER_PIXELATE, max(1, $size), true);
        }

        return $this;
    }

    /**
     * 应用颜色叠加滤镜
     *
     * @param  string|array  $color  叠加颜色
     * @param  int           $alpha  叠加透明度（0-127）
     * @return $this
     *
     * @throws Exception
     */
    public function filterColorize(string|array $color, int $alpha = 0): static
    {
        if ($this->image !== null) {
            $rgb = $this->parseColor($color);
            imagefilter($this->image, IMG_FILTER_COLORIZE, $rgb[0], $rgb[1], $rgb[2], $alpha);
        }

        return $this;
    }

    // ==================== 渲染与输出方法 ====================

    /**
     * 执行绘制流程
     *
     * 创建画布、绘制背景、边框、文字、水印等所有元素。
     * 此方法在 render 之前自动调用，也可手动调用以获取 GD 资源。
     *
     * @return $this
     *
     * @throws Exception
     */
    public function build(): static
    {
        if ($this->fontFile === '' || ! is_file($this->fontFile)) {
            throw new Exception('未设置有效的字体文件，请先调用 setFontFile() 或 setFontStyle()。');
        }

        // 预处理文字：统一换行符
        $text = str_replace(['\r\n', '\r', '<br>', '<br/>', '<br />'], "\n", $this->text);

        // 自动换行处理
        $tempSize = $this->size ?? $this->minSize;
        $lines = $this->autoWrap
            ? $this->wrapText($text, $tempSize, $this->angle)
            : $this->splitTextIntoLines($text);

        // 自动计算字号
        $fontSize = $this->size ?? $this->calculateFontSize($lines, $this->angle);

        // 如果开启了自适应尺寸，根据文字尺寸调整画布
        if ($this->autoSize) {
            $dims = $this->getTextDimensions($lines, $fontSize, $this->angle);
            $this->width  = (int) round($dims['width'] / $this->scale) + $this->padding * 2;
            $this->height = (int) round($dims['height'] / $this->scale) + $this->padding * 2;
        }

        // 创建画布
        $this->createCanvas();

        // 绘制背景
        $this->drawBackground();

        // 绘制边框
        $this->drawBorder();

        // 绘制文字
        $this->drawTextLines($lines, $fontSize, $this->angle);

        // 绘制水印
        $this->drawWatermarks();

        return $this;
    }

    /**
     * 渲染并输出图片
     *
     * 如果传入文件名则保存到本地，否则直接输出到浏览器（自动发送 Header）。
     *
     * @param  string|null  $fileName  保存路径，null 则输出到浏览器
     * @return $this
     *
     * @throws Exception
     */
    public function render(?string $fileName = null): static
    {
        $this->build();

        $format = strtolower($this->outputFormat);

        if ($fileName !== null) {
            $dir = dirname($fileName);
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new Exception('无法创建目录: ' . $dir);
            }

            $this->saveToFile($fileName, $format);
        } else {
            $this->outputToBrowser($format);
        }

        return $this;
    }

    /**
     * 保存图片到文件
     *
     * @param  string  $fileName  文件路径
     * @param  string  $format    图片格式
     *
     * @throws Exception
     */
    private function saveToFile(string $fileName, string $format): void
    {
        $success = match ($format) {
            self::FORMAT_PNG   => imagepng($this->image, $fileName),
            self::FORMAT_JPEG, self::FORMAT_JPG => imagejpeg($this->image, $fileName, $this->quality),
            self::FORMAT_GIF   => imagegif($this->image, $fileName),
            self::FORMAT_WEBP  => function_exists('imagewebp') ? imagewebp($this->image, $fileName, $this->quality) : false,
            self::FORMAT_BMP   => function_exists('imagebmp') ? imagebmp($this->image, $fileName) : false,
            default => false,
        };

        if (! $success) {
            throw new Exception('图片保存失败: ' . $fileName);
        }
    }

    /**
     * 输出图片到浏览器
     *
     * @param  string  $format  图片格式
     *
     * @throws Exception
     */
    private function outputToBrowser(string $format): void
    {
        $mime = match ($format) {
            self::FORMAT_PNG  => 'image/png',
            self::FORMAT_JPEG, self::FORMAT_JPG => 'image/jpeg',
            self::FORMAT_GIF  => 'image/gif',
            self::FORMAT_WEBP => 'image/webp',
            self::FORMAT_BMP  => 'image/bmp',
            default => 'image/png',
        };

        if (! headers_sent()) {
            header('Content-Type: ' . $mime);
        }

        $success = match ($format) {
            self::FORMAT_PNG  => imagepng($this->image),
            self::FORMAT_JPEG, self::FORMAT_JPG => imagejpeg($this->image, null, $this->quality),
            self::FORMAT_GIF  => imagegif($this->image),
            self::FORMAT_WEBP => function_exists('imagewebp') ? imagewebp($this->image, null, $this->quality) : false,
            self::FORMAT_BMP  => function_exists('imagebmp') ? imagebmp($this->image) : false,
            default => false,
        };

        if (! $success) {
            throw new Exception('图片输出失败，请检查 GD 扩展是否支持该格式。');
        }
    }

    /**
     * 获取当前 GD 图片资源
     *
     * 调用 build() 后可使用此方法获取原始 GD 资源进行二次处理。
     *
     * @return GdImage|null
     */
    public function getImage(): ?GdImage
    {
        return $this->image;
    }

    /**
     * 输出 Base64 编码的图片字符串
     *
     * 可直接用于 HTML img 标签的 src 属性。
     *
     * @param  bool   $includePrefix  是否包含 data:image/... 前缀
     * @return string
     *
     * @throws Exception
     */
    public function toBase64(bool $includePrefix = true): string
    {
        $this->build();

        ob_start();
        $format = strtolower($this->outputFormat);
        $success = match ($format) {
            self::FORMAT_PNG  => imagepng($this->image),
            self::FORMAT_JPEG, self::FORMAT_JPG => imagejpeg($this->image, null, $this->quality),
            self::FORMAT_GIF  => imagegif($this->image),
            self::FORMAT_WEBP => function_exists('imagewebp') ? imagewebp($this->image, null, $this->quality) : false,
            self::FORMAT_BMP  => function_exists('imagebmp') ? imagebmp($this->image) : false,
            default => false,
        };

        if (! $success) {
            ob_end_clean();
            throw new Exception('Base64 编码失败。');
        }

        $data = ob_get_clean();
        $base64 = base64_encode($data);

        if ($includePrefix) {
            $mime = match ($format) {
                self::FORMAT_PNG  => 'image/png',
                self::FORMAT_JPEG, self::FORMAT_JPG => 'image/jpeg',
                self::FORMAT_GIF  => 'image/gif',
                self::FORMAT_WEBP => 'image/webp',
                self::FORMAT_BMP  => 'image/bmp',
                default => 'image/png',
            };
            return 'data:' . $mime . ';base64,' . $base64;
        }

        return $base64;
    }

    /**
     * 获取图片二进制数据
     *
     * @return string
     *
     * @throws Exception
     */
    public function toBinary(): string
    {
        $this->build();

        ob_start();
        $format = strtolower($this->outputFormat);
        $success = match ($format) {
            self::FORMAT_PNG  => imagepng($this->image),
            self::FORMAT_JPEG, self::FORMAT_JPG => imagejpeg($this->image, null, $this->quality),
            self::FORMAT_GIF  => imagegif($this->image),
            self::FORMAT_WEBP => function_exists('imagewebp') ? imagewebp($this->image, null, $this->quality) : false,
            self::FORMAT_BMP  => function_exists('imagebmp') ? imagebmp($this->image) : false,
            default => false,
        };

        if (! $success) {
            ob_end_clean();
            throw new Exception('二进制数据获取失败。');
        }

        return ob_get_clean();
    }

    /**
     * 销毁图片资源并释放内存
     *
     * 建议在图片处理完成后主动调用，特别是在批量生成场景下。
     *
     * @return $this
     */
    public function destroy(): static
    {
        if ($this->image !== null) {
            imagedestroy($this->image);
            $this->image = null;
        }

        return $this;
    }

    /**
     * 析构函数
     *
     * 自动释放 GD 资源，防止内存泄漏。
     */
    public function __destruct()
    {
        $this->destroy();
    }

    // ==================== 高级图像处理方法 ====================

    /**
     * 旋转整张图片
     *
     * 在 build() 之后调用，可将已绘制的图片按指定角度旋转。
     *
     * @param  float  $angle       旋转角度（逆时针，0-360）
     * @param  string|array  $bgColor  旋转后空白区域的背景色
     * @return $this
     *
     * @throws Exception
     */
    public function rotateImage(float $angle = 0, string|array $bgColor = 'FFFFFF'): static
    {
        if ($this->image === null) {
            throw new Exception('请先调用 build() 或 render() 生成图片后再执行旋转。');
        }

        $rgb = $this->parseColor($bgColor);
        $bg = imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
        $rotated = imagerotate($this->image, $angle, $bg);

        if ($rotated !== false) {
            imagedestroy($this->image);
            $this->image = $rotated;
        }

        return $this;
    }

    /**
     * 裁剪图片
     *
     * 在 build() 之后调用，按指定区域裁剪图片。
     *
     * @param  int  $x  裁剪起始 X 坐标
     * @param  int  $y  裁剪起始 Y 坐标
     * @param  int  $w  裁剪宽度
     * @param  int  $h  裁剪高度
     * @return $this
     *
     * @throws Exception
     */
    public function cropImage(int $x, int $y, int $w, int $h): static
    {
        if ($this->image === null) {
            throw new Exception('请先调用 build() 或 render() 生成图片后再执行裁剪。');
        }

        $w = max(1, $w);
        $h = max(1, $h);
        $cropped = imagecreatetruecolor($w, $h);

        if (in_array($this->outputFormat, [self::FORMAT_PNG, self::FORMAT_WEBP, self::FORMAT_GIF], true)) {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
            imagefill($cropped, 0, 0, $transparent);
        }

        imagecopy($cropped, $this->image, 0, 0, $x, $y, $w, $h);
        imagedestroy($this->image);
        $this->image = $cropped;
        $this->width = $w;
        $this->height = $h;

        return $this;
    }

    /**
     * 缩放图片
     *
     * 在 build() 之后调用，按指定尺寸缩放图片。
     *
     * @param  int   $width   目标宽度
     * @param  int   $height  目标高度
     * @param  bool  $crop    是否裁剪填充（true 则保持比例裁剪，false 则拉伸）
     * @return $this
     *
     * @throws Exception
     */
    public function resizeImage(int $width, int $height, bool $crop = false): static
    {
        if ($this->image === null) {
            throw new Exception('请先调用 build() 或 render() 生成图片后再执行缩放。');
        }

        $srcW = imagesx($this->image);
        $srcH = imagesy($this->image);

        if ($crop) {
            $ratio = max($width / $srcW, $height / $srcH);
            $newW = (int) round($srcW * $ratio);
            $newH = (int) round($srcH * $ratio);
            $srcX = (int) round(($newW - $width) / 2 / $ratio);
            $srcY = (int) round(($newH - $height) / 2 / $ratio);
        } else {
            $newW = $width;
            $newH = $height;
            $srcX = 0;
            $srcY = 0;
        }

        $resized = imagecreatetruecolor($width, $height);

        if (in_array($this->outputFormat, [self::FORMAT_PNG, self::FORMAT_WEBP, self::FORMAT_GIF], true)) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        if ($crop) {
            imagecopyresampled($resized, $this->image, 0, 0, $srcX, $srcY, $width, $height, (int) round($width / ($width / $newW)), (int) round($height / ($height / $newH)));
        } else {
            imagecopyresampled($resized, $this->image, 0, 0, 0, 0, $width, $height, $srcW, $srcH);
        }

        imagedestroy($this->image);
        $this->image = $resized;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * 合并另一张图片到当前画布
     *
     * 在 build() 之后调用，将外部图片叠加到当前图片上。
     *
     * @param  string  $path      外部图片路径
     * @param  int     $x         叠加位置 X
     * @param  int     $y         叠加位置 Y
     * @param  int     $opacity   不透明度（0-100）
     * @param  int|null  $width   目标宽度，null 则保持原图宽度
     * @param  int|null  $height  目标高度，null 则保持原图高度
     * @return $this
     *
     * @throws Exception
     */
    public function mergeImage(string $path, int $x = 0, int $y = 0, int $opacity = 100, ?int $width = null, ?int $height = null): static
    {
        if ($this->image === null) {
            throw new Exception('请先调用 build() 或 render() 生成图片后再执行合并。');
        }

        $src = $this->loadImage($path);
        if ($src === null) {
            throw new Exception('无法加载图片: ' . $path);
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $targetW = $width ?? $srcW;
        $targetH = $height ?? ($width !== null ? (int) round($targetW * ($srcH / $srcW)) : $srcH);

        if ($opacity < 100) {
            imagefilter($src, IMG_FILTER_COLORIZE, 0, 0, 0, (int) round(127 * (1 - $opacity / 100)));
        }

        imagecopyresampled($this->image, $src, $x, $y, 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($src);

        return $this;
    }

    /**
     * 清除图片元数据（EXIF）
     *
     * 通过重新编码图片来去除所有元数据信息。
     * 此操作会丢失透明度信息（PNG 等格式），请谨慎使用。
     *
     * @return $this
     */
    public function stripMetadata(): static
    {
        if ($this->image === null) {
            return $this;
        }

        $w = imagesx($this->image);
        $h = imagesy($this->image);
        $clean = imagecreatetruecolor($w, $h);

        if (in_array($this->outputFormat, [self::FORMAT_PNG, self::FORMAT_WEBP, self::FORMAT_GIF], true)) {
            imagealphablending($clean, false);
            imagesavealpha($clean, true);
            $transparent = imagecolorallocatealpha($clean, 0, 0, 0, 127);
            imagefill($clean, 0, 0, $transparent);
        }

        imagecopy($clean, $this->image, 0, 0, 0, 0, $w, $h);
        imagedestroy($this->image);
        $this->image = $clean;

        return $this;
    }

}

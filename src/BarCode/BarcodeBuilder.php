<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode;

use zxf\Utils\BarCode\Contracts\BarcodeGeneratorInterface;
use zxf\Utils\BarCode\DTO\BarcodeConfig;
use zxf\Utils\BarCode\Renderer\BaseRenderer;
use zxf\Utils\BarCode\Renderer\PngRenderer;
use zxf\Utils\BarCode\Renderer\SvgRenderer;
use zxf\Utils\BarCode\Renderer\HtmlRenderer;

/**
 * 条形码构建器（增强版）
 *
 * 提供流畅的链式 API 用于生成条形码，统一支持 EAN-13、Code 128 等全部条码类型，
 * 并可在渲染阶段自定义尺寸、颜色、水印、渐变、圆角、Bearer Bar 等效果。
 *
 * 功能特性：
 * - 支持所有条码类型
 * - 支持自定义尺寸、颜色
 * - 支持直接输出到浏览器
 * - 支持跳过校验位验证
 * - 支持设置整体宽度和高度
 *
 * @package zxf\Utils\BarCode
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * ```php
 * // 生成一个 EAN-13 条码并保存为 PNG
 * use zxf\Utils\BarCode\BarcodeBuilder;
 *
 * BarcodeBuilder::create()
 *     ->type('ean13')
 *     ->data('690123456789')
 *     ->width(300)
 *     ->height(100)
 *     ->showText(true)
 *     ->savePng('/tmp/ean13.png');
 * ```
 */
class BarcodeBuilder
{
    /** @var BarcodeGeneratorInterface|null 条码生成器 */
    protected ?BarcodeGeneratorInterface $generator = null;

    /** @var BarcodeConfig 配置对象 */
    protected BarcodeConfig $config;

    /** @var string 条码类型 */
    protected string $type = '';

    /** @var string 条码数据 */
    protected string $data = '';

    /** @var array<int>|null 生成的条码数据 */
    protected ?array $barcodeData = null;

    /** @var int 条码内容总宽度（像素） */
    protected int $contentWidth = 0;

    /** @var int 整体宽度（最终图片宽度） */
    protected int $totalWidth = 0;

    /** @var int 整体高度（最终图片高度） */
    protected int $totalHeight = 0;

    /** @var bool 是否跳过校验位验证 */
    protected bool $skipChecksum = false;

    /** @var string|null 水印文本 */
    protected ?string $watermarkText = null;

    /** @var int 水印透明度（0-100） */
    protected int $watermarkOpacity = 50;

    /** @var int 水印字号（1-5对应GD内置字体，≥8对应TTF字号） */
    protected int $watermarkFontSize = 16;

    /** @var string 水印颜色（十六进制） */
    protected string $watermarkColor = '#CCCCCC';

    /** @var string 水印位置 */
    protected string $watermarkPosition = 'center';

    /** @var int 水印旋转角度（-180到180度） */
    protected int $watermarkAngle = 0;

    /** @var string|null 水印TTF字体路径 */
    protected ?string $watermarkFontPath = null;

    /** @var bool 是否启用Bearer Bar（ITF-14上下边框） */
    protected bool $bearerBarEnabled = false;

    /** @var int Bearer Bar宽度（像素） */
    protected int $bearerBarWidth = 2;

    /** @var bool 是否使用透明背景（仅SVG有效） */
    protected bool $transparentBackground = false;

    /** @var string 文本对齐方式（left/center/right） */
    protected string $textAlign = 'center';

    /** @var bool 是否启用圆角条 */
    protected bool $roundedBarsEnabled = false;

    /** @var int 圆角半径（像素） */
    protected int $roundedBarsRadius = 2;

    /** @var bool 是否启用渐变 */
    protected bool $gradientEnabled = false;

    /** @var string 渐变起始颜色 */
    protected string $gradientStartColor = '#000000';

    /** @var string 渐变结束颜色 */
    protected string $gradientEndColor = '#444444';

    /** @var int 边框宽度（像素），0 表示不显示边框 */
    protected int $borderWidth = 0;

    /** @var string 边框颜色（十六进制） */
    protected string $borderColor = '#000000';

    /**
     * 构造函数
     *
     * 初始化一个使用默认配置的构建器实例。
     */
    public function __construct()
    {
        $this->config = new BarcodeConfig();
    }

    /**
     * 静态创建方法
     *
     * 以静态方式获取构建器实例，等价于 new self()，便于书写链式调用。
     * 每次调用都返回全新实例，实例之间不共享任何配置。
     * 
     * @return self 返回构建器实例
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * 设置条码类型
     *
     * 根据类型名从工厂创建对应生成器并绑定当前配置；若此前已调用
     * skipChecksum(true)，会把跳过校验的开关同步给新生成器。
     * 重复调用会丢弃旧生成器，之前通过 type() 传入的类型名也随之覆盖。
     * 
     * @param string $type 条码类型（ean13, code128等）
     * @return self 支持链式调用
     */
    public function type(string $type): self
    {
        $this->type = $type;
        $this->generator = BarcodeFactory::create($type, $this->config);
        
        // 设置是否跳过校验位验证
        if ($this->skipChecksum && method_exists($this->generator, 'setSkipChecksumValidation')) {
            $this->generator->setSkipChecksumValidation(true);
        }
        
        return $this;
    }

    /**
     * 设置数据
     *
     * 只记录待编码的原始字符串，此处不做格式检查、不补校验位，真正的
     * 校验发生在 generate() 阶段。重复调用会覆盖上一次的数据。
     * 
     * @param string $data 要编码的数据
     * @return self 支持链式调用
     */
    public function data(string $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 设置配置
     *
     * 将传入数组与当前配置做浅合并（同名键覆盖、未传的键保持原值），
     * 重建配置对象；若生成器已创建则同步刷新其实例配置。
     * 
     * @param array $config 配置数组
     * @return self 支持链式调用
     */
    public function config(array $config): self
    {
        $this->config = BarcodeConfig::fromArray(array_merge($this->config->toArray(), $config));
        
        if ($this->generator !== null) {
            $this->generator->setConfig($this->config);
        }
        
        return $this;
    }

    /**
     * 设置条码内容总宽度
     *
     * 设置后渲染器会根据条码数据的总模块数自动计算单个模块的宽度。
     *
     * @param int $width 条码内容总宽度（像素，不含边距）
     * @return self 支持链式调用
     */
    public function width(int $width): self
    {
        $this->contentWidth = max(0, $width);
        return $this;
    }

    /**
     * 设置条码高度
     *
     * 高度指条与下方文字所占区域的高度（像素），不含上下边距；
     * 最终写入配置项 height，实际绘制高度还会受整体高度约束。
     * 
     * @param int $height 条码高度（像素）
     * @return self 支持链式调用
     */
    public function height(int $height): self
    {
        return $this->config(['height' => $height]);
    }

    /**
     * 设置整体宽度
     *
     * 指最终图片的宽度（像素，含左右边距）。渲染时会先扣除边距，再把
     * 剩余宽度按模块总数均分得到单模块宽度；与 width() 同时设置时以
     * width() 为准。
     * 
     * @param int $width 整体宽度（像素）
     * @return self 支持链式调用
     */
    public function totalWidth(int $width): self
    {
        $this->totalWidth = $width;
        return $this;
    }

    /**
     * 设置整体高度
     *
     * 指最终图片的高度（像素，含上下边距）。渲染时会扣除边距、文字高度
     * 与 Bearer Bar 占位后得到条码区高度，结果不小于 20 像素。
     * 
     * @param int $height 整体高度（像素）
     * @return self 支持链式调用
     */
    public function totalHeight(int $height): self
    {
        $this->totalHeight = $height;
        return $this;
    }

    /**
     * 设置是否显示文字
     * 
     * @param bool $show 是否显示
     * @return self 支持链式调用
     */
    public function showText(bool $show = true): self
    {
        return $this->config(['showText' => $show]);
    }

    /**
     * 设置背景颜色
     *
     * 参数为十六进制颜色值（如 #FFFFFF），原样写入配置项 bgColor；
     * 取值合法性由渲染器在解析颜色时判断。
     * 
     * @param string $color 十六进制颜色值
     * @return self 支持链式调用
     */
    public function bgColor(string $color): self
    {
        return $this->config(['bgColor' => $color]);
    }

    /**
     * 设置条颜色
     *
     * 参数为十六进制颜色值（如 #000000），原样写入配置项 barColor；
     * 与背景色的对比度是否足够由渲染器在渲染前校验。
     * 
     * @param string $color 十六进制颜色值
     * @return self 支持链式调用
     */
    public function barColor(string $color): self
    {
        return $this->config(['barColor' => $color]);
    }

    /**
     * 设置是否跳过校验位验证
     * 
     * @param bool $skip 是否跳过
     * @return self 支持链式调用
     */
    public function skipChecksum(bool $skip = true): self
    {
        $this->skipChecksum = $skip;
        
        if ($this->generator !== null && method_exists($this->generator, 'setSkipChecksumValidation')) {
            $this->generator->setSkipChecksumValidation($skip);
        }
        
        return $this;
    }

    /**
     * 设置水印
     *
     * 仅记录水印文本与样式，渲染阶段由 configureRenderer() 统一下发给渲染器。
     * 透明度被夹紧到 0-100，字号不小于 1，旋转角度限制在 -180 到 180 度之间。
     *
     * @param string $text 水印文本
     * @param int $opacity 透明度（0-100，值越大越清晰）
     * @param int $fontSize 字号（GD内置1-5，TTF≥8）
     * @param string $color 颜色（十六进制）
     * @param int $angle 旋转角度（-180到180度，0为不旋转）
     * @return self 支持链式调用
     */
    public function watermark(
        string $text,
        int $opacity = 50,
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
     *
     * 位置名不在允许列表内时静默回退为 center，不会抛出异常。
     *
     * @param string $position 位置（center/top/bottom/left/right/top-left/top-right/bottom-left/bottom-right）
     * @return self 支持链式调用
     */
    public function watermarkPosition(string $position): self
    {
        $valid = ['center', 'top', 'bottom', 'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right'];
        $this->watermarkPosition = in_array($position, $valid, true) ? $position : 'center';
        return $this;
    }

    /**
     * 设置水印字体路径（TTF字体）
     *
     * @param string|null $fontPath TTF字体文件路径
     * @return self 支持链式调用
     */
    public function watermarkFontPath(?string $fontPath): self
    {
        $this->watermarkFontPath = ($fontPath !== null && file_exists($fontPath)) ? $fontPath : null;
        return $this;
    }

    /**
     * 启用Bearer Bar（ITF-14上下边框）
     *
     * @param int $width 边框厚度（像素）
     * @return self 支持链式调用
     */
    public function bearerBar(int $width = 2): self
    {
        $this->bearerBarEnabled = true;
        $this->bearerBarWidth = max(1, $width);
        return $this;
    }

    /**
     * 设置透明背景（仅SVG有效）
     *
     * @param bool $transparent 是否透明
     * @return self 支持链式调用
     */
    public function transparentBackground(bool $transparent = true): self
    {
        $this->transparentBackground = $transparent;
        return $this;
    }

    /**
     * 设置文本对齐方式
     *
     * @param string $align 对齐方式（left/center/right）
     * @return self 支持链式调用
     */
    public function textAlign(string $align): self
    {
        $this->textAlign = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
        return $this;
    }

    /**
     * 启用圆角条
     *
     * 开启后条的两端按给定半径绘制为圆角，半径小于 0 时按 0 处理。
     * 该效果需要渲染器支持，不支持时自动忽略。
     *
     * @param int $radius 圆角半径（像素）
     * @return self 支持链式调用
     */
    public function roundedBars(int $radius): self
    {
        $this->roundedBarsEnabled = true;
        $this->roundedBarsRadius = max(0, $radius);
        return $this;
    }

    /**
     * 启用渐变效果
     *
     * 开启后条色由起始色向结束色做线性渐变，实际渲染时覆盖 barColor；
     * 该效果需要渲染器支持，不支持时自动退化为纯色。
     *
     * @param string $startColor 起始颜色
     * @param string $endColor 结束颜色
     * @return self 支持链式调用
     */
    public function gradient(string $startColor, string $endColor): self
    {
        $this->gradientEnabled = true;
        $this->gradientStartColor = $startColor;
        $this->gradientEndColor = $endColor;
        return $this;
    }

    /**
     * 设置字体大小
     *
     * 用于条码下方的可读文字，写入配置项 fontSize；小于 8 时按 8 处理，
     * 以保证文字仍可辨识。
     *
     * @param int $size 字体大小（像素）
     * @return self
     */
    public function fontSize(int $size): self
    {
        return $this->config(['fontSize' => max(8, $size)]);
    }

    /**
     * 设置字体路径（TTF字体）
     *
     * @param string $path TTF字体文件路径
     * @return self
     */
    public function fontPath(string $path): self
    {
        return $this->config(['fontPath' => $path]);
    }

    /**
     * 设置边距
     *
     * 支持1到4个参数，逻辑与CSS margin一致：
     * - margin(10)         四边均为10
     * - margin(10, 20)     上下10，左右20
     * - margin(10, 20, 30) 上10，左右20，下30
     * - margin(10, 20, 30, 40) 上10，右20，下30，左40
     *
     * @param int $top 上边距（像素），必填
     * @param int|null $right 右边距（像素），省略时取 $top
     * @param int|null $bottom 下边距（像素），省略时取 $top
     * @param int|null $left 左边距（像素），省略时取 $right，$right 也为空则取 $top
     * @return self
     */
    public function margin(int $top, ?int $right = null, ?int $bottom = null, ?int $left = null): self
    {
        $cfg = ['marginTop' => $top];
        $cfg['marginRight'] = $right ?? $top;
        $cfg['marginBottom'] = $bottom ?? $top;
        $cfg['marginLeft'] = $left ?? ($right ?? $top);
        return $this->config($cfg);
    }

    /**
     * 设置静区
     *
     * 静区是条码左右两侧的空白区，用于保证识读设备能正确定位起止符。
     * 宽度以模块数的倍数表示，小于 0 时按 0 处理。
     *
     * @param bool $show 是否显示静区
     * @param int $width 静区宽度（模块数量的倍数）
     * @return self
     */
    public function quietZone(bool $show = true, int $width = 10): self
    {
        return $this->config(['showQuietZone' => $show, 'quietZoneWidth' => max(0, $width)]);
    }

    /**
     * 设置长竖线高度比例
     *
     * @param float $ratio 长竖线相对于普通条的高度比例
     * @return self
     */
    public function longBarRatio(float $ratio): self
    {
        return $this->config(['longBarRatio' => max(1.0, $ratio)]);
    }

    /**
     * 设置边框
     *
     * 边框沿图片外沿绘制，宽度为 0 或负数时不绘制边框。
     *
     * @param int $width 边框宽度（像素），0表示无边框
     * @param string $color 边框颜色（十六进制）
     * @return self
     */
    public function border(int $width, string $color = '#000000'): self
    {
        $this->borderWidth = max(0, $width);
        $this->borderColor = $color;
        return $this;
    }

    /**
     * 生成条码数据
     *
     * 先校验已设置条码类型与数据，再调用对应生成器得到条空模式数组。
     *
     * @return array<int> 条空模式数组（正数为条、负数为空）
     * @throws \RuntimeException 当未设置条码类型或数据为空时抛出
     */
    public function generate(): array
    {
        if ($this->generator === null) {
            throw new \RuntimeException('必须先设置条码类型');
        }

        if (empty($this->data)) {
            throw new \RuntimeException('数据不能为空');
        }

        $this->barcodeData = $this->generator->generate($this->data);
        return $this->barcodeData;
    }

    /**
     * 渲染为PNG
     *
     * 尚未生成条码数据时先自动调用 generate()；随后依据 width()/totalWidth()/
     * totalHeight() 计算缩放后的配置，并把水印、渐变、圆角等样式下发给
     * PngRenderer。依赖 GD 扩展。
     *
     * @return string PNG二进制数据
     */
    public function toPng(): string
    {
        if ($this->barcodeData === null) {
            $this->generate();
        }

        $config = $this->resolveScaledConfig();
        $renderer = new PngRenderer($config);
        $this->configureRenderer($renderer);

        return $renderer->render($this->barcodeData, $this->getFullData(), []);
    }

    /**
     * 渲染为SVG
     *
     * 尚未生成条码数据时先自动调用 generate()；若开启 transparentBackground()，
     * 会把背景色改写为 transparent。SVG 为纯文本，可直接嵌入页面或写入文件。
     *
     * @return string SVG XML字符串
     */
    public function toSvg(): string
    {
        if ($this->barcodeData === null) {
            $this->generate();
        }

        $config = $this->resolveScaledConfig();
        if ($this->transparentBackground) {
            $configArray = $config->toArray();
            $configArray['bgColor'] = 'transparent';
            $config = BarcodeConfig::fromArray($configArray);
        }
        $renderer = new SvgRenderer($config);
        $this->configureRenderer($renderer);

        return $renderer->render($this->barcodeData, $this->getFullData(), []);
    }

    /**
     * 直接输出PNG到浏览器
     *
     * 以 image/png 内容类型将条码 PNG 二进制数据输出到标准输出。
     *
     * @return void
     */
    public function outputPng(): void
    {
        if ($this->barcodeData === null) {
            $this->generate();
        }

        $config = $this->resolveScaledConfig();
        $renderer = new PngRenderer($config);
        $this->configureRenderer($renderer);

        $renderer->outputToBrowser($this->barcodeData, $this->getFullData(), []);
    }

    /**
     * 直接输出SVG到浏览器
     *
     * 以 image/svg+xml 内容类型将条码 SVG 字符串输出到标准输出。
     *
     * @return void
     */
    public function outputSvg(): void
    {
        if ($this->barcodeData === null) {
            $this->generate();
        }

        $config = $this->resolveScaledConfig();
        if ($this->transparentBackground) {
            $configArray = $config->toArray();
            $configArray['bgColor'] = 'transparent';
            $config = BarcodeConfig::fromArray($configArray);
        }
        $renderer = new SvgRenderer($config);
        $this->configureRenderer($renderer);

        $svgData = $renderer->render($this->barcodeData, $this->getFullData(), []);

        header('Content-Type: image/svg+xml');
        header('Content-Length: ' . strlen($svgData));
        header('Cache-Control: no-cache, must-revalidate');

        echo $svgData;
    }

    /**
     * 获取Base64编码的条码
     *
     * @param string $format 格式（png 或 svg）
     * @return string Base64数据URI
     */
    public function toBase64(string $format = 'png'): string
    {
        $format = strtolower($format);
        $content = match ($format) {
            'svg' => $this->toSvg(),
            'html' => $this->toHtml(),
            default => $this->toPng(),
        };
        $mimeType = match ($format) {
            'svg' => 'image/svg+xml',
            'html' => 'text/html',
            default => 'image/png',
        };

        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }

    /**
     * 保存为PNG文件
     * 
     * @param string $filename 文件路径
     * @return bool 保存成功返回true
     */
    public function savePng(string $filename): bool
    {
        $pngData = $this->toPng();
        
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($filename, $pngData) !== false;
    }

    /**
     * 保存为SVG文件
     * 
     * @param string $filename 文件路径
     * @return bool 保存成功返回true
     */
    public function saveSvg(string $filename): bool
    {
        $svgData = $this->toSvg();
        
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($filename, $svgData) !== false;
    }

    /**
     * 渲染为HTML
     *
     * 尚未生成条码数据时先自动调用 generate()；输出为纯 HTML 片段，
     * 不含 html/body 等外层标签，可直接嵌入现有页面。
     *
     * @return string HTML字符串
     */
    public function toHtml(): string
    {
        if ($this->barcodeData === null) {
            $this->generate();
        }

        $config = $this->resolveScaledConfig();
        $renderer = new HtmlRenderer($config);
        $this->configureRenderer($renderer);

        return $renderer->render($this->barcodeData, $this->getFullData(), []);
    }

    /**
     * 保存为HTML文件
     *
     * @param string $filename 文件路径
     * @return bool 保存成功返回true
     */
    public function saveHtml(string $filename): bool
    {
        $htmlData = $this->toHtml();
        
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($filename, $htmlData) !== false;
    }

    /**
     * 直接输出HTML到浏览器
     *
     * 以 text/html 内容类型将条码 HTML 字符串输出到标准输出。
     *
     * @return void
     */
    public function outputHtml(): void
    {
        if ($this->barcodeData === null) {
            $this->generate();
        }

        $config = $this->resolveScaledConfig();
        $renderer = new HtmlRenderer($config);
        $this->configureRenderer($renderer);

        $renderer->outputToBrowser($this->barcodeData, $this->getFullData(), []);
    }

    /**
     * 获取完整数据（含校验位）
     * 
     * @return string 完整数据
     */
    public function getFullData(): string
    {
        if ($this->generator === null) {
            return $this->data;
        }

        // 惰性生成：生成器（含补全校验位/ISBN-10→13 等转换）的完整数据
        // 只有在 generate() 之后才可用。调用方若只问「完整数据」而不渲染，
        // 此处自动触发一次生成，避免返回空串。
        if ($this->barcodeData === null && $this->data !== '') {
            $this->generate();
        }

        if (method_exists($this->generator, 'getFullData')) {
            return $this->generator->getFullData();
        }

        return $this->data;
    }

    /**
     * 获取校验位
     *
     * 由当前生成器基于已设置的 data 计算并返回校验位字符，不修改构建器状态，
     * 也不会把校验位回写到数据中。未设置条码类型时抛异常。
     * 
     * @return string 校验位
     */
    public function getChecksum(): string
    {
        if ($this->generator === null) {
            throw new \RuntimeException('必须先设置条码类型');
        }

        return $this->generator->calculateChecksum($this->data);
    }

    /**
     * 获取生成器实例
     *
     * 未调用 type() 前返回 null，可用返回值判断是否已选定条码类型。
     * 
     * @return BarcodeGeneratorInterface|null 生成器实例
     */
    public function getGenerator(): ?BarcodeGeneratorInterface
    {
        return $this->generator;
    }

    /**
     * 统一配置渲染器
     *
     * 把构建器上累积的样式一次性写入渲染器：条码类型与长条位置来自生成器，
     * 其余为对齐方式、渐变、圆角、水印及其位置/字体、Bearer Bar、边框。
     * 未开启的选项不下发，保持渲染器自身默认值。
     *
     * @param BaseRenderer $renderer 渲染器实例
     * @return void
     */
    protected function configureRenderer(BaseRenderer $renderer): void
    {
        if ($this->generator !== null) {
            $renderer->setBarcodeType($this->generator->getType());
            if (method_exists($this->generator, 'getLongBarPositions')) {
                $renderer->setLongBarPositions($this->generator->getLongBarPositions());
            }
        }

        $renderer->setTextAlign($this->textAlign);

        if ($this->gradientEnabled) {
            $renderer->enableGradient($this->gradientStartColor, $this->gradientEndColor);
        }

        if ($this->roundedBarsEnabled) {
            $renderer->enableRoundedBars($this->roundedBarsRadius);
        }

        if ($this->watermarkText !== null) {
            $renderer->setWatermark(
                $this->watermarkText,
                $this->watermarkOpacity,
                $this->watermarkFontSize,
                $this->watermarkColor,
                $this->watermarkAngle
            );
            if (method_exists($renderer, 'setWatermarkPosition')) {
                $renderer->setWatermarkPosition($this->watermarkPosition);
            }
            if (method_exists($renderer, 'setWatermarkFontPath')) {
                $renderer->setWatermarkFontPath($this->watermarkFontPath);
            }
        }

        if ($this->bearerBarEnabled) {
            $renderer->enableBearerBar($this->bearerBarWidth);
        }

        if ($this->borderWidth > 0) {
            $renderer->setBorder($this->borderWidth, $this->borderColor);
        }
    }

    /**
     * 根据 contentWidth/totalWidth/totalHeight 自动计算缩放后的配置
     *
     * @return BarcodeConfig 调整后的配置
     */
    protected function resolveScaledConfig(): BarcodeConfig
    {
        $configArray = $this->config->toArray();

        if ($this->barcodeData !== null) {
            $totalModules = 0;
            foreach ($this->barcodeData as $element) {
                $totalModules += abs($element);
            }

            if ($totalModules > 0) {
                $moduleSize = 0;

                if ($this->contentWidth > 0) {
                    $moduleSize = (int) floor($this->contentWidth / $totalModules);
                    $configArray['width'] = $this->contentWidth;
                } elseif ($this->totalWidth > 0) {
                    $availableWidth = $this->totalWidth - $this->config->marginLeft - $this->config->marginRight;
                    $moduleSize = (int) floor($availableWidth / $totalModules);
                    $configArray['width'] = max(1, $availableWidth);
                }

                if ($moduleSize > 0) {
                    $configArray['moduleSize'] = max(1, $moduleSize);
                }
            }
        }

        if ($this->totalHeight > 0) {
            $textHeight = $this->config->showText ? ($this->config->fontSize + $this->config->textOffset) : 0;
            $bearerBarHeight = $this->bearerBarEnabled ? ($this->bearerBarWidth * 2) : 0;
            $availableHeight = $this->totalHeight - $this->config->marginTop - $this->config->marginBottom - $textHeight - $bearerBarHeight;
            $configArray['height'] = max(20, $availableHeight);
        }

        return BarcodeConfig::fromArray($configArray);
    }
}

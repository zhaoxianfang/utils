<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode;

use zxf\Utils\BarCode\Contracts\BarcodeGeneratorInterface;
use zxf\Utils\BarCode\DTO\BarcodeConfig;
use zxf\Utils\BarCode\Renderer\PngRenderer;
use zxf\Utils\BarCode\Renderer\SvgRenderer;

/**
 * 条形码构建器（增强版）
 * 
 * 提供流畅的API用于生成条形码
 * 支持链式调用配置和生成
 * 
 * 功能特性：
 * - 支持所有条码类型
 * - 支持自定义尺寸、颜色
 * - 支持直接输出到浏览器
 * - 支持跳过校验位验证
 * - 支持设置整体宽度和高度
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

    /** @var int 整体宽度 */
    protected int $totalWidth = 0;

    /** @var int 整体高度 */
    protected int $totalHeight = 0;

    /** @var bool 是否跳过校验位验证 */
    protected bool $skipChecksum = false;

    public function __construct()
    {
        $this->config = new BarcodeConfig();
    }

    /**
     * 静态创建方法
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
     * @param string $type 条码类型（ean13, isbn, code128等）
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
     * 设置条宽度
     * 
     * @param int $width 条宽度（像素）
     * @return self 支持链式调用
     */
    public function width(int $width): self
    {
        return $this->config(['width' => $width]);
    }

    /**
     * 设置条码高度
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
     * 生成条码数据
     * 
     * @return array<int> 条空模式数组
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
     * @return string PNG二进制数据
     */
    public function toPng(): string
    {
        if ($this->barcodeData === null) {
            $this->generate();
        }

        $renderer = new PngRenderer($this->config);
        
        if ($this->generator !== null) {
            $renderer->setBarcodeType($this->generator->getType());
        }

        return $renderer->render($this->barcodeData, $this->getFullData(), []);
    }

    /**
     * 渲染为SVG
     * 
     * @return string SVG XML字符串
     */
    public function toSvg(): string
    {
        if ($this->barcodeData === null) {
            $this->generate();
        }

        $renderer = new SvgRenderer($this->config);
        
        if ($this->generator !== null) {
            $renderer->setBarcodeType($this->generator->getType());
        }

        return $renderer->render($this->barcodeData, $this->getFullData(), []);
    }

    /**
     * 直接输出PNG到浏览器
     */
    public function outputPng(): void
    {
        if ($this->barcodeData === null) {
            $this->generate();
        }

        $renderer = new PngRenderer($this->config);
        
        if ($this->generator !== null) {
            $renderer->setBarcodeType($this->generator->getType());
        }

        $renderer->outputToBrowser($this->barcodeData, $this->getFullData(), []);
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
     * 获取完整数据（含校验位）
     * 
     * @return string 完整数据
     */
    public function getFullData(): string
    {
        if ($this->generator === null) {
            return $this->data;
        }

        if (method_exists($this->generator, 'getFullData')) {
            return $this->generator->getFullData();
        }

        return $this->data;
    }

    /**
     * 获取校验位
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
     * @return BarcodeGeneratorInterface|null 生成器实例
     */
    public function getGenerator(): ?BarcodeGeneratorInterface
    {
        return $this->generator;
    }
}

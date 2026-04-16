<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\DTO;

/**
 * 条形码配置数据传输对象
 * 
 * 封装条形码生成的所有配置选项
 * 提供丰富的自定义配置能力
 */
class BarcodeConfig
{
    /**
     * 构造函数
     * 
     * @param int    $width           条码内容总宽度（像素），0表示由渲染器根据模块数自动计算
     * @param int    $height          条码内容高度（像素，即条的高度）
     * @param int    $marginTop       上边距（像素）
     * @param int    $marginBottom    下边距（像素）
     * @param int    $marginLeft      左边距（像素）
     * @param int    $marginRight     右边距（像素）
     * @param string $bgColor         背景颜色（十六进制）
     * @param string $barColor        条颜色（十六进制）
     * @param bool   $showText        是否显示文字
     * @param int    $fontSize        字体大小
     * @param string $fontPath        字体文件路径
     * @param float  $longBarRatio    长竖线与标准条长度比例（默认1.1）
     * @param bool   $showQuietZone   是否显示静区（条码两侧空白区域）
     * @param int    $quietZoneWidth  静区宽度（窄条宽度的倍数）
     * @param bool   $addChecksum     是否自动添加校验位
     * @param string $textPosition    文字位置（'bottom', 'top'）
     * @param int    $textOffset      文字与条码间距（像素）
     * @param bool   $rotateLongBars  是否将保护符设为长竖线
     * @param int    $longBarHeight   长竖线额外高度（像素）
     * @param int    $moduleSize      单个模块宽度（像素），内部计算，一般不需要手动设置
     */
    public function __construct(
        public readonly int $width = 0,
        public readonly int $height = 80,
        public readonly int $marginTop = 10,
        public readonly int $marginBottom = 10,
        public readonly int $marginLeft = 10,
        public readonly int $marginRight = 10,
        public readonly string $bgColor = '#FFFFFF',
        public readonly string $barColor = '#000000',
        public readonly bool $showText = true,
        public readonly int $fontSize = 12,
        public readonly ?string $fontPath = null,
        public readonly float $longBarRatio = 1.1,
        public readonly bool $showQuietZone = true,
        public readonly int $quietZoneWidth = 10,
        public readonly bool $addChecksum = true,
        public readonly string $textPosition = 'bottom',
        public readonly int $textOffset = 5,
        public readonly bool $rotateLongBars = true,
        public readonly int $longBarHeight = 15,
        public readonly int $moduleSize = 0
    ) {}

    /**
     * 从数组创建配置对象
     * 
     * @param array $config 配置数组
     * @return self 返回配置对象实例
     */
    public static function fromArray(array $config): self
    {
        return new self(
            width: $config['width'] ?? 0,
            height: $config['height'] ?? 80,
            marginTop: $config['marginTop'] ?? 10,
            marginBottom: $config['marginBottom'] ?? 10,
            marginLeft: $config['marginLeft'] ?? 10,
            marginRight: $config['marginRight'] ?? 10,
            bgColor: $config['bgColor'] ?? '#FFFFFF',
            barColor: $config['barColor'] ?? '#000000',
            showText: $config['showText'] ?? true,
            fontSize: $config['fontSize'] ?? 12,
            fontPath: $config['fontPath'] ?? null,
            longBarRatio: $config['longBarRatio'] ?? 1.1,
            showQuietZone: $config['showQuietZone'] ?? true,
            quietZoneWidth: $config['quietZoneWidth'] ?? 10,
            addChecksum: $config['addChecksum'] ?? true,
            textPosition: $config['textPosition'] ?? 'bottom',
            textOffset: $config['textOffset'] ?? 5,
            rotateLongBars: $config['rotateLongBars'] ?? true,
            longBarHeight: $config['longBarHeight'] ?? 15,
            moduleSize: $config['moduleSize'] ?? 0
        );
    }

    /**
     * 转换为数组
     * 
     * @return array 返回配置数组
     */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'marginTop' => $this->marginTop,
            'marginBottom' => $this->marginBottom,
            'marginLeft' => $this->marginLeft,
            'marginRight' => $this->marginRight,
            'bgColor' => $this->bgColor,
            'barColor' => $this->barColor,
            'showText' => $this->showText,
            'fontSize' => $this->fontSize,
            'fontPath' => $this->fontPath,
            'longBarRatio' => $this->longBarRatio,
            'showQuietZone' => $this->showQuietZone,
            'quietZoneWidth' => $this->quietZoneWidth,
            'addChecksum' => $this->addChecksum,
            'textPosition' => $this->textPosition,
            'textOffset' => $this->textOffset,
            'rotateLongBars' => $this->rotateLongBars,
            'longBarHeight' => $this->longBarHeight,
            'moduleSize' => $this->moduleSize,
        ];
    }
}

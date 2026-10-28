<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Renderer;

use zxf\Utils\BarCode\DTO\BarcodeConfig;
use zxf\Utils\BarCode\Exceptions\RenderException;

/**
 * PNG 渲染器（动态文字定位版）
 *
 * 【核心特性】
 * - 动态检测长竖线（保护符）实际像素位置
 * - 根据条码内容和结构自动分段显示数字
 * - 动态计算字号大小适配条码宽度
 * - 动态计算上下偏移距离
 * - 每段数字在各自区域内均匀分布
 *
 * 【支持的长竖线特征条码】
 * - EAN-13: 分段 1+6+6（第1位左静区，2-7位左数据区，8-13位右数据区）
 * - ISSN: 基于EAN-13，显示格式不同
 * - UPC-A: 分段 1+5+5+1（第1位左静区，2-6位左数据区，7-11位右数据区，12位右静区）
 * - EAN-8: 分段 4+4（1-4位左数据区，5-8位右数据区）
 */
class PngRenderer extends BaseRenderer
{
    protected const TYPE = 'png';

    /** @var \GdImage|null GD图像资源 */
    protected ?\GdImage $image = null;

    /** @var int 动态计算的字号，用于长竖线条码的自动适配（GD 内置字体 1-5） */
    protected int $dynamicFontSize = 5;

    /** @var int 动态计算的文本Y轴偏移量，确保文字与长竖线不重叠 */
    protected int $dynamicTextOffset = 3;

    /** @var int 本次渲染实际使用的左静区宽度（因静区最小值可能被放大；不改写 config，避免实例复用残留副作用） */
    protected int $marginLeftUsed = 0;

    /** @var int 本次渲染实际使用的右静区宽度 */
    protected int $marginRightUsed = 0;

    /** @var int 水印原始像素字号（TTF 渲染路径使用，不再受 GD 位图 1-5 档限制） */
    protected int $watermarkFontSizePx = 16;

    /**
     * 构造 PNG 渲染器
     *
     * @param BarcodeConfig|null $config 条码配置对象；为 null 时使用默认配置
     */
    public function __construct(?BarcodeConfig $config = null)
    {
        parent::__construct($config);
    }

    /**
     * 渲染条形码为PNG
     *
     * 用传入的 $config 覆盖实例配置后重新计算模块大小与本次静区宽度，
     * 依次创建画布、绘条、Bearer Bar、文字、水印与边框，最终返回 PNG
     * 二进制数据。渲染完成后 image 资源被释放，实例可复用。
     *
     * @param array<int> $barcodeData 条空模式数组（正数为条、负数为空）
     * @param string $data 原始数据（用于下方文字）
     * @param array<string, mixed> $config 单次渲染的配置覆盖项
     * @return string PNG 二进制数据
     * @throws RenderException GD 扩展缺失或画布创建失败时抛出
     */
    public function render(array $barcodeData, string $data, array $config = []): string
    {
        $this->config = BarcodeConfig::fromArray(array_merge($this->config->toArray(), $config));
        $this->barcodeStructure = []; // 重置结构分析
        $this->renderModuleSize = $this->resolveModuleSize($barcodeData);

        // 确保有足够的静区宽度（仅作用于本次渲染，不改写 config，避免实例复用时残留副作用）
        $this->marginLeftUsed = $this->config->marginLeft;
        $this->marginRightUsed = $this->config->marginRight;
        if ($this->config->showQuietZone) {
            $minQuietZone = $this->quietZoneWidthPx();
            $this->marginLeftUsed = max($this->config->marginLeft, (int) $minQuietZone);
            $this->marginRightUsed = max($this->config->marginRight, (int) $minQuietZone);
        }

        $this->createImage($barcodeData);
        $this->drawBarcode($barcodeData);
        
        if ($this->bearerBarEnabled) {
            $this->drawBearerBar();
        }
        
        if ($this->config->showText) {
            $this->drawText($data);
        }
        
        if ($this->watermarkText !== null) {
            $this->drawWatermark();
        }

        if ($this->borderWidth > 0) {
            $this->drawBorder();
        }

        return $this->outputPng();
    }

    /**
     * 直接输出到浏览器
     *
     * 先完成一次完整渲染，再以 image/png 类型、正确 Content-Length 与
     * 禁止缓存的响应头把 PNG 数据输出到标准输出。
     *
     * @param array<int> $barcodeData 条空模式数组（正数为条、负数为空）
     * @param string $data 原始数据（用于下方文字）
     * @param array<string, mixed> $config 单次渲染的配置覆盖项
     * @return void
     * @throws RenderException GD 扩展缺失或画布创建失败时抛出
     */
    public function outputToBrowser(array $barcodeData, string $data, array $config = []): void
    {
        $pngData = $this->render($barcodeData, $data, $config);
        
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($pngData));
        header('Cache-Control: no-cache, must-revalidate');
        
        echo $pngData;
    }

    /**
     * 渲染并将 PNG 写入文件
     *
     * 目标目录不存在时自动递归创建。写入失败（如目录不可写）返回 false。
     *
     * @param array<int> $barcodeData 条空模式数组
     * @param string $data 显示文字
     * @param string $filename 目标 PNG 文件路径
     * @param array<string, mixed> $config 渲染配置项，可选
     * @return bool 写入成功返回 true，失败返回 false
     */
    public function saveToFile(array $barcodeData, string $data, string $filename, array $config = []): bool
    {
        $pngData = $this->render($barcodeData, $data, $config);
        
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($filename, $pngData) !== false;
    }

    /**
     * 添加水印（PNG 适配版）
     *
     * GD 内置字体仅支持 1-5 级字号，因此传入的字号会被映射到该范围。
     *
     * @param string $text 水印文本
     * @param int $opacity 透明度（0-100，值越大越不透明）
     * @param int $fontSize 字号（会被映射到 GD 内置字体 1-5）
     * @param string $color 颜色（十六进制格式）
     * @param int $angle 旋转角度（-180到180度）
     * @return self
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
        $this->watermarkColor = $color;
        $this->watermarkAngle = max(-180, min(180, $angle));
        // TTF 路径使用原始像素字号（与 SVG/HTML 水印字号语义一致，不再被 GD 位图 1-5 档换算放大）
        $this->watermarkFontSizePx = max(1, $fontSize);
        // GD 内置位图字体仅支持 1-5 级，超过此范围的值按 3 像素/级进行映射
        if ($fontSize <= 5) {
            $this->watermarkFontSize = max(1, min(5, $fontSize));
        } else {
            $mapped = (int) round($fontSize / 3);
            $this->watermarkFontSize = max(1, min(5, $mapped));
        }
        return $this;
    }

    /**
     * 创建图像资源
     *
     * 按模块总数与左右静区算出画布宽，按条区高度、上下边距、文字行高与
     * Bearer Bar 占位算出画布高，并填充背景。背景为 transparent 时启用
     * alpha 通道；背景与条色对比度不足 3:1 时强制改写为黑白。
     *
     * @param array<int> $barcodeData 条空模式数组（正数为条、负数为空）
     * @return void
     * @throws RenderException GD 扩展缺失或 imagecreatetruecolor 失败时抛出
     */
    protected function createImage(array $barcodeData): void
    {
        // PNG 渲染依赖 PHP GD 扩展：缺失时给出清晰错误，而非原生 Fatal
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            throw new RenderException('PngRenderer 依赖 PHP GD 扩展（imagecreatetruecolor），请先安装并启用 gd 扩展，或改用 toSvg()/toHtml()');
        }

        $totalWidth = 0;
        foreach ($barcodeData as $element) {
            $totalWidth += abs($element);
        }
        
        $width = $totalWidth * $this->renderModuleSize +
                 $this->marginLeftUsed +
                 $this->marginRightUsed;

        // 长竖线（保护符）从 marginTop 起绘 height×ratio 的高度：
        // 图像高度必须以“条区高度”为基准，否则 ratio>1 时延长部分会被画布裁剪
        $barAreaHeight = $this->config->height;
        if ($this->longBarsEnabled()) {
            $barAreaHeight = (int) ceil($barAreaHeight * $this->longBarHeightRatio);
        }

        $height = $barAreaHeight +
                  $this->config->marginTop +
                  $this->config->marginBottom;

        if ($this->config->showText) {
            $height += $this->config->fontSize + $this->config->textOffset;
        }

        if ($this->bearerBarEnabled) {
            $height += $this->bearerBarWidth * 2;
        }

        $this->image = imagecreatetruecolor($width, $height);
        
        if ($this->image === false) {
            throw new RenderException('创建图像失败');
        }

        $bgColorHex = $this->config->bgColor;
        $barColorHex = $this->config->barColor;

        if ($bgColorHex !== 'transparent' && $bgColorHex !== null && !$this->validateContrast($bgColorHex, $barColorHex)) {
            $bgColorHex = '#FFFFFF';
            $barColorHex = '#000000';
        }

        if ($bgColorHex === 'transparent' || $bgColorHex === null) {
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
            $transparent = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
            imagefill($this->image, 0, 0, $transparent);
        } else {
            $bgColor = $this->hexToColor($bgColorHex);
            imagefill($this->image, 0, 0, $bgColor);
        }
    }

    /**
     * 绘制条形码并分析结构
     * 
     * 【关键】在绘制过程中分析条码结构，记录关键位置
     *
     * 逐元素累加 x 坐标，仅对条（正值）落笔；长条按 longBarHeightRatio 加高，
     * 渐变可用时逐列绘制，否则按圆角/直角两种方式填充。结束时把全部条的
     * 几何信息交给 analyzeBarcodeStructure()。image 为空时直接返回。
     *
     * @param array<int> $barcodeData 条空模式数组（正数为条、负数为空）
     * @return void
     */
    protected function drawBarcode(array $barcodeData): void
    {
        if ($this->image === null) {
            return;
        }

        $barColor = $this->hexToColor($this->config->barColor);
        $x = $this->marginLeftUsed;
        
        // 计算长竖线位置
        $longBarPositions = $this->calculateLongBarPositions();
        
        $barY = $this->config->marginTop;
        
        // 记录所有条的位置和宽度
        $bars = [];
        $elementIndex = 0;
        
        foreach ($barcodeData as $elementIndex => $element) {
            $isBar = $element > 0;
            $width = abs($element) * $this->renderModuleSize;

            if ($isBar) {
                $isLongBar = in_array($elementIndex, $longBarPositions, true) && 
                             $this->config->rotateLongBars;
                
                $bars[] = [
                    'x' => $x,
                    'width' => $width,
                    'isLongBar' => $isLongBar,
                    'elementIndex' => $elementIndex,
                ];
                
                $barHeight = $this->config->height;
                if ($isLongBar) {
                    $barHeight = (int)($barHeight * $this->longBarHeightRatio);
                }
                
                // 渐变经对比度守卫：两端与背景对比不足时回退纯色，保证可扫性
                if ($this->gradientUsable()) {
                    $this->drawGradientBar($x, $barY, $width, $barHeight);
                } else {
                    if ($this->enableRoundedBars && $width > $this->cornerRadius * 2) {
                        $this->drawRoundedRect($x, $barY, $width, $barHeight, $this->cornerRadius, $barColor);
                    } else {
                        imagefilledrectangle(
                            $this->image,
                            $x,
                            $barY,
                            $x + $width - 1,
                            $barY + $barHeight - 1,
                            $barColor
                        );
                    }
                }
            }
            
            $x += $width;
        }
        
        // 分析条码结构
        $this->analyzeBarcodeStructure($bars, $x, $this->marginLeftUsed);
    }


    /**
     * 绘制文字（动态定位）
     *
     * 先清理控制字符得到可显示文本：无长条结构时按对齐方式整段绘制
     * （Code 39 会先去掉首尾 * 定界符），有长条结构时走分段逐字定位。
     *
     * @param string $data 原始数据，绘制前会做控制字符清理
     * @return void
     */
    protected function drawText(string $data): void
    {
        if ($this->image === null) {
            return;
        }

        $textColor = $this->hexToColor($this->config->barColor);

        // 清理控制字符，避免 GD 绘制异常（保留原始数据用于条码编码）
        $displayData = $this->sanitizeDisplayText($data);

        // 判断是否为长竖线特征条码
        if (!$this->barcodeStructure['hasLongBars']) {
            // 无长竖线条码使用普通对齐方式
            switch ($this->barcodeType) {
                case 'Code 39':
                    $this->drawAlignedText($this->removeDelimiters($displayData), $textColor);
                    break;
                default:
                    $this->drawAlignedText($displayData, $textColor);
                    break;
            }
            return;
        }

        // 长竖线特征条码使用动态分段显示
        $this->drawSegmentedText($displayData, $textColor);
    }

    /**
     * 清理显示文本中的控制字符
     *
     * GD 的 imagestring 无法正确渲染 ASCII 控制字符（0-31、127），
     * 会导致空白或乱码。此方法将其替换为空格，确保视觉显示正常。
     *
     * @param string $data 原始待显示文本
     * @return string 控制字符已替换为空格、长度不变的文本
     */
    protected function sanitizeDisplayText(string $data): string
    {
        $result = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $ord = ord($data[$i]);
            if ($ord < 32 || $ord === 127) {
                $result .= ' ';
            } else {
                $result .= $data[$i];
            }
        }
        return $result;
    }

    /**
     * 分段显示文字（核心方法）
     * 
     * 【动态计算逻辑】
     * 1. 根据条码类型确定分段方式
     * 2. 根据数据区域宽度动态计算字号
     * 3. 每段数字在各自区域内均匀分布
     * 4. 文字紧贴长竖线底部
     *
     * 区域信息或分段结果缺失时回退为整段对齐绘制，避免文字整体丢失。
     *
     * @param string $data 已清理的显示文本
     * @param int $textColor GD 颜色索引
     * @return void
     */
    protected function drawSegmentedText(string $data, int $textColor): void
    {
        $regions = $this->barcodeStructure['regions'] ?? null;
        if ($regions === null) {
            $this->drawAlignedText($data, $textColor);
            return;
        }
        
        // 根据条码类型确定分段策略
        $segments = $this->determineSegments($data);
        if (empty($segments)) {
            $this->drawAlignedText($data, $textColor);
            return;
        }
        
        // 计算动态参数
        $this->calculateDynamicParameters($segments, $regions);
        
        // 文字Y位置：长竖线底部下方
        $barY = $this->config->marginTop;
        $textY = $barY + (int)($this->config->height * $this->longBarHeightRatio) + $this->dynamicTextOffset;
        
        // 绘制每个分段
        foreach ($segments as $segment) {
            $this->drawSegment($segment, $regions, $textY, $textColor);
        }
    }

    /**
     * 确定分段策略
     *
     * 与 HTML 渲染器共用 BaseRenderer::splitDisplaySegments() 的实现，
     * 保证同一数据在 PNG/HTML 两种输出下的分段完全一致。
     *
     * @param string $data 已清理的显示文本
     * @return list<array{text: string, region: string, type: string}> 分段列表，不适用时为空数组
     */
    protected function determineSegments(string $data): array
    {
        return $this->splitDisplaySegments($data);
    }

    /**
     * 计算动态参数（字号、偏移等）
     *
     * 以多字符分段的平均单字宽度为准调整字号（±1），无可用分段时回退到
     * 按总宽度判断；GD 内置字体仅支持 1-5，最终会显式夹取到该区间。
     * 文字偏移取字号相关值与长条溢出高度的较大者，最小 3px。
     *
     * @param list<array{text: string, region: string, type: string}> $segments 文字分段列表
     * @param array<string, array<string, int>> $regions 各区域边界（left/right/center）
     * @return void
     */
    protected function calculateDynamicParameters(array $segments, array $regions): void
    {
        // 计算最小数据区域宽度
        $minRegionWidth = PHP_INT_MAX;
        foreach ($segments as $segment) {
            if ($segment['type'] === 'multi') {
                $regionName = $segment['region'];
                if (isset($regions[$regionName])) {
                    $width = $regions[$regionName]['right'] - $regions[$regionName]['left'];
                    $minRegionWidth = min($minRegionWidth, $width);
                }
            }
        }
        
        // 根据最小区域宽度计算最大字号
        // 每个数字需要一定宽度，根据区域宽度和数字数量计算
        $baseFontSize = $this->config->fontSize;
        
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
            // 根据平均每个数字的宽度调整字号 (GD库字号范围1-5)
            if ($avgRegionWidth < 6) {
                $this->dynamicFontSize = max(2, $baseFontSize - 1);
            } elseif ($avgRegionWidth > 10) {
                $this->dynamicFontSize = min(5, $baseFontSize + 1);
            } else {
                $this->dynamicFontSize = $baseFontSize;
            }
        } else {
            // 回退到基于总宽度的调整
            $barcodeWidth = $this->barcodeStructure['totalWidth'] ?? 200;
            if ($barcodeWidth < 150) {
                $this->dynamicFontSize = max(3, $baseFontSize - 1);
            } elseif ($barcodeWidth > 400) {
                $this->dynamicFontSize = min(6, $baseFontSize + 1);
            } else {
                $this->dynamicFontSize = $baseFontSize;
            }
        }

        // GD 内置字体仅支持 1-5，超出会静默回落 font5，这里显式夹取
        $this->dynamicFontSize = max(1, min(5, $this->dynamicFontSize));

        // 动态计算文本偏移（根据长竖线高度和字号）
        $longBarHeight = $this->config->height * $this->longBarHeightRatio;
        $normalHeight = $this->config->height;
        $extraHeight = $longBarHeight - $normalHeight;
        
        // 偏移量基于额外高度和字号，确保文字不会太贴近长竖线
        $fontBasedOffset = (int)($this->dynamicFontSize * 0.5);
        $this->dynamicTextOffset = max(3, max($fontBasedOffset, (int)($extraHeight * 0.35)));
    }

    /**
     * 绘制单个分段
     *
     * 单字符分段定位到区域中心（优先用预置 center 坐标）；多字符分段把区域
     * 宽度等分后逐字居中。区域不存在或文本为空时直接返回，不绘制任何内容。
     *
     * @param array{text: string, region: string, type: string} $segment 单个分段
     * @param array<string, array<string, int>> $regions 各区域边界（left/right/center）
     * @param int $textY 文字基线 y 坐标（像素）
     * @param int $textColor GD 颜色索引
     * @return void
     */
    protected function drawSegment(array $segment, array $regions, int $textY, int $textColor): void
    {
        $regionName = $segment['region'];
        if (!isset($regions[$regionName])) {
            return;
        }
        
        $region = $regions[$regionName];
        $text = $segment['text'];
        $length = strlen($text);
        
        if ($length === 0) {
            return;
        }
        
        if ($segment['type'] === 'single' || $length === 1) {
            // 单个数字：居中在区域内
            $centerX = $region['center'] ?? (int)(($region['left'] + $region['right']) / 2);
            $this->drawChar($text, $centerX, $textY, $textColor, $this->dynamicFontSize);
        } else {
            // 多个数字：在区域内均匀分布
            $regionWidth = $region['right'] - $region['left'];
            $spacing = $regionWidth / $length;
            
            for ($i = 0; $i < $length; $i++) {
                // 每个数字的中心位置
                $x = (int)($region['left'] + $spacing * $i + $spacing / 2);
                $this->drawChar($text[$i], $x, $textY, $textColor, $this->dynamicFontSize);
            }
        }
    }

    /**
     * 移除分隔符
     *
     * 去掉 Code 39 显示文本首尾的 * 定界符，仅影响肉眼可读文字，
     * 不影响条空编码。
     *
     * @param string $data 待处理的显示文本
     * @return string 去掉首尾 * 后的文本
     */
    protected function removeDelimiters(string $data): string
    {
        return trim($data, '*');
    }

    /**
     * 绘制对齐的文本
     *
     * 支持多行（按 \n 拆分）。配置了存在且可读的 TTF 字体时走 TTF 绘制，
     * 否则回退到 GD 内置字体，此时字号按 fontSize/3 折算并限制在 1-5。
     * 水平位置由 textAlign 决定（左/中/右）。
     *
     * @param string $data 待绘制的显示文本，可含换行符
     * @param int $textColor GD 颜色索引
     * @return void
     */
    protected function drawAlignedText(string $data, int $textColor): void
    {
        $lines = explode("\n", $data);

        $barY = $this->config->marginTop;
        $baseY = $barY + $this->config->height + $this->config->textOffset;

        $imgWidth = imagesx($this->image);

        // 优先使用 TTF 字体渲染
        if ($this->config->fontPath !== null && file_exists($this->config->fontPath)) {
            $this->drawAlignedTextTtf($lines, $baseY, $imgWidth, $textColor);
            return;
        }

        // GD 内置字体回退
        $fontSize = min(5, max(1, (int)($this->config->fontSize / 3)));
        $lineHeight = imagefontheight($fontSize) + 2;

        foreach ($lines as $index => $line) {
            $textWidth = imagefontwidth($fontSize) * strlen($line);
            $y = $baseY + $index * $lineHeight + imagefontheight($fontSize);

            switch ($this->textAlign) {
                case 'left':
                    $x = $this->marginLeftUsed;
                    break;
                case 'right':
                    $x = $imgWidth - $this->marginRightUsed - $textWidth;
                    break;
                case 'center':
                default:
                    $x = (int)(($imgWidth - $textWidth) / 2);
                    break;
            }

            imagestring($this->image, $fontSize, $x, $y - imagefontheight($fontSize), $line, $textColor);
        }
    }

    /**
     * 使用 TTF 字体绘制多行对齐文本
     *
     * 以样本字符串 "Mg" 的 bbox 推算行高，逐行按 textAlign 计算起点后
     * 写入画布，字号直接取配置的 fontSize（像素）。
     *
     * @param array<int, string> $lines 按行拆分后的文本
     * @param int $baseY 首行文字顶部的 y 坐标（像素）
     * @param int $imgWidth 画布宽度（像素），用于右对齐与居中计算
     * @param int $textColor GD 颜色索引
     * @return void
     */
    protected function drawAlignedTextTtf(array $lines, int $baseY, int $imgWidth, int $textColor): void
    {
        $fontSize = $this->config->fontSize;
        $fontPath = $this->config->fontPath;

        // 计算行高
        $sampleBox = imagettfbbox($fontSize, 0, $fontPath, 'Mg');
        $lineHeight = abs($sampleBox[1] - $sampleBox[7]) + 4;

        foreach ($lines as $index => $line) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $textHeight = abs($bbox[1] - $bbox[7]);
            $y = $baseY + $index * $lineHeight + $textHeight;

            switch ($this->textAlign) {
                case 'left':
                    $x = $this->marginLeftUsed;
                    break;
                case 'right':
                    $x = $imgWidth - $this->marginRightUsed - $textWidth;
                    break;
                case 'center':
                default:
                    $x = (int)(($imgWidth - $textWidth) / 2);
                    break;
            }

            imagettftext($this->image, $fontSize, 0, $x, $y, $textColor, $fontPath, $line);
        }
    }

    /**
     * 绘制单个字符
     *
     * 以给定 x 为字符中心反推左边距，并夹紧到画布范围内，防止越界字符
     * 被裁切；y 取字符顶部的视觉对齐位置。
     *
     * @param string $char 要绘制的单个字符
     * @param int $x 字符中心点的 x 坐标（像素）
     * @param int $y 字符参考 y 坐标（像素）
     * @param int $textColor GD 颜色索引
     * @param int $fontSize GD 内置字号（1-5）
     * @return void
     */
    protected function drawChar(string $char, int $x, int $y, int $textColor, int $fontSize = 5): void
    {
        if ($this->image === null) {
            return;
        }

        $charWidth = imagefontwidth($fontSize);
        $charHeight = imagefontheight($fontSize);
        
        // 居中显示 - 优化定位精度
        $centeredX = $x - (int)($charWidth / 2);
        
        // 确保不超出图像边界
        $imgWidth = imagesx($this->image);
        $centeredX = max(0, min($centeredX, $imgWidth - $charWidth));
        
        // 使用 imagestring 确保字符清晰显示
        imagestring($this->image, $fontSize, $centeredX, $y - $charHeight + 3, $char, $textColor);
    }

    /**
     * 绘制渐变条
     *
     * 按宽度逐列在起止色之间线性插值，每列画一条竖线拼出水平渐变；
     * 颜色未做对比度校验，调用前应由 gradientUsable() 把关。
     *
     * @param int $x 条的左上角 x 坐标（像素）
     * @param int $y 条的左上角 y 坐标（像素）
     * @param int $width 条宽（像素）
     * @param int $height 条高（像素）
     * @return void
     */
    protected function drawGradientBar(int $x, int $y, int $width, int $height): void
    {
        $startRgb = $this->hexToRgb($this->gradientStartColor);
        $endRgb = $this->hexToRgb($this->gradientEndColor);
        
        for ($i = 0; $i < $width; $i++) {
            $ratio = $i / $width;
            $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $ratio);
            $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $ratio);
            $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $ratio);
            
            $color = imagecolorallocate($this->image, $r, $g, $b);
            imageline($this->image, $x + $i, $y, $x + $i, $y + $height - 1, $color);
        }
    }

    /**
     * 绘制圆角矩形
     *
     * 用两个交叉矩形铺出中间区域，再用四个实心椭圆补出四角圆角。
     * 调用方需保证条宽大于两倍半径，否则圆角会互相覆盖导致形状异常。
     *
     * @param int $x 左上角 x 坐标（像素）
     * @param int $y 左上角 y 坐标（像素）
     * @param int $width 矩形宽度（像素）
     * @param int $height 矩形高度（像素）
     * @param int $radius 圆角半径（像素）
     * @param int $color GD 颜色索引
     * @return void
     */
    protected function drawRoundedRect(int $x, int $y, int $width, int $height, int $radius, int $color): void
    {
        imagefilledrectangle($this->image, $x + $radius, $y, $x + $width - $radius - 1, $y + $height - 1, $color);
        imagefilledrectangle($this->image, $x, $y + $radius, $x + $width - 1, $y + $height - $radius - 1, $color);
        
        imagefilledellipse($this->image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($this->image, $x + $width - $radius - 1, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($this->image, $x + $radius, $y + $height - $radius - 1, $radius * 2, $radius * 2, $color);
        imagefilledellipse($this->image, $x + $width - $radius - 1, $y + $height - $radius - 1, $radius * 2, $radius * 2, $color);
    }

    /**
     * 绘制 Bearer Bar（ITF-14 标准框型）
     *
     * 绘制完整的矩形框，四边紧贴条码内容区域（含静区），
     * 避免水平横条覆盖整个图像导致的“超长”视觉异常。
     */
    protected function drawBearerBar(): void
    {
        if ($this->image === null) {
            return;
        }

        $barColor = $this->hexToColor($this->config->barColor);
        $thickness = $this->bearerBarWidth;
        $barTop = $this->config->marginTop;
        $barBottom = $barTop + $this->config->height - 1;

        // 内容区域左右边界（与条码静区对齐，不覆盖图像边缘额外 padding）
        $contentLeft = $this->marginLeftUsed;
        $contentRight = imagesx($this->image) - $this->marginRightUsed - 1;
        if ($contentLeft >= $contentRight) {
            $contentLeft = 0;
            $contentRight = imagesx($this->image) - 1;
        }

        // 顶部横线
        $topY1 = max(0, $barTop - $thickness);
        $topY2 = $barTop - 1;
        if ($topY2 >= 0) {
            imagefilledrectangle($this->image, $contentLeft, $topY1, $contentRight, $topY2, $barColor);
        }

        // 底部横线
        $bottomY1 = $barBottom + 1;
        $bottomY2 = $barBottom + $thickness;
        imagefilledrectangle($this->image, $contentLeft, $bottomY1, $contentRight, $bottomY2, $barColor);

        // 左右垂直条（连接顶部和底部，形成完整框型 Bearer Bar）
        $boxTop = $topY1;
        $boxBottom = $bottomY2;
        imagefilledrectangle($this->image, $contentLeft, $boxTop, $contentLeft + $thickness - 1, $boxBottom, $barColor);
        imagefilledrectangle($this->image, $contentRight - $thickness + 1, $boxTop, $contentRight, $boxBottom, $barColor);
    }

    /**
     * 绘制水印（增强版，支持旋转、斜向平铺、位置定位和TTF字体）
     */
    protected function drawWatermark(): void
    {
        if ($this->image === null || $this->watermarkText === null) {
            return;
        }

        $width = imagesx($this->image);
        $height = imagesy($this->image);

        // 解析水印颜色
        $rgb = $this->parseColor($this->watermarkColor);

        // 计算透明度（GD库alpha值：0不透明，127完全透明）
        $alpha = (int)(127 * (1 - $this->watermarkOpacity / 100));
        $watermarkColor = imagecolorallocatealpha($this->image, $rgb['r'], $rgb['g'], $rgb['b'], $alpha);

        // 使用TTF字体或内置GD字体
        if ($this->watermarkFontPath !== null && $this->watermarkAngle === 0) {
            $this->drawTtfWatermark($width, $height, $watermarkColor);
            return;
        }

        $fontSize = $this->watermarkFontSize;
        $textWidth = imagefontwidth($fontSize) * strlen($this->watermarkText);
        $textHeight = imagefontheight($fontSize);

        // 如果角度为0，根据位置定位绘制
        if ($this->watermarkAngle === 0) {
            [$x, $y] = $this->resolveWatermarkPosition($width, $height, $textWidth, $textHeight, 0);
            imagestring($this->image, $fontSize, $x, $y, $this->watermarkText, $watermarkColor);
        } else {
            // 有旋转角度，绘制斜向平铺的水印
            $this->drawRotatedWatermark($width, $height, $watermarkColor);
        }
    }

    /**
     * 使用TTF字体绘制水印
     *
     * 仅在设置了水印字体路径时生效；字号取原始像素值（不小于 8），
     * 用 bbox 量出文本尺寸后按位置解析出坐标并绘制。bbox 计算失败则跳过。
     *
     * @param int $imgWidth 画布宽度（像素）
     * @param int $imgHeight 画布高度（像素）
     * @param int $color GD 颜色索引
     * @return void
     */
    protected function drawTtfWatermark(int $imgWidth, int $imgHeight, int $color): void
    {
        if ($this->watermarkFontPath === null) {
            return;
        }

        $fontSize = max(8, $this->watermarkFontSizePx);
        $bbox = imagettfbbox($fontSize, 0, $this->watermarkFontPath, $this->watermarkText);
        if ($bbox === false) {
            return;
        }

        $textWidth = (int) abs($bbox[4] - $bbox[0]);
        $textHeight = (int) abs($bbox[1] - $bbox[5]);

        [$x, $y] = $this->resolveWatermarkPosition($imgWidth, $imgHeight, $textWidth, $textHeight, $textHeight);

        imagettftext(
            $this->image,
            $fontSize,
            0,
            $x,
            $y,
            $color,
            $this->watermarkFontPath,
            $this->watermarkText
        );
    }

    /**
     * 根据位置解析水印坐标
     *
     * 按九宫格位置计算左上角坐标，边距固定 10px；$baselineOffset 用于把
     * GD 内置字体的左上角坐标换算到 TTF 基线坐标。结果按 0 下界夹紧。
     *
     * @param int $imgWidth 画布宽度（像素）
     * @param int $imgHeight 画布高度（像素）
     * @param int $textWidth 水印文本宽度（像素）
     * @param int $textHeight 水印文本高度（像素）
     * @param int $baselineOffset 基线偏移量（像素），GD 内置字体传 0
     * @return array{0:int,1:int} [x, y] 坐标
     */
    protected function resolveWatermarkPosition(int $imgWidth, int $imgHeight, int $textWidth, int $textHeight, int $baselineOffset): array
    {
        $margin = 10;
        $x = (int)(($imgWidth - $textWidth) / 2);
        $y = (int)(($imgHeight - $textHeight) / 2) + $baselineOffset;

        switch ($this->watermarkPosition) {
            case 'top-left':
                $x = $margin;
                $y = $margin + $baselineOffset;
                break;
            case 'top-right':
                $x = $imgWidth - $textWidth - $margin;
                $y = $margin + $baselineOffset;
                break;
            case 'bottom-left':
                $x = $margin;
                $y = $imgHeight - $textHeight - $margin + $baselineOffset;
                break;
            case 'bottom-right':
                $x = $imgWidth - $textWidth - $margin;
                $y = $imgHeight - $textHeight - $margin + $baselineOffset;
                break;
            case 'top':
                $x = (int)(($imgWidth - $textWidth) / 2);
                $y = $margin + $baselineOffset;
                break;
            case 'bottom':
                $x = (int)(($imgWidth - $textWidth) / 2);
                $y = $imgHeight - $textHeight - $margin + $baselineOffset;
                break;
            case 'left':
                $x = $margin;
                $y = (int)(($imgHeight - $textHeight) / 2) + $baselineOffset;
                break;
            case 'right':
                $x = $imgWidth - $textWidth - $margin;
                $y = (int)(($imgHeight - $textHeight) / 2) + $baselineOffset;
                break;
            case 'center':
            default:
                $x = (int)(($imgWidth - $textWidth) / 2);
                $y = (int)(($imgHeight - $textHeight) / 2) + $baselineOffset;
                break;
        }

        return [max(0, $x), max(0, $y)];
    }
    
    /**
     * 绘制旋转和平铺的水印
     *
     * 按字号推算横纵间距，把绘制范围外扩到画布对角线以覆盖旋转后的区域，
     * 每个格点先画到临时透明画布、旋转后再合成到主图。仅绘制落在画布
     * 附近的水印，避免无效开销。
     *
     * @param int $imgWidth 画布宽度（像素）
     * @param int $imgHeight 画布高度（像素）
     * @param int $color GD 颜色索引
     * @return void
     */
    protected function drawRotatedWatermark(int $imgWidth, int $imgHeight, int $color): void
    {
        if ($this->image === null || $this->watermarkText === null) {
            return;
        }

        // 计算水印间距（根据字号调整）
        $fontSize = $this->watermarkFontSize;
        $textWidth = imagefontwidth($fontSize) * strlen($this->watermarkText);
        $textHeight = imagefontheight($fontSize);
        
        // 斜向水印的间距（水平和垂直）
        $spacingX = $textWidth + 60;  // 水平间距
        $spacingY = $textHeight + 40; // 垂直间距
        
        // 角度转换为弧度
        $angleRad = deg2rad($this->watermarkAngle);
        
        // 扩大绘制范围，确保旋转后覆盖整个图像
        $diagonal = (int)sqrt($imgWidth * $imgWidth + $imgHeight * $imgHeight);
        $startX = -$diagonal;
        $startY = -$diagonal;
        $endX = $diagonal + $imgWidth;
        $endY = $diagonal + $imgHeight;
        
        // 斜向平铺水印
        for ($row = $startY; $row < $endY; $row += $spacingY) {
            for ($col = $startX; $col < $endX; $col += $spacingX) {
                // 计算旋转后的位置
                $x = (int)($col * cos($angleRad) - $row * sin($angleRad) + $imgWidth / 2);
                $y = (int)($col * sin($angleRad) + $row * cos($angleRad) + $imgHeight / 2);
                
                // 只绘制在图像范围内的水印
                if ($x >= -$textWidth && $x <= $imgWidth && $y >= -$textHeight && $y <= $imgHeight) {
                    // 使用 imagettftext 需要字体文件，这里用旋转图像的方式
                    // 创建临时图像用于旋转
                    $tempImg = imagecreatetruecolor($textWidth + 10, $textHeight + 10);
                    imagesavealpha($tempImg, true);
                    $transparent = imagecolorallocatealpha($tempImg, 255, 255, 255, 127);
                    imagefill($tempImg, 0, 0, $transparent);
                    
                    // 在临时图像上绘制文本
                    $tempColor = imagecolorallocatealpha($tempImg, 
                        ($color >> 16) & 0xFF, 
                        ($color >> 8) & 0xFF, 
                        $color & 0xFF, 
                        ($color >> 24) & 0x7F
                    );
                    imagestring($tempImg, $fontSize, 5, 5, $this->watermarkText, $tempColor);
                    
                    // 旋转临时图像
                    $rotatedImg = imagerotate($tempImg, -$this->watermarkAngle, $transparent);
                    
                    // 合并到主图像
                    $rotatedWidth = imagesx($rotatedImg);
                    $rotatedHeight = imagesy($rotatedImg);
                    $destX = $x - (int)($rotatedWidth / 2);
                    $destY = $y - (int)($rotatedHeight / 2);
                    
                    imagecopy($this->image, $rotatedImg, $destX, $destY, 0, 0, $rotatedWidth, $rotatedHeight);
                }
            }
        }
    }
    
    /**
     * 解析颜色字符串
     *
     * 支持 6 位十六进制（可带 #），返回分量的关联数组；长度不符或含
     * 非法字符时不做校验，统一回退为默认灰色 #CCCCCC。
     *
     * @param string $color 十六进制颜色值
     * @return array{r:int,g:int,b:int} RGB 分量（0-255）
     */
    protected function parseColor(string $color): array
    {
        $color = ltrim($color, '#');
        
        if (strlen($color) === 6) {
            return [
                'r' => hexdec(substr($color, 0, 2)),
                'g' => hexdec(substr($color, 2, 2)),
                'b' => hexdec(substr($color, 4, 2)),
            ];
        }
        
        // 默认灰色
        return ['r' => 204, 'g' => 204, 'b' => 204];
    }

    /**
     * 绘制边框
     *
     * 沿画布四边各画一条实心矩形，宽度取 borderWidth 且向内占位。
     * 边框宽度不大于 0 或画布未创建时不绘制。
     *
     * @return void
     */
    protected function drawBorder(): void
    {
        if ($this->image === null || $this->borderWidth <= 0) {
            return;
        }

        $color = $this->hexToColor($this->borderColor);
        $imgWidth = imagesx($this->image);
        $imgHeight = imagesy($this->image);
        $w = $this->borderWidth;

        // 上边框
        imagefilledrectangle($this->image, 0, 0, $imgWidth - 1, $w - 1, $color);
        // 下边框
        imagefilledrectangle($this->image, 0, $imgHeight - $w, $imgWidth - 1, $imgHeight - 1, $color);
        // 左边框
        imagefilledrectangle($this->image, 0, 0, $w - 1, $imgHeight - 1, $color);
        // 右边框
        imagefilledrectangle($this->image, $imgWidth - $w, 0, $imgWidth - 1, $imgHeight - 1, $color);
    }

    /**
     * 输出PNG数据
     *
     * 通过输出缓冲截获 imagepng() 的产物并返回二进制字符串，同时把
     * image 置空以释放资源，避免同一实例重复渲染时内存累积。
     *
     * @return string PNG 二进制数据
     * @throws RenderException 画布未创建或 PNG 编码失败时抛出
     */
    protected function outputPng(): string
    {
        if ($this->image === null) {
            throw new RenderException('图像未创建');
        }

        ob_start();
        imagepng($this->image);
        $data = ob_get_clean();

        $this->image = null;

        if ($data === false) {
            throw new RenderException('生成PNG失败');
        }

        return $data;
    }

    /**
     * 十六进制颜色转GD颜色
     *
     * 支持 3 位缩写与 6 位写法，分配并返回 GD 颜色索引。画布尚未创建时
     * 无法分配颜色，返回 0。
     *
     * @param string $hex 十六进制颜色值，可带 # 前缀
     * @return int GD 颜色索引；画布为空时为 0
     */
    protected function hexToColor(string $hex): int
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        if ($this->image === null) {
            return 0;
        }

        return imagecolorallocate($this->image, $r, $g, $b);
    }

}

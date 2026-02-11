<?php

namespace zxf\Utils\QrCode;

use Exception;
use GdImage;
use zxf\Utils\QrCode\Color\Color;
use zxf\Utils\QrCode\Encoder\QrEncoder;
use zxf\Utils\QrCode\ErrorCorrectionLevel;
use zxf\Utils\QrCode\LabelOptions;

/**
 * 二维码生成器主类
 * 提供链式调用方式生成标准二维码
 * 实现了ISO/IEC 18004国际标准
 */
class QrCode
{
    /** @var string 要编码的数据内容 */
    private string $data = '';

    /** @var int 二维码尺寸（像素） */
    private int $size = 300;

    /** @var int 二维码边距（模块数量） */
    private int $margin = 4;

    /** @var ErrorCorrectionLevel 错误纠正级别 */
    private ErrorCorrectionLevel $errorCorrectionLevel;

    /** @var int 二维码版本（0表示自动） */
    private int $version = 0;

    /** @var Color 前景色 */
    private Color $foregroundColor;

    /** @var Color 背景色 */
    private Color $backgroundColor;

    /** @var string|null Logo图片路径 */
    private ?string $logoPath = null;

    /** @var int Logo宽度（像素） */
    private int $logoWidth = 0;

    /** @var int Logo高度（像素） */
    private int $logoHeight = 0;

    /** @var int Logo缩放比例（百分比，0表示自动计算） */
    private int $logoScale = 0;

    /** @var bool Logo是否使用圆形裁剪 */
    private bool $logoCircular = false;

    /** @var bool Logo是否使用圆角矩形 */
    private bool $logoRounded = false;

    /** @var float Logo圆角半径（0-1） */
    private float $logoRadius = 0.2;

    /** @var Color|null Logo背景色（用于透明Logo） */
    private ?Color $logoBackgroundColor = null;

    /** @var Color|null Logo阴影颜色 */
    private ?Color $logoShadowColor = null;

    /** @var int Logo阴影偏移X */
    private int $logoShadowOffsetX = 2;

    /** @var int Logo阴影偏移Y */
    private int $logoShadowOffsetY = 2;

    /** @var int Logo透明度（0-100） */
    private int $logoOpacity = 100;

    /** @var float Logo旋转角度（度） */
    private float $logoRotation = 0;

    /** @var LabelOptions|null 标签配置 */
    private ?LabelOptions $labelOptions = null;

    /** @var string 输出格式 */
    private string $format = 'png';

    /** @var int 图片质量 */
    private int $quality = 90;

    /** @var QrEncoder 编码器（已弃用，使用内部编码器） */
    private QrEncoder $encoder;

    /** @var string|null 字符编码 */
    private ?string $encoding = 'UTF-8';

    /** @var string|null 背景图片路径 */
    private ?string $backgroundImagePath = null;

    /** @var bool 是否使用圆点风格 */
    private bool $rounded = false;

    /** @var float 圆点半径（0-1，相对于模块大小） */
    private float $roundedRadius = 0.5;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->errorCorrectionLevel = ErrorCorrectionLevel::medium();
        $this->foregroundColor = Color::black();
        $this->backgroundColor = Color::white();
        $this->encoder = new QrEncoder();
    }

    /**
     * 创建二维码实例
     *
     * @param string $data 二维码数据
     * @return self
     */
    public static function make(string $data = ''): self
    {
        $qrCode = new self();
        if ($data !== '') {
            $qrCode->data($data);
        }
        return $qrCode;
    }

    /**
     * 设置要编码的数据
     *
     * @param string $data 数据内容
     * @return self
     */
    public function data(string $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 设置二维码尺寸
     *
     * @param int $size 尺寸（像素）
     * @return self
     */
    public function size(int $size): self
    {
        $this->size = max(21, $size);
        return $this;
    }

    /**
     * 优化长文本二维码设置
     * 自动选择最低的错误纠正级别以获得最大容量
     *
     * @param string $text 长文本内容
     * @return self
     */
    public function optimizeForLongText(string $text): self
    {
        $this->data = $text;

        // 计算文本长度
        $length = mb_strlen($text, 'UTF-8');

        // 对于超长文本，自动选择L级别（最低纠错，最大容量）
        // 并建议使用较大尺寸和自动版本
        if ($length > 500) {
            $this->errorCorrectionLevel(ErrorCorrectionLevel::low());
            $this->version(0); // 自动选择版本

            // 如果尺寸小于400，建议增大
            if ($this->size < 400) {
                $this->size(400);
            }
        } elseif ($length > 200) {
            $this->errorCorrectionLevel(ErrorCorrectionLevel::low());
            $this->version(0);
        }

        return $this;
    }

    /**
     * 设置边距
     *
     * @param int $margin 边距（模块数量）
     * @return self
     */
    public function margin(int $margin): self
    {
        $this->margin = max(0, $margin);
        return $this;
    }

    /**
     * 设置错误纠正级别
     *
     * @param ErrorCorrectionLevel|string|int $level 错误纠正级别
     * @return self
     */
    public function errorCorrectionLevel($level): self
    {
        if (is_string($level)) {
            $level = strtoupper($level);
            $this->errorCorrectionLevel = match($level) {
                'L' => ErrorCorrectionLevel::low(),
                'M' => ErrorCorrectionLevel::medium(),
                'Q' => ErrorCorrectionLevel::quartile(),
                'H' => ErrorCorrectionLevel::high(),
                default => throw new Exception('无效的错误纠正级别: ' . $level)
            };
        } elseif (is_int($level)) {
            $this->errorCorrectionLevel = ErrorCorrectionLevel::fromValue($level);
        } elseif ($level instanceof ErrorCorrectionLevel) {
            $this->errorCorrectionLevel = $level;
        } else {
            throw new Exception('无效的错误纠正级别类型');
        }
        return $this;
    }

    /**
     * 设置二维码版本
     *
     * @param int $version 版本号（1-40，0表示自动）
     * @return self
     */
    public function version(int $version): self
    {
        if ($version < 0 || $version > 40) {
            throw new Exception('二维码版本必须在0-40之间');
        }
        $this->version = $version;
        return $this;
    }

    /**
     * 设置前景色
     *
     * @param Color|string $color 颜色对象或十六进制值
     * @return self
     */
    public function foregroundColor($color): self
    {
        if (is_string($color)) {
            $this->foregroundColor = Color::fromHex($color);
        } elseif ($color instanceof Color) {
            $this->foregroundColor = $color;
        } else {
            throw new Exception('无效的颜色类型');
        }
        return $this;
    }

    /**
     * 设置背景色
     *
     * @param Color|string $color 颜色对象或十六进制值
     * @return self
     */
    public function backgroundColor($color): self
    {
        if (is_string($color)) {
            $this->backgroundColor = Color::fromHex($color);
        } elseif ($color instanceof Color) {
            $this->backgroundColor = $color;
        } else {
            throw new Exception('无效的颜色类型');
        }
        return $this;
    }

    /**
     * 设置前景色和背景色
     *
     * @param Color|string $foregroundColor 前景色
     * @param Color|string $backgroundColor 背景色
     * @return self
     */
    public function colors($foregroundColor, $backgroundColor): self
    {
        return $this->foregroundColor($foregroundColor)->backgroundColor($backgroundColor);
    }

    /**
     * 设置Logo图片
     * Logo宽度将被严格限制在不含边距的二维码内容宽度的15%以内
     *
     * @param string $logoPath Logo文件路径
     * @param int|null $width Logo宽度（null表示按比例自动计算）
     * @param int|null $height Logo高度（null表示按比例自动计算）
     * @return self
     */
    public function logo(string $logoPath, ?int $width = null, ?int $height = null): self
    {
        if (!file_exists($logoPath)) {
            throw new Exception('Logo文件不存在: ' . $logoPath);
        }

        $imageInfo = getimagesize($logoPath);
        if ($imageInfo === false) {
            throw new Exception('无法读取Logo文件信息: ' . $logoPath);
        }

        $this->logoPath = $logoPath;

        if ($width !== null && $height !== null) {
            $this->logoWidth = $width;
            $this->logoHeight = $height;
        } else {
            // 自动计算尺寸，保证不超过二维码内容宽度的15%
            $this->logoWidth = 0;
            $this->logoHeight = 0;
            $this->logoScale = 0;
        }

        return $this;
    }

    /**
     * 设置Logo缩放比例（相对于二维码尺寸）
     * Logo宽度将被严格限制在不含边距的二维码内容宽度的15%以内
     *
     * @param int $scale 缩放比例（百分比，1-15）
     * @return self
     */
    public function logoScale(int $scale): self
    {
        // 严格限制在15%以内
        $this->logoScale = max(1, min(15, $scale));
        return $this;
    }

    /**
     * 设置Logo为圆形裁剪
     *
     * @param bool $circular 是否圆形
     * @return self
     */
    public function logoCircular(bool $circular = true): self
    {
        $this->logoCircular = $circular;
        if ($circular) {
            $this->logoRounded = false; // 圆形和圆角互斥
        }
        return $this;
    }

    /**
     * 设置Logo为圆角矩形
     *
     * @param bool $rounded 是否圆角
     * @param float $radius 圆角半径（0-1，默认0.2）
     * @return self
     */
    public function logoRounded(bool $rounded = true, float $radius = 0.2): self
    {
        $this->logoRounded = $rounded;
        $this->logoRadius = max(0, min(1, $radius));
        if ($rounded) {
            $this->logoCircular = false; // 圆角和圆形互斥
        }
        return $this;
    }

    /**
     * 设置Logo背景色（用于透明Logo）
     *
     * @param Color|string $color 颜色
     * @return self
     */
    public function logoBackgroundColor($color): self
    {
        if (is_string($color)) {
            $this->logoBackgroundColor = Color::fromHex($color);
        } else {
            $this->logoBackgroundColor = $color;
        }
        return $this;
    }

    /**
     * 设置Logo阴影效果
     *
     * @param Color|string $color 阴影颜色
     * @param int $offsetX X轴偏移（默认2）
     * @param int $offsetY Y轴偏移（默认2）
     * @return self
     */
    public function logoShadow($color, int $offsetX = 2, int $offsetY = 2): self
    {
        if (is_string($color)) {
            $this->logoShadowColor = Color::fromHex($color);
        } else {
            $this->logoShadowColor = $color;
        }
        $this->logoShadowOffsetX = $offsetX;
        $this->logoShadowOffsetY = $offsetY;
        return $this;
    }

    /**
     * 设置Logo透明度
     *
     * @param int $opacity 透明度（0-100，100为不透明）
     * @return self
     */
    public function logoOpacity(int $opacity): self
    {
        $this->logoOpacity = max(0, min(100, $opacity));
        return $this;
    }

    /**
     * 设置Logo旋转角度
     *
     * @param float $rotation 旋转角度（度）
     * @return self
     */
    public function logoRotation(float $rotation): self
    {
        $this->logoRotation = $rotation;
        return $this;
    }

    /**
     * 设置背景图片
     *
     * @param string $backgroundImagePath 背景图片路径
     * @return self
     */
    public function backgroundImage(string $backgroundImagePath): self
    {
        if (!file_exists($backgroundImagePath)) {
            throw new Exception('背景图片文件不存在: ' . $backgroundImagePath);
        }
        $this->backgroundImagePath = $backgroundImagePath;
        return $this;
    }

    /**
     * 设置圆点风格
     *
     * @param bool $rounded 是否使用圆点
     * @return self
     */
    public function rounded(bool $rounded = true): self
    {
        $this->rounded = $rounded;
        return $this;
    }

    /**
     * 设置圆点半径
     *
     * @param float $radius 圆点半径（0-1）
     * @return self
     */
    public function roundedRadius(float $radius): self
    {
        $this->roundedRadius = max(0, min(1, $radius));
        return $this;
    }

    /**
     * 设置标签配置
     *
     * @param LabelOptions|null $labelOptions 标签配置
     * @return self
     */
    public function label(?LabelOptions $labelOptions): self
    {
        $this->labelOptions = $labelOptions;
        return $this;
    }

    /**
     * 设置标签文本
     *
     * @param string $text 标签文本
     * @param string|null $fontPath 字体路径（null使用默认字体）
     * @return self
     */
    public function labelText(string $text, ?string $fontPath = null): self
    {
        $this->labelOptions = LabelOptions::create($text, $fontPath);
        return $this;
    }

    /**
     * 设置标签配置（alias方法）
     *
     * @param LabelOptions|null $labelOptions 标签配置
     * @return self
     */
    public function labelOptions(?LabelOptions $labelOptions): self
    {
        return $this->label($labelOptions);
    }

    /**
     * 设置输出格式
     *
     * @param string $format 格式（png/jpeg/gif/jpg/webp）
     * @return self
     */
    public function format(string $format): self
    {
        $validFormats = ['png', 'jpeg', 'gif', 'jpg', 'webp'];
        $format = strtolower($format);
        if (!in_array($format, $validFormats)) {
            throw new Exception('不支持的输出格式: ' . $format);
        }
        $this->format = $format;
        return $this;
    }

    /**
     * 设置图片质量
     *
     * @param int $quality 质量值（0-100）
     * @return self
     */
    public function quality(int $quality): self
    {
        $this->quality = max(0, min(100, $quality));
        return $this;
    }

    /**
     * 设置字符编码
     *
     * @param string $encoding 编码
     * @return self
     */
    public function encoding(string $encoding): self
    {
        $this->encoding = $encoding;
        return $this;
    }

    /**
     * 生成二维码并返回GD图像资源
     *
     * @return resource GD图像资源
     * @throws Exception
     */
    /**
     * 渲染二维码图像
     * 优化了二维码在目标图片中的居中逻辑，确保二维码内容完美居中
     *
     * @return resource GD图像资源
     * @throws Exception
     */
    public function render()
    {
        // 编码数据为矩阵
        $matrix = $this->encodeWithBacon();

        // 计算模块数量
        $moduleCount = count($matrix);

        // 计算模块大小（使用整数以确保色块均匀）
        // 总模块数 = 二维码内容模块数 + 边距模块数 * 2
        $totalModules = $moduleCount + $this->margin * 2;
        $moduleSize = (int)($this->size / $totalModules);

        // 确保模块大小至少为1像素
        $moduleSize = max(1, $moduleSize);

        // 计算实际二维码内容宽度（像素）
        $qrContentWidth = $moduleSize * $moduleCount;
        $qrTotalWidth = $moduleSize * $totalModules;

        // 计算最终尺寸（包含标签）
        [$finalSize, $qrHeight, $qrWidth] = $this->calculateFinalSize($moduleCount, $moduleSize, $qrContentWidth);

        // 创建图像 - 使用计算出的finalSize，确保完美适配
        $image = imagecreatetruecolor($finalSize, $finalSize);
        if ($image === false) {
            throw new Exception('无法创建图像');
        }

        // 分配颜色
        $bgColor = $this->backgroundColor->toGdColor($image);
        $fgColor = $this->foregroundColor->toGdColor($image);

        // 填充背景（确保整个区域都被填充）
        imagefill($image, 0, 0, $bgColor);

        // 绘制背景图片（如果有）- 填充整个图像
        if ($this->backgroundImagePath !== null) {
            $this->drawBackgroundImage($image, $this->backgroundImagePath, $finalSize);
        }

        // 计算二维码绘制起始位置（居中在二维码区域）
        $qrX = (int)(($finalSize - $qrTotalWidth) / 2); // 水平居中
        $qrY = (int)(($qrHeight - $qrTotalWidth) / 2);  // 垂直居中
        // $qrX = (int)(($qrWidth - $qrTotalWidth) / 2); // 水平居中
        // $qrY = (int)(($qrWidth - $qrTotalWidth) / 2);  // 垂直居中


        // 计算二维码内容区域的起始位置（排除margin）
        $contentX = $qrX + $moduleSize * $this->margin;
        $contentY = $qrY + $moduleSize * $this->margin;

        // 绘制二维码
        $this->drawQrCode($image, $matrix, $contentX, $contentY, $moduleSize, $fgColor, $moduleCount);

        // 添加Logo（严格限制在15%以内）
        if ($this->logoPath !== null) {
            $this->drawLogo($image, $contentX, $contentY, $qrContentWidth, $qrContentWidth);
        }

        // 添加标签
        if ($this->labelOptions !== null && $this->labelOptions->isEnabled()) {
            $this->drawLabel($image, $qrX, $qrY, (int)$qrWidth, $qrHeight);
        }

        return $image;
    }

    /**
     * 使用BaconQrCode进行编码
     *
     * @return array 二维码矩阵
     * @throws Exception
     */
    /**
     * 编码二维码（使用我们自己的类）
     *
     * @return array 二维码矩阵
     * @throws Exception
     */
    private function encodeWithBacon(): array
    {
        try {
            $encoder = new \zxf\Utils\QrCode\Encoder\Encoder();

            // 将自定义ErrorCorrectionLevel转换为内部的ErrorCorrectionLevel
            $ecLevel = match($this->errorCorrectionLevel->getName()) {
                'L' => \zxf\Utils\QrCode\Common\ErrorCorrectionLevel::low(),
                'M' => \zxf\Utils\QrCode\Common\ErrorCorrectionLevel::medium(),
                'Q' => \zxf\Utils\QrCode\Common\ErrorCorrectionLevel::quartile(),
                'H' => \zxf\Utils\QrCode\Common\ErrorCorrectionLevel::high()
            };

            // 处理版本
            $forcedVersion = null;
            if ($this->version > 0) {
                $forcedVersion = \zxf\Utils\QrCode\Common\Version::getVersionForNumber($this->version);
            }

            // 编码
            $qrCode = $encoder->encode($this->data, $ecLevel, $this->encoding, $forcedVersion);
            $matrix = $qrCode->getMatrix();

            // 转换为数组格式
            $result = [];
            for ($y = 0; $y < $matrix->getHeight(); $y++) {
                $result[$y] = [];
                for ($x = 0; $x < $matrix->getWidth(); $x++) {
                    $result[$y][$x] = $matrix->get($x, $y) === 1;
                }
            }

            return $result;
        } catch (\Exception $e) {
            throw new Exception('二维码编码失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 计算最终尺寸
     * 优化了尺寸计算逻辑，确保二维码在最终图像中完美居中
     *
     * @param int $moduleCount 模块数量
     * @param int $moduleSize 模块大小（像素）
     * @param int $qrContentWidth 二维码内容宽度（像素）
     * @return array [最终图像尺寸, 二维码区域高度, 二维码区域宽度]
     */
    private function calculateFinalSize(int $moduleCount, int $moduleSize, int $qrContentWidth): array
    {
        // 计算二维码总区域（包含边距）
        $totalModules = $moduleCount + $this->margin * 2;
        $qrAreaWidth = $moduleSize * $totalModules;
        $qrAreaHeight = $qrAreaWidth; // 二维码区域是正方形

        // 如果没有标签，直接返回
        if ($this->labelOptions === null || !$this->labelOptions->isEnabled()) {
            return [$qrAreaWidth, $qrAreaHeight, $qrAreaWidth];
        }

        // 计算标签高度
        $labelHeight = $this->labelOptions->calculateTextHeight($qrAreaWidth)[0];

        // 最终图像尺寸：二维码高度 + 标签高度
        // 保持图像为正方形，确保二维码在上方完美居中
        $finalSize = $qrAreaHeight + $labelHeight;

        return [$finalSize, $qrAreaHeight, $qrAreaWidth];
    }

    /**
     * 绘制二维码矩阵
     *
     * @param GdImage $image 图像资源
     * @param array $matrix 二维码矩阵
     * @param int $startX 起始X坐标
     * @param int $startY 起始Y坐标
     * @param int $moduleSize 模块大小（整数，像素）
     * @param int $fgColor 前景色
     * @param int $moduleCount 模块数量
     */
    private function drawQrCode(GdImage $image, array $matrix, int $startX, int $startY, int $moduleSize, int $fgColor, int $moduleCount): void
    {
        for ($y = 0; $y < $moduleCount; $y++) {
            for ($x = 0; $x < $moduleCount; $x++) {
                if ($matrix[$y][$x]) {
                    if ($this->rounded) {
                        $this->drawRoundedModule($image, $startX + $x * $moduleSize, $startY + $y * $moduleSize, $moduleSize, $fgColor);
                    } else {
                        // 使用整数坐标，确保色块紧贴无间隙
                        $x1 = $startX + $x * $moduleSize;
                        $y1 = $startY + $y * $moduleSize;
                        $x2 = $x1 + $moduleSize - 1;
                        $y2 = $y1 + $moduleSize - 1;

                        imagefilledrectangle($image, $x1, $y1, $x2, $y2, $fgColor);
                    }
                }
            }
        }
    }

    /**
     * 绘制圆点模块 - 高级版本
     * 使用精确的圆形绘制，确保相邻圆点有适当重叠以保证扫描识别
     * 优化视觉效果，支持渐变、高亮、阴影和3D立体效果
     *
     * @param GdImage $image 图像资源
     * @param int $x X坐标
     * @param int $y Y坐标
     * @param int $size 模块大小（像素）
     * @param int $color 颜色
     */
    private function drawRoundedModule(GdImage $image, int $x, int $y, int $size, int $color): void
    {
        // 计算圆点半径，使用动态系数确保相邻圆点连接
        // 半径系数说明：0.5=刚好相接，0.55=轻微重叠（推荐），0.6=明显重叠
        // 重叠确保扫描识别，但避免过大导致二维码失效
        $radius = (int)($size * $this->roundedRadius * 0.55);
        $cx = $x + (int)($size / 2);
        $cy = $y + (int)($size / 2);

        // 根据尺寸选择绘制策略
        if ($size >= 8) {
            // 大尺寸圆点：添加立体效果
            $this->draw3DModule($image, $cx, $cy, $radius, $color);
        } else if ($size >= 6) {
            // 中等尺寸圆点：添加高亮效果
            $this->drawHighlightedModule($image, $cx, $cy, $radius, $color);
        } else {
            // 小尺寸圆点：简单填充
            imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $color);
        }
    }

    /**
     * 绘制3D立体效果圆点模块
     * 使用渐变和高光创造立体感，适用于大尺寸二维码
     *
     * @param GdImage $image 图像资源
     * @param int $cx 圆心X坐标
     * @param int $cy 圆心Y坐标
     * @param int $radius 半径
     * @param int $color 基础颜色
     */
    private function draw3DModule(GdImage $image, int $cx, int $cy, int $radius, int $color): void
    {
        // 主圆点
        imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $color);

        // 主高光（左上）
        $highlightColor1 = $this->lightenColor($color, 60);
        $highlightRadius1 = (int)($radius * 0.5);
        imagefilledellipse($image, $cx - (int)($radius * 0.3), $cy - (int)($radius * 0.3), $highlightRadius1 * 2, $highlightRadius1 * 2, $highlightColor1);

        // 次高光（右上）
        $highlightColor2 = $this->lightenColor($color, 40);
        $highlightRadius2 = (int)($radius * 0.3);
        imagefilledellipse($image, $cx + (int)($radius * 0.2), $cy - (int)($radius * 0.2), $highlightRadius2 * 2, $highlightRadius2 * 2, $highlightColor2);

        // 阴影（右下）
        $shadowColor = $this->darkenColor($color, 30);
        $shadowRadius = (int)($radius * 0.4);
        imagefilledellipse($image, $cx + (int)($radius * 0.25), $cy + (int)($radius * 0.25), $shadowRadius * 2, $shadowRadius * 2, $shadowColor);
    }

    /**
     * 绘制高亮效果圆点模块
     * 添加轻微高光提升视觉层次
     *
     * @param GdImage $image 图像资源
     * @param int $cx 圆心X坐标
     * @param int $cy 圆心Y坐标
     * @param int $radius 半径
     * @param int $color 基础颜色
     */
    private function drawHighlightedModule(GdImage $image, int $cx, int $cy, int $radius, int $color): void
    {
        // 主圆点
        imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $color);

        // 添加高亮效果
        $highlightColor = $this->lightenColor($color, 40);
        $highlightRadius = (int)($radius * 0.35);
        imagefilledellipse($image, $cx - (int)($radius * 0.25), $cy - (int)($radius * 0.25), $highlightRadius * 2, $highlightRadius * 2, $highlightColor);
    }

    /**
     * 颜色变暗处理（用于阴影效果）
     *
     * @param int $color 原始颜色（GD颜色索引）
     * @param int $amount 变暗程度（0-100）
     * @return int 变暗后的颜色
     */
    private function darkenColor(int $color, int $amount): int
    {
        // 将GD颜色索引转换为RGB分量
        $r = ($color >> 16) & 0xFF;
        $g = ($color >> 8) & 0xFF;
        $b = $color & 0xFF;

        // 计算变暗后的RGB值
        $r = max(0, $r - $amount);
        $g = max(0, $g - $amount);
        $b = max(0, $b - $amount);

        // 重新组合为颜色值
        return ($r << 16) | ($g << 8) | $b;
    }

    /**
     * 颜色变亮处理（用于圆点高亮效果）
     *
     * @param int $color 原始颜色（GD颜色索引）
     * @param int $amount 变亮程度（0-100）
     * @return int 变亮后的颜色
     */
    private function lightenColor(int $color, int $amount): int
    {
        // 将GD颜色索引转换为RGB分量
        $r = ($color >> 16) & 0xFF;
        $g = ($color >> 8) & 0xFF;
        $b = $color & 0xFF;

        // 计算变亮后的RGB值
        $r = min(255, $r + $amount);
        $g = min(255, $g + $amount);
        $b = min(255, $b + $amount);

        // 重新组合为颜色值
        return ($r << 16) | ($g << 8) | $b;
    }

    /**
     * 绘制背景图片
     *
     * @param GdImage $image 图像资源
     * @param string $bgImagePath 背景图片路径
     * @param int $size 尺寸
     */
    private function drawBackgroundImage(GdImage $image, string $bgImagePath, int $size): void
    {
        $bgImage = $this->loadImage($bgImagePath);
        if ($bgImage === false) {
            return;
        }

        $srcWidth = imagesx($bgImage);
        $srcHeight = imagesy($bgImage);

        // 缩放背景图片到目标尺寸（保持比例或裁剪）
        $newBgImage = imagecreatetruecolor($size, $size);

        // 居中裁剪或拉伸
        if ($srcWidth > $srcHeight) {
            // 横向图片，裁剪宽度
            $newSrcWidth = $srcHeight;
            $srcX = (int)(($srcWidth - $srcHeight) / 2);
            $srcY = 0;
        } else {
            // 纵向图片，裁剪高度
            $newSrcHeight = $srcWidth;
            $srcX = 0;
            $srcY = (int)(($srcHeight - $srcWidth) / 2);
        }

        imagecopyresampled(
            $newBgImage,
            $bgImage,
            0,
            0,
            $srcX,
            $srcY,
            $size,
            $size,
            isset($newSrcWidth) ? $newSrcWidth : $srcWidth,
            isset($newSrcHeight) ? $newSrcHeight : $srcHeight
        );

        imagedestroy($bgImage);

        // 混合背景和底色
        imagecopy($image, $newBgImage, 0, 0, 0, 0, $size, $size);
        imagedestroy($newBgImage);
    }

    /**
     * 绘制Logo
     * Logo宽度严格限制在不含边距的二维码内容宽度的15%以内
     * 支持阴影、透明度、旋转等高级效果
     *
     * @param GdImage $image 图像资源
     * @param int $qrX 二维码内容区域X坐标（不含边距）
     * @param int $qrY 二维码内容区域Y坐标（不含边距）
     * @param int $qrWidth 二维码内容宽度（不含边距）
     * @param int $qrHeight 二维码内容高度（不含边距）
     * @throws Exception
     */
    private function drawLogo(GdImage $image, int $qrX, int $qrY, int $qrWidth, int $qrHeight): void
    {
        $logoImage = $this->loadLogoImage($this->logoPath);
        if ($logoImage === false) {
            throw new Exception('无法加载Logo图像');
        }

        // Logo尺寸已计算完成

        // 计算Logo尺寸（严格限制在15%以内）
        $maxLogoSize = (int)($qrWidth * 0.15);

        if ($this->logoScale > 0) {
            $logoSize = (int)($qrWidth * $this->logoScale / 100);
            $logoSize = min($logoSize, $maxLogoSize);
        } elseif ($this->logoWidth > 0 && $this->logoHeight > 0) {
            $logoSize = min($this->logoWidth, $this->logoHeight, $maxLogoSize);
        } else {
            // 自动计算，保持原始比例，但限制在15%以内
            $originalWidth = imagesx($logoImage);
            $originalHeight = imagesy($logoImage);
            $scale = $maxLogoSize / max($originalWidth, $originalHeight);
            $logoSize = (int)(min($originalWidth, $originalHeight) * $scale);
            $logoSize = min($logoSize, $maxLogoSize);
        }

        // 计算Logo居中位置
        $logoX = $qrX + (int)(($qrWidth - $logoSize) / 2);
        $logoY = $qrY + (int)(($qrHeight - $logoSize) / 2);

        // 计算边框大小（至少2个像素）
        $borderThickness = max(2, (int)($qrWidth / 40));

        // 创建临时图像处理Logo（支持圆形、圆角等效果）
        $processedLogo = imagecreatetruecolor($logoSize, $logoSize);
        if ($processedLogo === false) {
            throw new Exception('无法创建Logo处理图像');
        }

        // 填充透明背景
        imagealphablending($processedLogo, false);
        imagesavealpha($processedLogo, true);
        $transparent = imagecolorallocatealpha($processedLogo, 0, 0, 0, 127);
        imagefill($processedLogo, 0, 0, $transparent);

        // 根据选项处理Logo形状
        if ($this->logoCircular) {
            // 圆形Logo - 使用圆形遮罩
            $this->applyCircularMask($processedLogo, $logoImage, $logoSize);
        } elseif ($this->logoRounded) {
            // 圆角矩形Logo
            $this->applyRoundedMask($processedLogo, $logoImage, $logoSize, $this->logoRadius);
        } else {
            // 普通矩形Logo
            imagecopyresampled(
                $processedLogo,
                $logoImage,
                0,
                0,
                0,
                0,
                $logoSize,
                $logoSize,
                imagesx($logoImage),
                imagesy($logoImage)
            );
        }

        // 应用透明度
        if ($this->logoOpacity < 100) {
            $this->applyOpacity($processedLogo, $this->logoOpacity);
        }

        // 如果设置了Logo背景色（用于透明Logo），绘制背景
        if ($this->logoBackgroundColor !== null) {
            $bgColor = $this->logoBackgroundColor->toGdColor($image);
            $bgX = $logoX - $borderThickness;
            $bgY = $logoY - $borderThickness;
            $bgSize = $logoSize + $borderThickness * 2;

            // 根据Logo形状绘制背景
            if ($this->logoCircular) {
                imagefilledellipse($image, $bgX + $bgSize / 2, $bgY + $bgSize / 2, $bgSize, $bgSize, $bgColor);
            } else {
                imagefilledrectangle($image, $bgX, $bgY, $bgX + $bgSize - 1, $bgY + $bgSize - 1, $bgColor);
            }
        }

        // 如果设置了阴影，先绘制阴影
        if ($this->logoShadowColor !== null) {
            $this->drawLogoShadow($image, $logoX, $logoY, $logoSize, $borderThickness);
        }

        // 将处理后的Logo绘制到主图像
        if ($this->logoRotation != 0) {
            // 如果需要旋转，创建临时图像进行旋转
            $rotatedLogo = imagerotate($processedLogo, $this->logoRotation, $transparent);
            if ($rotatedLogo !== false) {
                // 计算旋转后的尺寸和位置
                $rotatedWidth = imagesx($rotatedLogo);
                $rotatedHeight = imagesy($rotatedLogo);
                $rotatedX = $logoX + (int)(($logoSize - $rotatedWidth) / 2);
                $rotatedY = $logoY + (int)(($logoSize - $rotatedHeight) / 2);

                imagecopy($image, $rotatedLogo, $rotatedX, $rotatedY, 0, 0, $rotatedWidth, $rotatedHeight);
                imagedestroy($rotatedLogo);
            } else {
                // 旋转失败，直接绘制
                imagecopy($image, $processedLogo, $logoX, $logoY, 0, 0, $logoSize, $logoSize);
            }
        } else {
            // 不旋转，直接绘制
            imagecopy($image, $processedLogo, $logoX, $logoY, 0, 0, $logoSize, $logoSize);
        }

        // 绘制白色边框
        $borderColor = imagecolorallocate($image, 255, 255, 255);

        // 根据Logo形状绘制边框
        if ($this->logoCircular) {
            // 圆形边框
            imagesetthickness($image, $borderThickness);
            imageellipse(
                $image,
                $logoX + (int)($logoSize / 2),
                $logoY + (int)($logoSize / 2),
                $logoSize + $borderThickness,
                $logoSize + $borderThickness,
                $borderColor
            );
        } else {
            // 矩形或圆角矩形边框
            if ($this->logoRounded && $this->logoRadius > 0) {
                $this->drawRoundedRectangle(
                    $image,
                    $logoX - $borderThickness,
                    $logoY - $borderThickness,
                    $logoSize + $borderThickness * 2,
                    $logoSize + $borderThickness * 2,
                    $borderColor,
                    $this->logoRadius
                );
            } else {
                // 普通矩形边框
                imagesetthickness($image, $borderThickness);
                imagerectangle(
                    $image,
                    $logoX - $borderThickness,
                    $logoY - $borderThickness,
                    $logoX + $logoSize + $borderThickness - 1,
                    $logoY + $logoSize + $borderThickness - 1,
                    $borderColor
                );
            }
        }

        // 清理资源
        imagedestroy($logoImage);
        imagedestroy($processedLogo);
    }

    /**
     * 绘制Logo阴影
     *
     * @param GdImage $image 主图像
     * @param int $logoX Logo X坐标
     * @param int $logoY Logo Y坐标
     * @param int $logoSize Logo尺寸
     * @param int $borderThickness 边框厚度
     */
    private function drawLogoShadow(GdImage $image, int $logoX, int $logoY, int $logoSize, int $borderThickness): void
    {
        if ($this->logoShadowColor === null) {
            return;
        }

        $shadowColor = $this->logoShadowColor->toGdColor($image);
        $shadowX = $logoX + $this->logoShadowOffsetX;
        $shadowY = $logoY + $this->logoShadowOffsetY;
        $shadowSize = $logoSize;

        // 根据Logo形状绘制阴影
        if ($this->logoCircular) {
            imagefilledellipse($image, $shadowX + $shadowSize / 2, $shadowY + $shadowSize / 2, $shadowSize, $shadowSize, $shadowColor);
        } else {
            if ($this->logoRounded && $this->logoRadius > 0) {
                $r = (int)($shadowSize * $this->logoRadius);
                $this->drawRoundedRectangle($image, $shadowX, $shadowY, $shadowSize, $shadowSize, $shadowColor, $this->logoRadius);
            } else {
                imagefilledrectangle($image, $shadowX, $shadowY, $shadowX + $shadowSize - 1, $shadowY + $shadowSize - 1, $shadowColor);
            }
        }
    }

    /**
     * 应用透明度到图像
     *
     * @param GdImage $image 图像资源
     * @param int $opacity 透明度（0-100）
     */
    private function applyOpacity(GdImage $image, int $opacity): void
    {
        if ($opacity >= 100) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $alpha = 127 * (100 - $opacity) / 100;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($image, $x, $y);
                $alphaChannel = ($color >> 24) & 0xFF;

                // 合并透明度
                $newAlpha = min(127, $alphaChannel + $alpha);
                $newColor = ($color & 0xFFFFFF) | ((int)round($newAlpha) << 24);

                imagesetpixel($image, (int)round($x), (int)round($y), $newColor);
            }
        }
    }

    /**
     * 加载Logo图像
     *
     * @param string $logoPath Logo路径
     * @return GdImage|false
     */
    private function loadLogoImage(string $logoPath)
    {
        $imageInfo = getimagesize($logoPath);
        if ($imageInfo === false) {
            return false;
        }

        $imageType = $imageInfo[2];

        return match($imageType) {
            IMAGETYPE_PNG => imagecreatefrompng($logoPath),
            IMAGETYPE_JPEG => imagecreatefromjpeg($logoPath),
            IMAGETYPE_GIF => imagecreatefromgif($logoPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($logoPath),
            default => false
        };
    }

    /**
     * 加载图像
     *
     * @param string $path 图像路径
     * @return GdImage|false
     */
    private function loadImage(string $path)
    {
        return $this->loadLogoImage($path);
    }

    /**
     * 绘制标签
     * 支持背景色、边框、内边距、圆角等高级样式
     * 动态计算文本高度，确保不会溢出图片安全区
     * 优化了标签居中逻辑，确保标签文本相对于最终图像宽度完美居中
     *
     * @param GdImage $image 图像资源
     * @param int $qrX 二维码X坐标
     * @param int $qrY 二维码Y坐标
     * @param int $qrWidth 二维码宽度
     * @param int $qrHeight 二维码高度
     * @throws Exception
     */
    private function drawLabel(GdImage $image, int $qrX, int $qrY, int $qrWidth, int $qrHeight): void
    {
        if ($this->labelOptions === null || !$this->labelOptions->isEnabled()) {
            return;
        }

        $fontPath = $this->labelOptions->getFontPath();
        if ($fontPath === null || !file_exists($fontPath)) {
            throw new Exception('字体文件不存在: ' . ($fontPath ?? 'null'));
        }

        // 获取最终图像尺寸
        $finalImageWidth = imagesx($image);
        $finalImageHeight = imagesy($image);

        $fontSize = $this->labelOptions->getFontSize();
        $marginTop = $this->labelOptions->getMarginTop();
        $marginLeft = $this->labelOptions->getMarginLeft();
        $marginRight = $this->labelOptions->getMarginRight();
        $alignment = $this->labelOptions->getAlignment();
        $paddingTop = $this->labelOptions->getPaddingTop();
        $paddingBottom = $this->labelOptions->getPaddingBottom();
        $paddingLeft = $this->labelOptions->getPaddingLeft();
        $paddingRight = $this->labelOptions->getPaddingRight();
        $backgroundColor = $this->labelOptions->getBackgroundColor();
        $borderColor = $this->labelOptions->getBorderColor();
        $borderWidth = $this->labelOptions->getBorderWidth();
        $borderRadius = $this->labelOptions->getBorderRadius();

        // 获取文本行
        $lines = $this->labelOptions->getLines($qrWidth);
        $lineCount = count($lines);

        // 计算每行的高度
        $lineHeight = $this->labelOptions->getLineHeight();

        // 计算标签内容区域高度（包含内边距）
        $contentHeight = $lineCount * $lineHeight + $paddingTop + $paddingBottom;

        // 计算总标签高度（包含外边距）- 变量将在后续版本中使用
        // $totalLabelHeight = $contentHeight + $marginTop + $this->labelOptions->getMarginBottom();

        // 检查标签是否超出图像底部
        // $labelY = $qrY + $qrHeight + $marginTop;
        $labelY = $qrHeight + $marginTop - $qrY * 2;

        if ($labelY + $contentHeight > $finalImageHeight) {
            // 如果会超出，缩小字号
            $newFontSize = $fontSize;
            do {
                $newFontSize -= 1;
                if ($newFontSize < 8) {
                    break;
                }

                // 重新计算行高和高度
                $newLineHeight = max($newFontSize, (int)($newFontSize * 1.2));
                $newContentHeight = $lineCount * $newLineHeight + $paddingTop + $paddingBottom;

                if ($labelY + $newContentHeight <= $finalImageHeight) {
                    $fontSize = $newFontSize;
                    $lineHeight = $newLineHeight;
                    $contentHeight = $newContentHeight;
                    break;
                }
            } while ($newFontSize >= 8);
        }

        // 计算标签区域位置和大小
        // 优化：标签应该相对于最终图像宽度居中，而不是相对于二维码宽度
        // 如果设置了左右外边距为0，则标签宽度等于二维码宽度
        // 否则，标签宽度为二维码宽度减去左右外边距
        if ($marginLeft === 0 && $marginRight === 0) {
            // 默认情况：标签宽度等于二维码宽度，标签X坐标相对于最终图像居中
            $labelAreaWidth = $qrWidth;
            $labelX = (int)(($finalImageWidth - $labelAreaWidth) / 2);
        } else {
            // 有外边距的情况
            $labelAreaWidth = $qrWidth - $marginLeft - $marginRight;
            $labelX = $qrX + $marginLeft;
        }

        // 绘制标签背景（如果有）
        if ($backgroundColor !== null) {
            $bgColor = $backgroundColor->toGdColor($image);

            // 绘制圆角矩形背景
            if ($borderRadius > 0) {
                $this->drawRoundedRectangle($image, $labelX, $labelY, $labelAreaWidth, $contentHeight, $bgColor, $borderRadius);
            } else {
                imagefilledrectangle($image, $labelX, $labelY, $labelX + $labelAreaWidth - 1, $labelY + $contentHeight - 1, $bgColor);
            }
        }

        // 绘制边框（如果有）
        if ($borderWidth > 0 && $borderColor !== null) {
            $bdColor = $borderColor->toGdColor($image);
            imagesetthickness($image, $borderWidth);

            if ($borderRadius > 0) {
                // 绘制圆角边框
                $this->drawRoundedRectangleBorder($image, $labelX, $labelY, $labelAreaWidth, $contentHeight, $bdColor, $borderWidth, $borderRadius);
            } else {
                // 绘制普通矩形边框
                imagerectangle($image, $labelX, $labelY, $labelX + $labelAreaWidth - 1, $labelY + $contentHeight - 1, $bdColor);
            }
        }

        // 绘制文本
        $textColor = $this->labelOptions->getColor()->toGdColor($image);
        $textStartY = $labelY + $paddingTop + $fontSize;

        foreach ($lines as $index => $line) {
            // 计算文本宽度
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            if ($bbox === false) {
                continue;
            }

            $textWidth = abs($bbox[2] - $bbox[0]);

            // 计算可用文本宽度（考虑内边距）
            $availableTextWidth = $labelAreaWidth - $paddingLeft - $paddingRight;

            // 计算文本X坐标
            // 优化：确保文本在标签区域内正确对齐
            $textX = match($alignment) {
                'left' => $labelX + $paddingLeft,
                'right' => $labelX + $labelAreaWidth - $textWidth - $paddingRight,
                'center' => $labelX + $paddingLeft + (int)(($availableTextWidth - $textWidth) / 2),
                default => $labelX + $paddingLeft + (int)(($availableTextWidth - $textWidth) / 2)
            };

            $y = $textStartY + $index * $lineHeight;

            // 绘制文本阴影（如果启用）
            if ($this->labelOptions->hasTextShadow()) {
                $shadowColor = $this->labelOptions->getShadowColor()->toGdColor($image);
                $shadowX = $textX + $this->labelOptions->getShadowOffsetX();
                $shadowY = $y + $this->labelOptions->getShadowOffsetY();
                imagettftext($image, $fontSize, 0, $shadowX, $shadowY, $shadowColor, $fontPath, $line);
            }

            // 绘制文本描边（如果启用）
            if ($this->labelOptions->hasTextStroke()) {
                $strokeColor = $this->labelOptions->getStrokeColor()->toGdColor($image);
                $strokeWidth = $this->labelOptions->getStrokeWidth();

                // 通过多次绘制实现描边效果
                for ($sx = -$strokeWidth; $sx <= $strokeWidth; $sx++) {
                    for ($sy = -$strokeWidth; $sy <= $strokeWidth; $sy++) {
                        // 跳过中心点（主文本位置）
                        if ($sx == 0 && $sy == 0) {
                            continue;
                        }
                        imagettftext($image, $fontSize, 0, $textX + $sx, $y + $sy, $strokeColor, $fontPath, $line);
                    }
                }
            }

            // 绘制主文本
            imagettftext($image, $fontSize, 0, $textX, $y, $textColor, $fontPath, $line);
        }
    }

    /**
     * 绘制圆角矩形（填充）
     *
     * @param GdImage $image 图像资源
     * @param int $x 左上角X坐标
     * @param int $y 左上角Y坐标
     * @param int $width 宽度
     * @param int $height 高度
     * @param int $color 颜色
     * @param float $radius 圆角半径（0-1）
     */
    private function drawRoundedRectangle(GdImage $image, int $x, int $y, int $width, int $height, int $color, float $radius): void
    {
        $r = (int)($height * $radius);

        // 绘制中心矩形
        imagefilledrectangle($image, $x + $r, $y, $x + $width - $r - 1, $y + $height - 1, $color);

        // 绘制左右两侧的圆形
        imagefilledellipse($image, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($image, $x + $width - $r - 1, $y + $r, $r * 2, $r * 2, $color);

        // 填充左右两侧
        imagefilledrectangle($image, $x, $y + $r, $x + $r, $y + $height - $r - 1, $color);
        imagefilledrectangle($image, $x + $width - $r - 1, $y + $r, $x + $width - 1, $y + $height - $r - 1, $color);
    }

    /**
     * 绘制圆角矩形边框
     *
     * @param GdImage $image 图像资源
     * @param int $x 左上角X坐标
     * @param int $y 左上角Y坐标
     * @param int $width 宽度
     * @param int $height 高度
     * @param int $color 颜色
     * @param int $borderWidth 边框宽度
     * @param float $radius 圆角半径（0-1）
     */
    private function drawRoundedRectangleBorder(GdImage $image, int $x, int $y, int $width, int $height, int $color, int $borderWidth, float $radius): void
    {
        $r = (int)($height * $radius);

        // 绘制四条边
        imagerectangle($image, $x + $r, $y, $x + $width - $r - 1, $y + $borderWidth - 1, $color);
        imagerectangle($image, $x + $r, $y + $height - $borderWidth, $x + $width - $r - 1, $y + $height - 1, $color);
        imagerectangle($image, $x, $y + $r, $x + $borderWidth - 1, $y + $height - $r - 1, $color);
        imagerectangle($image, $x + $width - $borderWidth, $y + $r, $x + $width - 1, $y + $height - $r - 1, $color);

        // 绘制四个圆角
        imagearc($image, $x + $r, $y + $r, $r * 2, $r * 2, 180, 270, $color);
        imagearc($image, $x + $width - $r - 1, $y + $r, $r * 2, $r * 2, 270, 0, $color);
        imagearc($image, $x + $r, $y + $height - $r - 1, $r * 2, $r * 2, 90, 180, $color);
        imagearc($image, $x + $width - $r - 1, $y + $height - $r - 1, $r * 2, $r * 2, 0, 90, $color);
    }

    /**
     * 生成二维码字符串
     *
     * @return string 二维码图像数据
     */
    public function toString(): string
    {
        $image = $this->render();

        ob_start();
        match($this->format) {
            'png' => imagepng($image, null, -1),
            'jpeg', 'jpg' => imagejpeg($image, null, $this->quality),
            'gif' => imagegif($image),
            default => throw new Exception('不支持的格式')
        };
        $data = ob_get_clean();

        imagedestroy($image);
        return $data;
    }

    /**
     * 生成二维码并保存到文件
     *
     * @param string $filename 文件名
     * @return bool
     */
    public function save(string $filename): bool
    {
        $directory = dirname($filename);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $image = $this->render();

        $result = match($this->format) {
            'png' => imagepng($image, $filename, -1),
            'jpeg', 'jpg' => imagejpeg($image, $filename, $this->quality),
            'gif' => imagegif($image, $filename),
            'webp' => imagewebp($image, $filename, $this->quality),
            default => throw new Exception('不支持的格式')
        };

        imagedestroy($image);
        return $result;
    }

    /**
     * 输出二维码到浏览器
     *
     * @return void
     */
    public function output(): void
    {
        $mimeType = match($this->format) {
            'png' => 'image/png',
            'jpeg', 'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/png'
        };

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="qrcode.' . $this->format . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 1 Jan 2000 00:00:00 GMT');

        echo $this->toString();
    }

    /**
     * 获取二维码的Base64编码
     *
     * @return string Base64编码
     */
    public function toBase64(): string
    {
        $mimeType = match($this->format) {
            'png' => 'image/png',
            'jpeg', 'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/png'
        };

        return 'data:' . $mimeType . ';base64,' . base64_encode($this->toString());
    }

    /**
     * 魔术方法，转换为字符串
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * 获取二维码数据
     *
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * 获取二维码尺寸
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * 获取边距
     *
     * @return int
     */
    public function getMargin(): int
    {
        return $this->margin;
    }

    /**
     * 获取错误纠正级别
     *
     * @return ErrorCorrectionLevel
     */
    public function getErrorCorrectionLevel(): ErrorCorrectionLevel
    {
        return $this->errorCorrectionLevel;
    }

    /**
     * 获取前景色
     *
     * @return Color
     */
    public function getForegroundColor(): Color
    {
        return $this->foregroundColor;
    }

    /**
     * 获取背景色
     *
     * @return Color
     */
    public function getBackgroundColor(): Color
    {
        return $this->backgroundColor;
    }

    /**
     * 生成WiFi二维码
     *
     * @param string $ssid WiFi名称
     * @param string $password WiFi密码
     * @param string $encryption 加密方式（WPA/WEP/nopass）
     * @param bool $hidden 是否隐藏
     * @return self
     */
    public static function wifi(string $ssid, string $password = '', string $encryption = 'WPA', bool $hidden = false): self
    {
        $wifiString = sprintf(
            'WIFI:T:%s;S:%s;P:%s;%s;;',
            $encryption,
            $ssid,
            $password,
            $hidden ? 'H:true' : ''
        );
        return self::make($wifiString);
    }

    /**
     * 生成电话号码二维码
     *
     * @param string $phone 电话号码
     * @return self
     */
    public static function phone(string $phone): self
    {
        return self::make('tel:' . $phone);
    }

    /**
     * 生成电子邮件二维码
     *
     * @param string $email 邮件地址
     * @param string $subject 主题（可选）
     * @param string $body 正文（可选）
     * @return self
     */
    public static function email(string $email, string $subject = '', string $body = ''): self
    {
        $mailString = 'mailto:' . $email;
        if ($subject !== '' || $body !== '') {
            $params = [];
            if ($subject !== '') {
                $params[] = 'subject=' . rawurlencode($subject);
            }
            if ($body !== '') {
                $params[] = 'body=' . rawurlencode($body);
            }
            $mailString .= '?' . implode('&', $params);
        }
        return self::make($mailString);
    }

    /**
     * 生成短信二维码
     *
     * @param string $phone 电话号码
     * @param string $message 短信内容
     * @return self
     */
    public static function sms(string $phone, string $message = ''): self
    {
        $smsString = 'smsto:' . $phone;
        if ($message !== '') {
            $smsString .= ':' . rawurlencode($message);
        }
        return self::make($smsString);
    }

    /**
     * 生成联系名片（VCard）二维码
     *
     * @param array $data 名片数据
     * @return self
     */
    public static function vcard(array $data): self
    {
        $vcard = "BEGIN:VCARD\nVERSION:3.0\n";
        
        if (isset($data['name'])) {
            $vcard .= 'FN:' . $data['name'] . "\n";
        }
        if (isset($data['title'])) {
            $vcard .= 'TITLE:' . $data['title'] . "\n";
        }
        if (isset($data['phone'])) {
            $vcard .= 'TEL;TYPE=CELL:' . $data['phone'] . "\n";
        }
        if (isset($data['email'])) {
            $vcard .= 'EMAIL:' . $data['email'] . "\n";
        }
        if (isset($data['url'])) {
            $vcard .= 'URL:' . $data['url'] . "\n";
        }
        if (isset($data['address'])) {
            $vcard .= 'ADR:' . $data['address'] . "\n";
        }
        if (isset($data['company'])) {
            $vcard .= 'ORG:' . $data['company'] . "\n";
        }
        
        $vcard .= "END:VCARD";
        return self::make($vcard);
    }

    /**
     * 生成日历事件二维码
     *
     * @param string $title 事件标题
     * @param string $start 开始时间（格式：YYYYMMDDTHHMMSS）
     * @param string $end 结束时间（格式：YYYYMMDDTHHMMSS）
     * @param string $location 地点
     * @param string $description 描述
     * @return self
     */
    public static function calendar(string $title, string $start, string $end = '', string $location = '', string $description = ''): self
    {
        $vevent = "BEGIN:VEVENT\nSUMMARY:$title\nDTSTART:$start\n";
        if ($end !== '') {
            $vevent .= "DTEND:$end\n";
        }
        if ($location !== '') {
            $vevent .= "LOCATION:$location\n";
        }
        if ($description !== '') {
            $vevent .= "DESCRIPTION:$description\n";
        }
        $vevent .= "END:VEVENT";
        return self::make($vevent);
    }

    /**
     * 生成地理位置二维码
     *
     * @param float $latitude 纬度
     * @param float $longitude 经度
     * @return self
     */
    public static function geo(float $latitude, float $longitude): self
    {
        $geoString = sprintf('geo:%.6f,%.6f', $latitude, $longitude);
        return self::make($geoString);
    }

    /**
     * 生成WhatsApp消息二维码
     *
     * @param string $phone 电话号码（带国家码，如：8613800138000）
     * @param string $message 预设消息
     * @return self
     */
    public static function whatsapp(string $phone, string $message = ''): self
    {
        $whatsappUrl = 'https://wa.me/' . $phone;
        if ($message !== '') {
            $whatsappUrl .= '/?text=' . rawurlencode($message);
        }
        return self::make($whatsappUrl);
    }

    /**
     * 生成Skype呼叫二维码
     *
     * @param string $username Skype用户名
     * @return self
     */
    public static function skype(string $username): self
    {
        return self::make('skype:' . $username . '?call');
    }

    /**
     * 生成Zoom会议二维码
     *
     * @param string $meetingId 会议ID
     * @param string $password 会议密码（可选）
     * @return self
     */
    public static function zoom(string $meetingId, string $password = ''): self
    {
        $zoomUrl = 'https://zoom.us/j/' . $meetingId;
        if ($password !== '') {
            $zoomUrl .= '?pwd=' . $password;
        }
        return self::make($zoomUrl);
    }

    /**
     * 生成PayPal支付二维码
     *
     * @param string $recipient 收款人邮箱或手机号
     * @param float $amount 金额
     * @param string $currency 货币代码（如：USD, EUR, CNY）
     * @param string $note 备注
     * @return self
     */
    public static function paypal(string $recipient, float $amount, string $currency = 'USD', string $note = ''): self
    {
        $paypalUrl = sprintf('https://paypal.me/%s/%.2f%s', $recipient, $amount, $currency);
        if ($note !== '') {
            $paypalUrl .= '?note=' . rawurlencode($note);
        }
        return self::make($paypalUrl);
    }

    /**
     * 生成加密货币地址二维码
     *
     * @param string $cryptoType 加密货币类型（bitcoin, ethereum, litecoin等）
     * @param string $address 钱包地址
     * @param float $amount 金额（可选）
     * @return self
     */
    public static function crypto(string $cryptoType, string $address, float $amount = 0): self
    {
        $cryptoType = strtolower($cryptoType);
        $cryptoUrl = $cryptoType . ':' . $address;

        if ($amount > 0) {
            $cryptoUrl .= '?amount=' . $amount;
        }

        return self::make($cryptoUrl);
    }

    /**
     * 生成App Store应用二维码
     *
     * @param string $appId 应用ID
     * @param string $platform 平台（ios, android）
     * @return self
     */
    public static function appStore(string $appId, string $platform = 'ios'): self
    {
        $platform = strtolower($platform);
        if ($platform === 'android') {
            $url = 'https://play.google.com/store/apps/details?id=' . $appId;
        } else {
            $url = 'https://apps.apple.com/app/id' . $appId;
        }
        return self::make($url);
    }

    /**
     * 生成社交媒体二维码
     *
     * @param string $platform 平台（facebook, twitter, instagram, linkedin, tiktok, wechat, weibo）
     * @param string $username 用户名或ID
     * @return self
     */
    public static function social(string $platform, string $username): self
    {
        $platform = strtolower($platform);
        $urls = [
            'facebook' => 'https://www.facebook.com/',
            'twitter' => 'https://twitter.com/',
            'instagram' => 'https://www.instagram.com/',
            'linkedin' => 'https://www.linkedin.com/in/',
            'tiktok' => 'https://www.tiktok.com/@',
            'wechat' => 'https://wx.qq.com/',
            'weibo' => 'https://weibo.com/n/'
        ];

        if (!isset($urls[$platform])) {
            throw new Exception('不支持的社交媒体平台: ' . $platform);
        }

        return self::make($urls[$platform] . $username);
    }

    /**
     * 生成企业微信二维码
     *
     * @param string $corpid 企业ID
     * @param string $agentid 应用AgentID
     * @return self
     */
    public static function wechatWork(string $corpid, string $agentid): self
    {
        $wxWorkUrl = sprintf('https://work.weixin.qq.com/kfid/kfc%s', $corpid);
        return self::make($wxWorkUrl);
    }

    /**
     * 生成抖音二维码
     *
     * @param string $secUid 抖音用户sec_uid
     * @return self
     */
    public static function douyin(string $secUid): self
    {
        return self::make('https://www.douyin.com/user/' . $secUid);
    }

    /**
     * 生成文件下载二维码
     *
     * @param string $fileUrl 文件URL
     * @param string $filename 文件名（可选）
     * @return self
     */
    public static function download(string $fileUrl, string $filename = ''): self
    {
        if ($filename !== '') {
            $fileUrl .= '#filename=' . rawurlencode($filename);
        }
        return self::make($fileUrl);
    }

    /**
     * 生成评分/评价二维码
     *
     * @param string $platform 平台（google, yelp, tripadvisor等）
     * @param string $businessId 商家ID
     * @return self
     */
    public static function review(string $platform, string $businessId): self
    {
        $platform = strtolower($platform);
        $urls = [
            'google' => 'https://search.google.com/local/writereview?placeid=',
            'yelp' => 'https://www.yelp.com/writeareview/biz/',
            'tripadvisor' => 'https://www.tripadvisor.com/UserReview-',
            'facebook' => 'https://www.facebook.com/reviews/'
        ];

        if (!isset($urls[$platform])) {
            throw new Exception('不支持的评价平台: ' . $platform);
        }

        return self::make($urls[$platform] . $businessId);
    }

    /**
     * 应用圆形遮罩到Logo
     *
     * @param GdImage $dest 目标图像
     * @param GdImage $source 源Logo图像
     * @param int $size Logo尺寸
     */
    private function applyCircularMask(GdImage $dest, GdImage $source, int $size): void
    {
        // 创建圆形遮罩
        $mask = imagecreatetruecolor($size, $size);
        if ($mask === false) {
            return;
        }
        imagealphablending($mask, false);
        imagesavealpha($mask, true);

        $transparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
        imagefill($mask, 0, 0, $transparent);

        $white = imagecolorallocate($mask, 255, 255, 255);
        imagefilledellipse($mask, (int)($size / 2), (int)($size / 2), $size, $size, $white);

        // 应用遮罩
        $resizedLogo = imagecreatetruecolor($size, $size);
        if ($resizedLogo === false) {
            imagedestroy($mask);
            return;
        }
        imagealphablending($resizedLogo, false);
        imagesavealpha($resizedLogo, true);
        imagefill($resizedLogo, 0, 0, $transparent);

        imagecopyresampled(
            $resizedLogo,
            $source,
            0,
            0,
            0,
            0,
            $size,
            $size,
            imagesx($source),
            imagesy($source)
        );

        // 应用遮罩
        imagealphablending($resizedLogo, true);
        imagecopy($resizedLogo, $mask, 0, 0, 0, 0, $size, $size);

        // 复制到目标
        imagecopy($dest, $resizedLogo, 0, 0, 0, 0, $size, $size);

        imagedestroy($mask);
        imagedestroy($resizedLogo);
    }

    /**
     * 应用圆角矩形遮罩到Logo
     *
     * @param GdImage $dest 目标图像
     * @param GdImage $source 源Logo图像
     * @param int $size Logo尺寸
     * @param float $radius 圆角半径（0-1）
     */
    private function applyRoundedMask(GdImage $dest, GdImage $source, int $size, float $radius): void
    {
        // 创建圆角遮罩
        $mask = imagecreatetruecolor($size, $size);
        if ($mask === false) {
            return;
        }
        imagealphablending($mask, false);
        imagesavealpha($mask, true);

        $transparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
        imagefill($mask, 0, 0, $transparent);

        $white = imagecolorallocate($mask, 255, 255, 255);
        $r = (int)($size * $radius);

        // 绘制圆角矩形
        imagefilledrectangle($mask, $r, 0, $size - $r - 1, $size - 1, $white);
        imagefilledrectangle($mask, 0, $r, $size - 1, $size - $r - 1, $white);
        imagefilledellipse($mask, $r, $r, $r * 2, $r * 2, $white);
        imagefilledellipse($mask, $size - $r - 1, $r, $r * 2, $r * 2, $white);
        imagefilledellipse($mask, $r, $size - $r - 1, $r * 2, $r * 2, $white);
        imagefilledellipse($mask, $size - $r - 1, $size - $r - 1, $r * 2, $r * 2, $white);

        // 应用遮罩
        $resizedLogo = imagecreatetruecolor($size, $size);
        if ($resizedLogo === false) {
            imagedestroy($mask);
            return;
        }
        imagealphablending($resizedLogo, false);
        imagesavealpha($resizedLogo, true);
        imagefill($resizedLogo, 0, 0, $transparent);

        imagecopyresampled(
            $resizedLogo,
            $source,
            0,
            0,
            0,
            0,
            $size,
            $size,
            imagesx($source),
            imagesy($source)
        );

        // 应用遮罩
        imagealphablending($resizedLogo, true);
        imagecopy($resizedLogo, $mask, 0, 0, 0, 0, $size, $size);

        // 复制到目标
        imagecopy($dest, $resizedLogo, 0, 0, 0, 0, $size, $size);

        imagedestroy($mask);
        imagedestroy($resizedLogo);
    }

    /**
     * 生成Telegram消息二维码
     *
     * @param string $username Telegram用户名（不含@）
     * @param string $message 预设消息
     * @return self
     */
    public static function telegram(string $username, string $message = ''): self
    {
        $telegramUrl = 'https://t.me/' . $username;
        if ($message !== '') {
            $telegramUrl .= '?text=' . rawurlencode($message);
        }
        return self::make($telegramUrl);
    }

    /**
     * 生成Discord邀请二维码
     *
     * @param string $inviteCode Discord邀请码
     * @return self
     */
    public static function discord(string $inviteCode): self
    {
        return self::make('https://discord.gg/' . $inviteCode);
    }

    /**
     * 生成Slack工作区二维码
     *
     * @param string $workspace Slack工作区域名
     * @param string $channel 频道ID（可选）
     * @return self
     */
    public static function slack(string $workspace, string $channel = ''): self
    {
        $slackUrl = 'https://' . $workspace . '.slack.com';
        if ($channel !== '') {
            $slackUrl .= '/archives/' . $channel;
        }
        return self::make($slackUrl);
    }

    /**
     * 生成YouTube视频二维码
     *
     * @param string $videoId YouTube视频ID
     * @return self
     */
    public static function youtube(string $videoId): self
    {
        return self::make('https://www.youtube.com/watch?v=' . $videoId);
    }

    /**
     * 生成Spotify音乐二维码
     *
     * @param string $trackId Spotify音乐ID
     * @return self
     */
    public static function spotify(string $trackId): self
    {
        return self::make('https://open.spotify.com/track/' . $trackId);
    }

    /**
     * 生成LinkedIn个人主页二维码
     *
     * @param string $profileId LinkedIn个人ID
     * @return self
     */
    public static function linkedinProfile(string $profileId): self
    {
        return self::make('https://www.linkedin.com/in/' . $profileId);
    }

    /**
     * 生成GitHub仓库二维码
     *
     * @param string $username GitHub用户名
     * @param string $repository 仓库名（可选）
     * @return self
     */
    public static function github(string $username, string $repository = ''): self
    {
        $githubUrl = 'https://github.com/' . $username;
        if ($repository !== '') {
            $githubUrl .= '/' . $repository;
        }
        return self::make($githubUrl);
    }

    /**
     * 生成会议签到二维码
     *
     * @param string $eventId 活动/会议ID
     * @param string $checkinCode 签到验证码
     * @param string $eventName 活动名称
     * @return self
     */
    public static function eventCheckin(string $eventId, string $checkinCode, string $eventName = ''): self
    {
        $data = json_encode([
            'type' => 'event_checkin',
            'event_id' => $eventId,
            'checkin_code' => $checkinCode,
            'event_name' => $eventName,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成预约预订二维码
     *
     * @param string $serviceId 服务ID
     * @param string $bookingId 预订ID
     * @param string $datetime 预约时间
     * @return self
     */
    public static function booking(string $serviceId, string $bookingId, string $datetime): self
    {
        $data = json_encode([
            'type' => 'booking',
            'service_id' => $serviceId,
            'booking_id' => $bookingId,
            'datetime' => $datetime
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成优惠券二维码
     *
     * @param string $couponCode 优惠码
     * @param float $discount 折扣金额
     * @param string $expiryDate 过期日期
     * @param string $description 描述
     * @return self
     */
    public static function coupon(string $couponCode, float $discount, string $expiryDate = '', string $description = ''): self
    {
        $data = json_encode([
            'type' => 'coupon',
            'coupon_code' => $couponCode,
            'discount' => $discount,
            'expiry_date' => $expiryDate,
            'description' => $description
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成会员/忠诚卡二维码
     *
     * @param string $cardNumber 会员卡号
     * @param string $memberName 会员姓名
     * @param int $points 积分余额
     * @param string $tier 会员等级
     * @return self
     */
    public static function loyaltyCard(string $cardNumber, string $memberName = '', int $points = 0, string $tier = ''): self
    {
        $data = json_encode([
            'type' => 'loyalty_card',
            'card_number' => $cardNumber,
            'member_name' => $memberName,
            'points' => $points,
            'tier' => $tier
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成产品溯源/防伪二维码
     *
     * @param string $productId 产品ID
     * @param string $batchNumber 批次号
     * @param string $productionDate 生产日期
     * @param string $authenticityCode 防伪码
     * @return self
     */
    public static function productTraceability(string $productId, string $batchNumber = '', string $productionDate = '', string $authenticityCode = ''): self
    {
        $data = json_encode([
            'type' => 'product_traceability',
            'product_id' => $productId,
            'batch_number' => $batchNumber,
            'production_date' => $productionDate,
            'authenticity_code' => $authenticityCode,
            'verify_url' => 'https://verify.example.com/product/' . $productId
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成健康码/通行码二维码
     *
     * @param string $userId 用户ID
     * @param string $healthStatus 健康状态（green/yellow/red）
     * @param string $testDate 检测日期
     * @param string $validUntil 有效期至
     * @return self
     */
    public static function healthPass(string $userId, string $healthStatus = 'green', string $testDate = '', string $validUntil = ''): self
    {
        $data = json_encode([
            'type' => 'health_pass',
            'user_id' => $userId,
            'health_status' => $healthStatus,
            'test_date' => $testDate,
            'valid_until' => $validUntil,
            'generated_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成疫苗接种证明二维码
     *
     * @param string $certificateId 证书ID
     * @param string $vaccineName 疫苗名称
     * @param string $vaccinationDate 接种日期
     * @param int $doseNumber 剂次
     * @return self
     */
    public static function vaccineCertificate(string $certificateId, string $vaccineName, string $vaccinationDate, int $doseNumber = 1): self
    {
        $data = json_encode([
            'type' => 'vaccine_certificate',
            'certificate_id' => $certificateId,
            'vaccine_name' => $vaccineName,
            'vaccination_date' => $vaccinationDate,
            'dose_number' => $doseNumber,
            'total_doses' => 2
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成票务/入场券二维码
     *
     * @param string $ticketId 票券ID
     * @param string $eventName 活动名称
     * @param string $venue 场馆
     * @param string $seat 座位号
     * @param string $datetime 日期时间
     * @return self
     */
    public static function ticket(string $ticketId, string $eventName, string $venue, string $seat = '', string $datetime = ''): self
    {
        $data = json_encode([
            'type' => 'ticket',
            'ticket_id' => $ticketId,
            'event_name' => $eventName,
            'venue' => $venue,
            'seat' => $seat,
            'datetime' => $datetime
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成证书/凭证二维码
     *
     * @param string $credentialId 凭证ID
     * @param string $credentialName 凭证名称
     * @param string $recipientName 获得者姓名
     * @param string $issueDate 颁发日期
     * @param string $expiryDate 过期日期
     * @return self
     */
    public static function credential(string $credentialId, string $credentialName, string $recipientName, string $issueDate, string $expiryDate = ''): self
    {
        $data = json_encode([
            'type' => 'credential',
            'credential_id' => $credentialId,
            'credential_name' => $credentialName,
            'recipient_name' => $recipientName,
            'issue_date' => $issueDate,
            'expiry_date' => $expiryDate
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成快递/物流单号二维码
     *
     * @param string $trackingNumber 快递单号
     * @param string $carrier 快递公司（如：sf, yt, zt, sto等）
     * @param string $recipientPhone 收件人手机号（后4位）
     * @return self
     */
    public static function express(string $trackingNumber, string $carrier = '', string $recipientPhone = ''): self
    {
        $data = json_encode([
            'type' => 'express',
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier,
            'recipient_phone' => $recipientPhone,
            'query_url' => 'https://www.kuaidi100.com/query?nu=' . $trackingNumber
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成发票/收据二维码
     *
     * @param string $invoiceNumber 发票号码
     * @param string $invoiceCode 发票代码
     * @param float $amount 发票金额
     * @param string $date 开票日期
     * @param string $sellerName 销售方名称
     * @return self
     */
    public static function invoice(string $invoiceNumber, string $invoiceCode, float $amount, string $date, string $sellerName = ''): self
    {
        $data = json_encode([
            'type' => 'invoice',
            'invoice_number' => $invoiceNumber,
            'invoice_code' => $invoiceCode,
            'amount' => $amount,
            'date' => $date,
            'seller_name' => $sellerName,
            'verify_url' => 'https://inv-veri.chinatax.gov.cn/'
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成餐厅/点餐二维码
     *
     * @param string $restaurantId 餐厅ID
     * @param int $tableNumber 桌号
     * @param string $orderType 点餐类型（dine_in/takeaway）
     * @return self
     */
    public static function restaurantMenu(string $restaurantId, int $tableNumber = 0, string $orderType = 'dine_in'): self
    {
        $data = json_encode([
            'type' => 'restaurant_menu',
            'restaurant_id' => $restaurantId,
            'table_number' => $tableNumber,
            'order_type' => $orderType,
            'menu_url' => 'https://menu.example.com/' . $restaurantId
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成停车/缴费二维码
     *
     * @param string $parkingLotId 停车场ID
     * @param string $plateNumber 车牌号
     * @param string $entryTime 入场时间
     * @return self
     */
    public static function parking(string $parkingLotId, string $plateNumber, string $entryTime): self
    {
        $data = json_encode([
            'type' => 'parking',
            'parking_lot_id' => $parkingLotId,
            'plate_number' => $plateNumber,
            'entry_time' => $entryTime,
            'payment_url' => 'https://pay.example.com/parking/' . $parkingLotId
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成问卷调查二维码
     *
     * @param string $surveyId 问卷ID
     * @param string $userId 用户ID（可选）
     * @param string $campaign 活动名称（可选）
     * @return self
     */
    public static function survey(string $surveyId, string $userId = '', string $campaign = ''): self
    {
        $surveyUrl = 'https://survey.example.com/s/' . $surveyId;
        $params = [];
        if ($userId !== '') {
            $params['uid'] = $userId;
        }
        if ($campaign !== '') {
            $params['campaign'] = $campaign;
        }
        if (!empty($params)) {
            $surveyUrl .= '?' . http_build_query($params);
        }
        return self::make($surveyUrl);
    }

    /**
     * 生成设备/产品注册二维码
     *
     * @param string $serialNumber 设备序列号
     * @param string $productModel 产品型号
     * @param string $purchaseDate 购买日期
     * @return self
     */
    public static function productRegistration(string $serialNumber, string $productModel, string $purchaseDate): self
    {
        $data = json_encode([
            'type' => 'product_registration',
            'serial_number' => $serialNumber,
            'product_model' => $productModel,
            'purchase_date' => $purchaseDate,
            'registration_url' => 'https://register.example.com/'
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成电子签名/合同二维码
     *
     * @param string $documentId 文档ID
     * @param string $signerName 签署人姓名
     * @param string $signerEmail 签署人邮箱
     * @return self
     */
    public static function esignature(string $documentId, string $signerName, string $signerEmail): self
    {
        $data = json_encode([
            'type' => 'esignature',
            'document_id' => $documentId,
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'sign_url' => 'https://sign.example.com/doc/' . $documentId
        ], JSON_UNESCAPED_UNICODE);
        return self::make($data);
    }

    /**
     * 生成NFC标签二维码
     *
     * @param string $tagId NFC标签ID
     * @param string $action 动作（open_url/write_data等）
     * @param string $data 数据内容
     * @return self
     */
    public static function nfcTag(string $tagId, string $action, string $data): self
    {
        $nfcData = json_encode([
            'type' => 'nfc_tag',
            'tag_id' => $tagId,
            'action' => $action,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        return self::make($nfcData);
    }

    /**
     * 生成蓝牙设备配对二维码
     *
     * @param string $deviceName 设备名称
     * @param string $macAddress MAC地址
     * @param string $pin 配对PIN码（可选）
     * @return self
     */
    public static function bluetoothPair(string $deviceName, string $macAddress, string $pin = ''): self
    {
        $btData = json_encode([
            'type' => 'bluetooth_pair',
            'device_name' => $deviceName,
            'mac_address' => $macAddress,
            'pin' => $pin
        ], JSON_UNESCAPED_UNICODE);
        return self::make($btData);
    }

    /**
     * 生成智能家居设备配置二维码
     *
     * @param string $deviceId 设备ID
     * @param string $deviceType 设备类型（light/switch/sensor/lock等）
     * @param string $wifiSsid WiFi名称
     * @param string $wifiPassword WiFi密码
     * @return self
     */
    public static function smartHome(string $deviceId, string $deviceType, string $wifiSsid, string $wifiPassword): self
    {
        $smartHomeData = json_encode([
            'type' => 'smart_home_config',
            'device_id' => $deviceId,
            'device_type' => $deviceType,
            'wifi_ssid' => $wifiSsid,
            'wifi_password' => $wifiPassword,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        return self::make($smartHomeData);
    }

    /**
     * 生成车载导航二维码
     *
     * @param float $latitude 目的地纬度
     * @param float $longitude 目的地经度
     * @param string $destinationName 目的地名称
     * @param string $travelMode 出行方式（driving/walking/transit/bicycling）
     * @return self
     */
    public static function carNavigation(float $latitude, float $longitude, string $destinationName = '', string $travelMode = 'driving'): self
    {
        $navUrl = "https://www.google.com/maps/dir/?api=1&destination={$latitude},{$longitude}&travelmode={$travelMode}";
        if ($destinationName !== '') {
            $navUrl .= '&destination_place_id=' . rawurlencode($destinationName);
        }
        return self::make($navUrl);
    }

    /**
     * 生成语音助手指令二维码
     *
     * @param string $assistant 助手类型（siri/google_alexa/xiaoai等）
     * @param string $command 指令内容
     * @return self
     */
    public static function voiceAssistant(string $assistant, string $command): self
    {
        $voiceData = json_encode([
            'type' => 'voice_command',
            'assistant' => $assistant,
            'command' => $command
        ], JSON_UNESCAPED_UNICODE);
        return self::make($voiceData);
    }

    /**
     * 生成物联网设备二维码
     *
     * @param string $deviceId 设备ID
     * @param string $deviceModel 设备型号
     * @param string $firmwareVersion 固件版本
     * @param string $serverUrl 服务器URL
     * @return self
     */
    public static function iotDevice(string $deviceId, string $deviceModel, string $firmwareVersion, string $serverUrl): self
    {
        $iotData = json_encode([
            'type' => 'iot_device',
            'device_id' => $deviceId,
            'device_model' => $deviceModel,
            'firmware_version' => $firmwareVersion,
            'server_url' => $serverUrl,
            'registration_time' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        return self::make($iotData);
    }

    /**
     * 生成AR/VR体验二维码
     *
     * @param string $experienceId 体验ID
     * @param string $experienceType 体验类型（ar/vr/mr）
     * @param string $assetUrl 资源URL
     * @return self
     */
    public static function arVrExperience(string $experienceId, string $experienceType, string $assetUrl): self
    {
        $arData = json_encode([
            'type' => 'ar_vr_experience',
            'experience_id' => $experienceId,
            'experience_type' => $experienceType,
            'asset_url' => $assetUrl
        ], JSON_UNESCAPED_UNICODE);
        return self::make($arData);
    }

    /**
     * 生成加密消息二维码
     *
     * @param string $message 消息内容
     * @param string $encryptionKey 加密密钥
     * @param string $recipient 接收者
     * @return self
     */
    public static function encryptedMessage(string $message, string $encryptionKey, string $recipient): self
    {
        // 使用简单的AES加密
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($message, 'AES-256-CBC', $encryptionKey, 0, $iv);
        $encryptedData = base64_encode($iv . $encrypted);

        $encData = json_encode([
            'type' => 'encrypted_message',
            'recipient' => $recipient,
            'encrypted_data' => $encryptedData,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        return self::make($encData);
    }

    /**
     * 生成区块链/NFT二维码
     *
     * @param string $contractAddress 合约地址
     * @param string $tokenId 代币ID
     * @param string $network 网络（ethereum/polygon/binance等）
     * @return self
     */
    public static function nft(string $contractAddress, string $tokenId, string $network = 'ethereum'): self
    {
        $nftUrl = json_encode([
            'type' => 'nft',
            'contract_address' => $contractAddress,
            'token_id' => $tokenId,
            'network' => $network,
            'opensea_url' => "https://opensea.io/assets/{$network}/{$contractAddress}/{$tokenId}"
        ], JSON_UNESCAPED_UNICODE);
        return self::make($nftUrl);
    }

    /**
     * 生成数字身份/DID二维码
     *
     * @param string $did 去中心化身份标识
     * @param string $name 姓名
     * @param string $verifiableCredentialUrl 可验证凭证URL
     * @return self
     */
    public static function digitalIdentity(string $did, string $name, string $verifiableCredentialUrl): self
    {
        $didData = json_encode([
            'type' => 'digital_identity',
            'did' => $did,
            'name' => $name,
            'credential_url' => $verifiableCredentialUrl,
            'issued_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        return self::make($didData);
    }

    /**
     * 生成游戏二维码
     *
     * @param string $gameId 游戏ID
     * @param string $gameType 游戏类型
     * @param string $inviteCode 邀请码
     * @param string $rewardUrl 奖励URL
     * @return self
     */
    public static function gaming(string $gameId, string $gameType, string $inviteCode, string $rewardUrl = ''): self
    {
        $gameData = json_encode([
            'type' => 'game_invite',
            'game_id' => $gameId,
            'game_type' => $gameType,
            'invite_code' => $inviteCode,
            'reward_url' => $rewardUrl
        ], JSON_UNESCAPED_UNICODE);
        return self::make($gameData);
    }

    /**
     * 生成无人机控制二维码
     *
     * @param string $droneId 无人机ID
     * @param string $mission 任务ID
     * @param string $controlUrl 控制URL
     * @return self
     */
    public static function droneControl(string $droneId, string $mission, string $controlUrl): self
    {
        $droneData = json_encode([
            'type' => 'drone_control',
            'drone_id' => $droneId,
            'mission' => $mission,
            'control_url' => $controlUrl,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        return self::make($droneData);
    }

    /**
     * 生成教育/学习资源二维码
     *
     * @param string $courseId 课程ID
     * @param string $lessonId 课程单元ID
     * @param string $resourceUrl 资源URL
     * @param string $quizUrl 测验URL
     * @return self
     */
    public static function education(string $courseId, string $lessonId, string $resourceUrl, string $quizUrl = ''): self
    {
        $eduData = json_encode([
            'type' => 'education_resource',
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
            'resource_url' => $resourceUrl,
            'quiz_url' => $quizUrl
        ], JSON_UNESCAPED_UNICODE);
        return self::make($eduData);
    }

    /**
     * 生成医疗/健康记录二维码
     *
     * @param string $patientId 患者ID
     * @param string $recordId 记录ID
     * @param string $hospitalId 医院ID
     * @param string $verifyUrl 验证URL
     * @return self
     */
    public static function medicalRecord(string $patientId, string $recordId, string $hospitalId, string $verifyUrl): self
    {
        $medicalData = json_encode([
            'type' => 'medical_record',
            'patient_id' => $patientId,
            'record_id' => $recordId,
            'hospital_id' => $hospitalId,
            'verify_url' => $verifyUrl,
            'generated_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        return self::make($medicalData);
    }

    /**
     * 生成法律文件/合同二维码
     *
     * @param string $documentId 文档ID
     * @param string $documentType 文档类型
     * @param string $version 版本号
     * @param string $verificationUrl 验证URL
     * @return self
     */
    public static function legalDocument(string $documentId, string $documentType, string $version, string $verificationUrl): self
    {
        $legalData = json_encode([
            'type' => 'legal_document',
            'document_id' => $documentId,
            'document_type' => $documentType,
            'version' => $version,
            'verification_url' => $verificationUrl,
            'signed_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        return self::make($legalData);
    }

    /**
     * 生成政府服务二维码
     *
     * @param string $serviceId 服务ID
     * @param string $serviceName 服务名称
     * @param string $citizenId 公民ID
     * @param string $portalUrl 政务门户URL
     * @return self
     */
    public static function governmentService(string $serviceId, string $serviceName, string $citizenId, string $portalUrl): self
    {
        $govData = json_encode([
            'type' => 'government_service',
            'service_id' => $serviceId,
            'service_name' => $serviceName,
            'citizen_id' => $citizenId,
            'portal_url' => $portalUrl,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        return self::make($govData);
    }

    /**
     * 生成应急/紧急救援二维码
     *
     * @param string $emergencyId 紧急事件ID
     * @param string $emergencyType 类型（medical/fire/police/rescue）
     * @param float $latitude 纬度
     * @param float $longitude 经度
     * @param string $contactPhone 联系电话
     * @return self
     */
    public static function emergency(string $emergencyId, string $emergencyType, float $latitude, float $longitude, string $contactPhone): self
    {
        $emergencyData = json_encode([
            'type' => 'emergency',
            'emergency_id' => $emergencyId,
            'emergency_type' => $emergencyType,
            'location' => [
                'latitude' => $latitude,
                'longitude' => $longitude
            ],
            'contact_phone' => $contactPhone,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        return self::make($emergencyData);
    }

    /**
     * 生成物流/仓储二维码
     *
     * @param string $itemId 物品ID
     * @param string $warehouseId 仓库ID
     * @param string $locationCode 位置代码
     * @param string $trackingUrl 追踪URL
     * @return self
     */
    public static function logistics(string $itemId, string $warehouseId, string $locationCode, string $trackingUrl): self
    {
        $logisticsData = json_encode([
            'type' => 'logistics_item',
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'location_code' => $locationCode,
            'tracking_url' => $trackingUrl,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        return self::make($logisticsData);
    }

    /**
     * 生成农业/农产品溯源二维码
     *
     * @param string $productId 产品ID
     * @param string $farmId 农场ID
     * @param string $batchNumber 批次号
     * @param string $harvestDate 采收日期
     * @param string $traceUrl 溯源URL
     * @return self
     */
    public static function agriculture(string $productId, string $farmId, string $batchNumber, string $harvestDate, string $traceUrl): self
    {
        $agriData = json_encode([
            'type' => 'agriculture_traceability',
            'product_id' => $productId,
            'farm_id' => $farmId,
            'batch_number' => $batchNumber,
            'harvest_date' => $harvestDate,
            'trace_url' => $traceUrl
        ], JSON_UNESCAPED_UNICODE);
        return self::make($agriData);
    }

    /**
     * 生成保险理赔二维码
     *
     * @param string $policyId 保单号
     * @param string $claimId 理赔单号
     * @param string $claimType 理赔类型
     * @param string $claimUrl 理赔URL
     * @return self
     */
    public static function insuranceClaim(string $policyId, string $claimId, string $claimType, string $claimUrl): self
    {
        $insuranceData = json_encode([
            'type' => 'insurance_claim',
            'policy_id' => $policyId,
            'claim_id' => $claimId,
            'claim_type' => $claimType,
            'claim_url' => $claimUrl,
            'submitted_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        return self::make($insuranceData);
    }

    /**
     * 生成体育赛事/门票二维码
     *
     * @param string $eventId 赛事ID
     * @param string $eventIdString 赛事名称
     * @param string $venue 场馆
     * @param string $seat 座位信息
     * @param string $matchDate 比赛日期
     * @return self
     */
    public static function sportsTicket(string $eventId, string $eventIdString, string $venue, string $seat, string $matchDate): self
    {
        $sportsData = json_encode([
            'type' => 'sports_ticket',
            'event_id' => $eventId,
            'event_name' => $eventIdString,
            'venue' => $venue,
            'seat' => $seat,
            'match_date' => $matchDate
        ], JSON_UNESCAPED_UNICODE);
        return self::make($sportsData);
    }

    /**
     * 生成房地产/房源二维码
     *
     * @param string $propertyId 房源ID
     * @param string $propertyType 类型（apartment/house/villa等）
     * @param string $address 地址
     * @param string $tourUrl 看房URL
     * @return self
     */
    public static function property(string $propertyId, string $propertyType, string $address, string $tourUrl): self
    {
        $propertyData = json_encode([
            'type' => 'property_listing',
            'property_id' => $propertyId,
            'property_type' => $propertyType,
            'address' => $address,
            'virtual_tour_url' => $tourUrl
        ], JSON_UNESCAPED_UNICODE);
        return self::make($propertyData);
    }
}

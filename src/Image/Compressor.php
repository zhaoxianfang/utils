<?php

declare(strict_types=1);

namespace zxf\Utils\Image;

use Closure;
use Exception;
use GdImage;

/**
 * 图片压缩处理类 - 全面增强版 (v2.0)
 *
 * 核心功能亮点：
 * 1. 【双模式智能压缩】支持"最小化优先"与"清晰优先"两种策略，满足不同场景需求
 * 2. 【透明保真】PNG/WebP/GIF 透明通道完整保留，压缩后透明度不丢失
 * 3. 【格式转换】支持 PNG/JPEG/WebP/GIF/BMP 互转
 * 4. 【智能裁剪】stretch/fill/fit 三种模式满足不同场景需求
 * 5. 【水印系统】图片+文字水印，9种标准位置，支持透明度、缩放、边距
 * 6. 【EXIF自动纠正】自动根据 Orientation 标记旋转图片至正确方向
 * 7. 【批量处理】支持进度回调的批量压缩能力
 *
 * ==================== 快速入门示例 ====================
 *
 * 【示例1】基础压缩（保持尺寸，仅调整质量）
 * <code>
 * $info = Compressor::instance()
 *     ->setSource('photo.jpg')
 *     ->setQuality(75)
 *     ->save('compressed.jpg');
 * </code>
 *
 * 【示例2】智能压缩到 100KB（最小化优先模式）
 * <code>
 * $info = Compressor::instance()
 *     ->setSource('photo.jpg')
 *     ->smartCompress(100 * 1024)           // 目标 100KB
 *     ->setCompressMode(Compressor::MODE_SIZE_FIRST)  // 最小化优先
 *     ->save('small.jpg');
 * </code>
 *
 * 【示例3】智能压缩到 100KB（清晰优先模式，允许稍微超体积以换取更高清晰度）
 * <code>
 * $info = Compressor::instance()
 *     ->setSource('photo.jpg')
 *     ->smartCompress(100 * 1024)
 *     ->setCompressMode(Compressor::MODE_QUALITY_FIRST) // 清晰优先
 *     ->save('clear.jpg');
 * </code>
 *
 * 【示例4】等比缩放 + 转换格式
 * <code>
 * Compressor::instance()
 *     ->setSource('photo.png')
 *     ->resize(800, 600)
 *     ->toJpeg()
 *     ->setQuality(85)
 *     ->save('photo.jpg');
 * </code>
 *
 * 【示例5】填充裁剪（保持比例，多余部分裁剪）
 * <code>
 * Compressor::instance()
 *     ->setSource('wide.jpg')
 *     ->fill(300, 300)   // 生成 300x300 正方形，自动居中裁剪
 *     ->save('thumb.jpg');
 * </code>
 *
 * 【示例6】适应容器（保持比例，可能有留白）
 * <code>
 * Compressor::instance()
 *     ->setSource('tall.jpg')
 *     ->fit(200, 200)    // 最长边适配 200，短边留白
 *     ->save('fit.jpg');
 * </code>
 *
 * 【示例7】添加图片水印 + 文字水印
 * <code>
 * Compressor::instance()
 *     ->setSource('photo.jpg')
 *     ->addImageWatermark('logo.png', [
 *         'position' => 'bottom-right',
 *         'scale'    => 0.15,       // 水印宽度占主图 15%
 *         'opacity'  => 80,         // 透明度 80%
 *         'margin'   => 20,
 *     ])
 *     ->addTextWatermark('© 2026 版权所有', [
 *         'position' => 'top-left',
 *         'size'     => 14,
 *         'color'    => '#FFFFFF',
 *         'opacity'  => 70,
 *     ])
 *     ->save('watermarked.jpg');
 * </code>
 *
 * 【示例8】批量压缩带进度回调
 * <code>
 * $items = [
 *     ['src' => 'a.jpg', 'dest' => 'out/a.jpg', 'width' => 800, 'quality' => 75],
 *     ['src' => 'b.jpg', 'dest' => 'out/b.jpg', 'width' => 800, 'quality' => 75],
 * ];
 * Compressor::batch($items, function ($item, $result, $current, $total) {
 *     echo "进度: {$current}/{$total}\n";
 * });
 * </code>
 *
 * @package zxf\Utils\Image
 * @version 2.0.0
 * @author  zxf
 */
class Compressor
{
    // ==================== 图片格式常量 ====================
    public const F_PNG  = 'png';
    public const F_JPEG = 'jpeg';
    public const F_WEBP = 'webp';
    public const F_GIF  = 'gif';
    public const F_BMP  = 'bmp';

    // ==================== 水印位置常量 ====================
    public const POS_TL = 'top-left';
    public const POS_TC = 'top-center';
    public const POS_TR = 'top-right';
    public const POS_CL = 'center-left';
    public const POS_C  = 'center';
    public const POS_CR = 'center-right';
    public const POS_BL = 'bottom-left';
    public const POS_BC = 'bottom-center';
    public const POS_BR = 'bottom-right';

    // ==================== 裁剪模式常量 ====================
    /** 拉伸模式：不保持比例，直接拉伸到目标尺寸 */
    public const CROP_STRETCH = 'stretch';
    /** 填充模式：保持比例，居中裁剪，完全填充目标尺寸 */
    public const CROP_FILL    = 'fill';
    /** 适应模式：保持比例，完整显示图片，可能有留白 */
    public const CROP_FIT     = 'fit';

    // ==================== 压缩模式常量 ====================
    /** 最小化优先：优先将文件压缩到目标大小以下，允许适当降低清晰度 */
    public const MODE_SIZE_FIRST     = 'size_first';
    /** 清晰优先：优先保证图片清晰度，允许文件大小稍微超出目标（最多允许超出 30%） */
    public const MODE_QUALITY_FIRST  = 'quality_first';

    // ==================== 单例 ====================
    /** 单例实例缓存 */
    protected static ?self $instance = null;

    // ==================== 核心属性 ====================
    /** 当前处理的 GD 图像资源 */
    private ?GdImage $image = null;
    /** 源图片的绝对路径 */
    private string $srcPath = '';
    /** 兼容旧版的保存路径（供 get() 方法使用） */
    private ?string $savePath = null;
    /** 最近一次处理的结果信息数组 */
    private array $res = [];

    // ==================== 图片信息 ====================
    /** 源图片的元数据信息（路径、格式、尺寸、大小、透明度等） */
    private array $srcInfo = [];
    /** 源图片的原始格式（png/jpeg/webp/gif/bmp） */
    private string $format = '';
    /** 输出格式，可与源格式不同以实现格式转换 */
    private string $outFormat = '';

    // ==================== 尺寸参数 ====================
    /** 目标输出宽度（0 表示自动计算） */
    private int $targetWidth = 0;
    /** 目标输出高度（0 表示自动计算） */
    private int $targetHeight = 0;
    /** 当前裁剪模式：stretch/fill/fit */
    private string $cropMode = self::CROP_FILL;
    /** 是否保持宽高比例（resize 方法用） */
    private bool $maintainRatio = true;
    /** 手动裁剪区域 [x, y, w, h]，null 表示不手动裁剪 */
    private ?array $cropArea = null;

    // ==================== 质量参数 ====================
    /** 基础质量值 0-100，值越大越清晰、文件越大。PNG 为 0-9 的压缩等级 */
    private int $quality = 80;
    /** 智能压缩的目标字节数，null 表示不启用智能压缩 */
    private ?int $smartTarget = null;
    /** 智能压缩的最大迭代次数（默认 10 次） */
    private int $smartMaxIter = 10;
    /** 压缩模式：size_first（最小化优先）或 quality_first（清晰优先） */
    private string $compressMode = self::MODE_SIZE_FIRST;

    // ==================== 变换参数 ====================
    /** 旋转角度（0/90/180/270），顺时针为正 */
    private int $rotateAngle = 0;
    /** 是否水平翻转 */
    private bool $flipHorizontal = false;
    /** 是否垂直翻转 */
    private bool $flipVertical = false;

    // ==================== 水印参数 ====================
    /** 水印配置数组，每个元素为图片或文字水印的配置 */
    private array $watermarks = [];

    // ==================== 状态 ====================
    /** 标记是否已执行过 process()，防止重复处理 */
    private bool $processed = false;

    /**
     * 私有构造函数
     * 防止外部直接实例化，强制通过 instance() 或 create() 方法创建
     */
    private function __construct() {}

    /**
     * 禁止克隆
     * 单例模式禁止克隆操作，防止产生多个实例破坏状态一致性
     * @throws Exception 始终抛出异常
     */
    private function __clone()
    {
        throw new Exception('Compressor 不支持克隆');
    }

    /**
     * 获取单例实例（自动重置状态）
     *
     * 推荐使用此方法获取实例，每次调用会自动重置所有状态，避免状态污染。
     * 适用于连续处理多张图片的场景。
     *
     * @return static 单例实例
     */
    public static function instance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        self::$instance->resetState();
        return self::$instance;
    }

    /**
     * 创建新实例（非单例）
     *
     * 创建全新的独立实例，不会自动重置状态。
     * 适用于需要保持多个处理器实例并行的场景。
     *
     * @return static 新的实例
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * 重置实例状态，便于单例复用
     *
     * 将所有属性重置为默认值，释放图片资源。
     * 在 instance() 方法中自动调用，也可手动调用。
     *
     * @return $this 支持链式调用
     */
    public function resetState(): static
    {
        $this->releaseImage();
        $this->srcPath = ''; $this->savePath = null;
        $this->res = [];
        $this->srcInfo = [];
        $this->format = ''; $this->outFormat = '';
        $this->targetWidth = 0; $this->targetHeight = 0;
        $this->cropMode = self::CROP_FILL;
        $this->maintainRatio = true; $this->cropArea = null;
        $this->quality = 80; $this->smartTarget = null; $this->smartMaxIter = 10; $this->compressMode = self::MODE_SIZE_FIRST;
        $this->rotateAngle = 0; $this->flipHorizontal = false; $this->flipVertical = false;
        $this->watermarks = []; $this->processed = false;
        return $this;
    }

    /**
     * 释放 GD 图片资源
     *
     * 销毁当前持有的 GD 图像资源，释放内存。
     * 在重置状态和析构时自动调用。
     */
    private function releaseImage(): void
    {
        if ($this->image !== null) {
            imagedestroy($this->image);
            $this->image = null;
        }
    }

    /**
     * 设置源图片路径（增强版）
     *
     * @param string $srcPath 源图片路径
     * @return $this
     * @throws Exception
     */
    public function setSource(string $srcPath): static
    {
        if (!is_file($srcPath)) {
            throw new Exception("源图片不存在: {$srcPath}");
        }
        if (!is_readable($srcPath)) {
            throw new Exception("源图片不可读: {$srcPath}");
        }
        $this->srcPath = $srcPath;
        $this->loadSourceInfo();
        return $this;
    }

    /**
     * 兼容旧版 API 的 set 方法
     * @deprecated 请使用 setSource()
     */
    public function set(string $srcPath, ?string $savePath = null): static
    {
        $this->setSource($srcPath);
        if ($savePath !== null) {
            $this->savePath = $savePath;
        }
        return $this;
    }

    /**
     * 加载并解析源图片信息
     *
     * 获取图片 MIME 类型、格式、尺寸、文件大小，自动检测透明度、读取 EXIF
     *
     * @throws Exception 无法获取信息或不支持格式时抛出
     */
    private function loadSourceInfo(): void
    {
        $info = @getimagesize($this->srcPath);
        if ($info === false) {
            throw new Exception("无法获取图片信息: {$this->srcPath}");
        }
        $mime = $info['mime'] ?? '';
        $fmt = match($mime) {
            'image/png' => self::F_PNG,
            'image/jpeg','image/jpg' => self::F_JPEG,
            'image/webp' => self::F_WEBP,
            'image/gif' => self::F_GIF,
            'image/bmp','image/x-ms-bmp' => self::F_BMP,
            default => ''
        };
        if ($fmt === '') {
            throw new Exception("不支持的格式: {$mime}");
        }
        $size = filesize($this->srcPath);
        $this->format = $fmt;
        $this->outFormat = $fmt;
        $this->srcInfo = [
            'path' => $this->srcPath,
            'name' => basename($this->srcPath),
            'format' => $fmt,
            'mime' => $mime,
            'width' => $info[0],
            'height' => $info[1],
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'transparent' => false,
        ];
        $this->checkTransparency();
        $this->loadExif();
        $this->targetWidth = $info[0];
        $this->targetHeight = $info[1];
    }

    /**
     * 检测 PNG 图片是否包含透明通道
     *
     * 通过采样像素 Alpha 值判断，设置 srcInfo['transparent'] 标志
     */
    private function checkTransparency(): void
    {
        if ($this->format !== self::F_PNG) return;
        $img = $this->loadImageFile($this->srcPath);
        if (!$img) return;
        $w = imagesx($img); $h = imagesy($img);
        $step = max(1, (int)round(min($w,$h)/50));
        for ($x=0; $x<$w; $x+=$step) {
            for ($y=0; $y<$h; $y+=$step) {
                if (((imagecolorat($img,$x,$y) & 0x7F000000) >> 24) > 0) {
                    $this->srcInfo['transparent'] = true;
                    break 2;
                }
            }
        }
        imagedestroy($img);
    }

    /**
     * 加载图片文件为 GD 图像资源
     *
     * @param string $path 图片文件路径
     * @return GdImage|null 成功返回 GD 资源，失败返回 null
     */
    private function loadImageFile(string $path): ?GdImage
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $img = match($ext) {
            'png' => @imagecreatefrompng($path),
            'jpg','jpeg' => @imagecreatefromjpeg($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            'gif' => @imagecreatefromgif($path),
            'bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : null,
            default => null
        };
        return $img instanceof GdImage ? $img : null;
    }

    /**
     * 将字节数格式化为人类可读的字符串
     *
     * @param int $bytes 字节数
     * @return string 如 "1.5 MB"
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes/1024, 2) . ' KB';
        if ($bytes < 1073741824) return round($bytes/1048576, 2) . ' MB';
        return round($bytes/1073741824, 2) . ' GB';
    }

    /**
     * 加载 EXIF 数据并自动纠正图片方向
     *
     * 仅对 JPEG 有效。读取 Orientation 标记，设置翻转和旋转参数
     */
    private function loadExif(): void
    {
        if (!function_exists('exif_read_data')) return;
        if ($this->format !== self::F_JPEG) return;
        $exif = @exif_read_data($this->srcPath, 'ANY_TAG', true);
        if (!$exif) return;
        if (isset($exif['IFD0']['Orientation'])) {
            $this->applyOrientation((int)$exif['IFD0']['Orientation']);
        }
    }

    /**
     * 根据 EXIF Orientation 值设置翻转和旋转参数
     *
     * @param int $o Orientation 值 (1-8)
     */
    private function applyOrientation(int $o): void
    {
        match($o) {
            2 => $this->flipHorizontal = true,
            3 => $this->rotateAngle = 180,
            4 => $this->flipVertical = true,
            5 => [$this->flipVertical = true, $this->rotateAngle = 90],
            6 => $this->rotateAngle = 270,
            7 => [$this->flipHorizontal = true, $this->rotateAngle = 270],
            8 => $this->rotateAngle = 90,
            default => null
        };
    }

    /**
     * 设置目标尺寸
     * @param int $width 目标宽度（0=自动）
     * @param int $height 目标高度（0=自动）
     * @param bool $maintainRatio 是否保持比例
     */
    public function resize(int $width, int $height, bool $maintainRatio = true): static
    {
        if ($width < 0 || $height < 0) throw new Exception('尺寸不能为负数');
        $this->targetWidth = $width; $this->targetHeight = $height;
        $this->maintainRatio = $maintainRatio; $this->cropMode = self::CROP_STRETCH;
        return $this;
    }

    /**
     * 按宽度等比缩放
     *
     * @param int $width 目标宽度（像素）
     * @return $this
     * @throws Exception 宽度须大于0，且需要先调用 setSource
     */
    public function resizeByWidth(int $width): static
    {
        if ($width <= 0) throw new Exception('宽度须>0');
        if (empty($this->srcInfo)) throw new Exception('先setSource');
        $this->targetWidth = $width;
        $this->targetHeight = (int)round($this->srcInfo['height'] * ($width / $this->srcInfo['width']));
        $this->maintainRatio = true; return $this;
    }

    /**
     * 按高度等比缩放
     *
     * @param int $height 目标高度（像素）
     * @return $this
     * @throws Exception 高度须大于0，且需要先调用 setSource
     */
    public function resizeByHeight(int $height): static
    {
        if ($height <= 0) throw new Exception('高度须>0');
        if (empty($this->srcInfo)) throw new Exception('先setSource');
        $this->targetHeight = $height;
        $this->targetWidth = (int)round($this->srcInfo['width'] * ($height / $this->srcInfo['height']));
        $this->maintainRatio = true; return $this;
    }

    /**
     * 按比例缩放
     *
     * @param float $ratio 缩放比例（如 0.5 表示缩小到一半）
     * @return $this
     * @throws Exception 比例须在 0.01-10 之间
     */
    public function scale(float $ratio): static
    {
        if ($ratio <= 0 || $ratio > 10) throw new Exception('比例须在0.01-10之间');
        if (empty($this->srcInfo)) throw new Exception('先setSource');
        $this->targetWidth = (int)round($this->srcInfo['width'] * $ratio);
        $this->targetHeight = (int)round($this->srcInfo['height'] * $ratio);
        $this->maintainRatio = true; return $this;
    }

    /**
     * 适应容器（保持比例，完整显示，可能有留白）
     *
     * @param int $w 容器宽度
     * @param int $h 容器高度
     * @return $this
     * @throws Exception 容器宽高须大于0，且需要先调用 setSource
     */
    public function fit(int $w, int $h): static
    {
        if ($w <= 0 || $h <= 0) throw new Exception('容器宽高须>0');
        if (empty($this->srcInfo)) throw new Exception('先setSource');
        $sr = $this->srcInfo['width'] / $this->srcInfo['height'];
        $tr = $w / $h;
        if ($sr > $tr) { $this->targetWidth = $w; $this->targetHeight = (int)round($w / $sr); }
        else { $this->targetHeight = $h; $this->targetWidth = (int)round($h * $sr); }
        $this->maintainRatio = true; $this->cropMode = self::CROP_FIT;
        return $this;
    }

    /**
     * 填充容器（保持比例，居中裁剪，完全填充）
     *
     * @param int $w 容器宽度
     * @param int $h 容器高度
     * @return $this
     * @throws Exception 目标宽高须大于0
     */
    public function fill(int $w, int $h): static
    {
        if ($w <= 0 || $h <= 0) throw new Exception('目标宽高须>0');
        $this->targetWidth = $w; $this->targetHeight = $h;
        $this->maintainRatio = true; $this->cropMode = self::CROP_FILL;
        return $this;
    }

    /**
     * 裁剪指定区域
     *
     * @param int $x 起始X坐标
     * @param int $y 起始Y坐标
     * @param int $w 裁剪宽度
     * @param int $h 裁剪高度
     * @return $this
     * @throws Exception 裁剪宽高须大于0
     */
    public function crop(int $x, int $y, int $w, int $h): static
    {
        if ($w <= 0 || $h <= 0) throw new Exception('裁剪宽高须>0');
        $this->cropArea = ['x' => max(0, $x), 'y' => max(0, $y), 'w' => $w, 'h' => $h];
        return $this;
    }

    /**
     * 从中心裁剪指定尺寸
     *
     * @param int $w 裁剪宽度
     * @param int $h 裁剪高度
     * @return $this
     * @throws Exception 须先调用 setSource
     */
    public function cropFromCenter(int $w, int $h): static
    {
        if (empty($this->srcInfo)) throw new Exception('先setSource');
        $x = (int)(($this->srcInfo['width'] - $w) / 2);
        $y = (int)(($this->srcInfo['height'] - $h) / 2);
        return $this->crop($x, $y, $w, $h);
    }

    /**
     * 设置压缩质量（修正版）
     * 质量值 0-100，值越大越清晰、文件越大
     */
    public function compress(int $quality = 80): static
    {
        return $this->setQuality($quality);
    }

    /**
     * 设置压缩质量
     *
     * @param int $q 质量值 0-100，值越大越清晰、文件越大
     * @return $this
     * @throws Exception 质量值须在 0-100 之间
     */
    public function setQuality(int $q): static
    {
        if ($q < 0 || $q > 100) throw new Exception('质量须在0-100之间');
        $this->quality = $q; return $this;
    }

    /**
     * 智能压缩到目标大小
     *
     * 通过二分查找算法自动寻找最佳质量参数，使输出文件尽量接近目标大小。
     * 配合 setCompressMode() 可控制压缩策略：
     * - MODE_SIZE_FIRST（默认）：优先满足体积要求，可能降低清晰度
     * - MODE_QUALITY_FIRST：优先保证清晰度，允许体积最多超出 30%
     *
     * @param int $targetBytes 目标文件大小（字节）
     * @param int $maxIter 最大迭代次数（默认10次，最少5次）
     * @return $this
     * @throws Exception 目标大小小于等于0时抛出
     */
    public function smartCompress(int $targetBytes, int $maxIter = 10): static
    {
        if ($targetBytes <= 0) throw new Exception('目标大小须>0');
        $this->smartTarget = $targetBytes;
        $this->smartMaxIter = max(5, $maxIter);
        return $this;
    }

    /**
     * 设置智能压缩模式
     *
     * @param string $mode 压缩模式：
     *                      - MODE_SIZE_FIRST：最小化优先，尽量压缩到目标大小以下
     *                      - MODE_QUALITY_FIRST：清晰优先，允许超出最多30%以换取更高清晰度
     * @return $this
     * @throws Exception 传入无效模式时抛出
     */
    public function setCompressMode(string $mode): static
    {
        if (!in_array($mode, [self::MODE_SIZE_FIRST, self::MODE_QUALITY_FIRST], true)) {
            throw new Exception('无效的压缩模式，请使用 MODE_SIZE_FIRST 或 MODE_QUALITY_FIRST');
        }
        $this->compressMode = $mode;
        return $this;
    }

    /**
     * 设置输出格式
     *
     * @param string $f 目标格式：png/jpeg/webp/gif/bmp 或 jpg
     * @return $this
     * @throws Exception 不支持的格式时抛出
     */
    public function setOutputFormat(string $f): static
    {
        $f = strtolower($f);
        if (!in_array($f, [self::F_PNG, self::F_JPEG, 'jpg', self::F_WEBP, self::F_GIF, self::F_BMP], true)) {
            throw new Exception('不支持格式: '.$f);
        }
        $this->outFormat = ($f === 'jpg') ? self::F_JPEG : $f;
        return $this;
    }

    public function toPng(): static  { $this->outFormat = self::F_PNG; return $this; }
    public function toJpeg(): static { $this->outFormat = self::F_JPEG; return $this; }
    public function toWebp(): static { $this->outFormat = self::F_WEBP; return $this; }

    /**
     * 旋转图片（顺时针）
     * @param int $deg 旋转角度（正数为顺时针）
     * @return $this
     */
    public function rotate(int $deg): static { $this->rotateAngle = ($this->rotateAngle + $deg) % 360; return $this; }
    /**
     * 顺时针旋转图片
     * @param int $deg 旋转角度
     * @return $this
     */
    public function rotateClockwise(int $deg): static { return $this->rotate(-$deg); }
    /** 水平翻转图片 @return $this */
    public function flipH(): static { $this->flipHorizontal = true; return $this; }
    /** 垂直翻转图片 @return $this */
    public function flipV(): static { $this->flipVertical = true; return $this; }
    /** 水平翻转图片（别名） @return $this */
    public function flipHorizontal(): static { return $this->flipH(); }
    /** 垂直翻转图片（别名） @return $this */
    public function flipVertical(): static { return $this->flipV(); }

    /**
     * 等比例缩放（兼容旧版）
     * @param float $percent 缩放比例
     */
    public function proportion(float $percent = 1): static
    {
        return $this->scale($percent);
    }

    /**
     * 添加图片水印
     *
     * 支持的选项：
     * - position: 位置（默认 bottom-right），可选 POS_TL/TC/TR/CL/C/CR/BL/BC/BR
     * - scale: 水印宽度占主图比例（如 0.15）
     * - width/height: 指定水印尺寸（与 scale 互斥）
     * - opacity: 透明度 0-100（默认100）
     * - margin: 边距（默认10像素）
     * - offsetX/offsetY: 额外偏移量
     *
     * @param string $path 水印图片路径
     * @param array $opts 水印选项数组
     * @return $this
     * @throws Exception 水印图片不存在时抛出
     */
    public function addImageWatermark(string $path, array $opts = []): static
    {
        if (!is_file($path)) throw new Exception("水印图片不存在: {$path}");
        $this->watermarks[] = [
            'type' => 'image', 'path' => $path,
            'position' => $opts['position'] ?? self::POS_BR,
            'scale' => $opts['scale'] ?? null,
            'width' => $opts['width'] ?? null, 'height' => $opts['height'] ?? null,
            'opacity' => $opts['opacity'] ?? 100,
            'margin' => $opts['margin'] ?? 10,
            'offsetX' => $opts['offsetX'] ?? 0, 'offsetY' => $opts['offsetY'] ?? 0,
        ]; return $this;
    }

    /**
     * 添加文字水印
     *
     * 支持的选项：
     * - position: 位置（默认 bottom-right）
     * - font: TTF 字体文件路径（自动查找系统默认字体）
     * - size: 字号（默认16）
     * - color: 颜色（默认 #FFFFFF）
     * - opacity: 透明度 0-100（默认100）
     * - angle: 旋转角度（默认0）
     * - margin: 边距（默认10像素）
     * - offsetX/offsetY: 额外偏移量
     *
     * @param string $text 水印文字内容
     * @param array $opts 文字水印选项数组
     * @return $this
     */
    public function addTextWatermark(string $text, array $opts = []): static
    {
        $this->watermarks[] = [
            'type' => 'text', 'text' => $text,
            'position' => $opts['position'] ?? self::POS_BR,
            'font' => $opts['font'] ?? '', 'size' => $opts['size'] ?? 16,
            'color' => $opts['color'] ?? '#FFFFFF', 'opacity' => $opts['opacity'] ?? 100,
            'angle' => $opts['angle'] ?? 0,
            'margin' => $opts['margin'] ?? 10,
            'offsetX' => $opts['offsetX'] ?? 0, 'offsetY' => $opts['offsetY'] ?? 0,
        ]; return $this;
    }

    /** 清除所有已添加的水印 @return $this */
    public function clearWatermarks(): static { $this->watermarks = []; return $this; }

    /**
     * 执行图片处理流程（核心方法）
     *
     * 完整的处理流水线：
     * 1. 加载源图片
     * 2. 裁剪（如果指定）
     * 3. 水平/垂直翻转
     * 4. 缩放/填充/适应
     * 5. 旋转
     * 6. 添加水印
     *
     * @return $this
     * @throws Exception
     */
    public function process(): static
    {
        if ($this->srcPath === '') throw new Exception('请先调用 setSource()');
        if ($this->processed) return $this;

        // 加载图片
        $this->image = $this->loadImageFile($this->srcPath);
        if (!$this->image) throw new Exception('无法加载源图片');

        // 裁剪
        if ($this->cropArea !== null) $this->doCrop();

        // 翻转
        if ($this->flipHorizontal) imageflip($this->image, IMG_FLIP_HORIZONTAL);
        if ($this->flipVertical) imageflip($this->image, IMG_FLIP_VERTICAL);

        // 缩放
        $this->doResize();

        // 旋转
        if ($this->rotateAngle !== 0) $this->doRotate();

        // 水印
        if (!empty($this->watermarks)) $this->doWatermarks();

        $this->processed = true;
        return $this;
    }

    /**
     * 执行手动裁剪
     *
     * 根据 cropArea 参数裁剪图片区域
     */
    private function doCrop(): void
    {
        $a = $this->cropArea;
        $srcW = imagesx($this->image); $srcH = imagesy($this->image);
        $x = min($a['x'], $srcW - 1); $y = min($a['y'], $srcH - 1);
        $w = min($a['w'], $srcW - $x); $h = min($a['h'], $srcH - $y);
        $cropped = imagecreatetruecolor($w, $h);
        $this->setupAlpha($cropped);
        imagecopy($cropped, $this->image, 0, 0, $x, $y, $w, $h);
        imagedestroy($this->image); $this->image = $cropped;
    }

    /**
     * 执行缩放/填充/适应操作
     *
     * 根据 maintainRatio 和 cropMode 选择不同缩放策略
     */
    private function doResize(): void
    {
        $srcW = imagesx($this->image); $srcH = imagesy($this->image);
        $dstW = $this->targetWidth; $dstH = $this->targetHeight;
        if ($dstW === 0 && $dstH === 0) return;
        if ($dstW === 0) $dstW = (int)round($srcW * ($dstH / $srcH));
        if ($dstH === 0) $dstH = (int)round($srcH * ($dstW / $srcW));

        if ($this->maintainRatio) {
            if ($this->cropMode === self::CROP_FILL) { $this->doCropFillResize($srcW, $srcH, $dstW, $dstH); return; }
            $sr = $srcW / $srcH; $dr = $dstW / $dstH;
            if ($sr > $dr) $dstH = (int)round($dstW / $sr); else $dstW = (int)round($dstH * $sr);
        }
        if ($srcW === $dstW && $srcH === $dstH) return;

        $dst = imagecreatetruecolor($dstW, $dstH);
        $this->setupAlpha($dst);
        imagecopyresampled($dst, $this->image, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($this->image); $this->image = $dst;
    }

    /**
     * 裁剪填充模式缩放（fill 模式的核心实现）
     *
     * 保持比例放大后居中裁剪，完全填充目标尺寸
     *
     * @param int $srcW 源宽度
     * @param int $srcH 源高度
     * @param int $dstW 目标宽度
     * @param int $dstH 目标高度
     */
    private function doCropFillResize(int $srcW, int $srcH, int $dstW, int $dstH): void
    {
        $ratio = max($dstW / $srcW, $dstH / $srcH);
        $newW = (int)round($srcW * $ratio); $newH = (int)round($srcH * $ratio);
        $srcX = (int)(($newW - $dstW) / 2 / $ratio); $srcY = (int)(($newH - $dstH) / 2 / $ratio);
        $tmp = imagecreatetruecolor($newW, $newH);
        $this->setupAlpha($tmp);
        imagecopyresampled($tmp, $this->image, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        $dst = imagecreatetruecolor($dstW, $dstH);
        $this->setupAlpha($dst);
        imagecopy($dst, $tmp, 0, 0, $srcX, $srcY, $dstW, $dstH);
        imagedestroy($tmp); imagedestroy($this->image); $this->image = $dst;
    }

    /**
     * 配置目标图片的 Alpha 透明通道支持
     *
     * 对 PNG/WebP/GIF 格式启用透明通道，填充透明背景色
     *
     * @param GdImage $img 目标 GD 图像资源
     */
    private function setupAlpha(GdImage $img): void
    {
        if (in_array($this->outFormat, [self::F_PNG, self::F_WEBP, self::F_GIF], true)) {
            imagealphablending($img, false); imagesavealpha($img, true);
            $t = imagecolorallocatealpha($img, 255, 255, 255, 127); imagefill($img, 0, 0, $t);
        }
    }

    /**
     * 执行图片旋转操作
     *
     * 使用 imagerotate 旋转图片，透明背景处理
     * @throws Exception 旋转失败时抛出
     */
    private function doRotate(): void
    {
        $bg = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
        $rotated = imagerotate($this->image, $this->rotateAngle, $bg);
        if ($rotated === false) throw new Exception('旋转失败');
        imagesavealpha($rotated, true); imagedestroy($this->image); $this->image = $rotated;
    }

    /**
     * 处理并绘制所有已添加的水印
     *
     * 遍历 watermarks 数组，依次绘制图片和文字水印
     */
    private function doWatermarks(): void
    {
        foreach ($this->watermarks as $wm) {
            if ($wm['type'] === 'image') $this->drawImageWatermark($wm);
            else $this->drawTextWatermark($wm);
        }
    }

    /**
     * 在图片上绘制图片水印
     *
     * @param array $cfg 水印配置数组（包含 path/position/scale/opacity 等）
     */
    private function drawImageWatermark(array $cfg): void
    {
        $src = $this->loadImageFile($cfg['path']); if (!$src) return;
        $srcW = imagesx($src); $srcH = imagesy($src);
        $cw = imagesx($this->image); $ch = imagesy($this->image);
        if ($cfg['scale'] !== null) { $tw = (int)round($cw * $cfg['scale']); $th = (int)round($tw * ($srcH / $srcW)); }
        elseif ($cfg['width'] !== null || $cfg['height'] !== null) { $tw = $cfg['width'] ?? (int)round(($cfg['height']) * ($srcW / $srcH)); $th = $cfg['height'] ?? (int)round(($cfg['width']) * ($srcH / $srcW)); }
        else { $tw = $srcW; $th = $srcH; }
        $pos = $this->calcPosition($cfg['position'], $cw, $ch, $tw, $th, $cfg['margin'], $cfg['offsetX'], $cfg['offsetY']);
        if ($cfg['opacity'] < 100) imagefilter($src, IMG_FILTER_COLORIZE, 0, 0, 0, (int)round(127 * (1 - $cfg['opacity'] / 100)));
        imagecopyresampled($this->image, $src, $pos['x'], $pos['y'], 0, 0, $tw, $th, $srcW, $srcH);
        imagedestroy($src);
    }

    /**
     * 在图片上绘制文字水印
     *
     * @param array $cfg 文字水印配置数组（包含 text/font/size/color 等）
     */
    private function drawTextWatermark(array $cfg): void
    {
        $cw = imagesx($this->image); $ch = imagesy($this->image);
        $font = $cfg['font']; if ($font === '' || !is_file($font)) $font = $this->findDefaultFont();
        if ($font === '' || !is_file($font)) return;
        $bbox = imagettfbbox($cfg['size'], $cfg['angle'], $font, $cfg['text']);
        $tw = abs($bbox[4] - $bbox[0]); $th = abs($bbox[5] - $bbox[1]);
        $pos = $this->calcPosition($cfg['position'], $cw, $ch, $tw, $th, $cfg['margin'], $cfg['offsetX'], $cfg['offsetY']);
        $rgb = $this->parseColor($cfg['color']);
        $alpha = (int)round(127 * (1 - $cfg['opacity'] / 100));
        $color = imagecolorallocatealpha($this->image, $rgb[0], $rgb[1], $rgb[2], $alpha);
        imagettftext($this->image, $cfg['size'], $cfg['angle'], $pos['x'] + abs($bbox[0]), $pos['y'] + abs($bbox[5]), $color, $font, $cfg['text']);
    }

    /**
     * 计算水印在图片上的位置坐标
     *
     * @param string $pos 位置常量（如 POS_BR）
     * @param int $cw 画布宽度
     * @param int $ch 画布高度
     * @param int $ww 水印宽度
     * @param int $wh 水印高度
     * @param int $margin 边距
     * @param int $ox 额外X偏移
     * @param int $oy 额外Y偏移
     * @return array ['x'=>int, 'y'=>int]
     */
    private function calcPosition(string $pos, int $cw, int $ch, int $ww, int $wh, int $margin, int $ox, int $oy): array
    {
        $x = match ($pos) { self::POS_TL, self::POS_CL, self::POS_BL => $margin, self::POS_TR, self::POS_CR, self::POS_BR => $cw - $ww - $margin, default => (int)(($cw - $ww) / 2) };
        $y = match ($pos) { self::POS_TL, self::POS_TC, self::POS_TR => $margin, self::POS_BL, self::POS_BC, self::POS_BR => $ch - $wh - $margin, default => (int)(($ch - $wh) / 2) };
        return ['x' => max(0, $x + $ox), 'y' => max(0, $y + $oy)];
    }

    /**
     * 解析颜色字符串为 RGB 数组
     *
     * 支持 #RGB 和 #RRGGBB 格式，解析失败返回白色
     *
     * @param string $color 颜色字符串（如 #FFFFFF）
     * @return array [R, G, B]
     */
    private function parseColor(string $color): array
    {
        $c = ltrim($color, '#');
        if (preg_match('/^([0-9a-f])([0-9a-f])([0-9a-f])$/i', $c, $m)) return [hexdec($m[1] . $m[1]), hexdec($m[2] . $m[2]), hexdec($m[3] . $m[3])];
        if (preg_match('/^([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $c, $m)) return [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
        return [255, 255, 255];
    }

    /**
     * 查找系统默认的 TTF 字体文件
     *
     * 依次检查 Linux、macOS、Windows 的常用字体路径
     *
     * @return string 字体文件路径，未找到返回空字符串
     */
    private function findDefaultFont(): string
    {
        $candidates = ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/System/Library/Fonts/Helvetica.ttc', 'C:\\Windows\\Fonts\\arial.ttf'];
        foreach ($candidates as $f) if (is_file($f)) return $f;
        return '';
    }

    // ==================== 输出方法 ====================

    /**
     * 保存到文件
     * @param string|null $path 保存路径，null则覆盖源图
     * @return array 结果信息
     */
    public function save(?string $path = null): array
    {
        $this->process();
        $dest = $path ?? $this->srcPath;
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new Exception("无法创建目录: {$dir}");
        $quality = $this->resolveQuality();
        if ($this->smartTarget !== null) $quality = $this->findOptimalQuality($dest, $quality);
        $this->saveImage($dest, $quality);
        $this->buildResultInfo($dest);
        $this->releaseImage();
        return $this->res;
    }

    /**
     * 输出图片到浏览器
     *
     * 自动发送 Content-Type 和 Cache-Control 头，直接输出图片流
     */
    public function output(): void
    {
        $this->process();
        $quality = $this->resolveQuality();
        $mime = match ($this->outFormat) { self::F_PNG => 'image/png', self::F_JPEG => 'image/jpeg', self::F_WEBP => 'image/webp', self::F_GIF => 'image/gif', self::F_BMP => 'image/bmp', default => 'image/png' };
        if (!headers_sent()) { header('Content-Type: ' . $mime); header('Cache-Control: no-cache'); }
        $this->outputImage(null, $quality); $this->releaseImage();
    }

    /**
     * 获取处理后的图片 Base64 编码字符串
     *
     * @param bool $withPrefix 是否包含 data URI 前缀（默认 true）
     * @return string Base64 字符串
     */
    public function toBase64(bool $withPrefix = true): string
    {
        $this->process();
        $quality = $this->resolveQuality();
        ob_start(); $this->outputImage(null, $quality);
        $data = ob_get_clean();
        $b64 = base64_encode($data);
        $this->releaseImage();
        if (!$withPrefix) return $b64;
        $mime = match ($this->outFormat) { self::F_PNG => 'image/png', self::F_JPEG => 'image/jpeg', self::F_WEBP => 'image/webp', self::F_GIF => 'image/gif', self::F_BMP => 'image/bmp', default => 'image/png' };
        return "data:{$mime};base64,{$b64}";
    }

    /**
     * 兼容旧版的 get 方法
     *
     * @param Closure|null $beforeFunc 处理前的回调函数
     * @return bool|string 设置了 savePath 返回 true，否则返回 Base64
     * @deprecated 请使用 save() 或 toBase64() 替代
     */
    public function get(?Closure $beforeFunc = null): bool|string
    {
        $result = $this->save($this->savePath);
        if ($beforeFunc) $beforeFunc($result);
        if ($this->savePath) return true;
        return $this->toBase64();
    }

    /**
     * 解析最终使用的质量参数
     *
     * 不同格式使用不同质量范围：PNG 为 0-9 压缩等级，JPEG/WebP 为 0-100
     *
     * @return int 解析后的质量值
     */
    private function resolveQuality(): int
    {
        return match ($this->outFormat) { self::F_PNG => (int)round((100 - $this->quality) / 10), self::F_JPEG, self::F_WEBP => $this->quality, default => $this->quality };
    }

    /**
     * 保存图片到文件
     *
     * @param string $path 保存路径
     * @param int $q 质量参数
     * @throws Exception 保存失败时抛出
     */
    private function saveImage(string $path, int $q): void
    {
        $ok = match ($this->outFormat) {
            self::F_PNG => imagepng($this->image, $path, min(9, max(0, $q))),
            self::F_JPEG => imagejpeg($this->image, $path, min(100, max(0, $q))),
            self::F_WEBP => function_exists('imagewebp') ? imagewebp($this->image, $path, min(100, max(0, $q))) : false,
            self::F_GIF => imagegif($this->image, $path),
            self::F_BMP => function_exists('imagebmp') ? imagebmp($this->image, $path) : false,
            default => false
        };
        if (!$ok) throw new Exception("保存失败: {$path}");
    }

    /**
     * 输出图片到文件或内存流
     *
     * @param string|null $path 文件路径，null 则输出到内存
     * @param int $q 质量参数
     */
    private function outputImage(?string $path, int $q): void
    {
        match ($this->outFormat) {
            self::F_PNG => imagepng($this->image, $path, min(9, max(0, $q))),
            self::F_JPEG => imagejpeg($this->image, $path, min(100, max(0, $q))),
            self::F_WEBP => function_exists('imagewebp') ? imagewebp($this->image, $path, min(100, max(0, $q))) : null,
            self::F_GIF => imagegif($this->image, $path),
            self::F_BMP => function_exists('imagebmp') ? imagebmp($this->image, $path) : null,
            default => null
        };
    }

    /**
     * 智能寻找最佳质量参数（支持双模式）
     *
     * 根据当前 compressMode 采用不同策略：
     * - MODE_SIZE_FIRST：优先满足体积要求，二分查找尽量压缩到 target 以下
     * - MODE_QUALITY_FIRST：优先保证清晰度，允许体积最多超出 target 的 30%，
     *   但质量不会低于 35，确保图片可用性
     *
     * @param string $path 临时保存路径，用于测量文件大小
     * @param int $initial 初始质量值
     * @return int 最终确定的最佳质量值
     */
    private function findOptimalQuality(string $path, int $initial): int
    {
        $target = $this->smartTarget;
        $isQualityFirst = $this->compressMode === self::MODE_QUALITY_FIRST;
        $minQ = $isQualityFirst ? 35 : 10;   // 清晰优先模式下最低质量为 35
        $maxQ = 100;
        $bestQ = $initial;
        $bestSize = PHP_INT_MAX;

        for ($i = 0; $i < $this->smartMaxIter; $i++) {
            $this->saveImage($path, $bestQ);
            $size = filesize($path);

            // 记录当前最佳（用于清晰优先模式回溯）
            if ($size < $bestSize) {
                $bestSize = $size;
            }

            // ========== 清晰优先模式 ==========
            if ($isQualityFirst) {
                $maxAllowed = (int)($target * 1.30); // 允许最多超出 30%
                if ($size <= $target) {
                    // 已达到目标体积，尝试提高质量（清晰优先）
                    $minQ = $bestQ;
                    $bestQ = (int)(($bestQ + $maxQ) / 2);
                    if ($bestQ === $minQ) break;
                } elseif ($size <= $maxAllowed) {
                    // 在允许的超额范围内，接受当前质量（优先清晰）
                    break;
                } else {
                    // 超出允许范围，需要降低质量
                    $maxQ = $bestQ;
                    $bestQ = (int)(($minQ + $maxQ) / 2);
                    if ($bestQ === $maxQ) break;
                }
                continue;
            }

            // ========== 最小化优先模式（默认） ==========
            if ($size <= $target || $bestQ <= $minQ) {
                break;
            }
            if ($size > $target * 1.2) {
                // 大幅超出目标，二分查找快速逼近
                $maxQ = $bestQ;
                $bestQ = (int)(($minQ + $maxQ) / 2);
            } elseif ($size > $target) {
                // 小幅超出，每次降低 10 质量
                $bestQ = (int)max($minQ, $bestQ - 10);
            } else {
                break;
            }
        }

        return max($minQ, min(100, $bestQ));
    }

    /**
     * 构建处理结果信息数组
     *
     * 生成包含原始信息和压缩后信息的对比数据
     *
     * @param string $dest 输出文件路径
     */
    private function buildResultInfo(string $dest): void
    {
        $newSize = filesize($dest);
        $origSize = $this->srcInfo['size'] ?? 0;
        $ratio = $origSize > 0 ? round(($origSize - $newSize) / $origSize * 100, 2) : 0;
        $info = @getimagesize($dest);
        $this->res = [
            'original' => $this->srcInfo,
            'compressed' => [
                'path' => $dest,
                'format' => $this->outFormat,
                'width' => $info[0] ?? $this->targetWidth,
                'height' => $info[1] ?? $this->targetHeight,
                'size' => $newSize,
                'size_human' => $this->formatBytes($newSize),
                'ratio' => $ratio . '%',
            ]
        ];
    }

    // ==================== 批量处理 ====================

    /**
     * 批量处理图片
     * @param array $items 处理项数组
     * @param Closure|null $progress 进度回调
     */
    public static function batch(array $items, ?Closure $progress = null): array
    {
        $results = []; $total = count($items); $inst = self::instance();
        foreach ($items as $index => $item) {
            $inst->resetState();
            try {
                $src = $item['src'] ?? '';
                if ($src === '') { $results[] = ['success' => false, 'error' => '缺少源文件']; continue; }
                $inst->setSource($src);
                if (isset($item['width']) || isset($item['height'])) {
                    if (isset($item['crop']) && $item['crop']) $inst->fill($item['width'] ?? 0, $item['height'] ?? 0);
                    else $inst->resize($item['width'] ?? 0, $item['height'] ?? 0);
                }
                if (isset($item['quality'])) $inst->setQuality($item['quality']);
                if (isset($item['format'])) $inst->setOutputFormat($item['format']);
                $info = $inst->save($item['dest'] ?? null);
                $results[] = ['success' => true, 'info' => $info];
            } catch (Exception $e) {
                $results[] = ['success' => false, 'error' => $e->getMessage()];
            }
            if ($progress) $progress($item, $results[count($results) - 1], $index + 1, $total);
        }
        return $results;
    }

    /**
     * 析构时释放 GD 资源
     *
     * 防止内存泄漏，对象销毁时自动释放图片资源
     */
    public function __destruct()
    {
        $this->releaseImage();
    }
}

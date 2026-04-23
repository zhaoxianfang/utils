# Image 图像处理工具包

一套功能强大的 PHP 图像处理工具集合，包含文字生成图片、图片压缩、高级图像处理（Imagick）三大核心组件。

## 目录

- [TextToImg - 文字生成图片](#texttoimg---文字生成图片)
- [Compressor - 图片压缩](#compressor---图片压缩)
- [ImagickTool - Imagick 高级图像处理](#imagicktool---imagick-高级图像处理)

---

## TextToImg - 文字生成图片

基于 PHP GD 库的高性能文字图片生成工具，支持丰富的排版特效、水印、背景处理、滤镜及多种输出格式。

### 功能特性

- ✅ **多样化排版**：自动换行、多行文字、旋转角度（支持任意角度旋转，无溢出）
- ✅ **丰富特效**：阴影、描边、发光、高亮背景、渐变背景
- ✅ **智能适配**：自动计算最佳字号、自适应画布尺寸
- ✅ **水印支持**：支持添加多个文字和图片水印
- ✅ **多种输出**：保存文件、Base64、浏览器直接输出
- ✅ **高 DPI 支持**：通过缩放比例生成高清图片

### 快速入门

#### 基础用法

```php
use zxf\Utils\Image\TextToImg;

// 基础文字图片
TextToImg::instance(1200, 600)
    ->setText('欢迎使用 TextToImg')
    ->setColor('333333')
    ->setBgColor('F5F5F5')
    ->setFontFile('/path/to/font.ttf')
    ->render('output.png');
```

#### 链式调用风格

```php
TextToImg::instance()
    ->setText("这是一段支持自动换行的文字内容，当文字长度超过设定宽度时会自动折行。")
    ->setFontSize(36)
    ->setColor('FF5733')
    ->setBgColor('FFFFFF')
    ->setAutoWrap(true)
    ->setPadding(40)
    ->setAlign(TextToImg::ALIGN_LEFT, TextToImg::VALIGN_TOP)
    ->render('advanced.png');
```

### 核心方法详解

#### 实例化与配置

| 方法 | 说明 | 示例 |
|------|------|------|
| `instance($w, $h)` | 获取单例实例 | `TextToImg::instance(800, 600)` |
| `setSize($w, $h)` | 设置画布尺寸 | `->setSize(1200, 800)` |
| `setText($text)` | 设置文字内容 | `->setText('Hello World')` |
| `setFontFile($path)` | 设置字体文件 | `->setFontFile('/path/to/arial.ttf')` |
| `setFontSize($size)` | 设置字号（pt） | `->setFontSize(48)` |
| `setAngle($angle)` | 设置旋转角度 | `->setAngle(45)` |
| `setPadding($padding)` | 设置内边距 | `->setPadding(30)` |
| `setLineHeight($height)` | 设置行高倍数 | `->setLineHeight(1.8)` |

#### 对齐方式常量

```php
// 水平对齐
TextToImg::ALIGN_LEFT    // 左对齐
TextToImg::ALIGN_CENTER  // 水平居中
TextToImg::ALIGN_RIGHT   // 右对齐

// 垂直对齐
TextToImg::VALIGN_TOP    // 顶部对齐
TextToImg::VALIGN_MIDDLE // 垂直居中
TextToImg::VALIGN_BOTTOM // 底部对齐
```

### 高级特效示例

#### 1. 阴影 + 描边 + 圆角边框

```php
TextToImg::instance()
    ->setText('阴影描边效果')
    ->setFontSize(60)
    ->setColor('FFFFFF')
    ->setBgColor('333333')
    ->setTextShadow([
        'color'   => '000000',
        'alpha'   => 80,
        'offsetX' => 5,
        'offsetY' => 5,
        'blur'    => 3
    ])
    ->setTextStroke([
        'color' => 'FF5733',
        'width' => 3
    ])
    ->setBorder(4, 'FFFFFF', 16)  // 4px 宽度，白色，16px 圆角
    ->setPadding(50)
    ->render('shadow_stroke.png');
```

#### 2. 渐变背景 + 发光效果

```php
TextToImg::instance(1000, 500)
    ->setText('渐变发光效果')
    ->setFontSize(72)
    ->setColor('FFFFFF')
    ->setGradientBackground([
        ['color' => '667eea', 'position' => 0],
        ['color' => '764ba2', 'position' => 0.5],
        ['color' => 'f093fb', 'position' => 1],
    ])
    ->setGradientType(TextToImg::GRADIENT_LINEAR)
    ->setGradientAngle(135)
    ->setTextGlow([
        'color'    => 'FFFFFF',
        'alpha'    => 100,
        'radius'   => 15,
        'strength' => 3
    ])
    ->render('gradient_glow.png');
```

#### 3. 文字高亮背景

```php
TextToImg::instance()
    ->setText('文字高亮效果')
    ->setFontSize(48)
    ->setColor('333333')
    ->setBgColor('F5F5F5')
    ->setTextHighlight([
        'color'   => 'FFD700',
        'alpha'   => 60,
        'padding' => 10,
        'radius'  => 8
    ])
    ->render('highlight.png');
```

#### 4. 旋转文字（修复版 - 无溢出）

```php
// 45度旋转 - 自动适应安全区
TextToImg::instance(800, 600)
    ->setText("第一行文字\n第二行文字\n第三行文字")
    ->setFontSize(36)
    ->setAngle(45)
    ->setColor('FFFFFF')
    ->setBgColor('667eea')
    ->setPadding(40)
    ->render('rotated_45.png');

// 90度旋转 - 自动缩放防止溢出
TextToImg::instance(600, 400)
    ->setText("多行\n旋转\n文字")
    ->setFontSize(48)
    ->setAngle(90)
    ->setColor('333333')
    ->setBgColor('F5F5F5')
    ->render('rotated_90.png');
```

#### 5. 自适应尺寸（AutoSize）

```php
// 根据内容自动计算画布大小
TextToImg::instance()
    ->setAutoSize(true)
    ->setPadding(30)
    ->setText('根据内容自动调整画布大小')
    ->setFontSize(48)
    ->setColor('FFFFFF')
    ->setBgColor('FF5733')
    ->render('autosize.png');
```

### 水印功能

#### 文字水印

```php
TextToImg::instance(1200, 800)
    ->setText('主文字内容')
    ->setFontSize(60)
    ->addTextWatermark('内部资料', [
        'size'     => 24,
        'color'    => 'FFFFFF',
        'alpha'    => 40,
        'angle'    => 30,
        'position' => TextToImg::WATERMARK_CENTER,
    ])
    ->render('text_watermark.png');
```

#### 图片水印

```php
TextToImg::instance(1200, 800)
    ->setText('带图片水印')
    ->addImageWatermark('/path/to/logo.png', [
        'position' => TextToImg::WATERMARK_BOTTOM_RIGHT,
        'opacity'  => 60,
        'scale'    => 0.3,
        'margin'   => 30,
    ])
    ->render('image_watermark.png');
```

### 输出方式

```php
$img = TextToImg::instance(800, 600)
    ->setText('输出示例')
    ->setFontSize(48)
    ->build();

// 1. 保存到文件
$img->render('/path/to/output.png');

// 2. 获取 Base64
$base64 = $img->toBase64();
echo '<img src="' . $base64 . '">';

// 3. 浏览器直接输出（自动发送 Header）
$img->render();  // 或 render(null, true) 自动 exit

// 4. 获取 GD 资源（自定义处理）
$gdResource = $img->getImage();
```

### 批量生成

```php
// 批量生成多张图片，复用单例提高效率
$handlers = [
    function($img) { 
        return $img->setText('图片1')->render('output1.png'); 
    },
    function($img) { 
        return $img->setText('图片2')->render('output2.png'); 
    },
    function($img) { 
        return $img->setText('图片3')->render('output3.png'); 
    },
];

TextToImg::instance()->batch($handlers);
```

---

## Compressor - 图片压缩

轻量级 GD 图片压缩类，支持改变尺寸、压缩质量、等比例缩放，返回 Base64 或保存文件。

### 快速开始

```php
use zxf\Utils\Image\Compressor;

// 简单压缩（默认压缩率 70%）
Compressor::instance()
    ->set('source.jpg', 'output.jpg')
    ->get();

// 获取压缩信息
$result = Compressor::instance()
    ->set('source.jpg', 'output.jpg')
    ->compress(80)
    ->get(function($info) {
        echo "原图大小: {$info['original']['size']}\n";
        echo "压缩后: {$info['compressed']['size']}\n";
        echo "压缩率: {$info['compressed']['ratio']}\n";
    });
```

### 核心方法

| 方法 | 说明 |
|------|------|
| `set($srcPath, $savePath)` | 设置原图路径和保存路径（savePath 为 null 返回 base64） |
| `resize($width, $height)` | 调整尺寸，参数为 0 时保持比例 |
| `proportion($percent)` | 等比例缩放（0.1~1 缩小，>1 放大） |
| `compress($quality)` | 设置压缩质量 0-100（值越大质量越差） |
| `get($callback)` | 执行压缩并返回结果 |

### 使用示例

#### 仅压缩不改变尺寸

```php
$result = Compressor::instance()
    ->set('photo.jpg', 'photo_compressed.jpg')
    ->compress(75)  // 压缩质量 75
    ->get();

// $result 为布尔值，表示是否保存成功
```

#### 修改尺寸并压缩

```php
// 指定宽高（可能变形）
Compressor::instance()
    ->set('photo.jpg', 'photo_thumb.jpg')
    ->resize(500, 400)
    ->compress(80)
    ->get();

// 仅指定宽度，高度自适应
Compressor::instance()
    ->set('photo.jpg', 'photo_800w.jpg')
    ->resize(800, 0)
    ->compress(85)
    ->get();
```

#### 等比例缩放

```php
// 缩放到原图的 60%
Compressor::instance()
    ->set('photo.jpg', 'photo_small.jpg')
    ->proportion(0.6)
    ->compress(80)
    ->get();
```

#### 获取 Base64

```php
$base64 = Compressor::instance()
    ->set('photo.jpg')  // 不指定保存路径
    ->compress(70)
    ->get();

echo '<img src="' . $base64 . '">';
```

#### 获取压缩信息

```php
Compressor::instance()
    ->set('photo.jpg', 'output.jpg')
    ->resize(800, 600)
    ->compress(75)
    ->get(function($res) {
        print_r($res['original']);   // 原图信息
        print_r($res['compressed']); // 压缩后信息
    });
```

返回信息结构：

```php
[
    'original' => [
        'name'      => 'photo.jpg',
        'type'      => 'image/jpeg',
        'size'      => '2.5 MB',
        'bits'      => 2621440,
        'width'     => 1920,
        'height'    => 1080,
        'file_path' => '/path/to/photo.jpg',
    ],
    'compressed' => [
        'name'       => 'output.jpg',
        'type'       => 'image/jpeg',
        'size'       => '156 KB',
        'bits'       => 159744,
        'width'      => 800,
        'height'     => 600,
        'ratio'      => '93.9%',
        'save_path'  => '/path/to/output.jpg',
    ],
]
```

---

## ImagickTool - Imagick 高级图像处理

基于 ImageMagick 的专业级图像处理工具，提供更强大的文字渲染、滤镜效果、批量处理等能力。**需要安装 Imagick PHP 扩展。**

### 环境要求

```bash
# 检查 Imagick 扩展是否安装
php -m | grep imagick
```

### 核心功能

- 🎨 **文字图片生成**：基于 Imagick 的高质量文字渲染
- 🔧 **图像处理**：缩放、裁剪、旋转、翻转
- 🏷️ **水印系统**：文字水印、图片水印、批量水印
- 🎭 **滤镜特效**：模糊、锐化、浮雕、油画、像素化等
- 🖼️ **拼图布局**：支持 9 种布局（网格、螺旋、圆形等）
- 🔄 **格式转换**：支持 100+ 种图像格式

### 快速入门

```php
use zxf\Utils\Image\ImagickTool;

// 生成文字图片
ImagickTool::instance()
    ->createTextImage('Hello Imagick', 800, 400, '#667eea', '#FFFFFF')
    ->saveImage('output.png');

// 打开图片并添加水印
ImagickTool::instance()
    ->openImage('photo.jpg')
    ->addTextWatermark('版权所有', 'pmzdxx', 24, '#FFFFFF', ImagickTool::POSITION_BOTTOM_RIGHT)
    ->saveImage('watermarked.jpg');
```

### 文字生成图片

```php
ImagickTool::instance()
    ->createTextImage(
        text: '高质量文字渲染',
        width: 1000,
        height: 500,
        backgroundColor: '#F5F5F5',
        textColor: '#333333',
        textBackgroundColor: 'transparent',
        angle: 0,
        fontName: 'pmzdxx',  // 字体名称（无需路径）
        targetAreaRatio: 80, // 文字占画布比例
        lineHeightRatio: 1.2,
        textAlign: 'center',
        textValign: 'middle',
        padding: 30,
        textStyle: 'normal',  // normal, bold, italic
        textDecoration: 'none',  // none, underline, overline, line-through
        strokeColor: '#FF5733',  // 描边颜色
        strokeWidth: 2,
        textOpacity: 100,
        backgroundOpacity: 100,
        shadow: [
            'x' => 3,
            'y' => 3,
            'blur' => 5
        ]
    )
    ->saveImage('text_image.png');
```

### 图像处理

#### 基础操作

```php
$tool = ImagickTool::instance()->openImage('photo.jpg');

// 调整尺寸（保持比例）
$tool->resizeImage(800, 600, true);

// 裁剪
$tool->cropImage(100, 100, 400, 300);

// 旋转
$tool->rotateImage(45, '#FFFFFF');

// 水平翻转
$tool->flipImage();

// 保存
$tool->saveImage('processed.jpg', 'jpg', 90);
```

#### 滤镜效果

```php
$tool = ImagickTool::instance()->openImage('photo.jpg');

// 高斯模糊
$tool->applyFilter(ImagickTool::FILTER_GAUSSIAN_BLUR, [
    'radius' => 10,
    'sigma'  => 5
]);

// 锐化
$tool->applyFilter(ImagickTool::FILTER_SHARPEN, [
    'radius' => 2,
    'sigma'  => 1
]);

// 油画效果
$tool->applyFilter(ImagickTool::FILTER_OIL_PAINT, [
    'radius' => 3
]);

// 复古色调
$tool->applyFilter(ImagickTool::FILTER_SEPIA, [
    'threshold' => 80
]);

// 像素化
$tool->applyFilter(ImagickTool::FILTER_PIXELATE, [
    'width'  => 20,
    'height' => 20
]);

$tool->saveImage('filtered.jpg');
```

支持的所有滤镜：

| 常量 | 效果 | 参数 |
|------|------|------|
| `FILTER_GAUSSIAN_BLUR` | 高斯模糊 | radius, sigma |
| `FILTER_MOTION_BLUR` | 运动模糊 | radius, sigma, angle |
| `FILTER_RADIAL_BLUR` | 径向模糊 | angle |
| `FILTER_SHARPEN` | 锐化 | radius, sigma |
| `FILTER_EDGE_DETECT` | 边缘检测 | radius |
| `FILTER_EMBOSS` | 浮雕 | radius, sigma |
| `FILTER_OIL_PAINT` | 油画 | radius |
| `FILTER_CHARCOAL` | 炭笔 | radius, sigma |
| `FILTER_SEPIA` | 复古/棕褐色 | threshold |
| `FILTER_PIXELATE` | 像素化 | width, height |

### 水印系统

#### 文字水印

```php
ImagickTool::instance()
    ->openImage('photo.jpg')
    ->addTextWatermark(
        text: '版权所有 © 2024',
        fontName: 'pmzdxx',
        fontSize: 24,
        color: '#FFFFFF',
        position: ImagickTool::POSITION_BOTTOM_RIGHT,
        angle: 0,
        padding: 20,
        textAntialias: true,
        strokeColor: '#000000',
        strokeWidth: 1,
        backgroundColor: '#000000',
        backgroundOpacity: 50
    )
    ->saveImage('watermarked.jpg');
```

#### 图片水印

```php
ImagickTool::instance()
    ->openImage('photo.jpg')
    ->addImageWatermark(
        watermarkPath: 'logo.png',
        x: 0,
        y: 0,
        opacity: 50,
        composite: Imagick::COMPOSITE_OVER,
        position: ImagickTool::POSITION_TOP_LEFT,
        padding: 20
    )
    ->saveImage('watermarked.jpg');
```

#### 批量添加水印

```php
$results = ImagickTool::instance()->batchWatermark(
    imagePaths: ['1.jpg', '2.jpg', '3.jpg'],
    watermarkPath: 'logo.png',
    position: ImagickTool::POSITION_BOTTOM_RIGHT,
    opacity: 50,
    outputDir: '/path/to/output'
);
```

### 拼图布局

```php
$images = ['1.jpg', '2.jpg', '3.jpg', '4.jpg'];

ImagickTool::instance()
    ->createCollage(
        imagePaths: $images,
        layout: ImagickTool::LAYOUT_GRID_2X2,  // 2x2 网格
        canvasWidth: 1200,
        canvasHeight: 1200,
        spacing: 10,
        backgroundColor: '#FFFFFF'
    )
    ->saveImage('collage.jpg');
```

支持的布局常量：

| 常量 | 说明 |
|------|------|
| `LAYOUT_HORIZONTAL` | 水平排列 |
| `LAYOUT_VERTICAL` | 垂直排列 |
| `LAYOUT_GRID_2X2` | 2x2 网格 |
| `LAYOUT_GRID_3X3` | 3x3 网格 |
| `LAYOUT_GRID_4X4` | 4x4 网格 |
| `LAYOUT_DIAGONAL` | 对角线布局 |
| `LAYOUT_SPIRAL` | 螺旋布局 |
| `LAYOUT_CIRCLE` | 圆形布局 |
| `LAYOUT_MOSAIC` | 马赛克布局 |
| `LAYOUT_COLLAGE` | 拼贴布局 |

### 输出与保存

```php
$tool = ImagickTool::instance()->openImage('photo.jpg');

// 保存到文件
$tool->saveImage('output.jpg', 'jpg', 90);

// 浏览器输出
$tool->outputToBrowser('png', 85, 'download.png', false);

// 获取二进制数据
$blob = $tool->getImageBlob();
```

### 位置常量

所有水印和定位方法使用统一的位置常量：

```
1 2 3    左上  中上  右上
4 5 6    左中  中心  右中
7 8 9    左下  中下  右下
```

```php
ImagickTool::POSITION_TOP_LEFT      // 1
ImagickTool::POSITION_TOP_CENTER    // 2
ImagickTool::POSITION_TOP_RIGHT     // 3
ImagickTool::POSITION_MIDDLE_LEFT   // 4
ImagickTool::POSITION_CENTER        // 5
ImagickTool::POSITION_MIDDLE_RIGHT  // 6
ImagickTool::POSITION_BOTTOM_LEFT   // 7
ImagickTool::POSITION_BOTTOM_CENTER // 8
ImagickTool::POSITION_BOTTOM_RIGHT  // 9
```

---

## 三剑客对比

| 特性 | TextToImg | Compressor | ImagickTool |
|------|-----------|------------|-------------|
| **依赖** | GD | GD | Imagick |
| **主要用途** | 文字生成图片 | 图片压缩 | 高级图像处理 |
| **文字渲染** | ✅ 优秀 | ❌ 不支持 | ✅ 优秀 |
| **旋转文字** | ✅ 支持（修复版） | ❌ 不支持 | ✅ 支持 |
| **滤镜特效** | ✅ 基础 | ❌ 不支持 | ✅ 丰富 |
| **水印** | ✅ 支持 | ❌ 不支持 | ✅ 丰富 |
| **拼图** | ❌ 不支持 | ❌ 不支持 | ✅ 支持 |
| **性能** | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **功能丰富度** | ⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐⭐ |

## 最佳实践

1. **仅文字生成**：优先使用 `TextToImg`，功能完善且无需额外扩展
2. **简单压缩**：使用 `Compressor`，轻量高效
3. **复杂图像处理**：使用 `ImagickTool`，功能最全面
4. **批量处理**：所有类都支持批量操作，注意内存管理

## 常见问题

### Q: TextToImg 的 setFontSize 设置后无效？
A: 已修复。之前版本中当设置旋转角度时，`wrapText` 错误地使用了旋转角度测量文字宽度，导致过度换行。现在已改为始终使用水平宽度测量。

### Q: 旋转后的文字溢出图片边界？
A: 已修复。现在在 `drawRotatedTextBlock` 中增加了溢出检测与自适应缩放，当旋转后的文字块超过画布安全区时会自动等比例缩放。

### Q: ImagickTool 提示扩展未加载？
A: 需要安装 Imagick PHP 扩展：
```bash
# Ubuntu/Debian
sudo apt-get install php-imagick

# CentOS/RHEL
sudo yum install php-imagick

# macOS
brew install imagemagick
pecl install imagick
```

---

**文档版本**: 1.0  
**最后更新**: 2026-04-23

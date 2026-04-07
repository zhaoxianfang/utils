# Zxf QrCode

一个基于原生 PHP 8.2+ 和 GD 库实现的现代化、功能强大的二维码生成器扩展包。

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## ✨ 特性

- ✅ **完全原生**：基于 PHP 8.2+ 和 GD 库实现，无需外部依赖
- ✅ **国际标准**：符合 ISO/IEC 18004 国际标准
- ✅ **完整版本**：支持所有标准二维码版本（1-40）
- ✅ **纠错级别**：四种错误纠正级别（L/M/Q/H）
- ✅ **多编码支持**：支持 UTF-8、GBK、ISO-8859-1、Shift-JIS、Big5 等编码
- ✅ **多语言**：完美支持中文、日文等多语言内容
- ✅ **颜色自定义**：自定义前景色和背景色
- ✅ **Logo 支持**：支持添加 Logo 图片（自动限制为二维码宽度的20%以内）
- ✅ **Logo 增强**：支持阴影、透明度、旋转、圆角、圆形效果
- ✅ **文本标签**：支持添加文本标签（多行、自动换行、背景色、边框、圆角）
- ✅ **标签样式**：字体、字号、颜色、对齐方式、内外边距等自定义
- ✅ **标签特效**：支持标签阴影和描边效果
- ✅ **圆点风格**：支持圆点风格二维码（可自定义圆点半径0-1）
- ✅ **背景图片**：支持设置背景图片
- ✅ **多种格式**：支持 PNG、JPEG、GIF 输出
- ✅ **透明背景**：支持生成透明背景的二维码（仅PNG格式）
- ✅ **边距控制**：支持模块边距（margin）和图片外边距（padding）
- ✅ **链式调用**：简洁优雅的 API 设计
- ✅ **中文文档**：完善的中文注释和文档
- ✅ **辅助工具**：提供便捷的静态方法和工具函数

## 📦 安装

```bash
composer require zxf/utils
```

## 🔧 环境要求

- PHP >= 8.2
- GD 扩展
- TrueType 字体支持（用于标签功能）

## 📖 快速开始

### 基础用法

```php
<?php

require_once 'vendor/autoload.php';

use zxf\Utils\QrCode\QrCode;

// 生成最简单的二维码
QrCode::make('Hello World!')
    ->size(300)
    ->save('qrcode.png');
```

### 保存为不同格式

```php
// 保存为 PNG 文件（默认）
QrCode::make('https://www.example.com')
    ->size(300)
    ->save('url_qrcode.png');

// 保存为 JPEG 文件
QrCode::make('Test')
    ->size(300)
    ->format('jpeg')
    ->quality(90)
    ->save('qrcode.jpg');

// 保存为 GIF 文件
QrCode::make('Test')
    ->size(300)
    ->format('gif')
    ->save('qrcode.gif');
```

### 输出到浏览器

```php
// 直接输出到浏览器
header('Content-Type: image/png');
QrCode::make('https://www.example.com')
    ->size(300)
    ->output();
```

### 获取图像数据

```php
// 获取二进制数据
$imageData = QrCode::make('Test')
    ->size(300)
    ->toString();

// 获取 Base64 编码
$base64 = QrCode::make('Test')
    ->size(300)
    ->toBase64();
```

---

## 📋 完整功能参考

### 1. 基本配置方法

| 方法 | 参数类型 | 参数说明 | 默认值 | 范围限制 | 示例 |
|------|---------|---------|--------|---------|------|
| `data()` | string | 要编码的数据内容 | '' | 无 | `->data('Hello')` |
| `size()` | int | 二维码尺寸（像素） | 300 | 50-5000 | `->size(300)` |
| `margin()` | int | 二维码边距（模块数量） | 4 | 0-32 | `->margin(4)` |
| `padding()` | int | 二维码外边距（像素） | 0 | 0-200 | `->padding(10)` |
| `setMargins()` | int, int | 同时设置 margin 和 padding | - | - | `->setMargins(4, 10)` |
| `errorCorrectionLevel()` | string/对象 | 纠错级别（L/M/Q/H） | M | L/M/Q/H | `->errorCorrectionLevel('H')` |
| `version()` | int | 二维码版本（0=自动） | 0 | 0-40 | `->version(10)` |
| `encoding()` | string | 字符编码 | UTF-8 | - | `->encoding('UTF-8')` |
| `optimizeForLongText()` | string | 优化长文本 | - | - | `->optimizeForLongText($text)` |
| `skipContrastValidation()` | void | 跳过对比度验证 | false | - | `->skipContrastValidation()` |

**示例：**
```php
QrCode::make('Hello World')
    ->size(300)
    ->margin(4)
    ->padding(10)
    ->errorCorrectionLevel('H')
    ->version(10)
    ->encoding('UTF-8')
    ->save('basic_config.png');
```

### 2. 颜色配置方法

| 方法 | 参数类型 | 参数说明 | 示例 |
|------|---------|---------|------|
| `foregroundColor()` | Color/string | 前景色（模块颜色） | `->foregroundColor('#000000')` |
| `backgroundColor()` | Color/string/path | 背景色或背景图片路径 | `->backgroundColor('#FFFFFF')` |
| `colors()` | Color/string, Color/string | 同时设置前景色和背景色 | `->colors('#000000', '#FFFFFF')` |
| `transparentBackground()` | bool | 是否透明背景 | `->transparentBackground(true)` |

**颜色设置示例：**

```php
use zxf\Utils\QrCode\Color\Color;

// 使用十六进制颜色
QrCode::make('Color Test')
    ->size(300)
    ->foregroundColor('#FF5733')
    ->backgroundColor('#FFE5D9')
    ->save('color_hex.png');

// 使用 Color 对象
QrCode::make('Color Test')
    ->size(300)
    ->foregroundColor(Color::fromHex('#FF5733'))
    ->backgroundColor(Color::white())
    ->save('color_object.png');

// 使用预定义颜色
QrCode::make('Color Test')
    ->size(300)
    ->foregroundColor(Color::blue())
    ->backgroundColor(Color::white())
    ->save('color_predefined.png');

// 透明背景
QrCode::make('Transparent')
    ->size(300)
    ->foregroundColor(Color::black())
    ->transparentBackground(true)
    ->save('transparent.png');

// 背景图片
QrCode::make('Background Image')
    ->size(300)
    ->backgroundColor('/path/to/background.jpg')
    ->save('bg_image.png');
```

### 3. Logo 配置方法

| 方法 | 参数类型 | 参数说明 | 默认值/范围 | 示例 |
|------|---------|---------|------------|------|
| `logo()` | string, int?, int? | Logo路径、宽度、高度 | 0, 0 | `->logo('/path/logo.png', 60, 60)` |
| `logoScale()` | int | Logo缩放比例（百分比） | 0-20 | `->logoScale(15)` |
| `logoCircular()` | bool | Logo是否圆形 | false | `->logoCircular(true)` |
| `logoRounded()` | bool, float | Logo是否圆角、圆角半径 | false, 0.2 | `->logoRounded(true, 0.3)` |
| `logoBackgroundColor()` | Color/string | Logo背景色 | null | `->logoBackgroundColor('#FFFFFF')` |
| `logoShadow()` | Color/string, int, int | Logo阴影颜色、X偏移、Y偏移 | null, 2, 2 | `->logoShadow('#000000', 4, 4)` |
| `logoOpacity()` | int | Logo透明度（0-100） | 100 | `->logoOpacity(70)` |
| `logoRotation()` | float | Logo旋转角度（度） | 0 | `->logoRotation(45)` |

**Logo 使用示例：**

```php
// 基础 Logo
QrCode::make('Logo Test')
    ->size(300)
    ->logo('/path/to/logo.png')
    ->save('logo_basic.png');

// 自定义 Logo 尺寸
QrCode::make('Logo Test')
    ->size(300)
    ->logo('/path/to/logo.png', 60, 60)
    ->save('logo_size.png');

// Logo 缩放比例
QrCode::make('Logo Test')
    ->size(300)
    ->logo('/path/to/logo.png')
    ->logoScale(15)  // Logo 占二维码 15%
    ->save('logo_scale.png');

// 圆形 Logo
QrCode::make('Circular Logo')
    ->size(300)
    ->logo('/path/to/logo.png')
    ->logoCircular(true)
    ->save('logo_circular.png');

// 圆角 Logo
QrCode::make('Rounded Logo')
    ->size(300)
    ->logo('/path/to/logo.png')
    ->logoRounded(true, 0.3)
    ->save('logo_rounded.png');

// Logo 透明度
QrCode::make('Transparent Logo')
    ->size(300)
    ->logo('/path/to/logo.png')
    ->logoOpacity(70)  // 70% 不透明度
    ->save('logo_opacity.png');

// Logo 阴影
use zxf\Utils\QrCode\Color\Color;
QrCode::make('Logo with Shadow')
    ->size(300)
    ->logo('/path/to/logo.png')
    ->logoShadow(Color::fromHex('#000000'), 4, 4)
    ->save('logo_shadow.png');

// Logo 旋转
QrCode::make('Rotated Logo')
    ->size(300)
    ->logo('/path/to/logo.png')
    ->logoRotation(45)
    ->save('logo_rotation.png');

// Logo 背景色
QrCode::make('Logo with Background')
    ->size(300)
    ->logo('/path/to/logo.png')
    ->logoBackgroundColor(Color::white())
    ->save('logo_bg.png');

// 完整 Logo 配置
QrCode::make('Full Logo')
    ->size(400)
    ->logo('/path/to/logo.png')
    ->logoScale(15)
    ->logoRounded(true, 0.2)
    ->logoOpacity(80)
    ->logoBackgroundColor(Color::white())
    ->logoShadow(Color::fromHex('#00000080'), 3, 3)
    ->logoRotation(0)
    ->save('logo_full.png');
```

### 4. 圆点风格配置方法

| 方法 | 参数类型 | 参数说明 | 默认值/范围 | 示例 |
|------|---------|---------|------------|------|
| `rounded()` | bool | 是否使用圆点风格 | false | `->rounded(true)` |
| `roundedRadius()` | float | 圆点半径（0-1） | 0.5 | `->roundedRadius(0.6)` |

**圆点风格示例：**

```php
// 基础圆点风格
QrCode::make('Rounded')
    ->size(300)
    ->rounded(true)
    ->save('rounded_basic.png');

// 自定义圆点半径
QrCode::make('Rounded Radius 0.3')
    ->size(300)
    ->rounded(true)
    ->roundedRadius(0.3)
    ->save('rounded_03.png');

QrCode::make('Rounded Radius 0.5')
    ->size(300)
    ->rounded(true)
    ->roundedRadius(0.5)
    ->save('rounded_05.png');

QrCode::make('Rounded Radius 0.6')
    ->size(300)
    ->rounded(true)
    ->roundedRadius(0.6)
    ->save('rounded_06.png');

QrCode::make('Rounded Radius 0.8')
    ->size(300)
    ->rounded(true)
    ->roundedRadius(0.8)
    ->save('rounded_08.png');

QrCode::make('Rounded Radius 1.0')
    ->size(300)
    ->rounded(true)
    ->roundedRadius(1.0)
    ->save('rounded_10.png');

// 圆点 + Logo 组合
QrCode::make('Rounded with Logo')
    ->size(400)
    ->rounded(true)
    ->roundedRadius(0.6)
    ->logo('/path/to/logo.png')
    ->logoScale(15)
    ->errorCorrectionLevel('H')  // 建议提高纠错级别
    ->save('rounded_logo.png');
```

### 5. 标签配置方法

| 方法 | 参数类型 | 参数说明 | 默认值/范围 | 示例 |
|------|---------|---------|------------|------|
| `label()` | LabelOptions\|null | 标签配置对象 | null | `->label(LabelOptions::create('Text'))` |
| `labelText()` | string, string? | 标签文本、字体路径 | null, null | `->labelText('Scan Me')` |
| `labelOptions()` | LabelOptions\|null | 标签配置对象（别名） | null | `->labelOptions($options)` |

**LabelOptions 配置方法：**

| 方法 | 参数类型 | 参数说明 | 默认值 | 示例 |
|------|---------|---------|--------|------|
| `text()` | string | 标签文本 | null | `->text('Scan Me')` |
| `fontPath()` | string\|null | 字体文件路径或内置字体名 | 内置字体 | `->fontPath('lishu')` |
| `fontSize()` | int | 字体大小 | 22 | `->fontSize(16)` |
| `color()` | Color/string | 文本颜色 | 黑色 | `->color('#FF5733')` |
| `backgroundColor()` | Color/string | 背景色 | null | `->backgroundColor('#FFE5D9')` |
| `borderColor()` | Color/string | 边框颜色 | null | `->borderColor('#FF5733')` |
| `borderWidth()` | int | 边框宽度 | 0 | `->borderWidth(2)` |
| `borderRadius()` | float | 圆角半径 | 0 | `->borderRadius(8)` |
| `padding()` | int | 内边距（所有方向） | 5/10 | `->padding(10)` |
| `paddingTop/Bottom/Left/Right()` | int | 各方向内边距 | 5/10 | `->paddingTop(5)` |
| `marginTop/Bottom/Left/Right()` | int | 各方向外边距 | 10/0 | `->marginTop(20)` |
| `margin()` | int | 外边距（所有方向） | - | `->margin(20)` |
| `lineHeight()` | int | 行高 | 20 | `->lineHeight(24)` |
| `alignment()` | string | 对齐方式（left/center/right） | center | `->alignment('center')` |
| `textShadow()` | Color/string, int, int | 阴影颜色、X偏移、Y偏移 | - | `->textShadow('#000000', 2, 2)` |
| `textStroke()` | Color/string, int | 描边颜色、宽度 | - | `->textStroke('#FFFFFF', 1)` |
| `fontSizeAutoLineHeight()` | int | 字体大小并自动调整行高 | - | `->fontSizeAutoLineHeight(16)` |

**静态方法：**

| 方法 | 参数 | 说明 |
|------|------|------|
| `setDefaultFontPath()` | string | 设置全局默认字体路径 |
| `create()` | string, string? | 创建 LabelOptions 实例 |

**标签使用示例：**

```php
use zxf\Utils\QrCode\LabelOptions;
use zxf\Utils\QrCode\Color\Color;

// 简单标签
QrCode::make('Label Test')
    ->size(300)
    ->labelText('Scan Me!')
    ->save('label_simple.png');

// 多行标签
QrCode::make('Multi-line Label')
    ->size(300)
    ->labelText("Line 1\nLine 2\nLine 3")
    ->save('label_multiline.png');

// 使用 LabelOptions 创建标签
QrCode::make('Label Options')
    ->size(300)
    ->label(LabelOptions::create('Scan Me!'))
    ->save('label_options.png');

// 自定义字体
QrCode::make('Custom Font')
    ->size(300)
    ->labelText('Scan Me!', '/path/to/font.ttf')
    ->save('label_custom_font.png');

// 使用内置字体
QrCode::make('Built-in Font')
    ->size(300)
    ->labelText('扫描我', 'lishu')  // 使用内置隶书字体
    ->save('label_builtin_font.png');

// 自定义文本样式
$options = LabelOptions::create('Scan Me!')
    ->fontSize(18)
    ->color('#FF5733')
    ->fontPath('xingkai');

QrCode::make('Styled Label')
    ->size(300)
    ->label($options)
    ->save('label_styled_text.png');

// 标签背景和边框
$options = LabelOptions::create('Label')
    ->backgroundColor('#FFE5D9')
    ->borderColor('#FF5733')
    ->borderWidth(2)
    ->borderRadius(8)
    ->padding(10);

QrCode::make('Label with Border')
    ->size(300)
    ->label($options)
    ->save('label_border.png');

// 标签对齐方式
$options = LabelOptions::create('Left Aligned')
    ->alignment('left');

QrCode::make('Left Label')
    ->size(300)
    ->label($options)
    ->save('label_left.png');

// 标签阴影
$options = LabelOptions::create('Shadow Text')
    ->textShadow('#00000080', 2, 2);

QrCode::make('Label Shadow')
    ->size(300)
    ->label($options)
    ->save('label_shadow.png');

// 标签描边
$options = LabelOptions::create('Stroke Text')
    ->textStroke('#FFFFFF', 1);

QrCode::make('Label Stroke')
    ->size(300)
    ->label($options)
    ->save('label_stroke.png');

// 完整标签配置
$options = LabelOptions::create('扫描二维码')
    ->fontSize(18)
    ->color('#333333')
    ->fontPath('xingkai')
    ->backgroundColor('#FFF5E6')
    ->borderColor('#FF6B6B')
    ->borderWidth(2)
    ->borderRadius(10)
    ->padding(12)
    ->margin(15)
    ->lineHeight(22)
    ->alignment('center')
    ->textShadow('#00000033', 2, 2);

QrCode::make('Full Label')
    ->size(400)
    ->label($options)
    ->save('label_full.png');
```

### 6. 输出配置方法

| 方法 | 参数类型 | 参数说明 | 默认值/范围 | 示例 |
|------|---------|---------|------------|------|
| `format()` | string | 输出格式（png/jpeg/gif） | png | `->format('jpeg')` |
| `quality()` | int | 图片质量（0-100） | 90 | `->quality(90)` |

**输出方法：**

| 方法 | 返回类型 | 说明 |
|------|---------|------|
| `save()` | bool | 保存到文件 |
| `toString()` | string | 获取二进制数据 |
| `toBase64()` | string | 获取 Base64 编码 |
| `output()` | void | 输出到浏览器 |
| `render()` | GdImage | 获取 GD 图像资源 |

**输出示例：**

```php
// 保存为不同格式
QrCode::make('Output Test')
    ->size(300)
    ->format('png')
    ->save('output.png');

QrCode::make('Output Test')
    ->size(300)
    ->format('jpeg')
    ->quality(90)
    ->save('output.jpg');

QrCode::make('Output Test')
    ->size(300)
    ->format('gif')
    ->save('output.gif');

// 输出到浏览器
header('Content-Type: image/png');
QrCode::make('Output Test')
    ->size(300)
    ->output();

// 获取二进制数据
$imageData = QrCode::make('Output Test')
    ->size(300)
    ->toString();

// 获取 Base64
$base64 = QrCode::make('Output Test')
    ->size(300)
    ->toBase64();

// 获取 GD 图像资源
$image = QrCode::make('Output Test')
    ->size(300)
    ->render();
```

### 7. Getter 方法

| 方法 | 返回类型 | 说明 |
|------|---------|------|
| `getData()` | string | 获取编码数据 |
| `getSize()` | int | 获取尺寸 |
| `getMargin()` | int | 获取边距 |
| `getPadding()` | int | 获取外边距 |
| `getVersion()` | int | 获取二维码版本 |
| `getEncoding()` | string | 获取编码格式 |
| `getFormat()` | string | 获取输出格式 |
| `getQuality()` | int | 获取图片质量 |
| `getErrorCorrectionLevel()` | ErrorCorrectionLevel | 获取纠错级别 |
| `getForegroundColor()` | Color | 获取前景色 |
| `getBackgroundColor()` | Color | 获取背景色 |
| `getLogoPath()` | string|null | 获取Logo路径 |
| `isRounded()` | bool | 检查是否启用圆点风格 |
| `getRoundedRadius()` | float | 获取圆点半径 |
| `isTransparentBackground()` | bool | 检查是否启用透明背景 |
| `getInfo()` | array | 获取完整配置信息 |
| `cloneWithData(string $newData)` | QrCode | 克隆配置并修改数据 |

**示例：**
```php
$info = QrCode::make('Test')
    ->size(300)
    ->errorCorrectionLevel('H')
    ->getInfo();

print_r($info);
/*
Array (
    [data] => Test
    [size] => 300
    [margin] => 4
    [padding] => 0
    [version] => 0
    [errorCorrectionLevel] => H
    [encoding] => UTF-8
    [hasLogo] => false
    [rounded] => false
    [roundedRadius] => 0.5
    [hasLabel] => false
    [transparentBackground] => false
)
*/
```

---

## 🎨 高级功能

### AdvancedFeatures 类

```php
use zxf\Utils\QrCode\AdvancedFeatures;
use zxf\Utils\QrCode\Color\Color;

// 创建渐变背景
$gradient = AdvancedFeatures::createGradientBackground(
    300,           // 宽度
    300,           // 高度
    '#FF6B6B',     // 起始颜色
    '#4ECDC4',     // 结束颜色
    'vertical'     // 方向：horizontal/vertical/diagonal
);

QrCode::make('Gradient Background')
    ->size(300)
    ->backgroundColor($gradient)
    ->save('gradient_bg.png');

// 添加水印
$image = QrCode::make('Watermark')->size(300)->render();
AdvancedFeatures::addWatermark(
    $image,
    '/path/to/watermark.png',
    'bottom-right',  // 位置
    50              // 透明度
);
imagepng($image, 'watermark_qrcode.png');

// 添加文字水印
$image = QrCode::make('Text Watermark')->size(300)->render();
AdvancedFeatures::addTextWatermark(
    $image,
    '© 2024',
    '/path/to/font.ttf',
    14,
    '#000000',
    'bottom-right',
    0
);
imagepng($image, 'text_watermark_qrcode.png');

// 批量生成
$results = AdvancedFeatures::batchGenerate(
    ['Data 1', 'Data 2', 'Data 3'],  // 数据列表
    './output',                        // 输出目录
    300,                              // 尺寸
    'qr_',                            // 文件名前缀
    ['errorCorrectionLevel' => 'M']   // 选项
);

print_r($results);
/*
Array (
    [total] => 3
    [success] => 3
    [failed] => 0
    [files] => [...]
)
*/

// 从模板生成
AdvancedFeatures::generateFromTemplate(
    '/path/to/template.jpg',
    'QR Data',
    'output.jpg',
    [50, 50, 200, 200],  // x, y, width, height
    ['size' => 200]
);

// 添加边框
$image = QrCode::make('Border')->size(300)->render();
AdvancedFeatures::addBorder(
    $image,
    '#000000',
    2,
    8
);
imagepng($image, 'border_qrcode.png');
```

### QrCodeHelper 辅助类

```php
use zxf\Utils\QrCode\QrCodeHelper;

// 快速生成
QrCodeHelper::quickGenerate(
    'Hello World',
    'quick_qrcode.png',
    ['size' => 300, 'errorCorrectionLevel' => 'M']
);

// 估算版本
$version = QrCodeHelper::estimateVersion('My QR Code Data', 'M');
echo "推荐版本: {$version}\n";

// 获取容量信息
$capacity = QrCodeHelper::getCapacity(10, 'M');
print_r($capacity);
/*
Array (
    [version] => 10
    [ecLevel] => M
    [numeric] => 461
    [alphanumeric] => 345
    [byte] => 253
    [kanji] => 184
    [dimensions] => 57
)
*/

// 验证数据
$validation = QrCodeHelper::validateData('Test Data', 'UTF-8');
print_r($validation);
/*
Array (
    [valid] => true
    [errors] => []
    [warnings] => []
    [info] => [...]
)
*/

// 计算推荐尺寸
$size = QrCodeHelper::calculateRecommendedSize(10, 5, 4);
echo "推荐尺寸: {$size} 像素\n";

// 环境检查
$envCheck = QrCodeHelper::checkEnvironment();
print_r($envCheck);

// 批量准备
$batchConfig = QrCodeHelper::prepareBatch(
    ['Data 1', 'Data 2'],
    './output',
    ['size' => 300]
);

// 生成WiFi配置字符串
$wifiString = QrCodeHelper::generateWifiString('MyNetwork', 'password123', 'WPA');
QrCode::make($wifiString)->save('wifi.png');

// 生成VCard名片字符串
$vcardString = QrCodeHelper::generateVCardString([
    'name' => '张三',
    'phone' => '13800138000',
    'email' => 'zhangsan@example.com',
    'company' => '示例公司'
]);
QrCode::make($vcardString)->save('vcard.png');

// 分析二维码数据类型
$analysis = QrCodeHelper::analyzeData('https://www.example.com');
echo "数据类型: {$analysis['type']}\n";
echo "推荐纠错级别: {$analysis['recommended']['errorCorrectionLevel']}\n";

// 生成SVG格式二维码
$svg = QrCodeHelper::generateSvg('Hello World', 300);
file_put_contents('qrcode.svg', $svg);
```

---

## 📊 配置参考

### 纠错级别

| 级别 | 说明 | 纠错能力 | 使用场景 |
|------|------|----------|---------|
| L | Low | 约 7% | 数据量大，环境清洁 |
| M | Medium | 约 15% | 一般环境（推荐） |
| Q | Quartile | 约 25% | 有 Logo 或污损可能 |
| H | High | 约 30% | 恶劣环境或大 Logo |

### 版本说明

| 版本范围 | 模块数 | 容量（M级别） | 适用场景 |
|---------|--------|--------------|---------|
| 1-10 | 21-57 | 小型数据 | 名片、标签 |
| 11-20 | 61-97 | 中型数据 | 海报、传单 |
| 21-30 | 101-137 | 大型数据 | 广告牌、展示屏 |
| 31-40 | 141-177 | 超大数据 | 大型广告、印刷品 |

### 尺寸限制

| 参数 | 范围 | 默认值 | 说明 |
|------|------|--------|------|
| 尺寸 | 50-5000 像素 | 300 | 最终图片尺寸 |
| 边距 | 0-32 模块 | 4 | 模块边距（空白区域） |
| 外边距 | 0-200 像素 | 0 | 图片外边距（padding） |
| Logo 比例 | 1-20% | 15% | Logo 占二维码宽度比例 |

### 支持的格式

| 格式 | 扩展名 | 透明背景 | 质量参数 | MIME 类型 |
|------|--------|---------|---------|-----------|
| PNG | .png | ✅ 支持 | 0-9 | image/png |
| JPEG | .jpg/.jpeg | ❌ 不支持 | 0-100 | image/jpeg |
| GIF | .gif | ❌ 不支持 | 无 | image/gif |

### 支持的编码

| 编码 | 说明 | 语言支持 |
|------|------|---------|
| UTF-8 | 通用编码 | 所有语言 |
| GBK | 中文编码 | 简体中文 |
| ISO-8859-1 | 西欧编码 | 拉丁语系 |
| Shift-JIS | 日文编码 | 日语 |
| Big5 | 繁体中文编码 | 繁体中文 |

---

## 🎯 完整示例集合

### 示例 1：最简单二维码

```php
QrCode::make('Hello World')
    ->size(300)
    ->save('example1_basic.png');
```

### 示例 2：自定义颜色

```php
QrCode::make('Color QR')
    ->size(300)
    ->foregroundColor('#FF5733')
    ->backgroundColor('#FFE5D9')
    ->save('example2_color.png');
```

### 示例 3：圆点风格

```php
QrCode::make('Rounded QR')
    ->size(300)
    ->rounded(true)
    ->roundedRadius(0.6)
    ->save('example3_rounded.png');
```

### 示例 4：带 Logo

```php
QrCode::make('Logo QR')
    ->size(400)
    ->logo('/path/to/logo.png')
    ->logoScale(15)
    ->errorCorrectionLevel('H')
    ->save('example4_logo.png');
```

### 示例 5：带标签

```php
use zxf\Utils\QrCode\LabelOptions;

$options = LabelOptions::create('扫描我')
    ->fontSize(16)
    ->color('#FF5733')
    ->backgroundColor('#FFE5D9')
    ->borderColor('#FF5733')
    ->borderWidth(1)
    ->margin(15);

QrCode::make('Label QR')
    ->size(300)
    ->label($options)
    ->save('example5_label.png');
```

### 示例 6：透明背景

```php
QrCode::make('Transparent QR')
    ->size(300)
    ->foregroundColor(Color::black())
    ->transparentBackground(true)
    ->save('example6_transparent.png');
```

### 示例 7：高级 Logo 配置

```php
QrCode::make('Advanced Logo QR')
    ->size(500)
    ->logo('/path/to/logo.png')
    ->logoScale(15)
    ->logoRounded(true, 0.2)
    ->logoOpacity(80)
    ->logoBackgroundColor(Color::white())
    ->logoShadow(Color::fromHex('#00000080'), 4, 4)
    ->errorCorrectionLevel('H')
    ->save('example7_advanced_logo.png');
```

### 示例 8：完整配置

```php
use zxf\Utils\QrCode\LabelOptions;
use zxf\Utils\QrCode\Color\Color;

$labelOptions = LabelOptions::create('扫描二维码')
    ->fontSize(18)
    ->color('#333333')
    ->fontPath('xingkai')
    ->backgroundColor('#FFF5E6')
    ->borderColor('#FF6B6B')
    ->borderWidth(2)
    ->borderRadius(10)
    ->padding(12)
    ->margin(15)
    ->alignment('center');

QrCode::make('完整配置示例')
    ->size(500)
    ->margin(4)
    ->padding(20)
    ->errorCorrectionLevel('H')
    ->version(0)
    ->encoding('UTF-8')
    ->foregroundColor('#2C3E50')
    ->backgroundColor('#ECF0F1')
    ->rounded(false)
    ->logo('/path/to/logo.png')
    ->logoScale(12)
    ->logoRounded(true, 0.2)
    ->logoOpacity(90)
    ->logoBackgroundColor(Color::white())
    ->label($labelOptions)
    ->format('png')
    ->quality(95)
    ->save('example8_full.png');
```

### 示例 9：批量生成

```php
$dataList = [
    'https://www.example.com',
    'tel:+1234567890',
    'mailto:contact@example.com',
    'WIFI:S:MyNetwork;T:WPA;P:MyPassword;;'
];

$results = AdvancedFeatures::batchGenerate(
    $dataList,
    './batch_output',
    300,
    'qr_',
    ['errorCorrectionLevel' => 'M', 'margin' => 4]
);

echo "生成完成：成功 {$results['success']}，失败 {$results['failed']}\n";
```

### 示例 10：获取信息

```php
$qrCode = QrCode::make('Info Example')
    ->size(400)
    ->errorCorrectionLevel('H')
    ->rounded(true)
    ->roundedRadius(0.6);

$info = $qrCode->getInfo();
print_r($info);

// 保存二维码
$qrCode->save('example10_info.png');
```

---

## 🔧 故障排除

### 常见问题

**Q: 生成的二维码无法扫描？**

检查清单：
- [ ] 模块大小是否 ≥ 2 像素
- [ ] 对比度是否 ≥ 2:1
- [ ] Logo 是否 ≤ 20%
- [ ] 圆点半径是否在 0.5-0.7 之间
- [ ] 纠错级别是否足够（建议 M 或更高）

**Q: 如何优化长文本？**

```php
// 使用较低纠错级别
QrCode::make($longText)
    ->errorCorrectionLevel('L')
    ->size(400)
    ->save('optimized.png');
```

**Q: Logo 导致扫描失败？**

```php
// 减小 Logo 或提高纠错级别
QrCode::make($data)
    ->logo('/path/logo.png')
    ->logoScale(10)              // 减小到 10%
    ->errorCorrectionLevel('H')  // 提高到 H
    ->save('scanable.png');
```

**Q: 圆点风格无法扫描？**

```php
// 调整圆点半径和尺寸
QrCode::make($data)
    ->rounded(true)
    ->roundedRadius(0.6)  // 推荐值
    ->size(400)            // 增大尺寸
    ->errorCorrectionLevel('H')
    ->save('rounded_scanable.png');
```

**Q: 内存溢出？**

```php
// 分批生成
for ($i = 0; $i < 100; $i += 10) {
    $batch = array_slice($dataList, $i, 10);
    AdvancedFeatures::batchGenerate($batch, './output', 300, 'qr_');
}
```

---

## 📊 性能优化建议

### 1. 尺寸选择

```php
// 根据使用场景选择合适尺寸
$size = match($scenario) {
    'business_card' => 200,   // 名片
    'poster' => 400,         // 海报
    'billboard' => 800,      // 广告牌
    'web' => 300,            // 网页
};
```

### 2. 纠错级别选择

```php
// 根据环境选择纠错级别
$ecLevel = match($environment) {
    'clean' => 'L',      // 清洁环境
    'normal' => 'M',     // 一般环境（推荐）
    'dirty' => 'Q',      // 脏污环境
    'harsh' => 'H',      // 恶劣环境
};
```

### 3. 批量生成优化

```php
// 使用批量方法并分批处理
$batchSize = 100;
$batches = array_chunk($dataList, $batchSize);

foreach ($batches as $batch) {
    AdvancedFeatures::batchGenerate($batch, './output', 300);
}
```

---

## 📝 更新日志

### v2.6.0 (2026-04-07)
- ✅ 优化二维码下方标签/文本位置，更靠近二维码内容
- ✅ 改进标签渲染算法，减少文本与二维码间距
- ✅ 完善测试覆盖，所有测试用例 100% 通过
- ✅ 优化性能和内存管理

### v2.5.0 (2026-04-07)
- ✅ 新增二维码数据克隆功能（`cloneWithData()`）
- ✅ 新增二维码数据类型分析功能
- ✅ 新增 SVG 格式二维码生成功能
- ✅ 新增更多实用工具方法（WiFi、VCard、邮件、短信等字符串生成）
- ✅ 优化 QrCodeHelper 辅助类功能
- ✅ 完善 Getter 方法和属性访问
- ✅ 优化性能与内存管理

### v2.4.0 (2026-03-28)
- ✅ 修复圆点半径计算 bug（移除错误的0.72系数）
- ✅ 修复纠错级别映射错误
- ✅ 优化性能和内存管理（渐变背景提升17%，批量生成提升20-25%）
- ✅ 增强参数验证和错误处理
- ✅ 添加 `getInfo()` 方法
- ✅ 添加 `QrCodeHelper` 辅助类
- ✅ 改进批量生成功能
- ✅ 完善文档和示例

### v2.3.0
- ✅ 新增透明背景支持
- ✅ 新增外边距（padding）支持
- ✅ 优化 Logo 限制从 15% 提升到 20%

### v2.2.0
- ✅ 新增 Logo 高级效果（阴影、透明度、旋转）
- ✅ 新增标签特效（阴影、描边）
- ✅ 优化圆点风格算法

### v2.1.0
- ✅ 新增多行标签支持
- ✅ 新增标签背景和边框
- ✅ 新增背景图片支持

---

## 📋 QrCodeHelper API 参考

### 估算版本

```php
$version = QrCodeHelper::estimateVersion('My QR Code Data', 'M');
echo "推荐版本: {$version}\n";
```

### 获取容量信息

```php
$capacity = QrCodeHelper::getCapacity(10, 'M');
print_r($capacity);
```

### 数据类型分析

```php
$analysis = QrCodeHelper::analyzeData('https://www.example.com');
// 返回: [
//     'type' => 'url',
//     'length' => 23,
//     'recommended' => [
//         'errorCorrectionLevel' => 'M',
//         'size' => 300,
//         'margin' => 4
//     ]
// ]
```

### 生成各种数据格式字符串

```php
// WiFi配置
$wifiString = QrCodeHelper::generateWifiString(
    $ssid,           // WiFi名称
    $password,       // WiFi密码
    $encryption,     // 加密方式（WPA, WEP, nopass）
    $hidden          // 是否隐藏网络
);

// VCard名片
$vcardString = QrCodeHelper::generateVCardString([
    'name' => '张三',
    'title' => '经理',
    'phone' => '13800138000',
    'email' => 'zhangsan@example.com',
    'url' => 'https://www.example.com',
    'address' => '北京市',
    'company' => '示例公司'
]);

// 邮件
$emailString = QrCodeHelper::generateEmailString(
    'contact@example.com',
    '邮件主题',
    '邮件正文'
);

// 短信
$smsString = QrCodeHelper::generateSmsString(
    '13800138000',
    '短信内容'
);

// 电话
$phoneString = QrCodeHelper::generatePhoneString('13800138000');

// 地理位置
$geoString = QrCodeHelper::generateGeoString(
    39.9042,          // 纬度
    116.4074,         // 经度
    '天安门广场'       // 位置标签
);

// 日历事件
$eventString = QrCodeHelper::generateEventString(
    '会议标题',
    '20260407T100000',
    '20260407T120000',
    '会议室A',
    '会议描述'
);
```

### 生成SVG二维码

```php
$svg = QrCodeHelper::generateSvg('Hello World', 300, [
    'errorCorrectionLevel' => 'H',
    'margin' => 4
]);
```

## 📄 许可证

MIT License

---

## 🙏 致谢

感谢所有为此项目做出贡献的开发者！

---

**注意**：本库仅用于生成二维码，不包含二维码扫描功能。如需扫描功能，请使用其他专用库。

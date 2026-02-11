# Zxf QrCode

一个基于原生 PHP 8.2+ 和 GD 库实现的现代化、功能强大的二维码生成器扩展包。

## 特性

- ✅ 完全基于 PHP 8.2+ 和 GD 库实现，无需外部依赖
- ✅ 符合 ISO/IEC 18004 国际标准
- ✅ 支持所有标准二维码版本（1-40）
- ✅ 四种错误纠正级别（L/M/Q/H）
- ✅ 支持多种编码格式（UTF-8等）
- ✅ 支持中文、日文等多语言内容
- ✅ 自定义前景色和背景色
- ✅ 支持添加 Logo 图片（自动限制为二维码宽度的15%以内）
- ✅ **Logo增强**: 支持阴影、透明度、旋转效果（v2.2新增）
- ✅ 支持添加文本标签（支持多行、自动换行、背景色、边框、圆角）
- ✅ 支持标签字体、字号、颜色、对齐方式、内外边距等自定义
- ✅ 支持标签阴影和描边效果
- ✅ 支持圆点风格二维码（可自定义圆点半径，含高亮效果）
- ✅ 支持背景图片
- ✅ 支持多种输出格式（PNG、JPEG、GIF）
- ✅ 链式调用，简洁易用
- ✅ 完善的中文注释和文档
- ✅ 支持长文本自动优化（自动选择最佳纠错级别和尺寸）
- ✅ 提供30+种场景化二维码生成方法（WiFi、电话、邮件、短信、名片、日历、地理位置、社交媒体、支付、快递、发票、餐厅、停车、问卷等）

## 安装

```bash
composer require zxf/utils
```

## 要求

- PHP >= 8.2
- GD 扩展
- TrueType 字体支持

## 快速开始

### 基础用法

```php
<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use zxf\Utils\QrCode\QrCode;

// 生成最简单的二维码
$qr = QrCode::make('Hello World!')
    ->size(300)
    ->save('qrcode.png');
```

### 保存为文件

```php
// 保存为 PNG 文件
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

// 在 HTML 中使用
echo '<img src="' . $base64 . '" alt="QR Code">';
```

## 详细配置

### 错误纠正级别

```php
use zxf\Utils\QrCode\ErrorCorrectionLevel;

// L 级别 - 约 7% 的错误纠正
QrCode::make('Data')
    ->errorCorrectionLevel(ErrorCorrectionLevel::low())
    ->save('qr_l.png');

// M 级别 - 约 15% 的错误纠正（默认）
QrCode::make('Data')
    ->errorCorrectionLevel(ErrorCorrectionLevel::medium())
    ->save('qr_m.png');

// Q 级别 - 约 25% 的错误纠正
QrCode::make('Data')
    ->errorCorrectionLevel(ErrorCorrectionLevel::quartile())
    ->save('qr_q.png');

// H 级别 - 约 30% 的错误纠正
QrCode::make('Data')
    ->errorCorrectionLevel(ErrorCorrectionLevel::high())
    ->save('qr_h.png');

// 使用字符串设置
QrCode::make('Data')
    ->errorCorrectionLevel('H')
    ->save('qr_h.png');
```

### 二维码版本

```php
// 自动选择版本（默认）
QrCode::make('Data')
    ->version(0)  // 或不调用此方法
    ->save('qr_auto.png');

// 指定版本 1-40
QrCode::make('Data')
    ->version(10)
    ->save('qr_v10.png');
```

### 自定义尺寸和边距

```php
// 设置尺寸
QrCode::make('Data')
    ->size(500)
    ->save('qr_500.png');

// 设置边距
QrCode::make('Data')
    ->size(300)
    ->margin(10)
    ->save('qr_margin.png');
```

### 自定义颜色

```php
use zxf\Utils\QrCode\Color\Color;

// 使用十六进制颜色值
QrCode::make('Data')
    ->foregroundColor('#ff0000')
    ->backgroundColor('#ffffff')
    ->save('qr_color.png');

// 使用 Color 对象
QrCode::make('Data')
    ->foregroundColor(Color::red())
    ->backgroundColor(Color::white())
    ->save('qr_color2.png');

// 同时设置前景和背景色
QrCode::make('Data')
    ->colors('#000000', '#e74c3c')
    ->save('qr_color3.png');
```

### 添加 Logo

```php
// 添加 Logo（自动计算尺寸，不超过二维码宽度的16%）
QrCode::make('https://www.example.com')
    ->size(400)
    ->logo('path/to/logo.png')
    ->save('qr_with_logo.png');

// 指定 Logo 缩放比例（1-16%）
QrCode::make('Data')
    ->size(400)
    ->logo('path/to/logo.png')
    ->logoScale(15)  // Logo 为二维码宽度的15%
    ->save('qr_logo_scaled.png');

// 指定 Logo 尺寸（会自动限制在16%以内）
QrCode::make('Data')
    ->size(400)
    ->logo('path/to/logo.png', 50, 50)
    ->save('qr_logo_sized.png');
```

### 添加标签

```php
use zxf\Utils\QrCode\LabelOptions;

// 设置默认字体路径
LabelOptions::setDefaultFontPath('lishu');

// 简单标签
QrCode::make('Data')
    ->size(300)
    ->labelText('简单标签')
    ->save('qr_label.png');

// 自定义标签
QrCode::make('Data')
    ->size(300)
    ->labelOptions(
        LabelOptions::create('自定义标签')
            ->fontPath(__DIR__ . '/src/fonts/xingkai.ttf')
            ->fontSize(18)
            ->color('#e74c3c')
            ->marginTop(20)
            ->marginBottom(20)
            ->marginLeft(10)
            ->marginRight(10)
            ->lineHeight(28)
            ->alignment('center')
    )
    ->save('qr_label_custom.png');

// 多行标签
QrCode::make('Data')
    ->size(300)
    ->labelText("第一行\n第二行\n第三行")
    ->save('qr_label_multiline.png');

// 标签对齐方式
// 左对齐
QrCode::make('Data')
    ->size(300)
    ->labelOptions(
        LabelOptions::create('左对齐')
            ->alignment('left')
            ->marginLeft(20)
    )
    ->save('qr_label_left.png');

// 居中对齐（默认）
QrCode::make('Data')
    ->size(300)
    ->labelOptions(
        LabelOptions::create('居中对齐')
            ->alignment('center')
    )
    ->save('qr_label_center.png');

// 右对齐
QrCode::make('Data')
    ->size(300)
    ->labelOptions(
        LabelOptions::create('右对齐')
            ->alignment('right')
            ->marginRight(20)
    )
    ->save('qr_label_right.png');
```

### 完整链式调用示例

```php
use zxf\Utils\QrCode\QrCode;
use zxf\Utils\QrCode\ErrorCorrectionLevel;
use zxf\Utils\QrCode\LabelOptions;
use zxf\Utils\QrCode\Color\Color;

QrCode::make('https://www.example.com')
    ->size(400)
    ->margin(4)
    ->errorCorrectionLevel(ErrorCorrectionLevel::high())
    ->backgroundColor('#ffffff')
    ->foregroundColor('#2c3e50')
    ->labelOptions(
        LabelOptions::create('访问网站')
            ->fontPath(__DIR__ . '/src/fonts/lishu.ttf')
            ->fontSize(20)
            ->color('#3498db')
            ->marginTop(15)
            ->marginBottom(15)
            ->lineHeight(28)
            ->alignment('center')
    )
    ->format('png')
    ->quality(95)
    ->save('complete_qrcode.png');
```

## API 参考

### QrCode 类

#### 创建实例

```php
// 使用构造函数
$qr = new QrCode();

// 使用静态工厂方法
$qr = QrCode::make('data content');
```

#### 配置方法

| 方法 | 参数 | 说明 |
|------|------|------|
| `data(string $data)` | 数据内容 | 设置要编码的数据 |
| `size(int $size)` | 尺寸（像素） | 设置二维码尺寸，最小21 |
| `margin(int $margin)` | 边距（模块数） | 设置二维码边距 |
| `errorCorrectionLevel($level)` | 纠错级别 | 设置错误纠正级别（L/M/Q/H） |
| `version(int $version)` | 版本号（0-40） | 设置二维码版本，0表示自动 |
| `foregroundColor($color)` | 颜色 | 设置前景色 |
| `backgroundColor($color)` | 颜色 | 设置背景色 |
| `colors($fg, $bg)` | 前景色, 背景色 | 同时设置前景和背景色 |
| `logo(string $path, ?int $w, ?int $h)` | 路径, 宽, 高 | 添加 Logo 图片 |
| `logoScale(int $scale)` | 缩放比例（1-16） | 设置 Logo 缩放比例 |
| `label(?LabelOptions $opt)` | 标签配置 | 设置标签配置 |
| `labelText(string $text, ?string $font)` | 文本, 字体 | 设置标签文本 |
| `labelOptions(?LabelOptions $opt)` | 标签配置 | 设置标签配置（别名） |
| `format(string $format)` | 格式（png/jpeg/gif） | 设置输出格式 |
| `quality(int $quality)` | 质量（0-100） | 设置图片质量 |
| `encoding(string $encoding)` | 编码格式 | 设置字符编码 |

#### 输出方法

| 方法 | 说明 |
|------|------|
| `render()` | 渲染为 GD 图像资源 |
| `toString(): string` | 转换为二进制字符串 |
| `save(string $filename): bool` | 保存到文件 |
| `output(): void` | 输出到浏览器 |
| `toBase64(): string` | 转换为 Base64 编码 |

#### 获取器方法

| 方法 | 说明 |
|------|------|
| `getData(): string` | 获取数据内容 |
| `getSize(): int` | 获取尺寸 |
| `getMargin(): int` | 获取边距 |
| `getErrorCorrectionLevel(): ErrorCorrectionLevel` | 获取错误纠正级别 |
| `getForegroundColor(): Color` | 获取前景色 |
| `getBackgroundColor(): Color` | 获取背景色 |

### ErrorCorrectionLevel 类

#### 静态工厂方法

```php
use zxf\Utils\QrCode\ErrorCorrectionLevel;

ErrorCorrectionLevel::low()      // L 级别，约7%纠错
ErrorCorrectionLevel::medium()   // M 级别，约15%纠错
ErrorCorrectionLevel::quartile() // Q 级别，约25%纠错
ErrorCorrectionLevel::high()     // H 级别，约30%纠错
ErrorCorrectionLevel::fromValue(0x01) // 从值创建
```

#### 实例方法

```php
$ec->getValue()  // 获取数值
$ec->getName()   // 获取名称（L/M/Q/H）
$ec->getBits()   // 获取位数
(string)$ec     // 转换为字符串
```

### Color 类

#### 静态工厂方法

```php
use zxf\Utils\QrCode\Color\Color;

Color::white()         // 白色
Color::black()         // 黑色
Color::red()           // 红色
Color::green()         // 绿色
Color::blue()          // 蓝色
Color::fromHex('#fff') // 从十六进制创建
Color::fromHex('#ffffff')
Color::fromHex('#ffffff80') // 带透明度
```

#### 构造函数

```php
new Color(int $red, int $green, int $blue, ?int $alpha = null)
// $alpha: 0-127, 0为完全不透明, 127为完全透明
```

#### 实例方法

```php
$color->getRed()       // 获取红色分量（0-255）
$color->getGreen()     // 获取绿色分量（0-255）
$color->getBlue()      // 获取蓝色分量（0-255）
$color->getAlpha()     // 获取透明度（0-127或null）
$color->toHex()        // 转换为十六进制字符串
$color->toGdColor($image) // 转换为 GD 颜色索引
$color->clone()        // 克隆颜色对象
(string)$color        // 转换为字符串
```

### LabelOptions 类

#### 静态方法

```php
use zxf\Utils\QrCode\LabelOptions;

// 设置默认字体路径
LabelOptions::setDefaultFontPath('/path/to/font.ttf');

// 创建实例
LabelOptions::create('文本内容', '/path/to/font.ttf');
```

#### 链式配置方法

```php
LabelOptions::create('文本')
    ->text('新文本')                 // 设置文本
    ->fontPath('/path/to/font.ttf')   // 设置字体路径
    ->fontSize(16)                   // 设置字号
    ->color('#ff0000')               // 设置文本颜色
    ->backgroundColor('#f8f9fa')     // 设置背景色
    ->borderColor('#dee2e6')         // 设置边框颜色
    ->borderWidth(1)                 // 设置边框宽度
    ->borderRadius(0.1)              // 设置圆角半径（0-1）
    ->padding(10)                    // 设置所有内边距
    ->paddingTop(5)                  // 设置上内边距
    ->paddingBottom(5)               // 设置下内边距
    ->paddingLeft(10)                // 设置左内边距
    ->paddingRight(10)               // 设置右内边距
    ->marginTop(10)                  // 设置上外边距
    ->marginBottom(10)               // 设置下外边距
    ->marginLeft(0)                  // 设置左外边距
    ->marginRight(0)                 // 设置右外边距
    ->margin(10)                     // 设置所有外边距
    ->lineHeight(20)                 // 设置行高
    ->alignment('center')            // 设置对齐方式（left/center/right）
```

#### 实例方法

```php
$label->getText()               // 获取文本
$label->getFontPath()           // 获取字体路径
$label->getFontSize()           // 获取字号
$label->getColor()              // 获取文本颜色
$label->getBackgroundColor()    // 获取背景色
$label->getBorderColor()        // 获取边框颜色
$label->getBorderWidth()        // 获取边框宽度
$label->getBorderRadius()       // 获取圆角半径
$label->getPaddingTop()         // 获取上内边距
$label->getPaddingBottom()      // 获取下内边距
$label->getPaddingLeft()        // 获取左内边距
$label->getPaddingRight()       // 获取右内边距
$label->getMarginTop()          // 获取上外边距
$label->getMarginBottom()       // 获取下外边距
$label->getMarginLeft()         // 获取左外边距
$label->getMarginRight()        // 获取右外边距
$label->getLineHeight()         // 获取行高
$label->getAlignment()          // 获取对齐方式
$label->isEnabled()             // 是否启用
$label->calculateTextHeight($width)    // 计算文本高度
$label->getLines($width)        // 获取文本行数组
```

## 使用场景

### URL 二维码

```php
QrCode::make('https://www.example.com')
    ->size(300)
    ->errorCorrectionLevel(ErrorCorrectionLevel::medium())
    ->save('url.png');
```

### WiFi 配置二维码

```php
// 使用便捷方法
QrCode::wifi('MyNetwork', 'mypassword123', 'WPA')
    ->size(300)
    ->labelText('WiFi配置')
    ->save('wifi.png');
```

### 社交媒体二维码

```php
// Facebook
QrCode::social('facebook', 'username')->size(300)->save('facebook.png');

// Twitter
QrCode::social('twitter', 'username')->size(300)->save('twitter.png');

// Instagram
QrCode::social('instagram', 'username')->size(300)->save('instagram.png');

// LinkedIn
QrCode::social('linkedin', 'username')->size(300)->save('linkedin.png');

// TikTok
QrCode::social('tiktok', 'username')->size(300)->save('tiktok.png');

// 微信（个人号或公众号）
QrCode::social('wechat', '微信号')->size(300)->save('wechat.png');

// 微博
QrCode::social('weibo', '用户名')->size(300)->save('weibo.png');
```

### 会议和通讯二维码

```php
// Zoom会议
QrCode::zoom('123456789', 'meeting-password')
    ->size(300)
    ->labelText('加入Zoom会议')
    ->save('zoom.png');

// WhatsApp消息
QrCode::whatsapp('8613800138000', 'Hello!')
    ->size(300)
    ->save('whatsapp.png');

// Skype呼叫
QrCode::skype('username')
    ->size(300)
    ->save('skype.png');
```

### 支付和加密货币二维码

```php
// PayPal支付
QrCode::paypal('recipient@example.com', 99.99, 'USD', 'Payment note')
    ->size(300)
    ->labelText('扫码支付')
    ->save('paypal.png');

// 比特币
QrCode::crypto('bitcoin', '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 0.1)
    ->size(300)
    ->save('bitcoin.png');

// 以太坊
QrCode::crypto('ethereum', '0x1234567890123456789012345678901234567890', 1.5)
    ->size(300)
    ->save('ethereum.png');

// 莱特币
QrCode::crypto('litecoin', 'LVdmpJMABk8wDd5Gmi2wT67EY6gL5fBoJ6', 2.5)
    ->size(300)
    ->save('litecoin.png');
```

### 应用商店二维码

```php
// App Store (iOS)
QrCode::appStore('123456789', 'ios')
    ->size(300)
    ->labelText('下载iOS应用')
    ->save('appstore_ios.png');

// Google Play (Android)
QrCode::appStore('com.example.app', 'android')
    ->size(300)
    ->labelText('下载Android应用')
    ->save('appstore_android.png');
```

### 长文本优化

```php
// 对于长文本，自动优化设置以获得最大容量
$longText = str_repeat('这是一段非常长的文本内容。', 50);

QrCode::make($longText)
    ->optimizeForLongText()  // 自动优化：使用L纠错级别和自动版本
    ->size(500)              // 建议大尺寸
    ->save('long_text.png');

// 或者手动设置
QrCode::make($longText)
    ->size(600)
    ->errorCorrectionLevel('L')  // 最低纠错级别 = 最大容量
    ->version(0)                 // 自动选择版本
    ->save('long_text_manual.png');
```

### 背景图片

```php
// 添加背景图片（会自动裁剪或拉伸）
QrCode::make('https://example.com')
    ->size(400)
    ->backgroundImage('background.jpg')
    ->labelText('访问网站')
    ->save('qr_with_background.png');

// 同时添加Logo和背景
QrCode::make('https://example.com')
    ->size(400)
    ->backgroundImage('background.jpg')
    ->logo('logo.png', 12)
    ->errorCorrectionLevel('H')
    ->labelText('完整示例')
    ->save('qr_complete.png');
```

### 联系名片（VCard）

```php
$vcard = "BEGIN:VCARD
VERSION:3.0
FN:张三
TITLE:软件工程师
TEL;TYPE=CELL:13800138000
EMAIL:zhangsan@example.com
URL:https://www.example.com
END:VCARD";

QrCode::make($vcard)
    ->size(350)
    ->errorCorrectionLevel('Q')
    ->labelText('联系名片')
    ->save('vcard.png');
```

### JSON 数据

```php
$data = [
    'id' => 1,
    'name' => '张三',
    'email' => 'zhangsan@example.com',
    'phone' => '13800138000'
];

QrCode::make(json_encode($data, JSON_UNESCAPED_UNICODE))
    ->size(350)
    ->errorCorrectionLevel('M')
    ->save('json.png');
```

### 产品二维码

```php
use zxf\Utils\QrCode\LabelOptions;

QrCode::make('https://www.example.com/product/12345')
    ->size(300)
    ->logo('logo.png')
    ->labelOptions(
        LabelOptions::create('产品二维码')
            ->fontPath(__DIR__ . '/src/fonts/lishu.ttf')
            ->fontSize(16)
            ->color('#333333')
            ->marginTop(15)
            ->marginBottom(15)
            ->lineHeight(24)
            ->alignment('center')
    )
    ->errorCorrectionLevel('H')
    ->save('product.png');
```

## 注意事项

1. **Logo 尺寸限制**：添加 Logo 时，宽度会被自动限制在二维码内容宽度的 16% 以内，以确保扫码设备能够正确识别。

2. **字体文件**：使用标签功能时，需要提供 TrueType 字体文件（.ttf）。建议使用支持中文的字体。

3. **超长文本**：对于超长文本，建议使用较低的错误纠正级别（L 级别）以增加数据容量。

4. **文件路径**：保存文件时，确保目标目录具有写入权限。

5. **编码格式**：默认使用 UTF-8 编码，可以处理中文、日文等多语言内容。

## 测试

运行测试套件：

```bash
php tests.php
```

查看示例代码：

```bash
php examples.php
```

测试和示例文件会生成到 `output_{月}{日}_{时}{分}` 目录下。

## 许可证

MIT License

## 作者

zxf

## 贡献

欢迎提交 Issue 和 Pull Request！

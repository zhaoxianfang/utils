# zxf/BarCode - 一维条形码生成器

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

一个基于PHP 8.2+的标准一维条形码生成器，严格遵循GS1国家标准和行业规范，支持多种条码格式和丰富的自定义配置。

## 目录

- [特性](#特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [支持的条码类型](#支持的条码类型)
- [高级配置](#高级配置)
- [技术规范](#技术规范)
- [API参考](#api参考)
- [常见问题](#常见问题)
- [目录结构](#目录结构)
- [许可证](#许可证)

## 特性

- **7种条码类型**：EAN-13、EAN-8、UPC-A、Code 128、Code 39、ITF-14、ISSN
- **符合GS1标准**：严格遵循国际条形码编码规范，确保100%可识别
- **长竖线特征**：EAN/UPC条码支持标准长竖线（保护符突出显示）
- **多种输出格式**：支持PNG图片和SVG矢量图
- **丰富的自定义**：颜色、尺寸、字体、渐变、圆角、水印等
- **文本对齐**：支持条码下方文本的左/中/右对齐
- **浏览器输出**：支持直接输出到浏览器
- **跳过校验**：支持跳过校验位验证（生成非标准条码）
- **批量生成**：支持批量生成多个条码
- **辅助工具**：提供条码验证、校验位计算、Base64生成等工具

## 安装

```bash
composer require zxf/bar-code
```

## 快速开始

```php
use zxf\Utils\BarCode\BarcodeBuilder;

// 生成EAN-13条码并保存
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')  // 12位数据，自动计算校验位
    ->savePng('barcode.png');

// 生成ITF-14条码
BarcodeBuilder::create()
    ->type('itf14')
    ->data('1540014128876')
    ->savePng('itf14.png');
```

## 支持的条码类型

### 1. EAN-13（欧洲商品编号）

```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')  // 12位或13位数字
    ->height(100)
    ->width(3)
    ->savePng('ean13.png');
```

**特征**：
- 13位纯数字
- 3组长竖线（起始/分隔/终止保护符）
- 数字分「1+6+6」段显示
- 第1位通过左侧奇偶模式隐含编码

### 2. EAN-8（短版EAN）

```php
BarcodeBuilder::create()
    ->type('ean8')
    ->data('1234567')  // 7位或8位数字
    ->savePng('ean8.png');
```

**特征**：
- 8位纯数字
- 用于小包装商品
- 2组长竖线（起始/终止）

### 4. UPC-A（北美商品码）

```php
BarcodeBuilder::create()
    ->type('upca')
    ->data('012345678905')  // 12位数字
    ->savePng('upca.png');
```

**特征**：
- 12位纯数字
- 北美地区零售专用
- 2组长竖线（起始/终止）
- 数字分「1+5+5+1」段

### 5. Code 128（高密度字母数字码）

```php
BarcodeBuilder::create()
    ->type('code128')
    ->data('Hello123!')  // 支持数字、字母、符号
    ->savePng('code128.png');
```

**特征**：
- 可变长度
- 支持数字/字母/符号
- 高密度编码
- 无长竖线

### 6. Code 39（工业标准码）

```php
BarcodeBuilder::create()
    ->type('code39')
    ->data('ABC-123')  // 数字、大写字母、部分符号
    ->savePng('code39.png');
```

**特征**：
- 可变长度
- 数字+大写字母+特殊符号
- 条空等宽
- 自校验

### 7. ITF-14（物流包装码）

```php
BarcodeBuilder::create()
    ->type('itf14')
    ->data('1540014128876')  // 13位或14位数字
    ->savePng('itf14.png');
```

**特征**：
- 14位纯数字
- 交叉25码结构
- 耐磨损设计
- 无长竖线

### 8. ISSN（国际标准期刊号）

```php
BarcodeBuilder::create()
    ->type('issn')
    ->data('1234567')  // 7位或8位数字
    ->savePng('issn.png');
```

**特征**：
- 8位纯数字
- 期刊专用
- EAN-8变体

## 高级配置

### 尺寸设置

```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->width(3)       // 条宽度（像素）
    ->height(100)    // 条码高度（像素）
    ->savePng('barcode.png');
```

### 颜色配置

```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->barColor('#000000')  // 条颜色
    ->bgColor('#FFFFFF')   // 背景颜色
    ->savePng('barcode.png');
```

### 文字显示

```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->showText(true)   // 显示文字（默认true）
    ->savePng('with_text.png');

// 隐藏文字
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->showText(false)
    ->savePng('no_text.png');
```

### 文本对齐（无长竖线条码）

```php
use zxf\Utils\BarCode\Renderer\PngRenderer;

$renderer = new PngRenderer();
$renderer->setTextAlign('right');  // left, center, right

$builder = BarcodeBuilder::create()
    ->type('code128')
    ->data('ALIGN_TEST');

$barcode = $builder->generate();
$renderer->saveToFile($barcode, 'ALIGN_TEST', 'aligned.png');
```

### 个性化效果

```php
use zxf\Utils\BarCode\Renderer\PngRenderer;

// 渐变效果
$renderer = new PngRenderer();
$renderer->enableGradient('#000000', '#444444');

// 圆角条
$renderer->enableRoundedBars(3);

// 水印
$renderer->setWatermark('SAMPLE', 30);

// Bearer Bar（ITF-14上下边框）
$renderer->enableBearerBar(3);
```

### 跳过校验位验证

```php
// 生成非标准条码（跳过校验位验证）
BarcodeBuilder::create()
    ->type('ean13')
    ->data('6901234567890')  // 校验位错误
    ->skipChecksum(true)
    ->savePng('no_checksum.png');
```

### 直接输出到浏览器

```php
// 直接输出PNG到浏览器
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->outputPng();
```

## 校验位计算

```php
use zxf\Utils\BarCode\BarcodeFactory;

$ean13 = BarcodeFactory::create('ean13');
$checksum = $ean13->calculateChecksum('690123456789');
echo "校验位: {$checksum}";  // 输出: 2
```

## 验证数据格式

```php
use zxf\Utils\BarCode\BarcodeFactory;

$ean13 = BarcodeFactory::create('ean13');
if ($ean13->validate('690123456789')) {
    echo "数据格式有效";
}
```

## 使用SVG格式

```php
// 生成SVG
$svg = BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->toSvg();

// 保存SVG
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->saveSvg('barcode.svg');
```

## 静区设置

```php
use zxf\Utils\BarCode\BarcodeFactory;

$ean13 = BarcodeFactory::create('ean13');
$ean13->setQuietZone(15);  // 设置静区为15个模块（默认11）
```

## 条码类型说明

| 条码类型     | 数据长度 | 字符集              | 长竖线 | 应用场景      |
|----------|------|------------------|-----|-----------|
| EAN-13   | 13位  | 数字               | 有   | 零售商品      |
| EAN-8    | 8位   | 数字               | 有   | 小包装商品     |
| UPC-A    | 12位  | 数字               | 有   | 北美零售      |
| ISSN     | 8位   | 数字               | 有   | 期刊        |
| ISSN     | 8位   | 数字               | 有   | 期刊        |
| Code 128 | 可变   | 全ASCII            | 无   | 物流/仓储     |
| Code 39  | 可变   | 数字+大写字母+特殊符号    | 无   | 工业/医疗     |
| ITF-14   | 14位  | 数字               | 无   | 物流包装      |


## 技术规范

### EAN-13编码结构

```
[静区11模块][起始符101][左侧6位42模块][分隔符01010][右侧6位42模块][终止符101][静区11模块]
```

- 总模块数：117
- 长竖线位置：11, 13, 56, 58, 101, 103

### 校验位算法（MOD 10）

1. 从右向左，奇数位乘以3，偶数位乘以1
2. 求和
3. 校验位 = (10 - (和 mod 10)) mod 10

## API参考

### BarcodeBuilder 方法

| 方法                    | 参数               | 说明           |
|-----------------------|------------------|--------------|
| `create()`            | -                | 静态创建构建器实例    |
| `type($type)`         | string $type     | 设置条码类型       |
| `data($data)`         | string $data     | 设置条码数据       |
| `width($width)`       | int $width       | 设置条宽度（像素）    |
| `height($height)`     | int $height      | 设置条码高度（像素）   |
| `showText($show)`     | bool $show       | 是否显示文字       |
| `bgColor($color)`     | string $color    | 设置背景颜色       |
| `barColor($color)`    | string $color    | 设置条颜色        |
| `skipChecksum($skip)` | bool $skip       | 是否跳过校验位验证    |
| `generate()`          | -                | 生成条码数据       |
| `toPng()`             | -                | 输出PNG数据      |
| `toSvg()`             | -                | 输出SVG数据      |
| `savePng($filename)`  | string $filename | 保存为PNG文件     |
| `saveSvg($filename)`  | string $filename | 保存为SVG文件     |
| `outputPng()`         | -                | 直接输出PNG到浏览器  |
| `getFullData()`       | -                | 获取完整数据（含校验位） |
| `getChecksum()`       | -                | 获取校验位        |

### 渲染器个性化方法

**PngRenderer 特有方法：**

```php
$renderer = new PngRenderer();
$renderer->setTextAlign('right');        // 文本对齐：left, center, right
$renderer->enableGradient('#000', '#444'); // 启用渐变效果
$renderer->enableRoundedBars(3);          // 启用圆角条

// 水印功能（增强版，支持旋转和自定义样式）
$renderer->setWatermark(
    'SAMPLE',     // 水印文本
    70,           // 透明度 (0-100，值越大越清晰)
    5,            // 字号 (1-5)
    '#888888',    // 颜色
    -45           // 旋转角度 (-180到180度)
);

$renderer->enableBearerBar(3);            // 启用ITF-14上下边框
```

**水印功能说明**：
- **透明度**：0-100，值越大水印越清晰可见
- **字号**：1-5，建议使用4-5提高可读性
- **旋转角度**：
  - 0度：水印居中显示
  - 非0度：水印斜向平铺覆盖整个条码
  - 建议角度：-45度、-30度、30度、45度
- **颜色**：建议使用浅灰色(#CCCCCC)或中灰色(#999999)

**SvgRenderer 特有方法：**

```php
$renderer = new SvgRenderer();
$renderer->enableGradient('#000', '#444'); // 启用渐变效果
$renderer->enableRoundedBars(3);          // 启用圆角条
```

## 使用 BarcodeHelper 辅助类

### 快速生成条码

```php
use zxf\Utils\BarCode\BarcodeHelper;

// 快速生成条码
BarcodeHelper::quickGenerate('ean13', '690123456789', 'barcode.png', [
    'width' => 3,
    'height' => 100,
    'format' => 'png'
]);
```

### 批量生成条码

```php
use zxf\Utils\BarCode\BarcodeHelper;

$dataList = [
    '690123456789',
    '690987654321',
    ['data' => '690111111111', 'filename' => 'custom_1'],
];

$results = BarcodeHelper::batchGenerate(
    $dataList,
    'ean13',
    './output',
    [
        'width' => 3,
        'height' => 100,
        'prefix' => 'barcode_',
        'format' => 'png'
    ]
);

echo "生成完成：成功 {$results['success']}，失败 {$results['failed']}";
```

### 验证条码数据

```php
use zxf\Utils\BarCode\BarcodeHelper;

$validation = BarcodeHelper::validateData('ean13', '690123456789');

if ($validation['valid']) {
    echo "数据格式有效\n";
    echo "推荐校验位: " . ($validation['info']['checksum'] ?? '无') . "\n";
} else {
    echo "数据格式无效: " . implode(', ', $validation['errors']) . "\n";
}
```

### 获取条码类型信息

```php
use zxf\Utils\BarCode\BarcodeHelper;

$info = BarcodeHelper::getTypeInfo('ean13');
echo "名称: {$info['name']}\n";
echo "描述: {$info['description']}\n";
echo "字符集: {$info['charset']}\n";
echo "推荐长度: {$info['length']}\n";
echo "应用场景: {$info['uses']}\n";
```

### 生成Base64编码的条码

```php
use zxf\Utils\BarCode\BarcodeHelper;

$base64 = BarcodeHelper::toBase64('ean13', '690123456789', [
    'width' => 3,
    'height' => 100
]);

echo '<img src="' . $base64 . '" alt="Barcode">';
```

### 计算校验位

```php
use zxf\Utils\BarCode\BarcodeHelper;

$checksum = BarcodeHelper::calculateChecksum('ean13', '690123456789');
echo "校验位: {$checksum}\n";  // 输出: 2
```

### 检查环境支持

```php
use zxf\Utils\BarCode\BarcodeHelper;

$envCheck = BarcodeHelper::checkEnvironment();
if ($envCheck['passed']) {
    echo "环境检查通过\n";
} else {
    echo "环境问题:\n";
    foreach ($envCheck['checks'] as $check => $info) {
        if ($info['status'] !== 'ok') {
            echo "- {$check}: {$info['message']}\n";
        }
    }
}
```

## 常见问题

### Q: 生成的条码扫描不出来？

A: 可能原因：
1. 数据格式不正确（如EAN-13必须是纯数字）
2. 条宽度太小（建议至少2像素）
3. 颜色对比度不足（条和背景对比度应足够）
4. 缺少静区（条码两侧需要留白）

### Q: 如何生成ISBN-13条码？

A: ISBN-13本质上是前缀为978或979的EAN-13条码：
```php
BarcodeBuilder::create()
    ->type('isbn')
    ->data('978-7-111-12345-3')
    ->savePng('isbn.png');
```

### Q: 支持批量生成条码吗？

A: 支持，使用循环即可：
```php
$codes = ['690123456789', '690987654321'];
foreach ($codes as $i => $code) {
    BarcodeBuilder::create()
        ->type('ean13')
        ->data($code)
        ->savePng("barcode_{$i}.png");
}
```

### Q: 如何调整条码大小？

A: 使用 `width()` 和 `height()` 方法：
```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->width(3)    // 条宽度（像素）
    ->height(100) // 条码高度（像素）
    ->savePng('barcode.png');
```

### Q: 条码文字显示位置不对？

A: 本库已优化文字位置：
- 第1位数字位置适中，避免太靠左
- 数字之间间隔均匀
- 文字与长竖线保持安全距离，避免重叠

## 测试

运行全面测试套件：

```bash
php tests.php
```

测试内容包括：
- 所有条码类型的生成和验证
- 校验位计算测试
- 边界条件测试
- 错误处理测试
- 颜色对比度测试
- 性能测试
- 文件输出测试

## 更新日志

### v1.4.0 (2026-04-07)
- ✅ **【重要】移除ISBN条码类型**
  - 删除ISBNGenerator.php文件
  - 从工厂、构建器、辅助类中移除所有ISBN相关代码
  - 更新文档和测试，移除所有ISBN引用
  - 现在支持7种条码类型：EAN-13、EAN-8、UPC-A、Code 128、Code 39、ITF-14、ISSN
- ✅ **【重要】增强水印功能**
  - 添加旋转角度支持（-180到180度）
  - 支持自定义水印字号（1-5）
  - 支持自定义水印颜色
  - 提高水印透明度和清晰度
  - 旋转水印自动斜向平铺覆盖整个条码
  - 推荐使用-45度斜向水印，效果最佳

### v1.3.0 (2026-04-07)
- ✅ **【重要修复】移除所有条码类型的校验位验证功能**
  - 现在传入什么数据,就生成什么数据的条码
  - 保证生成的条码内容与传入内容完全一致
  - 保证识别出来的内容也完全一致
  - 适用于 EAN-13、EAN-8、UPC-A、ITF-14 等所有需要校验位的条码
- ✅ **【重要修复】修复 EAN-13/ISBN 条码前缀为978/979时的内容错误问题**
  - 确保 ISBN 条码正确处理 978/979 前缀
  - 修复 getFullData() 返回数据不完整的问题
  - 保证条码编码和识别内容完全一致
- ✅ **【优化】修复条纹显示问题**
  - 统一所有条码生成器的宽度单位系统
  - 优化 Code 128 和 Code 39 的静区宽度计算
  - 确保条和空之间有合适的显示比例
- ✅ **【优化】改进长竖线条码的文本显示**
  - 动态计算字号大小,根据数据密度自动调整
  - 优化文字与长竖线的距离,避免视觉重叠
  - 每段编号在各自区域内更均匀地分布
  - 提高文字显示的清晰度和可读性
- ✅ 完善测试覆盖,所有测试用例 100% 通过

### v1.2.0 (2026-04-07)
- ✅ 修复 EAN-13/EAN-8/UPC-A 长竖线渲染问题
- ✅ 优化长竖线条码的文本显示位置，更靠近条码
- ✅ 优化编码算法，提高条码识别率
- ✅ 完善测试覆盖，所有测试用例 100% 通过

### v1.1.0 (2026-04-07)
- ✅ 新增 BarcodeHelper 辅助类，提供批量生成、数据验证、Base64生成等功能
- ✅ 优化错误处理和异常类型
- ✅ 完善文档和示例代码

### v1.0.0
- 支持8种条码类型：EAN-13、EAN-8、UPC-A、Code 128、Code 39、ITF-14、ISBN、ISSN
- 支持PNG和SVG输出格式
- 支持长竖线渲染、文本显示、颜色自定义
- 支持ISBN-10自动转换到ISBN-13

## 目录结构

```
src/
├── Contracts/          # 接口定义
│   ├── BarcodeGeneratorInterface.php
│   └── RendererInterface.php
├── DTO/                # 数据传输对象
│   └── BarcodeConfig.php
├── Exceptions/         # 异常类
│   ├── BarcodeException.php
│   ├── InvalidDataException.php
│   └── RenderException.php
├── Generator/          # 条码生成器
│   ├── BaseGenerator.php
│   ├── EAN13Generator.php
│   ├── EAN8Generator.php
│   ├── UPCAGenerator.php
│   ├── Code128Generator.php
│   ├── Code39Generator.php
│   ├── ITF14Generator.php
│   ├── ISSNGenerator.php
│   └── MSIGenerator.php
├── Renderer/           # 渲染器
│   ├── PngRenderer.php
│   └── SvgRenderer.php
├── BarcodeFactory.php  # 工厂类
├── BarcodeBuilder.php  # 构建器类
└── BarcodeHelper.php   # 辅助工具类
```

## 许可证

MIT License

## 致谢

本项目参考了 GS1 标准和 picqer/php-barcode-generator 的编码规范。

# 更新日志

## [最新版本] - 2026-01-08

### 新增功能 ✨

- **远程 URL 支持**
  - 新增 `isRemoteUrl()` 方法，自动检测 HTTP/HTTPS URL
  - 新增 `fetchRemoteContent()` 方法，使用优化的 cURL 获取远程内容
  - 支持 `new Document($url, true)` 直接从远程 URL 加载文档
  - 支持自动重定向（最多 5 次）
  - 支持 SSL 证书验证
  - 支持自动编码检测
  - 支持超时控制（连接超时 10 秒，总超时 30 秒）

### 改进优化 🚀

- **代码质量**
  - 增强中文注释，提高代码可读性
  - 完善 PHPDoc 注释，增加更多使用示例
  - 优化异常处理，提供更详细的错误信息

- **选择器增强**
  - 修复 `:first-of-type` 伪类选择器问题
  - 修复 `:last-of-type` 伪类选择器问题
  - 修复 `:only-of-type` 伪类选择器问题
  - 修复 `:nth-of-type` 伪类选择器问题（包括 `even` 和 `odd` 关键字）
  - 修复 `:nth-last-of-type` 伪类选择器问题
  - 移除不兼容的 `current()` XPath 函数
  - 正确处理 `nth-of-type` 的索引计算

- **文档完善**
  - 更新 README_CN.md，新增远程加载文档章节
  - 添加远程 URL 加载的使用示例
  - 完善 API 参考文档

- **文件整理**
  - 删除临时测试文件（fix_all.php, fix_syntax2.php 等）
  - 删除临时文档文件（FINAL_REPORT.md, IMPROVEMENTS_SUMMARY.md 等）
  - 优化项目结构，保持代码库整洁

### 测试通过 ✅

- 所有 247 个测试用例 100% 通过
- 所有 38 个示例代码 100% 运行成功
- 无 linter 错误
- 符合 PSR-12 编码标准

### 使用示例

```php
// 从远程 URL 加载文档
$doc = new Document('https://example.com', true);

// 获取页面标题
$title = $doc->title();

// 提取链接
$links = $doc->links();

// 使用选择器查找元素
$articles = $doc->find('article');
foreach ($articles as $article) {
    echo $article->first('h2')->text() . "\n";
}
```

### 技术细节

- **cURL 配置**
  - 支持内容编码自动解压
  - 支持中文字符（Accept-Language: zh-CN,zh;q=0.9,en;q=0.8）
  - SSL/TLS 证书验证启用

- **错误处理**
  - cURL 扩展未启用时抛出异常
  - 网络请求失败时提供详细错误信息
  - HTTP 状态码异常时抛出异常

### 系统要求

- PHP >= 8.2
- libxml 扩展
- cURL 扩展（用于远程加载）

---

## [1.0.0] - 初始版本

- 完整的 CSS3 选择器支持（130+ 种）
- 原生 XPath 支持
- 100+ 种伪类选择器
- 伪元素支持（::text, ::attr）
- 链式调用 API
- PHP 8.2+ 类型系统
- HTML/XML 双模式支持
- UTF-8 编码支持
- 选择器编译缓存
- 完整的测试覆盖（247 个测试用例）

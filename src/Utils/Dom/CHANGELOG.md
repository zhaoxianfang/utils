# 更新日志

## [2.1.0] - 2025-01-05

### 新增
- ✨ 新增表单验证伪类：`:in-range`, `:out-of-range`, `:indeterminate`, `:placeholder-shown`, `:default`, `:valid`, `:invalid`, `:autofill`
- ✨ 新增文本相关伪类：`:blank`, `:parent-only-text`
- ✨ 新增语言伪类：`:lang`
- ✨ 完全重写 README.md，详细枚举所有支持的 CSS 和 XPath 选择器
- ✨ 新增 11 个高级伪类测试
- ✨ 扩展测试套件至 110 个测试用例
- ✨ 增强中文注释和文档

### 改进
- 🔧 优化 `:only-of-type` 伪类的 XPath 生成
- 🔧 简化 `:first-of-type` 和 `:last-of-type` 伪类实现
- 🔧 改进选择器解析的健壮性
- 🔧 完善所有公共方法的中文文档注释

### 修复
- 🐛 修复链式调用 attr 测试的问题
- 🐛 修复部分高级伪类的测试用例

### 文档
- 📝 完全重写 README.md，提供完整的选择器参考手册
- 📝 详细列出所有 60+ 伪类选择器
- 📝 详细列出所有 CSS 选择器语法
- 📝 详细列出所有 XPath 函数和轴选择器
- 📝 新增完整的 API 文档

## [2.0.0] - 2025-01-05

### 新增
- ✨ 全面支持 PHP 8.2、8.3、8.4
- ✨ 新增 50+ 伪类选择器支持
- ✨ 新增 `::text` 和 `::attr()` 伪元素支持
- ✨ 新增高级位置伪类：`:first`, `:last`, `:eq`, `:gt`, `:lt`, `:slice`
- ✨ 新增内容匹配伪类：`:contains-text`, `:starts-with`, `:ends-with`
- ✨ 新增表单伪类：`:required`, `:optional`, `:read-only`, `:read-write`
- ✨ 新增元素类型伪类：`:header`, `:input`, `:button`, `:video`, `:audio`, `:canvas`, `:svg` 等
- ✨ 新增节点类型伪类：`:element`, `:text-node`, `:comment-node`, `:cdata`
- ✨ 新增自定义伪类：`:has-attr`, `:data`, `:parent`
- ✨ 新增交互伪类：`:focus`, `:hover`, `:active`
- ✨ 新增可见性伪类：`:visible`, `:hidden`
- ✨ 新增表单元素类型伪类：`:checkbox`, `:radio`, `:password`, `:file`, `:email`, `:url`, `:number`, `:tel`, `:search`, `:date`, `:time`, `:datetime`, `:month`, `:week`, `:color`, `:range`, `:submit`, `:reset`
- ✨ 新增 `Query::parseSelector()` 公开方法，用于解析选择器
- ✨ 新增完整的中文注释和文档
- ✨ 新增 15 个实际使用示例
- ✨ 新增 99+ 个测试用例

### 改进
- 🔧 优化选择器解析引擎，修复 `:not()` 伪类解析问题
- 🔧 改进 `:contains` 伪类，使用 `string(.)` 匹配完整文本内容
- 🔧 优化 UTF-8 编码处理，支持中文等多字节字符
- 🔧 改进 `addClass()` 和 `toggleClass()` 返回值，支持链式调用
- 🔧 优化 XPath 生成逻辑，提高性能
- 🔧 增强选择器缓存机制

### 修复
- 🐛 修复 `:not(.class)` 伪类中类名被错误提取的问题
- 🐛 修复中文文本在 `:contains` 伪类中无法匹配的问题
- 🐛 修复 HTML 字符串加载时的 UTF-8 编码问题
- 🐛 修复属性选择器 `$=` 操作符在 XPath 1.0 中的兼容性问题
- 🐛 修复多个选择器组合时的解析问题

### 文档
- 📝 创建完整的 README.md 文档
- 📝 新增 examples.php，包含 15 个实际使用场景的示例代码
- 📝 完善所有类的中文注释
- 📝 新增性能优化指南和常见问题解答

### 清理
- 🧹 删除所有临时和备份文件
- 🧹 移除无用的示例和测试文件
- 🧹 统一代码风格和格式

## [1.0.0] - 初始版本

### 功能
- ✅ 基础 CSS 选择器支持
- ✅ XPath 选择器支持
- ✅ 基本伪类支持
- ✅ 元素操作方法
- ✅ 属性操作方法
- ✅ 类和样式操作

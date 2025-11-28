# PHP沙箱运行环境 - 完整使用文档

PHP沙箱运行环境是一个安全、高性能的PHP代码执行环境，允许您在受控的环境中运行不受信任的PHP代码。它提供了多层次的安全防护、资源限制和完整的错误处理机制。


### 主要特性

- 🛡️ **完全隔离的执行环境**
- ⚡ **高性能代码执行**
- 🔒 **多层次安全检查**
- 📊 **详细的执行统计**
- 🧹 **自动资源管理**
- 🔧 **灵活的配置系统**

## 快速开始

### 基础示例

```php
<?php

require_once 'vendor/autoload.php';

use zxf\Utils\Sandbox\PhpSandbox;

// 创建沙箱实例
$sandbox = new PhpSandbox();

// 执行简单的PHP代码
$code = '<?php echo "Hello, World!"; ?>';
$result = $sandbox->execute($code);

if ($result->isSuccess()) {
    echo "执行成功: " . $result->getOutput();
} else {
    echo "执行失败: " . $result->getError();
}
```
### 带配置的示例

```php
<?php

require_once 'vendor/autoload.php';

use zxf\Utils\Sandbox\PhpSandbox;

// 使用自定义配置
$sandbox = new PhpSandbox([
    'memory_limit' => 512,      // 内存限制（MB）
    'max_execution_time' => 30, // 执行时间限制（秒）
    'max_code_length' => 50000, // 代码长度限制（字符）
]);

$code = '
    $numbers = range(1, 100);
    $sum = array_sum($numbers);
    echo "1到100的和是: " . $sum;
';

$result = $sandbox->execute($code, 'sum_calculation');

echo "执行时间: " . $result->getExecutionTime() . "秒\n";
echo "内存使用: " . round($result->getMemoryUsed() / 1024, 2) . "KB\n";
```
## 配置选项

### 完整配置示例

```php
$config = [
    'memory_limit' => 256,           // 内存限制（MB），默认256
    'max_execution_time' => 10,      // 执行时间限制（秒），默认10
    'max_code_length' => 50000,      // 代码长度限制（字符），默认50000
    'max_history_size' => 100,       // 历史记录数量，默认100
    'temp_dir' => '/custom/temp',    // 临时目录，默认系统临时目录
];

$sandbox = new PhpSandbox($config);
```
### 运行时配置修改

```php
$sandbox = new PhpSandbox();

// 运行时修改配置
$sandbox->setConfig([
    'memory_limit' => 1024,
    'max_execution_time' => 60,
]);

// 获取当前配置
$currentConfig = $sandbox->getConfig();
print_r($currentConfig);
```
## 基础用法

### 执行简单代码

```php
$code = '<?php
    $name = "PHP沙箱";
    $version = "3.2.0";
    echo "欢迎使用 {$name} {$version}\n";
    
    // 数学运算
    $result = (1 + 2) * 3 / 4;
    echo "数学运算结果: {$result}\n";
    
    // 字符串操作
    $text = "Hello, World!";
    echo "原始: {$text}\n";
    echo "大写: " . strtoupper($text) . "\n";
    echo "反转: " . strrev($text) . "\n";
?>';

$result = $sandbox->execute($code, 'basic_demo');
```
### 处理执行结果

```php
$result = $sandbox->execute($code, 'demo');

if ($result->isSuccess()) {
    echo "✅ 执行成功\n";
    echo "输出内容:\n" . $result->getOutput();
    echo "执行时间: " . $result->getExecutionTime() . "秒\n";
    echo "内存使用: " . $result->getMemoryUsed() . "字节\n";
    echo "峰值内存: " . $result->getPeakMemory() . "字节\n";
} else {
    echo "❌ 执行失败\n";
    echo "错误类型: " . $result->getErrorType() . "\n";
    echo "错误信息: " . $result->getError() . "\n";
}

// 转换为数组
$resultArray = $result->toArray();
print_r($resultArray);

// 转换为字符串
echo (string)$result;
```
### 批量执行代码

```php
$batchCodes = [
    'math_operations' => '<?php
        echo "数学运算:\n";
        echo "加法: " . (5 + 3) . "\n";
        echo "乘法: " . (4 * 6) . "\n";
        echo "除法: " . (15 / 3) . "\n";
    ?>',
    
    'string_operations' => '<?php
        echo "字符串操作:\n";
        $text = "hello world";
        echo "原始: {$text}\n";
        echo "大写: " . strtoupper($text) . "\n";
        echo "单词首字母大写: " . ucwords($text) . "\n";
    ?>',
    
    'array_operations' => '<?php
        echo "数组操作:\n";
        $numbers = [1, 2, 3, 4, 5];
        echo "数组: " . implode(", ", $numbers) . "\n";
        echo "总和: " . array_sum($numbers) . "\n";
        echo "平均值: " . array_sum($numbers) / count($numbers) . "\n";
    ?>',
    
    'date_operations' => '<?php
        echo "日期操作:\n";
        echo "当前时间: " . date("Y-m-d H:i:s") . "\n";
        echo "时间戳: " . time() . "\n";
        echo "格式化: " . date("l, F jS Y") . "\n";
    ?>'
];

$results = $sandbox->execute($batchCodes);

foreach ($results as $name => $result) {
    echo "任务: {$name}\n";
    echo "状态: " . ($result->isSuccess() ? '成功' : '失败') . "\n";
    echo "时间: " . $result->getExecutionTime() . "秒\n";
    
    if ($result->isSuccess()) {
        echo "输出:\n" . $result->getOutput() . "\n";
    } else {
        echo "错误: " . $result->getError() . "\n";
    }
    echo str_repeat("-", 40) . "\n";
}
```
## 高级功能

### 复杂算法执行

```php
$algorithmCode = '<?php
    // 快速排序算法
    function quickSort($array) {
        if (count($array) < 2) {
            return $array;
        }
        $pivot = $array[0];
        $left = $right = [];
        for ($i = 1; $i < count($array); $i++) {
            if ($array[$i] < $pivot) {
                $left[] = $array[$i];
            } else {
                $right[] = $array[$i];
            }
        }
        return array_merge(quickSort($left), [$pivot], quickSort($right));
    }
    
    // 斐波那契数列
    function fibonacci($n) {
        if ($n <= 1) return $n;
        return fibonacci($n - 1) + fibonacci($n - 2);
    }
    
    // 性能测试
    $startTime = microtime(true);
    
    // 测试快速排序
    $testData = [64, 34, 25, 12, 22, 11, 90, 5, 77, 30];
    echo "原始数据: " . implode(", ", $testData) . "\n";
    $sortedData = quickSort($testData);
    echo "排序后: " . implode(", ", $sortedData) . "\n";
    
    // 测试斐波那契
    echo "斐波那契数列前15项:\n";
    for ($i = 0; $i < 15; $i++) {
        echo "fib({$i}) = " . fibonacci($i) . "\n";
    }
    
    $endTime = microtime(true);
    echo "总执行时间: " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
?>';

$result = $sandbox->execute($algorithmCode, 'algorithm_demo');
```
### 面向对象编程

```php
$oopCode = '<?php
    // 基础类定义
    class BankAccount {
        private $balance = 0;
        private $accountNumber;
        private $owner;
        
        public function __construct($accountNumber, $owner, $initialBalance = 0) {
            $this->accountNumber = $accountNumber;
            $this->owner = $owner;
            $this->balance = $initialBalance;
        }
        
        public function deposit($amount) {
            if ($amount > 0) {
                $this->balance += $amount;
                return true;
            }
            return false;
        }
        
        public function withdraw($amount) {
            if ($amount > 0 && $this->balance >= $amount) {
                $this->balance -= $amount;
                return true;
            }
            return false;
        }
        
        public function getBalance() {
            return $this->balance;
        }
        
        public function getAccountInfo() {
            return "账户: {$this->accountNumber}, 户主: {$this->owner}, 余额: {$this->balance}";
        }
    }
    
    // 继承示例
    class SavingsAccount extends BankAccount {
        private $interestRate;
        
        public function __construct($accountNumber, $owner, $initialBalance, $interestRate) {
            parent::__construct($accountNumber, $owner, $initialBalance);
            $this->interestRate = $interestRate;
        }
        
        public function applyInterest() {
            $interest = $this->getBalance() * $this->interestRate;
            $this->deposit($interest);
            return $interest;
        }
        
        public function getInterestRate() {
            return $this->interestRate;
        }
    }
    
    // 使用示例
    echo "=== 银行账户演示 ===\n";
    
    $account = new BankAccount("123456789", "张三", 1000);
    echo $account->getAccountInfo() . "\n";
    
    $account->deposit(500);
    echo "存入500后: " . $account->getBalance() . "\n";
    
    $account->withdraw(200);
    echo "取出200后: " . $account->getBalance() . "\n";
    
    echo "\n=== 储蓄账户演示 ===\n";
    
    $savings = new SavingsAccount("987654321", "李四", 5000, 0.05);
    echo $savings->getAccountInfo() . "\n";
    echo "利率: " . ($savings->getInterestRate() * 100) . "%\n";
    
    $interest = $savings->applyInterest();
    echo "应用利息: {$interest}\n";
    echo "新余额: " . $savings->getBalance() . "\n";
?>';

$result = $sandbox->execute($oopCode, 'oop_demo');
```
### 数据处理和转换

```php
$dataProcessingCode = '<?php
    // 模拟数据处理
    class DataProcessor {
        private $data = [];
        
        public function __construct(array $data) {
            $this->data = $data;
        }
        
        public function filter(callable $filterFunc) {
            $this->data = array_filter($this->data, $filterFunc);
            return $this;
        }
        
        public function map(callable $mapFunc) {
            $this->data = array_map($mapFunc, $this->data);
            return $this;
        }
        
        public function sort($ascending = true) {
            if ($ascending) {
                sort($this->data);
            } else {
                rsort($this->data);
            }
            return $this;
        }
        
        public function getStatistics() {
            $count = count($this->data);
            $sum = array_sum($this->data);
            $average = $count > 0 ? $sum / $count : 0;
            $min = $count > 0 ? min($this->data) : null;
            $max = $count > 0 ? max($this->data) : null;
            
            return [
                "count" => $count,
                "sum" => $sum,
                "average" => $average,
                "min" => $min,
                "max" => $max,
                "range" => $max - $min
            ];
        }
        
        public function getData() {
            return $this->data;
        }
    }
    
    // 生成测试数据
    $testData = [];
    for ($i = 0; $i < 100; $i++) {
        $testData[] = rand(1, 1000);
    }
    
    echo "原始数据样本: " . implode(", ", array_slice($testData, 0, 10)) . "...\n";
    
    $processor = new DataProcessor($testData);
    
    // 数据处理流程
    $result = $processor
        ->filter(fn($x) => $x > 100)           // 过滤小于100的值
        ->map(fn($x) => $x * 1.1)              // 每个值增加10%
        ->sort(true)                           // 升序排序
        ->getData();
    
    echo "处理后的数据样本: " . implode(", ", array_slice($result, 0, 10)) . "...\n";
    
    $stats = $processor->getStatistics();
    echo "\n数据统计:\n";
    foreach ($stats as $key => $value) {
        echo "{$key}: {$value}\n";
    }
?>';

$result = $sandbox->execute($dataProcessingCode, 'data_processing');
```
### 性能监控和统计

```php
// 创建高性能配置的沙箱
$sandbox = new PhpSandbox([
    'memory_limit' => 1024,
    'max_execution_time' => 60,
    'max_history_size' => 50,
]);

// 执行多个性能测试
$performanceTests = [
    'string_processing' => '<?php
        $start = microtime(true);
        $result = "";
        for ($i = 0; $i < 10000; $i++) {
            $result .= "Item " . $i . ": " . md5($i) . "\n";
        }
        $time = microtime(true) - $start;
        echo "字符串处理完成\n";
        echo "执行时间: " . round($time * 1000, 2) . "ms\n";
        echo "结果长度: " . strlen($result) . " bytes\n";
    ?>',
    
    'array_operations' => '<?php
        $start = microtime(true);
        $data = [];
        for ($i = 0; $i < 5000; $i++) {
            $data[] = [
                "id" => $i,
                "value" => $i * rand(1, 10),
                "timestamp" => time() + $i
            ];
        }
        
        // 复杂数组操作
        $filtered = array_filter($data, fn($item) => $item["value"] > 1000);
        $mapped = array_map(fn($item) => $item["value"] * 2, $filtered);
        $sorted = $mapped;
        sort($sorted);
        
        $time = microtime(true) - $start;
        echo "数组操作完成\n";
        echo "执行时间: " . round($time * 1000, 2) . "ms\n";
        echo "原始数据: " . count($data) . " 条\n";
        echo "过滤后: " . count($filtered) . " 条\n";
    ?>',
    
    'math_calculations' => '<?php
        $start = microtime(true);
        $total = 0;
        for ($i = 0; $i < 100000; $i++) {
            $total += sqrt($i) * cos($i) / (sin($i) + 1);
        }
        $time = microtime(true) - $start;
        echo "数学计算完成\n";
        echo "执行时间: " . round($time * 1000, 2) . "ms\n";
        echo "计算结果: " . $total . "\n";
    ?>'
];

$results = $sandbox->execute($performanceTests);

// 获取详细统计信息
$statistics = $sandbox->getStatistics();
echo "\n=== 全局执行统计 ===\n";
foreach ($statistics as $key => $value) {
    if (is_float($value)) {
        echo $key . ": " . round($value, 4) . "\n";
    } else {
        echo $key . ": " . $value . "\n";
    }
}

// 获取执行历史
$history = $sandbox->getExecutionHistory(5);
echo "\n=== 最近5次执行历史 ===\n";
foreach ($history as $index => $record) {
    echo ($index + 1) . ". " . $record['identifier'] . 
         " - " . ($record['success'] ? '成功' : '失败') .
         " - " . $record['execution_time'] . "s\n";
}
```
## 安全特性

### 安全限制示例

```php
$sandbox = new PhpSandbox();

// 测试安全限制
$dangerousCodes = [
    'file_operations' => '<?php
        // 尝试文件操作（会被阻止）
        file_put_contents("test.txt", "hack");
        echo "这行不会执行";
    ?>',
    
    'system_commands' => '<?php
        // 尝试系统命令（会被阻止）
        system("ls -la");
        echo "这行不会执行";
    ?>',
    
    'dangerous_functions' => '<?php
        // 尝试使用危险函数（会被阻止）
        eval("echo \'hack\'");
        echo "这行不会执行";
    ?>',
    
    'information_disclosure' => '<?php
        // 尝试信息泄露（会被阻止）
        phpinfo();
        echo "这行不会执行";
    ?>'
];

$results = $sandbox->execute($dangerousCodes);

foreach ($results as $name => $result) {
    echo "测试: {$name}\n";
    if ($result->isSuccess()) {
        echo "❌ 安全漏洞: 危险代码被执行成功！\n";
    } else {
        echo "✅ 安全保护: " . $result->getError() . "\n";
    }
    echo "\n";
}
```
### 自定义安全规则

```php
$sandbox = new PhpSandbox();

// 添加自定义允许函数
$sandbox->addAllowedFunction('my_custom_function');

// 添加自定义禁用函数
$sandbox->addDisabledFunction('some_dangerous_function');

// 测试自定义函数
$customCode = '<?php
    function my_custom_function($input) {
        return "Processed: " . strtoupper($input);
    }
    
    echo my_custom_function("hello world") . "\n";
    
    // 这个会被阻止
    some_dangerous_function();
?>';

$result = $sandbox->execute($customCode, 'custom_functions');
```
## 性能优化

### 优化配置示例

```php
// 高性能配置
$highPerformanceSandbox = new PhpSandbox([
    'memory_limit' => 2048,           // 2GB内存
    'max_execution_time' => 120,      // 2分钟执行时间
    'max_code_length' => 200000,      // 200K代码长度
    'max_history_size' => 20,         // 减少历史记录节省内存
]);

// 低内存配置
$lowMemorySandbox = new PhpSandbox([
    'memory_limit' => 64,             // 64MB内存
    'max_execution_time' => 5,        // 5秒执行时间
    'max_code_length' => 10000,       // 10K代码长度
]);

// 批量处理优化
function processMultipleScripts($scripts, $sandbox) {
    $results = [];
    $batchSize = 5; // 每次处理5个脚本
    
    foreach (array_chunk($scripts, $batchSize, true) as $batch) {
        $batchResults = $sandbox->execute($batch);
        $results = array_merge($results, $batchResults);
        
        // 强制垃圾回收
        if (gc_enabled()) {
            gc_collect_cycles();
        }
    }
    
    return $results;
}
```
## 故障排除

### 常见错误处理

```php
try {
    $sandbox = new PhpSandbox();
    
    // 测试各种错误情况
    $problematicCodes = [
        'syntax_error' => '<?php
            $a = 10
            $b = 20; // 缺少分号
            echo $a + $b;
        ?>',
        
        'memory_overflow' => '<?php
            $data = "";
            while (true) {
                $data .= str_repeat("x", 1024 * 1024); // 1MB每次
            }
        ?>',
        
        'timeout' => '<?php
            // 无限循环
            while (true) {
                // 什么都不做，但消耗时间
            }
        ?>',
        
        'undefined_function' => '<?php
            // 调用不存在的函数
            undefined_function_call();
        ?>'
    ];
    
    $results = $sandbox->execute($problematicCodes);
    
    foreach ($results as $name => $result) {
        echo "测试: {$name}\n";
        if ($result->isSuccess()) {
            echo "状态: 成功\n";
            echo "输出: " . $result->getOutput() . "\n";
        } else {
            echo "状态: 失败\n";
            echo "错误类型: " . $result->getErrorType() . "\n";
            echo "错误信息: " . $result->getError() . "\n";
        }
        echo "执行时间: " . $result->getExecutionTime() . "s\n";
        echo "内存使用: " . $result->getMemoryUsed() . " bytes\n";
        echo str_repeat("-", 50) . "\n";
    }
    
} catch (Exception $e) {
    echo "沙箱初始化错误: " . $e->getMessage() . "\n";
    echo "请检查系统要求和配置\n";
}
```
### 调试和日志

```php
// 启用详细错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sandbox = new PhpSandbox();

// 获取沙箱信息用于调试
echo "沙箱临时目录: " . $sandbox->getTempDir() . "\n";
echo "沙箱唯一前缀: " . $sandbox->getUniquePrefix() . "\n";

// 执行测试代码
$debugCode = '<?php
    echo "PHP版本: " . PHP_VERSION . "\n";
    echo "内存限制: " . ini_get("memory_limit") . "\n";
    echo "最大执行时间: " . ini_get("max_execution_time") . "\n";
    echo "当前内存使用: " . memory_get_usage(true) . " bytes\n";
    echo "峰值内存使用: " . memory_get_peak_usage(true) . " bytes\n";
    
    // 测试可用函数
    $functions = ["strlen", "substr", "array_map", "json_encode"];
    foreach ($functions as $func) {
        echo "函数 {$func} 可用: " . (function_exists($func) ? "是" : "否") . "\n";
    }
?>';

$result = $sandbox->execute($debugCode, 'debug_info');

if ($result->isSuccess()) {
    echo "调试信息:\n" . $result->getOutput();
} else {
    echo "调试失败: " . $result->getError() . "\n";
}
```
## API参考

### PhpSandbox 类

#### 构造函数

```php
new PhpSandbox(array $config = [])
```
#### 主要方法

```php
// 执行PHP代码
execute($code, ?string $identifier = null): PhpSandboxResult|array

// 设置配置
setConfig(array $config): self

// 获取配置
getConfig(): array

// 获取统计信息
getStatistics(): array

// 获取执行历史
getExecutionHistory(int $limit = 10): array

// 清理资源
cleanup(): void

// 添加允许函数
addAllowedFunction(string $function): self

// 添加禁用函数
addDisabledFunction(string $function): self

// 重置历史
resetHistory(): self

// 获取临时目录
getTempDir(): string

// 获取唯一前缀
getUniquePrefix(): string
```
### PhpSandboxResult 类

#### 主要方法

```php
isSuccess(): bool
getOutput(): string
getError(): string
getErrorType(): string
getExecutionTime(): float
getMemoryUsed(): int
getPeakMemory(): int
getIdentifier(): string
getTimestamp(): int
toArray(): array
__toString(): string
```
### 完整示例项目

#### 在线代码执行平台模拟

```php
<?php

require_once 'vendor/autoload.php';

use zxf\Utils\Sandbox\PhpSandbox;

class OnlineCodeExecutor {
    private $sandbox;
    private $executionHistory = [];
    
    public function __construct() {
        $this->sandbox = new PhpSandbox([
            'memory_limit' => 512,
            'max_execution_time' => 30,
            'max_code_length' => 100000,
            'max_history_size' => 100,
        ]);
    }
    
    public function executeCode($code, $language = 'php') {
        if ($language !== 'php') {
            return [
                'success' => false,
                'error' => '不支持的编程语言: ' . $language,
                'output' => ''
            ];
        }
        
        $identifier = 'user_code_' . uniqid();
        $result = $this->sandbox->execute($code, $identifier);
        
        // 记录执行历史
        $this->executionHistory[] = [
            'timestamp' => time(),
            'identifier' => $identifier,
            'code_preview' => substr($code, 0, 100) . (strlen($code) > 100 ? '...' : ''),
            'result' => $result->toArray()
        ];
        
        return $result->toArray();
    }
    
    public function getPlatformStatistics() {
        $sandboxStats = $this->sandbox->getStatistics();
        
        return [
            'platform_stats' => [
                'total_executions' => $sandboxStats['total_executions'],
                'success_rate' => $sandboxStats['success_rate'],
                'average_execution_time' => $sandboxStats['average_execution_time'],
                'current_memory_usage' => $sandboxStats['current_memory_usage'],
            ],
            'recent_executions' => array_slice($this->executionHistory, -10)
        ];
    }
    
    public function cleanup() {
        $this->sandbox->cleanup();
        $this->executionHistory = [];
    }
}

// 使用示例
$executor = new OnlineCodeExecutor();

// 模拟用户提交代码
$userCodes = [
    '基础数学运算' => '<?php
        $a = 15;
        $b = 25;
        echo "{$a} + {$b} = " . ($a + $b) . "\n";
        echo "{$a} * {$b} = " . ($a * $b) . "\n";
        echo "{$b} / {$a} = " . ($b / $a) . "\n";
    ?>',
    
    '字符串处理' => '<?php
        $text = "欢迎使用在线PHP代码执行器";
        echo "原始文本: {$text}\n";
        echo "文本长度: " . strlen($text) . "\n";
        echo "单词数量: " . str_word_count($text) . "\n";
        echo "MD5哈希: " . md5($text) . "\n";
    ?>',
    
    '数组操作' => '<?php
        $data = ["苹果", "香蕉", "橙子", "葡萄", "芒果"];
        echo "水果列表: " . implode(", ", $data) . "\n";
        echo "排序后: " . implode(", ", sort($data)) . "\n";
        echo "随机选择: " . $data[array_rand($data)] . "\n";
    ?>'
];

echo "=== 在线代码执行平台演示 ===\n\n";

foreach ($userCodes as $description => $code) {
    echo "执行: {$description}\n";
    $result = $executor->executeCode($code);
    
    if ($result['success']) {
        echo "✅ 执行成功\n";
        echo "输出:\n" . $result['output'] . "\n";
    } else {
        echo "❌ 执行失败\n";
        echo "错误: " . $result['error'] . "\n";
    }
    echo "执行时间: " . $result['execution_time'] . "秒\n";
    echo str_repeat("=", 50) . "\n\n";
}

// 显示平台统计
$stats = $executor->getPlatformStatistics();
echo "=== 平台统计 ===\n";
echo "总执行次数: " . $stats['platform_stats']['total_executions'] . "\n";
echo "成功率: " . round($stats['platform_stats']['success_rate'], 2) . "%\n";
echo "平均执行时间: " . round($stats['platform_stats']['average_execution_time'], 4) . "秒\n";
echo "当前内存使用: " . round($stats['platform_stats']['current_memory_usage'] / 1024 / 1024, 2) . "MB\n";

// 清理资源
$executor->cleanup();
```
这个完整的文档涵盖了PHP沙箱运行环境的所有主要功能和用法。
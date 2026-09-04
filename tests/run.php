<?php

/**
 * ============================================================================
 * zxf/utils 测试运行器
 * ============================================================================
 *
 * 用法：
 *   composer test              # 运行全部测试
 *   php tests/run.php          # 等价写法
 *   php tests/run.php --filter=Version   # 只运行类名含 Version 的用例
 *
 * 退出码：0 = 全部通过，1 = 存在失败。
 *
 * 刻意不依赖 PHPUnit：本包为零依赖工具库，测试应能在 composer install
 * 之后直接运行，无需额外下载测试框架。
 */

declare(strict_types=1);

namespace zxf\Utils\Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

$autoloaders = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php', // 作为依赖被安装到 vendor/ 时的路径
];

$loaded = false;
foreach ($autoloaders as $file) {
    if (is_file($file)) {
        require $file;
        $loaded = true;
        break;
    }
}

if (! $loaded) {
    fwrite(STDERR, "未找到 Composer 自动加载器，请先运行：composer install\n");
    exit(1);
}

// 兼容无 PSR-4 自动加载（autoload-dev 未重新生成）的场景
foreach (['Assert.php', 'AssertionFailedException.php', 'TestCase.php'] as $file) {
    $path = __DIR__ . '/' . $file;
    if (is_file($path) && ! class_exists(__NAMESPACE__ . '\\' . substr($file, 0, -4), false)) {
        require_once $path;
    }
}

$filter = null;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--filter=')) {
        $filter = substr($arg, 9);
    }
}

echo str_repeat('=', 70), "\n";
echo " zxf/utils 测试套件  (PHP ", PHP_VERSION, ")\n";
echo str_repeat('=', 70), "\n\n";

/** @var list<class-string<TestCase>> $testClasses */
$testClasses = [];

$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
foreach ($directory as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();

    if (! str_ends_with($path, 'Test.php')) {
        continue;
    }

    // 目录 /…/tests/Unit/FooTest.php  =>  zxf\Utils\Tests\Unit\FooTest
    $relative = substr($path, strlen(__DIR__), -4);
    $class = __NAMESPACE__ . str_replace('/', '\\', $relative);

    if (! class_exists($class)) {
        require_once $path;
    }

    if (class_exists($class) && is_subclass_of($class, TestCase::class)) {
        $testClasses[] = $class;
    }
}

sort($testClasses);

if ($testClasses === []) {
    fwrite(STDERR, "未发现任何测试用例（tests/ 下缺少 *Test.php）\n");
    exit(1);
}

$totalPassed = 0;
$totalFailed = 0;
/** @var list<array{0: string, 1: string, 2: string}> $allFailures */
$allFailures = [];
$startedAt = microtime(true);

foreach ($testClasses as $class) {
    if ($filter !== null && ! str_contains($class, $filter)) {
        continue;
    }

    $shortName = substr($class, strlen(__NAMESPACE__) + 1);

    try {
        $instance = new $class();
    } catch (Throwable $e) {
        echo "  \033[31m✗\033[0m {$shortName}: 无法实例化 — {$e->getMessage()}\n";
        $totalFailed++;
        continue;
    }

    $result = $instance->run();

    $totalPassed += $result['passed'];
    $totalFailed += $result['failed'];
    $allFailures = array_merge($allFailures, $result['failures']);

    $icon = $result['failed'] > 0 ? "\033[31m✗\033[0m" : "\033[32m✓\033[0m";
    printf(
        "  %s %-46s %3d passed %s\n",
        $icon,
        $shortName,
        $result['passed'],
        $result['failed'] > 0 ? ", \033[31m{$result['failed']} failed\033[0m" : ''
    );
}

$elapsed = microtime(true) - $startedAt;

echo "\n", str_repeat('-', 70), "\n";

if ($allFailures !== []) {
    echo "\n\033[31m失败明细：\033[0m\n\n";
    foreach ($allFailures as $index => [$label, $method, $reason]) {
        printf("  %d) %s\n     %s\n\n", $index + 1, $label, $reason);
    }
}

printf(
    "测试类 %d 个 | 测试方法 %d 个 | 失败 %d 个 | 耗时 %.3fs\n",
    count($testClasses),
    $totalPassed + $totalFailed,
    $totalFailed,
    $elapsed
);

if ($totalFailed > 0) {
    echo "\n\033[41;97m FAILED \033[0m\n";
    exit(1);
}

echo "\n\033[42;97m ALL PASSED \033[0m\n";
exit(0);

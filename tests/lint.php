<?php

/**
 * ============================================================================
 * zxf/utils 静态检查脚本
 * ============================================================================
 *
 * 用法：
 *   composer lint
 *   php tests/lint.php
 *
 * 检查项：
 *  1. 全量 PHP 语法检查（php -l）
 *  2. strict_types 声明覆盖率
 *  3. 类文件是否使用未声明的第三方命名空间（漏写 composer 依赖的常见原因）
 *  4. 注释/字符串中误写 PHP 结束标记 `?>`（会提前截断文件）
 *
 * 退出码：0 = 全部通过，1 = 发现问题。
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$srcDir = $root . '/src';

echo str_repeat('=', 70), "\n";
echo " zxf/utils 静态检查\n";
echo str_repeat('=', 70), "\n\n";

$problems = [];

/* ---------------------------------------------------------------- 1. 语法 */

$phpFiles = [];
$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
foreach ($directory as $file) {
    if ($file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}
sort($phpFiles);

$syntaxErrors = 0;
foreach ($phpFiles as $file) {
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1');

    if (is_string($output) && ! str_contains($output, 'No syntax errors')) {
        $syntaxErrors++;
        $problems[] = ['语法错误', $file, trim($output)];
    }
}

printf("  语法检查      : %d 个文件，%s\n", count($phpFiles), $syntaxErrors === 0 ? '全部通过' : "{$syntaxErrors} 个失败");

/* ------------------------------------------------- 2. strict_types 覆盖率 */

$missingStrict = [];
foreach ($phpFiles as $file) {
    // 排除 vendor 进来的第三方代码（版权与风格各异，不做强制）
    if (str_contains($file, '/src/PHPMailer/') || str_contains($file, '/src/QrCode/')) {
        continue;
    }

    $head = (string) file_get_contents($file, false, null, 0, 2000);

    if (! str_contains($head, 'declare(strict_types=1)')) {
        $missingStrict[] = $file;
    }
}

printf(
    "  strict_types  : %s\n",
    $missingStrict === [] ? '全部已声明' : count($missingStrict) . ' 个文件缺失'
);

foreach ($missingStrict as $file) {
    $problems[] = ['缺少 strict_types', $file, '建议在 namespace 声明后添加 declare(strict_types=1);'];
}

/* -------------------------------------------- 3. 未声明的第三方命名空间 */

/**
 * 已知命名空间根白名单（本包为「零第三方运行时依赖」的独立库）：
 * - zxf：本包自身
 * - PHPMailer：以源码形式内嵌于本包（src/PHPMailer），非 composer 依赖
 * - Illuminate / League / Composer：可选集成点——仅在宿主为 Laravel 或
 *   按需启用 PHPMailer OAuth（league/oauth2-client）时才会被加载，二者
 *   均在 composer.json 的 suggest 中注明，不属于运行时硬依赖
 * - Random：PHP 8.2 内置命名空间（如 Random\RandomException），非第三方
 *
 * 注意：任何新增的第三方 use（如曾引入过的 picqer/psr/cache）都不会通过
 * 此白名单，lint 会将其标记为「未声明」，防止依赖回归。
 */
$knownRoots = [
    'zxf', 'PHPMailer', 'Illuminate', 'League', 'Composer',
    'Random',
];

$undeclared = [];
foreach ($phpFiles as $file) {
    $source = (string) file_get_contents($file);

    if (preg_match_all('/^use\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\s*;/m', $source, $matches) === false) {
        continue;
    }

    foreach ($matches[1] as $fqcn) {
        $root = explode('\\', $fqcn)[0];

        if (in_array($root, $knownRoots, true)) {
            continue;
        }
        // 全局类（无命名空间）与 PHP 内置类不在此列
        if (! str_contains($fqcn, '\\')) {
            continue;
        }

        $undeclared[$fqcn][] = $file;
    }
}

printf(
    "  第三方依赖    : %s\n",
    $undeclared === [] ? '无未声明项' : count($undeclared) . ' 个待确认'
);

foreach ($undeclared as $fqcn => $files) {
    $problems[] = ['未声明的命名空间', $files[0], "use {$fqcn} —— 请确认已写入 composer.json 的 require 或 suggest"];
}

/* ------------------------------------------- 4. 误写 PHP 结束标记（? >） */

$endTagIssues = [];
foreach ($phpFiles as $file) {
    $source = (string) file_get_contents($file);
    $lines = explode("\n", $source);

    foreach ($lines as $index => $line) {
        $trimmed = ltrim($line);

        // 只关注注释与字符串中的误写；真正的 PHP 代码块结尾在类库文件中不应出现
        $isComment = str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '#');

        if ($isComment && str_contains($line, '?>')) {
            $endTagIssues[] = [$file, $index + 1, $line];
        }
    }
}

printf(
    "  PHP 结束标记  : %s\n",
    $endTagIssues === [] ? '无风险' : count($endTagIssues) . ' 处风险'
);

foreach ($endTagIssues as [$file, $lineNo, $line]) {
    $problems[] = ['注释中误写 ?>', $file . ':' . $lineNo, '注释里的 ?> 会终止 PHP 代码块，导致文件被截断'];
}

/* --------------------------------------------------------------- 汇总输出 */

echo "\n", str_repeat('-', 70), "\n";

if ($problems === []) {
    echo "\n\033[42;97m LINT PASSED \033[0m\n";
    exit(0);
}

echo "\n\033[31m共发现 " . count($problems) . " 处问题：\033[0m\n\n";

foreach ($problems as $index => [$type, $location, $detail]) {
    printf("  %d) [%s]\n     %s\n     %s\n\n", $index + 1, $type, $location, $detail);
}

echo "\n\033[41;97m LINT FAILED \033[0m\n";
exit(1);

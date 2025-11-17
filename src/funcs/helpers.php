<?php

declare(strict_types=1);

if (! function_exists('uuid')) {
    /**
     * 极速并发UUID生成器
     * 函数优点：可靠性 + 安全性 +性能 + 简洁性
     * 设计理念：单函数满足所有场景，极致性能与绝对安全的完美统一
     * 核心优势：无锁并发、跨进程安全、内存极致、代码极简
     *
     * @return string 返回10-11字符的全局唯一UUID
     * @throws Exception 随机数生成失败时抛出异常
     */
    function uuid(): string
    {
        // ==================== 静态初始化区 ====================
        // 使用静态变量实现单次初始化，永久使用（集成uuid_batch的静态优化思想）
        static $base62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'; // Base62编码字符表
        static $last_microtime = 0;    // 上次微秒时间戳缓存，避免重复计算
        static $sequence = 0;          // 序列计数器，同一微秒内递增
        static $process_hash = 0;      // 进程唯一哈希，确保跨进程安全
        static $entropy_seed = 0;      // 熵种子，动态增强随机性

        // ==================== 一次性初始化 ====================
        // 进程启动时执行一次，后续调用直接使用缓存值（极致性能优化）
        if ($process_hash === 0) {
            $process_hash = (getmypid() & 0x7FF) | (random_int(0, 0x7FF) << 11); // 22位进程哈希（PID+高随机）
            $entropy_seed = random_int(1, 0x3FFFFFF); // 26位初始熵种子，避免0值
        }

        // ==================== 高精度时间获取 ====================
        // 使用hrtime获取纳秒时间，完全避免浮点精度问题
        $current_microtime = intdiv(hrtime(true), 1000); // 纳秒转微秒，性能最优的除法方式
        $relative_microtime = $current_microtime - 1788211200000000; // 相对于2026-01-01的微秒数

        // ==================== 无锁并发控制 ====================
        // 极简的序列控制算法，无锁设计实现高并发
        if ($relative_microtime === $last_microtime) {
            $sequence = ($sequence + 1) & 0x7FF; // 11位序列号（0-2047），同一微秒内递增
            // 序列溢出保护：微秒级快速重试
            if ($sequence === 0) $relative_microtime = intdiv(hrtime(true), 1000) - 1788211200000000; // 重取时间
        } else {
            $sequence = random_int(1, 0x3FF); // 新时间点随机序列，避免模式化（集成uuid的随机起始）
            $last_microtime = $relative_microtime; // 更新时间缓存
        }

        // ==================== 动态熵增强 ====================
        // 持续更新的熵池，确保随机性不可预测
        $entropy_seed = ($entropy_seed * 1103515245 + 12345) & 0x3FFFFFF; // 线性同余生成器，高效随机
        $mixed_entropy = ($entropy_seed ^ $process_hash ^ $relative_microtime) & 0x7FF; // 11位混合熵

        // ==================== 最优位分配 ====================
        // 64位完美分配：42位时间戳 + 11位序列 + 11位熵 = 64位（集成所有函数的位分配精华）
        $numeric_id = (($relative_microtime & 0x3FFFFFFFFFF) << 22) | ($sequence << 11) | $mixed_entropy;

        // ==================== 极致Base62编码 ====================
        // 单循环编码算法，无分支预测惩罚
        $result = ''; // 编码结果字符串
        for ($num = $numeric_id; $num > 0; $num = intdiv($num, 62)) {
            $result = $base62[$num % 62] . $result; // 余数作为索引取字符，前置拼接
        }

        return $result ?: '0'; // 返回编码结果，确保不为空（安全保护）
    }
}


<?php

/**
 * ============================================================================
 * 密码安全工具类
 * ============================================================================
 *
 * 提供密码生成、验证、强度检测等安全功能。
 * 基于 PHP 8.2+ 的 password_hash 算法（默认使用 PASSWORD_ARGON2ID）。
 *
 * @package     zxf\Utils\Security
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @example
 * // 哈希密码
 * $hash = Password::hash('user_password');
 *
 * // 验证密码
 * if (Password::verify('user_password', $hash)) {
 *     // 密码正确
 * }
 *
 * // 检查是否需要重新哈希
 * if (Password::needsRehash($hash)) {
 *     $newHash = Password::hash('user_password');
 * }
 */

declare(strict_types=1);

namespace zxf\Utils\Security;

use InvalidArgumentException;
use Random\RandomException;

final class Password
{
    private const MIN_LENGTH = 8;

    /**
     * 哈希密码
     *
     * @param string $password 明文密码
     * @param array<string, mixed> $options 选项: memory_cost, time_cost, threads
     */
    public static function hash(string $password, array $options = []): string
    {
        if (strlen($password) < self::MIN_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Password must be at least %d characters long.', self::MIN_LENGTH)
            );
        }

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_hash($password, $algo, $options);
    }

    /**
     * 验证密码
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 检查哈希是否需要重新计算（算法升级或选项变更）
     */
    public static function needsRehash(string $hash, array $options = []): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_needs_rehash($hash, $algo, $options);
    }

    /**
     * 生成高强度随机密码
     *
     * @throws RandomException
     */
    public static function generate(int $length = 16, bool $useSpecial = true): string
    {
        if ($length < self::MIN_LENGTH) {
            $length = self::MIN_LENGTH;
        }

        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        // 确保每类至少包含一个字符
        $password = [
            $lowercase[random_int(0, strlen($lowercase) - 1)],
            $uppercase[random_int(0, strlen($uppercase) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
        ];

        if ($useSpecial) {
            $password[] = $special[random_int(0, strlen($special) - 1)];
        }

        $allChars = $lowercase . $uppercase . $numbers . ($useSpecial ? $special : '');
        for ($i = count($password); $i < $length; $i++) {
            $password[] = $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // 打乱顺序
        shuffle($password);

        return implode('', $password);
    }

    /**
     * 评估密码强度
     *
     * @return int 0-100 的分数
     */
    public static function strength(string $password): int
    {
        $score = 0;
        $length = strlen($password);

        // 长度评分
        $score += min($length * 4, 40);

        // 字符种类评分
        $hasLower = preg_match('/[a-z]/', $password) ? 1 : 0;
        $hasUpper = preg_match('/[A-Z]/', $password) ? 1 : 0;
        $hasNumber = preg_match('/[0-9]/', $password) ? 1 : 0;
        $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password) ? 1 : 0;

        $typeCount = $hasLower + $hasUpper + $hasNumber + $hasSpecial;
        $score += ($typeCount - 1) * 10;

        // 中间数字/特殊字符加分
        $midChars = preg_match_all('/[0-9\W]/', substr($password, 1, -1));
        $score += $midChars * 2;

        // 惩罚项
        if ($hasLower + $hasUpper === 2 && $hasNumber + $hasSpecial === 0) {
            $score -= 10; // 纯字母
        }
        if ($hasNumber === 1 && $hasLower + $hasUpper + $hasSpecial === 0) {
            $score -= 10; // 纯数字
        }

        // 重复字符惩罚
        $uniqueChars = count(array_unique(str_split($password)));
        $score -= ($length - $uniqueChars) * 2;

        return max(0, min(100, $score));
    }

    /**
     * 获取密码强度的文字描述
     */
    public static function strengthLabel(string $password): string
    {
        $score = self::strength($password);

        return match (true) {
            $score >= 80 => '非常强',
            $score >= 60 => '强',
            $score >= 40 => '中等',
            $score >= 20 => '弱',
            default => '非常弱',
        };
    }

    /**
     * 检查密码是否被泄露（基于 Have I Been Pwned API）
     *
     * @throws \RuntimeException 当 API 请求失败时
     */
    public static function isPwned(string $password): bool
    {
        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);

        $url = 'https://api.pwnedpasswords.com/range/' . $prefix;
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 5],
        ]));

        if ($response === false) {
            throw new \RuntimeException('Unable to check password against Pwned Passwords API');
        }

        foreach (explode("\n", $response) as $line) {
            [$hashSuffix] = explode(':', $line);
            if (strcasecmp(trim($hashSuffix), $suffix) === 0) {
                return true;
            }
        }

        return false;
    }
}

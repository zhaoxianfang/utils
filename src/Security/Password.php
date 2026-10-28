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

/**
 * 密码安全工具类
 *
 * 基于 password_hash（默认 PASSWORD_ARGON2ID）提供密码哈希、验证、
 * 强度检测与是否需要重新哈希的判断。
 *
 * @package zxf\Utils\Security
 */
final class Password
{
    private const MIN_LENGTH = 8;

    /**
     * 哈希密码
     *
     * 优先使用 PASSWORD_ARGON2ID，未编译 Argon2 时自动退回 PASSWORD_BCRYPT；
     * 明文长度按字节计算，少于 8 字节直接抛异常。返回的哈希串已内嵌算法与
     * 参数信息，验证时无需再次传入，可直接存库。
     *
     * @param string $password 明文密码，长度不得少于 8 个字节
     * @param array<string, mixed> $options 选项: memory_cost, time_cost, threads
     * @return string 可直接存储的密码哈希串
     *
     * @throws InvalidArgumentException 密码长度不足 8 个字节时抛出
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
     *
     * 委托 password_verify()，按哈希串内嵌的算法与参数重新计算并做定时安全比较；
     * 哈希串格式非法或算法不支持时返回 false，不抛异常。
     *
     * @param string $password 待验证的明文密码
     * @param string $hash     数据库中保存的密码哈希串
     * @return bool 密码匹配返回 true，否则返回 false
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 检查哈希是否需要重新计算（算法升级或选项变更）
     *
     * 以当前默认算法（Argon2id，不可用时为 bcrypt）与传入的 $options 为基准，
     * 判断已有哈希串是否与之不一致；返回 true 时应重新哈希并更新存储。
     * 哈希串格式非法时同样返回 true。
     *
     * @param string $hash    已有的密码哈希串
     * @param array<string, mixed> $options 目标选项，需与哈希时使用的一致
     * @return bool 需要重新哈希返回 true，否则返回 false
     */
    public static function needsRehash(string $hash, array $options = []): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_needs_rehash($hash, $algo, $options);
    }

    /**
     * 生成高强度随机密码
     *
     * 使用 random_int（CSPRNG）逐字符取样，并强制包含小写、大写、数字各至少
     * 一个（$useSpecial 为 true 时再加一个特殊字符），最后整体打乱顺序，
     * 避免固定前缀规律。长度小于 8 时会自动提升为 8，因此返回长度可能大于入参。
     *
     * @param int  $length     期望长度，小于 8 时按 8 处理
     * @param bool $useSpecial 是否允许并强制包含特殊字符
     * @return string 随机密码，长度不小于 8
     *
     * @throws \Exception 当 CSPRNG 不可用或无法产生随机数时抛出
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
     * 打分规则：长度每字符 4 分（上限 40）＋ 字符种类每多一种加 10 分
     * ＋ 中间位置的数字/符号每个加 2 分（不设上限）－ 纯字母/纯数字各扣 10 分
     * － 重复字符每个扣 2 分。最终裁剪到 0~100 区间。
     * 长度按字节计，且未做字典/常见密码检测，仅可作为前端提示参考。
     *
     * @param string $password 待评估的明文密码
     * @return int 0-100 的分数，越高越强
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
     *
     * 以 strength() 的分数分档：≥80 非常强、≥60 强、≥40 中等、≥20 弱、
     * 其余非常弱，返回中文文案。
     *
     * @param string $password 待评估的明文密码
     * @return string 强度文案，取值之一：非常弱 / 弱 / 中等 / 强 / 非常强
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
     * 采用 k-Anonymity 方案：只把 SHA-1 的前 5 位（大写十六进制）发给
     * api.pwnedpasswords.com，本地再比对返回列表中的后 35 位，明文与完整哈希
     * 都不会出网。请求超时固定为 5 秒，遇到网络异常直接抛异常。
     * 每个网络请求都会带来明显延迟，建议只在注册/改密时调用。
     *
     * @param string $password 待检查的明文密码
     * @return bool 命中泄露库返回 true，未命中返回 false
     *
     * @throws \RuntimeException 当 API 请求失败（网络不可达、超时等）时抛出
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

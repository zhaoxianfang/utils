<?php

declare(strict_types=1);

namespace zxf\Utils\Crypto;

use InvalidArgumentException;
use RuntimeException;

/**
 * 密码处理类
 * 提供密码哈希、验证、强度评估和强密码生成功能
 * 基于PHP 8.2+ password_* 函数和原生随机扩展
 *
 * @package Crypto
 * @version 1.0.0
 * @license MIT
 */
class Password
{
    /**
     * @var array 支持的哈希算法
     */
    private const SUPPORTED_ALGORITHMS = [
        PASSWORD_BCRYPT    => 'bcrypt',
        PASSWORD_ARGON2I   => 'argon2i',
        PASSWORD_ARGON2ID  => 'argon2id',
        PASSWORD_DEFAULT   => 'default',
    ];

    /**
     * @var array 密码强度规则
     */
    private const STRENGTH_RULES = [
        'min_length'       => 8,
        'max_length'       => 128,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_number'    => true,
        'require_special'   => true,
    ];

    /**
     * @var string 特殊字符集
     */
    private const SPECIAL_CHARS = '!@#$%^&*()_+-=[]{}|;:,.<>?';

    /**
     * 使用 bcrypt 哈希密码
     *
     * @param string $password 明文密码
     * @param array  $options 选项（cost: 10-31，默认12）
     * @return string 密码哈希
     */
    public static function bcrypt(string $password, array $options = []): string
    {
        if (empty($password)) {
            throw new InvalidArgumentException('密码不能为空');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT, $options);
        if ($hash === false) {
            throw new RuntimeException('bcrypt哈希失败');
        }
        return $hash;
    }

    /**
     * 使用 Argon2id 哈希密码
     *
     * @param string $password 明文密码
     * @param array  $options 选项（memory_cost, time_cost, threads）
     * @return string 密码哈希
     */
    public static function argon2id(string $password, array $options = []): string
    {
        if (empty($password)) {
            throw new InvalidArgumentException('密码不能为空');
        }
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException('当前PHP版本不支持Argon2id');
        }
        $hash = password_hash($password, PASSWORD_ARGON2ID, $options);
        if ($hash === false) {
            throw new RuntimeException('Argon2id哈希失败');
        }
        return $hash;
    }

    /**
     * 使用 Argon2i 哈希密码
     *
     * @param string $password 明文密码
     * @param array  $options 选项
     * @return string 密码哈希
     */
    public static function argon2i(string $password, array $options = []): string
    {
        if (empty($password)) {
            throw new InvalidArgumentException('密码不能为空');
        }
        if (!defined('PASSWORD_ARGON2I')) {
            throw new RuntimeException('当前PHP版本不支持Argon2i');
        }
        $hash = password_hash($password, PASSWORD_ARGON2I, $options);
        if ($hash === false) {
            throw new RuntimeException('Argon2i哈希失败');
        }
        return $hash;
    }

    /**
     * 验证密码
     *
     * @param string $password 明文密码
     * @param string $hash     密码哈希
     * @return bool 验证结果
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 检查密码哈希是否需要重新计算
     *
     * @param string $hash 密码哈希
     * @param string|int|null $algo 目标算法
     * @param array  $options 选项
     * @return bool 是否需要重新哈希
     */
    public static function needsRehash(string $hash, string|int|null $algo = PASSWORD_DEFAULT, array $options = []): bool
    {
        return password_needs_rehash($hash, $algo ?? PASSWORD_DEFAULT, $options);
    }

    /**
     * 获取哈希信息
     *
     * @param string $hash 密码哈希
     * @return array 哈希信息
     */
    public static function getInfo(string $hash): array
    {
        return password_get_info($hash);
    }

    /**
     * 评估密码强度
     *
     * @param string $password 要评估的密码
     * @param array  $rules    自定义规则（覆盖默认规则）
     * @return array 评估结果
     */
    public static function strength(string $password, array $rules = []): array
    {
        $rules = array_merge(self::STRENGTH_RULES, $rules);
        $score = 0;
        $maxScore = 100;
        $issues = [];

        $length = mb_strlen($password);

        if ($length < $rules['min_length']) {
            $issues[] = "密码长度至少为 {$rules['min_length']} 个字符";
        } else {
            $score += min(20, $length * 2);
        }

        if ($length > $rules['max_length']) {
            $issues[] = "密码长度不能超过 {$rules['max_length']} 个字符";
        }

        if ($rules['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $issues[] = '密码必须包含大写字母';
        } else {
            $score += 15;
        }

        if ($rules['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $issues[] = '密码必须包含小写字母';
        } else {
            $score += 15;
        }

        if ($rules['require_number'] && !preg_match('/[0-9]/', $password)) {
            $issues[] = '密码必须包含数字';
        } else {
            $score += 15;
        }

        if ($rules['require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $issues[] = '密码必须包含特殊字符';
        } else {
            $score += 15;
        }

        // 额外加分项
        if ($length >= 16) $score += 10;
        if ($length >= 24) $score += 10;
        if (preg_match('/[^A-Za-z0-9\s]/', $password)) $score += 5;

        $score = min($maxScore, $score);

        $level = match (true) {
            $score >= 90 => '极强',
            $score >= 70 => '强',
            $score >= 50 => '中等',
            $score >= 30 => '弱',
            default      => '极弱',
        };

        return [
            'score'       => $score,
            'level'       => $level,
            'length'      => $length,
            'issues'      => $issues,
            'is_acceptable' => $score >= 60 && empty($issues),
        ];
    }

    /**
     * 生成强密码
     *
     * @param int    $length        密码长度，默认16
     * @param bool   $useUppercase  包含大写字母
     * @param bool   $useLowercase  包含小写字母
     * @param bool   $useNumbers    包含数字
     * @param bool   $useSpecial    包含特殊字符
     * @param string $excludeChars  排除的字符
     * @return string 生成的密码
     */
    public static function generate(int $length = 16, bool $useUppercase = true, bool $useLowercase = true, bool $useNumbers = true, bool $useSpecial = true, string $excludeChars = ''): string
    {
        if ($length < 4) {
            throw new InvalidArgumentException('密码长度至少为4');
        }

        $chars = '';
        $required = [];

        if ($useLowercase) {
            $chars .= 'abcdefghijklmnopqrstuvwxyz';
            $required[] = 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)];
        }
        if ($useUppercase) {
            $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $required[] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 25)];
        }
        if ($useNumbers) {
            $chars .= '0123456789';
            $required[] = '0123456789'[random_int(0, 9)];
        }
        if ($useSpecial) {
            $special = self::SPECIAL_CHARS;
            if ($excludeChars !== '') {
                $special = str_replace(str_split($excludeChars), '', $special);
            }
            if ($special !== '') {
                $chars .= $special;
                $required[] = $special[random_int(0, max(0, strlen($special) - 1))];
            }
        }

        if ($chars === '') {
            throw new InvalidArgumentException('至少选择一种字符类型');
        }

        // 移除排除字符
        if ($excludeChars !== '') {
            $chars = str_replace(str_split($excludeChars), '', $chars);
        }

        $password = '';
        $charLength = strlen($chars);

        // 确保包含每种类型的字符
        foreach ($required as $req) {
            $password .= $req;
        }

        // 填充剩余长度
        for ($i = count($required); $i < $length; $i++) {
            $password .= $chars[random_int(0, $charLength - 1)];
        }

        // 打乱顺序
        return str_shuffle($password);
    }

    /**
     * 生成易记密码（基于单词列表）
     *
     * @param int  $wordCount 单词数量，默认4
     * @param bool $addNumber 是否添加随机数字
     * @param bool $addSpecial 是否添加特殊字符
     * @param string $separator 分隔符
     * @return string 易记密码
     */
    public static function generateMemorable(int $wordCount = 4, bool $addNumber = true, bool $addSpecial = true, string $separator = '-'): string
    {
        $words = self::getWordList();
        $parts = [];

        for ($i = 0; $i < $wordCount; $i++) {
            $parts[] = $words[array_rand($words)];
        }

        if ($addNumber) {
            $parts[] = (string) random_int(10, 99);
        }

        $password = implode($separator, $parts);

        if ($addSpecial) {
            $specials = str_split(self::SPECIAL_CHARS);
            $password .= $specials[array_rand($specials)];
        }

        return $password;
    }

    /**
     * 检查密码是否在常见弱密码列表中
     *
     * @param string $password 要检查的密码
     * @return bool 是否是常见弱密码
     */
    public static function isCommon(string $password): bool
    {
        $common = [
            '123456', 'password', '12345678', 'qwerty', '123456789',
            'letmein', '1234567', 'football', 'iloveyou', 'admin',
            'welcome', 'monkey', 'login', 'abc123', '111111',
            '123123', 'password123', '1234', 'baseball', 'qwertyuiop',
            'princess', 'solo', 'dragon', 'sunshine', 'master',
            'photoshop', '1q2w3e4r', '696969', 'mustang', 'access',
            'shadow', 'ashley', 'bailey', 'superman', 'michael',
            'qazwsx', 'ninja', 'azerty', '000000', 'starwars',
        ];

        return in_array(strtolower($password), $common, true);
    }

    /**
     * 获取推荐的bcrypt cost值
     *
     * @param float $targetTime 目标耗时（秒），默认0.25
     * @return int 推荐的cost值
     */
    public static function recommendBcryptCost(float $targetTime = 0.25): int
    {
        $cost = 10;
        $password = random_bytes(32);

        do {
            $cost++;
            $start = microtime(true);
            password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
            $time = microtime(true) - $start;
        } while ($time < $targetTime && $cost < 31);

        return $cost;
    }

    /**
     * 内部单词列表
     */
    private static function getWordList(): array
    {
        return [
            'apple', 'beach', 'cloud', 'dance', 'eagle', 'flame', 'grape', 'heart',
            'island', 'juice', 'knife', 'lemon', 'music', 'night', 'ocean', 'piano',
            'queen', 'river', 'space', 'tiger', 'uncle', 'voice', 'water', 'zebra',
            'angel', 'brave', 'camel', 'dream', 'earth', 'fairy', 'giant', 'happy',
            'ideal', 'jolly', 'kitty', 'lucky', 'magic', 'noble', 'olive', 'pearl',
            'quiet', 'royal', 'sunny', 'truth', 'unity', 'vivid', 'witty', 'young',
        ];
    }
}

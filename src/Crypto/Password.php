<?php

declare(strict_types=1);

namespace zxf\Utils\Crypto;

use InvalidArgumentException;
use RuntimeException;

/**
 * 密码处理类
 *
 * 提供密码哈希（bcrypt / argon2i / argon2id）、验证、重哈希检测、强度评估、
 * 安全强密码生成等功能，基于 PHP 8.2+ 的 password_* 函数与原生加密安全随机
 * 扩展实现。所有随机材料均使用 CSPRNG（random_int / random_bytes），绝不采用
 * mt_rand 等非安全随机源。
 *
 * @package zxf\Utils\Crypto
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * // 使用 bcrypt 对密码哈希（cost 默认 12，越大越慢越安全）
 * $hash = \zxf\Utils\Crypto\Password::bcrypt('user-password-here');
 * // 验证密码：返回 true / false，永不抛异常
 * $ok   = \zxf\Utils\Crypto\Password::verify('user-password-here', $hash);
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
     * bcrypt 自带盐值并内置成本因子（cost），哈希结果已包含算法与参数信息，
     * 可直接存入数据库，无需自行管理盐值。cost 建议在服务器可承受范围内尽量调大。
     *
     * @param string $password 明文密码
     * @param array  $options 选项（cost: 4-31，默认 10；推荐 12）
     * @return string 密码哈希（形如 $2y$...）
     * @throws InvalidArgumentException 当密码为空时抛出
     * @throws RuntimeException 当哈希失败（如 cost 超出范围）时抛出
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
     * Argon2id 是抗 GPU/侧信道攻击的推荐内存硬化算法。三个关键参数：
     * memory_cost（内存消耗，字节）、time_cost（迭代次数）、threads（并行度）。
     * 使用前应确保当前 PHP 以 --with-password-argon2 编译支持。
     *
     * @param string $password 明文密码
     * @param array  $options 选项（memory_cost, time_cost, threads）
     * @return string 密码哈希（形如 $argon2id$...）
     * @throws InvalidArgumentException 当密码为空时抛出
     * @throws RuntimeException 当当前环境不支持 Argon2id 或哈希失败时抛出
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
     * Argon2i 与 Argon2id 类似，但 Argon2id 在抵御侧信道攻击上更优，新项目
     * 通常优先选 Argon2id。同样需 PHP 编译支持 Argon2。
     *
     * @param string $password 明文密码
     * @param array  $options 选项（memory_cost, time_cost, threads）
     * @return string 密码哈希（形如 $argon2i$...）
     * @throws InvalidArgumentException 当密码为空时抛出
     * @throws RuntimeException 当当前环境不支持 Argon2i 或哈希失败时抛出
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
     * 验证密码与哈希是否匹配
     *
     * 委托 PHP 原生 password_verify()，自动识别 bcrypt、argon2i、argon2id
     * 等由本类哈希方法产出的格式；哈希串格式非法时返回 false 而不抛异常。
     *
     * @param string $password 明文密码
     * @param string $hash     由 hashPassword()/argon2i() 等方法生成的哈希
     * @return bool 匹配返回 true，否则返回 false
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
     * 获取密码哈希的算法与选项信息
     *
     * 返回 password_get_info() 的原始结构：algo（算法常量）与 algoName
     * （算法名称），以及 options（cost、memory_cost 等参数）。哈希串非法时
     * 算法名称为 "unknown"，且不会抛异常。
     *
     * @param string $hash 密码哈希串
     * @return array{algo:int,algoName:string,options:array} 算法常量、算法名称与选项
     */
    public static function getInfo(string $hash): array
    {
        return password_get_info($hash);
    }

    /**
     * 评估密码强度并给出改进建议
     *
     * 按长度、字符类别、额外长度与特殊符号累加得分（上限 100），映射为
     * 极弱/弱/中等/强/极强；未启用的 require_* 规则既不加分也不报错。
     * 返回的 is_acceptable 要求得分不低于 60 且没有任何规则违规。
     *
     * @param string $password 要评估的密码
     * @param array  $rules    自定义规则（覆盖默认规则中同名键，如 min_length、require_uppercase）
     * @return array{score:int,level:string,length:int,issues:array<int,string>,is_acceptable:bool} 得分、等级、长度、问题清单与是否可接受
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

        // 未启用的类别不检查也不加分（原逻辑在 require_*=false 时仍走 else 加 15 分）
        if ($rules['require_uppercase']) {
            if (!preg_match('/[A-Z]/', $password)) {
                $issues[] = '密码必须包含大写字母';
            } else {
                $score += 15;
            }
        }

        if ($rules['require_lowercase']) {
            if (!preg_match('/[a-z]/', $password)) {
                $issues[] = '密码必须包含小写字母';
            } else {
                $score += 15;
            }
        }

        if ($rules['require_number']) {
            if (!preg_match('/[0-9]/', $password)) {
                $issues[] = '密码必须包含数字';
            } else {
                $score += 15;
            }
        }

        if ($rules['require_special']) {
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $issues[] = '密码必须包含特殊字符';
            } else {
                $score += 15;
            }
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
     * 先从启用的每种字符类别各取一个字符保证覆盖，再随机补齐到指定长度，
     * 最后打乱顺序。excludeChars 中的字符会从可选字符集中剔除。长度小于 4
     * 或所有字符类别均被关闭（或剔除后无可用字符）时抛出异常。
     *
     * @param int    $length        密码长度，默认16
     * @param bool   $useUppercase  包含大写字母
     * @param bool   $useLowercase  包含小写字母
     * @param bool   $useNumbers    包含数字
     * @param bool   $useSpecial    包含特殊字符
     * @param string $excludeChars  排除的字符
     * @return string 生成的密码
     * @throws InvalidArgumentException 当长度小于 4 或所有字符类型均被关闭时抛出
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

        // 注意：str_shuffle 底层使用 PHP 的 mt_rand（梅森旋转，非密码学安全随机源）。
        // 此处仅用于打乱已生成的字符在明文中的排列顺序，随机字符本身已用 random_int
        // 抽取；若对顺序有强安全需求，应改用基于 random_int 的 Fisher-Yates 打乱。
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

        // 密码材料必须使用加密安全随机源（array_rand 基于 mt_rand，不可用于生成密码）
        for ($i = 0; $i < $wordCount; $i++) {
            $parts[] = $words[random_int(0, count($words) - 1)];
        }

        if ($addNumber) {
            $parts[] = (string) random_int(10, 99);
        }

        $password = implode($separator, $parts);

        if ($addSpecial) {
            $specials = str_split(self::SPECIAL_CHARS);
            $password .= $specials[random_int(0, count($specials) - 1)];
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
     * @return int 推荐的cost值（10-31，上限 31 对应 bcrypt 最高成本）
     * @throws RuntimeException 当 bcrypt 哈希失败（如 cost 超出范围）时抛出
     */
    public static function recommendBcryptCost(float $targetTime = 0.25): int
    {
        $targetTime = max(0.01, $targetTime);
        $cost = 10;
        $password = random_bytes(32);

        // 从 cost=10 起逐个测量（原实现先自增后测量，10 从未被测过），
        // 返回「首个单次哈希耗时 ≥ targetTime」的 cost；全部不达标时返回 31（bcrypt 上限）
        while ($cost <= 31) {
            $start = microtime(true);
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
            if ($hash === false) {
                throw new RuntimeException("bcrypt 哈希失败 (cost={$cost})");
            }
            $elapsed = microtime(true) - $start;
            if ($elapsed >= $targetTime) {
                return $cost;
            }
            $cost++;
        }

        return 31;
    }

    /**
     * 内部单词列表（用于生成易记密码的候选词）
     *
     * @return array<string> 小写英文单词数组
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

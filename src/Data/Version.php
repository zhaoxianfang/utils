<?php

declare(strict_types=1);

namespace zxf\Utils\Data;

use InvalidArgumentException;

/**
 * 语义化版本号（SemVer）工具类
 *
 * 支持版本号解析、比较、兼容性判断和范围匹配。
 * 遵循 SemVer 2.0.0 规范。
 *
 * @package Data
 * @version 1.0.0
 * @license MIT
 */
class Version
{
    /** @var int 主版本号 */
    public readonly int $major;

    /** @var int 次版本号 */
    public readonly int $minor;

    /** @var int 修订号 */
    public readonly int $patch;

    /** @var string 预发布标识 */
    public readonly string $preRelease;

    /** @var string 构建元数据 */
    public readonly string $build;

    /** @var string 原始版本字符串 */
    public readonly string $raw;

    public function __construct(string $version)
    {
        $this->raw = ltrim($version, 'v');
        $this->parse($this->raw);
    }

    /**
     * 快速创建实例
     */
    public static function of(string $version): self
    {
        return new self($version);
    }

    /**
     * 比较两个版本号
     *
     * @return int -1: $a < $b, 0: 相等, 1: $a > $b
     */
    public static function compare(string|self $a, string|self $b): int
    {
        $va = $a instanceof self ? $a : new self($a);
        $vb = $b instanceof self ? $b : new self($b);

        foreach (['major', 'minor', 'patch'] as $part) {
            if ($va->$part !== $vb->$part) {
                return $va->$part <=> $vb->$part;
            }
        }

        // 预发布版本号低于正式版本
        if ($va->preRelease !== '' && $vb->preRelease === '') {
            return -1;
        }
        if ($va->preRelease === '' && $vb->preRelease !== '') {
            return 1;
        }
        if ($va->preRelease !== '' && $vb->preRelease !== '') {
            return self::comparePreRelease($va->preRelease, $vb->preRelease);
        }

        return 0;
    }

    /**
     * 判断 $a 是否等于 $b
     */
    public static function eq(string|self $a, string|self $b): bool
    {
        return self::compare($a, $b) === 0;
    }

    /**
     * 判断 $a 是否大于 $b
     */
    public static function gt(string|self $a, string|self $b): bool
    {
        return self::compare($a, $b) > 0;
    }

    /**
     * 判断 $a 是否大于等于 $b
     */
    public static function gte(string|self $a, string|self $b): bool
    {
        return self::compare($a, $b) >= 0;
    }

    /**
     * 判断 $a 是否小于 $b
     */
    public static function lt(string|self $a, string|self $b): bool
    {
        return self::compare($a, $b) < 0;
    }

    /**
     * 判断 $a 是否小于等于 $b
     */
    public static function lte(string|self $a, string|self $b): bool
    {
        return self::compare($a, $b) <= 0;
    }

    /**
     * 判断是否满足版本范围约束
     *
     * 支持约束：>=1.0, <2.0, ~1.2.3, ^1.2.3, 1.0 - 2.0, 1.x, *
     *
     * @param string $constraint 版本约束表达式
     * @return bool
     */
    public function satisfies(string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        // 范围表达式: 1.0.0 - 2.0.0
        if (str_contains($constraint, ' - ')) {
            [$min, $max] = array_map('trim', explode(' - ', $constraint, 2));
            return self::gte($this->raw, $min) && self::lte($this->raw, $max);
        }

        // 逗号分隔的复合约束
        if (str_contains($constraint, ',')) {
            foreach (array_map('trim', explode(',', $constraint)) as $sub) {
                if (!$this->satisfies($sub)) {
                    return false;
                }
            }
            return true;
        }

        // 波浪线 ~1.2.3 表示 >=1.2.3, <1.3.0
        if (str_starts_with($constraint, '~')) {
            $base = new self(substr($constraint, 1));
            $nextMinor = "{$base->major}." . ($base->minor + 1) . '.0';
            return self::gte($this->raw, $base->raw) && self::lt($this->raw, $nextMinor);
        }

        // 插入符 ^1.2.3 表示 >=1.2.3, <2.0.0（若 major > 0）
        if (str_starts_with($constraint, '^')) {
            $base = new self(substr($constraint, 1));
            if ($base->major > 0) {
                $nextMajor = ($base->major + 1) . '.0.0';
                return self::gte($this->raw, $base->raw) && self::lt($this->raw, $nextMajor);
            }
            if ($base->minor > 0) {
                $nextMinor = "0." . ($base->minor + 1) . '.0';
                return self::gte($this->raw, $base->raw) && self::lt($this->raw, $nextMinor);
            }
            return self::gte($this->raw, $base->raw);
        }

        // 比较运算符
        foreach (['>=', '<=', '>', '<', '='] as $op) {
            if (str_starts_with($constraint, $op)) {
                $target = trim(substr($constraint, strlen($op)));
                return match ($op) {
                    '>=' => self::gte($this->raw, $target),
                    '<=' => self::lte($this->raw, $target),
                    '>'  => self::gt($this->raw, $target),
                    '<'  => self::lt($this->raw, $target),
                    '='  => self::eq($this->raw, $target),
                };
            }
        }

        // x 通配符: 1.2.x, 1.x
        if (str_contains($constraint, '.x') || str_contains($constraint, '.X') || str_contains($constraint, '.*')) {
            $parts = explode('.', str_replace(['x', 'X', '*'], '0', $constraint));
            $major = (int) ($parts[0] ?? 0);
            $minor = (int) ($parts[1] ?? 0);
            if (count($parts) === 2 || str_ends_with($constraint, '.x') || str_ends_with($constraint, '.X') || str_ends_with($constraint, '.*')) {
                return $this->major === $major && $this->minor === $minor;
            }
        }

        // 精确匹配
        return self::eq($this->raw, $constraint);
    }

    /**
     * 递增版本号
     *
     * @param string $type major|minor|patch
     * @return static 新的版本实例
     */
    public function increment(string $type = 'patch'): self
    {
        $major = $this->major;
        $minor = $this->minor;
        $patch = $this->patch;

        match ($type) {
            'major' => [$major++, $minor = 0, $patch = 0],
            'minor' => [$minor++, $patch = 0],
            'patch' => [$patch++],
            default => throw new InvalidArgumentException('类型必须是 major、minor 或 patch'),
        };

        return new self("{$major}.{$minor}.{$patch}");
    }

    /**
     * 转换为字符串
     */
    public function __toString(): string
    {
        $str = "{$this->major}.{$this->minor}.{$this->patch}";
        if ($this->preRelease !== '') {
            $str .= "-{$this->preRelease}";
        }
        if ($this->build !== '') {
            $str .= "+{$this->build}";
        }
        return $str;
    }

    private function parse(string $version): void
    {
        $pattern = '/^(\d+)\.(\d+)\.(\d+)(?:-([a-zA-Z0-9.]+))?(?:\+([a-zA-Z0-9.]+))?$/';
        if (!preg_match($pattern, $version, $m)) {
            throw new InvalidArgumentException("无效的 SemVer 版本号: {$version}");
        }

        $this->major = (int) $m[1];
        $this->minor = (int) $m[2];
        $this->patch = (int) $m[3];
        $this->preRelease = $m[4] ?? '';
        $this->build = $m[5] ?? '';
    }

    /**
     * 比较预发布版本标识符
     */
    private static function comparePreRelease(string $a, string $b): int
    {
        $aParts = explode('.', $a);
        $bParts = explode('.', $b);
        $max = max(count($aParts), count($bParts));

        for ($i = 0; $i < $max; $i++) {
            $av = $aParts[$i] ?? null;
            $bv = $bParts[$i] ?? null;

            if ($av === null && $bv !== null) {
                return -1;
            }
            if ($av !== null && $bv === null) {
                return 1;
            }

            $aIsNum = ctype_digit($av);
            $bIsNum = ctype_digit($bv);

            if ($aIsNum && $bIsNum) {
                $cmp = (int) $av <=> (int) $bv;
                if ($cmp !== 0) {
                    return $cmp;
                }
            } elseif ($aIsNum) {
                return -1;
            } elseif ($bIsNum) {
                return 1;
            } else {
                $cmp = strcmp($av, $bv);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
        }

        return 0;
    }
}

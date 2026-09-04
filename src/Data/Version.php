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
     * 支持完整的 Composer / npm 约束语法：
     *  - `||` 分隔的「或」组：`1.2.0 || >=1.4.0`（任一组满足即满足）
     *  - 空格或逗号分隔的「且」条件：`>=1.0.0 <2.0.0`、`>=1.0,<2.0`
     *  - 范围：`1.0.0 - 2.0.0`
     *  - 波浪号：`~1.2.3`（>=1.2.3 <1.3.0）
     *  - 插入符：`^1.2.3`（>=1.2.3 <2.0.0）
     *  - 通配符：`1.2.x`、`1.x`、`*`
     *  - 比较符：`>=1.0`、`<=2.0`、`>1.0`、`<2.0`、`=1.0.0`
     *  - 精确匹配：`1.2.3`
     *
     * 解析顺序与 Composer 一致：先按 `||` 切成或组，组内按空白/逗号切成且条件。
     *
     * @param string $constraint 版本约束表达式
     * @return bool
     */
    public function satisfies(string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*' || $constraint === 'x' || $constraint === 'X') {
            return true;
        }

        // 先按 `||` 切分为「或」组：任一组满足即视为满足
        foreach (self::splitOrGroups($constraint) as $group) {
            if ($this->satisfiesAndGroup($group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 按 `||` 切分「或」组
     *
     * @return list<string>
     */
    private static function splitOrGroups(string $constraint): array
    {
        // 兼容 `||`、`|` 以及两侧无空格的写法（如 "1.0.0||^2.0"）
        $parts = preg_split('/\s*\|\|?\s*/', $constraint, -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [$constraint] : array_values($parts);
    }

    /**
     * 判断单个「或」组（内部为「且」条件）是否满足
     */
    private function satisfiesAndGroup(string $group): bool
    {
        $group = trim($group);

        if ($group === '' || $group === '*' || $group === 'x' || $group === 'X') {
            return true;
        }

        // 范围表达式 `1.0.0 - 2.0.0`：必须优先于「按空白切分」处理，
        // 否则会被拆成 ["1.0.0", "-", "2.0.0"] 而解析失败。
        if (preg_match('/^(\S+)\s+-\s+(\S+)$/', $group, $m) === 1) {
            return self::gte($this->raw, $m[1]) && self::lte($this->raw, $m[2]);
        }

        // 空白或逗号分隔的复合「且」条件（如 ">=1.0.0 <2.0.0"、">=1.0,<2.0"）
        $parts = preg_split('/[\s,]+/', $group, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts !== false && count($parts) > 1) {
            foreach ($parts as $part) {
                if (! $this->satisfiesAndGroup($part)) {
                    return false;
                }
            }

            return true;
        }

        return $this->satisfiesSingle($group);
    }

    /**
     * 判断单个约束条件是否满足
     */
    private function satisfiesSingle(string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        // 波浪线 ~：上界由「最后一段被明确指定的段」决定（Composer 语义）：
        //   ~1.2.3 → >=1.2.3 <1.3.0（指定到 patch，上界为下一 minor）
        //   ~1.2   → >=1.2.0 <2.0.0（指定到 minor，上界为下一 major）
        //   ~1     → >=1.0.0 <2.0.0
        if (str_starts_with($constraint, '~')) {
            $raw = substr($constraint, 1);
            $segments = count(self::versionSegments($raw));
            $base = new self(self::normalizePartial($raw));

            $upper = $segments >= 3
                ? "{$base->major}." . ($base->minor + 1) . '.0'
                : ($base->major + 1) . '.0.0';

            return self::gte($this->raw, $base->raw) && self::lt($this->raw, $upper);
        }

        // 插入符 ^：不改变最左侧非零段（Composer 语义）
        //   ^1.2.3 / ^1.2 / ^1 → >=X.Y.Z <2.0.0
        //   ^0.3   → >=0.3.0 <0.4.0（major 为 0 时锁 minor）
        //   ^0.0.3 → >=0.0.3 <0.0.4
        if (str_starts_with($constraint, '^')) {
            $base = new self(self::normalizePartial(substr($constraint, 1)));

            if ($base->major > 0) {
                return self::gte($this->raw, $base->raw)
                    && self::lt($this->raw, ($base->major + 1) . '.0.0');
            }
            if ($base->minor > 0) {
                return self::gte($this->raw, $base->raw)
                    && self::lt($this->raw, "0." . ($base->minor + 1) . '.0');
            }

            return self::gte($this->raw, $base->raw)
                && self::lt($this->raw, "0.0." . ($base->patch + 1));
        }

        // 比较运算符。
        // 顺序要点：`<=`/`<>` 必须排在 `<` 之前，`>=` 排在 `>` 之前，
        // 否则 "<=1.0" 会被 `<` 先匹配成 "<" + "=1.0"。
        foreach (['>=', '<=', '!=', '<>', '>', '<', '='] as $op) {
            if (str_starts_with($constraint, $op)) {
                // 约束中的版本号允许省略段（如 ">=1.0"），补齐为完整三段再比较
                $target = self::normalizePartial(trim(substr($constraint, strlen($op))));

                return match ($op) {
                    '>='      => self::gte($this->raw, $target),
                    '<='      => self::lte($this->raw, $target),
                    '>',      => self::gt($this->raw, $target),
                    '<'       => self::lt($this->raw, $target),
                    '=',      => self::eq($this->raw, $target),
                    '!=', '<>' => ! self::eq($this->raw, $target),
                };
            }
        }

        // x 通配符: 1.2.x、1.x、*.x、* 等。
        // 仅校验约束中“明确指定的段”，被通配的段（x/*）不参与比较。
        if (str_contains($constraint, '.x') || str_contains($constraint, '.X') || str_contains($constraint, '.*')
            || $constraint === 'x' || $constraint === 'X' || $constraint === '*') {
            $segments = explode('.', $constraint);
            $majorMatch = true;
            $minorMatch = true;
            $patchMatch = true;

            // 第 1 段（主版本）
            if (isset($segments[0]) && !in_array($segments[0], ['x', 'X', '*'], true)) {
                $majorMatch = $this->major === (int) $segments[0];
            }
            // 第 2 段（次版本）
            if (isset($segments[1]) && !in_array($segments[1], ['x', 'X', '*'], true)) {
                $minorMatch = $this->minor === (int) $segments[1];
            }
            // 第 3 段（修订号）：仅当约束显式给出非通配的第三段才校验
            if (isset($segments[2]) && !in_array($segments[2], ['x', 'X', '*'], true)) {
                $patchMatch = $this->patch === (int) $segments[2];
            }

            return $majorMatch && $minorMatch && $patchMatch;
        }

        // 省略段的裸版本号按 npm 语义视为通配范围：
        //   "1.2"  等价于 "1.2.x"（1.2.0 ~ 1.2.∞）
        //   "1"    等价于 "1.x"（1.0.0 ~ 1.∞.∞）
        // 只有完整三段式（"1.2.3"）才按精确匹配处理。
        if (count(self::versionSegments($constraint)) < 3) {
            // 补出的 "1.2.*" 已满足三段，不会再次进入本分支造成递归
            return $this->satisfiesSingle($constraint . '.*');
        }

        // 精确匹配
        return self::eq($this->raw, self::normalizePartial($constraint));
    }

    /**
     * 将约束中允许省略段的版本号补齐为完整的 X.Y.Z 三段式
     *
     * Composer / npm 的约束普遍允许 "1"、"1.2" 这类写法，
     * 其语义等价于 "1.0.0"、"1.2.0"；比较前必须先补齐，
     * 否则会被 parse() 判为非法版本号。
     *
     * @param string $version 形如 "1"、"1.2"、"1.2.3"、"v1.2"、"1.2.3-beta"
     * @return string 补齐后的版本号（保留预发布与构建元数据）
     */
    private static function normalizePartial(string $version): string
    {
        $version = trim($version);

        // 保留预发布与构建元数据，只处理核心数字部分
        $suffix = '';
        if (preg_match('/^([^-+]*)([-+].*)$/', $version, $m) === 1) {
            $version = $m[1];
            $suffix = $m[2];
        }

        $segments = explode('.', trim($version, '.'));
        $numeric = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            // 遇到通配符段就停止（通配符由 satisfiesSingle 的 x 分支单独处理）
            if (in_array($segment, ['x', 'X', '*'], true)) {
                break;
            }
            $numeric[] = ctype_digit($segment) ? (int) $segment : 0;
        }

        // 补齐到三段，不足补 0；多余段忽略（SemVer 只认三段）
        $major = $numeric[0] ?? 0;
        $minor = $numeric[1] ?? 0;
        $patch = $numeric[2] ?? 0;

        return "{$major}.{$minor}.{$patch}" . $suffix;
    }

    /**
     * 拆分版本号的数字段（忽略预发布与构建元数据）
     *
     * @return list<string>
     */
    private static function versionSegments(string $version): array
    {
        $core = preg_replace('/[-+].*$/', '', trim($version)) ?? $version;
        $segments = array_values(array_filter(explode('.', $core), static fn (string $s): bool => $s !== ''));

        return $segments;
    }

    /**
     * 递增版本号
     *
     * @param string $type major|minor|patch
     * @return static 新的版本实例
     */
    public function increment(string $type = 'patch'): self
    {
        // 直接计算并返回，避免在 match 的分支表达式中做赋值
        // （原写法依赖数组字面量从左到右的求值顺序，可读性差且易被误改）
        return match ($type) {
            'major' => new self(($this->major + 1) . '.0.0'),
            'minor' => new self($this->major . '.' . ($this->minor + 1) . '.0'),
            'patch' => new self("{$this->major}.{$this->minor}." . ($this->patch + 1)),
            default => throw new InvalidArgumentException('类型必须是 major、minor 或 patch'),
        };
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

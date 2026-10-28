<?php

/**
 * ============================================================================
 * Cron 表达式解析器
 * ============================================================================
 *
 * 解析 Cron 表达式并计算下一次执行时间。
 * 支持标准 5 字段格式（分 时 日 月 周）。
 * 注意：本实现为轻量解析器，不支持 6 字段（带秒）与 @reboot 等非标准宏；
 *
 * @package     zxf\Utils\Date
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Date;

use InvalidArgumentException;

/**
 * Cron 表达式解析器
 *
 * 解析标准 5 字段 Cron 表达式（分 时 日 月 周），计算下一次/未来多次执行时间，
 * 并支持表达式有效性校验与时间匹配判断。遵循标准 cron「日与周均被限定时为 OR」
 * 的语义，适用于定时任务调度等场景。
 */
final class CronExpression
{
    private array $fields = [];

    private static array $fieldRanges = [
        0 => [0, 59],   // 分钟
        1 => [0, 23],   // 小时
        2 => [1, 31],   // 日期
        3 => [1, 12],   // 月份
        4 => [0, 6],    // 星期（0=周日）
    ];

    private static array $monthAliases = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
        'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private static array $weekAliases = [
        'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3,
        'thu' => 4, 'fri' => 5, 'sat' => 6,
    ];

    /**
     * 解析 Cron 表达式并构造实例
     *
     * @param string $expression 5 字段 Cron 表达式（分 时 日 月 周，空格分隔）
     * @throws InvalidArgumentException 当表达式为空或字段数不正确时抛出
     */
    public function __construct(string $expression)
    {
        $this->parse($expression);
    }

    /**
     * 创建 CronExpression 实例
     *
     * @param string $expression 5 字段 Cron 表达式（分 时 日 月 周，空格分隔）
     * @return static
     */
    public static function make(string $expression): self
    {
        return new self($expression);
    }

    /**
     * 判断表达式是否有效
     *
     * 内部直接尝试构造实例，捕获解析阶段的 InvalidArgumentException；
     * 字段数不对、含非法字符、数值越界或步长非法等都会被判为无效，
     * 方法本身不会抛异常。
     *
     * @param string $expression 待校验的 5 字段 Cron 表达式
     * @return bool 表达式合法返回 true，否则返回 false
     */
    public static function isValid(string $expression): bool
    {
        try {
            new self($expression);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * 获取下一次执行时间
     *
     * 采用「月 → 日 → 小时/分钟」的跳跃式扫描：候选日期之外的月份/日期直接整月/整日跳过，
     * 命中候选日后在其内部直接定位匹配的小时与分钟，无需逐分钟穷举。
     * 扫描上限为 8 年，闰日（如 `0 0 29 2 *`）这类稀疏表达式也能命中；8 年内无匹配返回 null。
     *
     * 基准时刻会被清零秒并前进 1 分钟，因此返回的始终是严格晚于基准时刻的整分时刻；
     * 未传 $currentTime 时以「当前时间」为基准，返回结果沿用基准时刻的时区。
     *
     * @param \DateTimeInterface|null $currentTime 基准时刻，null 表示当前时间
     * @return \DateTimeImmutable|null 下一次执行时刻，8 年内无匹配返回 null
     */
    public function getNextRunDate(?\DateTimeInterface $currentTime = null): ?\DateTimeImmutable
    {
        // 兼容 PHP 8.2：createFromInterface 自 8.3 才提供，改用 setTimestamp + setTimezone 复刻同一时刻与时区
        $current = $currentTime
            ? (new \DateTimeImmutable())
                ->setTimestamp($currentTime->getTimestamp())
                ->setTimezone($currentTime->getTimezone())
            : new \DateTimeImmutable();
        // 秒清零并前进 1 分钟：下一个候选时刻必须是 > 当前时刻的整分
        $current = $current->setTime((int) $current->format('H'), (int) $current->format('i'), 0);
        $current = $current->add(new \DateInterval('PT1M'));

        for ($days = 0; $days < 366 * 8; $days++) {
            // 月份不在候选列表：整月跳过（下月 1 日 00:00）
            if (! in_array((int) $current->format('m'), $this->fields[3], true)) {
                $current = $current->modify('first day of next month')->setTime(0, 0, 0);
                continue;
            }

            // 日期不匹配：整日跳过
            if (! $this->dayMatches($current)) {
                $current = $current->modify('tomorrow')->setTime(0, 0, 0);
                continue;
            }

            // 当日有效：定位当天第一个 >= 当前时刻的匹配时刻
            $found = $this->findNextTimeOnDay($current);
            if ($found !== null) {
                return $found;
            }

            $current = $current->modify('tomorrow')->setTime(0, 0, 0);
        }

        return null;
    }

    /**
     * 获取未来 N 次执行时间
     *
     * 以第一次的结果为基准逐次向后推进 1 分钟再取下一次，保证结果严格递增、
     * 互不重复。若某次计算在 8 年扫描窗口内无匹配，会提前结束循环，
     * 因此返回数组长度可能小于 $count（含 $count 为 0 或负数时返回空数组）。
     *
     * @param int                     $count       需要的执行次数，默认 5
     * @param \DateTimeInterface|null $currentTime 基准时刻，null 表示当前时间
     * @return array<int, \DateTimeImmutable> 按时间升序排列的执行时刻列表
     */
    public function getMultipleRunDates(int $count = 5, ?\DateTimeInterface $currentTime = null): array
    {
        $dates = [];
        $current = $currentTime
            ? (new \DateTimeImmutable())
                ->setTimestamp($currentTime->getTimestamp())
                ->setTimezone($currentTime->getTimezone())
            : new \DateTimeImmutable();

        for ($i = 0; $i < $count; $i++) {
            $next = $this->getNextRunDate($current);
            if ($next === null) {
                break;
            }
            $dates[] = $next;
            $current = $next->add(new \DateInterval('PT1M'));
        }

        return $dates;
    }

    /**
     * 判断给定时间是否匹配 Cron 表达式
     *
     * 依次比对分钟、小时、月份，最后交给 dayMatches() 处理「日/周」字段；
     * 秒数与毫秒不参与判断（按整分匹配）。
     *
     * 【重要】标准 cron 语义：当「日」与「星期」两个字段都被限定（均非 *）时，
     * 二者是 OR 关系（任一命中即执行），而不是 AND。本方法已按此实现，
     * 与 getNextRunDate() 使用同一套 dayMatches() 判定，保证二者结果一致。
     *
     * @param \DateTimeInterface $dateTime 待判断的时刻，按该时刻自身时区取字段
     * @return bool 完全匹配返回 true，否则返回 false
     */
    public function isMatching(\DateTimeInterface $dateTime): bool
    {
        $date = (new \DateTimeImmutable())
            ->setTimestamp($dateTime->getTimestamp())
            ->setTimezone($dateTime->getTimezone());

        if (! in_array((int) $date->format('i'), $this->fields[0], true)) {
            return false;
        }
        if (! in_array((int) $date->format('H'), $this->fields[1], true)) {
            return false;
        }
        if (! in_array((int) $date->format('m'), $this->fields[3], true)) {
            return false;
        }

        return $this->dayMatches($date);
    }

    /**
     * 判断「日」与「星期」是否匹配（含标准 cron 的 OR 语义）
     *
     * 取值：日为月内第几天（1~31），星期为 0~6（0 表示周日）。
     * 判定顺序：两个字段都等价于 * 时恒为 true；仅日字段为 * 时只看星期；
     * 仅星期字段为 * 时只看日；两者都被限定时取 OR（任一命中即匹配）。
     *
     * @param \DateTimeInterface $date 待判断的时刻
     * @return bool 日/周字段判定通过返回 true，否则返回 false
     */
    private function dayMatches(\DateTimeInterface $date): bool
    {
        $dayOfMonth = (int) $date->format('d');
        $dayOfWeek  = (int) $date->format('w'); // 0=周日
        $domMatch   = in_array($dayOfMonth, $this->fields[2], true);
        $dowMatch   = in_array($dayOfWeek, $this->fields[4], true);
        $domAll     = $this->isFullRange(2);
        $dowAll     = $this->isFullRange(4);

        if ($domAll && $dowAll) {
            return true;
        }
        if ($domAll) {
            return $dowMatch;
        }
        if ($dowAll) {
            return $domMatch;
        }

        // 两个字段均被限定时为 OR（标准 cron 行为）
        return $domMatch || $dowMatch;
    }

    /**
     * 某字段展开后是否覆盖完整范围（等价于 *）
     *
     * 展开结果已经过去重排序，因此只要元素个数等于该字段的取值范围宽度，
     * 即可判定为完整覆盖；`*`、`0-59`、星号加步长 1 这类写法都会被视为完整范围。
     *
     * @param int $index 字段下标，0=分 1=时 2=日 3=月 4=周
     * @return bool 覆盖完整范围返回 true，否则返回 false
     */
    private function isFullRange(int $index): bool
    {
        [$min, $max] = self::$fieldRanges[$index];

        return count($this->fields[$index]) === $max - $min + 1;
    }

    /**
     * 解析 Cron 表达式
     *
     * trim 后按任意空白切分为 5 段，逐段调用 expandField() 展开为升序去重的
     * 整数数组并按下标存入 $fields。表达式为空或段数不是 5 时抛异常。
     *
     * @param string $expression 5 字段 Cron 表达式（分 时 日 月 周，空格分隔）
     * @return void
     *
     * @throws InvalidArgumentException 表达式为空、字段数不是 5 或字段内容非法时抛出
     */
    private function parse(string $expression): void
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new InvalidArgumentException('Cron 表达式不能为空');
        }
        $parts = preg_split('/\s+/', $expression);

        if ($parts === false || count($parts) !== 5) {
            throw new InvalidArgumentException("Cron 表达式必须是 5 个字段（分 时 日 月 周），用空格分隔: {$expression}");
        }

        foreach ($parts as $i => $part) {
            $this->fields[$i] = $this->expandField($part, $i);
        }
    }

    /**
     * 展开单个字段
     *
     * 使用统一正则严格校验：只允许 `*`、单个数字、`N-M` 范围，三者均可选带 `/步长`。
     * 非法字符（字母残留、`1x`、空分隔项、`5-10-3` 等多连字符）一律抛异常，
     * 绝不再被 (int) 静默吞掉（如分钟字段 "x" 此前会被当成 0 静默接受）。
     *
     * 星期字段额外允许 7（等价周日），展开后统一归一化为 0；结果会去重并升序排序。
     *
     * @param string $field 单个字段的原始文本，如 "*"、"1-5/2"、"mon-fri"
     * @param int    $index 字段下标，0=分 1=时 2=日 3=月 4=周
     * @return array<int, int> 展开后的升序去重整数列表
     *
     * @throws InvalidArgumentException 出现空分隔项、非法字符、步长非正、起始大于结束或数值越界时抛出
     */
    private function expandField(string $field, int $index): array
    {
        $range = self::$fieldRanges[$index];
        // 标准 cron 的星期字段允许 7（等价于 0=周日）：校验/展开时放宽上限到 7，
        // 展开完成后统一把 7 归一化为 0
        $isDOW = $index === 4;
        $values = [];

        // 处理逗号分隔的多个值
        $segments = explode(',', $field);
        foreach ($segments as $segment) {
            $segment = $this->replaceAliases(trim($segment), $index);
            if ($segment === '') {
                throw new InvalidArgumentException("字段 {$index} 含空分隔项（检查是否出现连续逗号）");
            }

            if (! preg_match('/^(?:\*|(\d+)(?:-(\d+))?)(?:\/(\d+))?$/', $segment, $m)) {
                throw new InvalidArgumentException("字段 {$index} 含非法值: {$segment}");
            }

            $isStar = $segment === '*';
            $start  = $isStar ? $range[0] : (int) $m[1];
            if ($isStar) {
                $end = $range[1];
            } else {
                $end = ($m[2] ?? '') === '' ? $start : (int) $m[2];
            }
            $step = ($m[3] ?? '') === '' ? 1 : (int) $m[3];

            if ($step < 1) {
                throw new InvalidArgumentException("步长必须大于 0: {$segment}");
            }
            if ($start > $end) {
                throw new InvalidArgumentException("范围起始不能大于结束（{$start} > {$end}）: {$segment}");
            }
            $max = $isDOW ? 7 : $range[1];
            if ($start < $range[0] || $end > $max) {
                throw new InvalidArgumentException("字段 {$index} 的值越界，需在 [{$range[0]}, {$max}] 内: {$segment}");
            }

            for ($value = $start; $value <= $end; $value += $step) {
                $values[] = $value;
            }
        }

        if ($isDOW) {
            // 7 等价于周日（0），标准 cron 语义
            $values = array_map(static fn (int $v): int => $v === 7 ? 0 : $v, $values);
        }

        $values = array_unique($values);
        sort($values);

        return $values;
    }

    /**
     * 替换月份和星期的别名
     *
     * 别名替换为「段内逐字母块」处理：既支持整段（`jan`），
     * 也支持范围/步长形式（`mon-fri`、`jan-dec/2`）中的别名端点。
     * 无法识别的字母块原样保留，后续会被 expandField 的正则拦截并抛异常。
     *
     * 仅对月份字段（下标 3）与星期字段（下标 4）生效，其余字段及空段原样返回；
     * 匹配以 3 个字母为一组、不区分大小写。
     *
     * @param string $segment 单个字段内的一个逗号分段（已 trim）
     * @param int    $index   字段下标，0=分 1=时 2=日 3=月 4=周
     * @return string 别名替换后的文本，非月/周字段或无别名时与入参相同
     */
    private function replaceAliases(string $segment, int $index): string
    {
        $aliases = $index === 3 ? self::$monthAliases : ($index === 4 ? self::$weekAliases : null);
        if ($aliases === null || $segment === '') {
            return $segment;
        }

        return preg_replace_callback('/[a-z]{3}/i', static function (array $mm) use ($aliases): string {
            $key = strtolower($mm[0]);

            return (string) ($aliases[$key] ?? $mm[0]);
        }, $segment);
    }

    /**
     * 在「某天」内定位当天第一个 >= $start 时刻的匹配时分
     *
     * 依赖 fields[1]（小时）与 fields[0]（分钟）均已升序排列：先跳过小于当前
     * 小时候选，再在同一小时内跳过小于当前分钟的候选，首个命中即返回。
     * 当天已无候选时刻（如所有小时都已过去）时返回 null，由调用方跳到次日。
     *
     * @param \DateTimeImmutable $start 扫描起点，需为当天某一整分时刻
     * @return \DateTimeImmutable|null 当天匹配的时刻（秒固定为 0），无匹配返回 null
     */
    private function findNextTimeOnDay(\DateTimeImmutable $start): ?\DateTimeImmutable
    {
        $hour = (int) $start->format('H');
        $minute = (int) $start->format('i');

        // fields[1]（小时）、fields[0]（分钟）均已升序排列
        foreach ($this->fields[1] as $candidateHour) {
            if ($candidateHour < $hour) {
                continue;
            }
            foreach ($this->fields[0] as $candidateMinute) {
                if ($candidateHour === $hour && $candidateMinute < $minute) {
                    continue;
                }

                return $start->setTime($candidateHour, $candidateMinute, 0);
            }
        }

        return null;
    }
}

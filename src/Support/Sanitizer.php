<?php

/**
 * ============================================================================
 * 输入数据清理工具类
 * ============================================================================
 *
 * 提供统一的数据清理和规范化接口，用于过滤用户输入。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @example
 * $clean = Sanitizer::make($_POST)
 *     ->string('name')
 *     ->email('email')
 *     ->int('age', 0, 150)
 *     ->float('price', 0)
 *     ->bool('active')
 *     ->get();
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

final class Sanitizer
{
    private array $data = [];

    private array $cleaned = [];

    private array $errors = [];

    private bool $strict = false;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    /**
     * 严格模式：遇到无效值时抛出异常而非跳过
     */
    public function strict(bool $strict = true): self
    {
        $this->strict = $strict;
        return $this;
    }

    public function string(string $key, int $maxLength = 0): self
    {
        $value = $this->data[$key] ?? null;
        if (! is_string($value)) {
            $value = $value !== null ? (string) $value : '';
        }
        $value = trim($value);
        if ($maxLength > 0) {
            $value = mb_substr($value, 0, $maxLength);
        }
        $this->cleaned[$key] = $value;
        return $this;
    }

    public function email(string $key): self
    {
        $value = filter_var($this->data[$key] ?? '', FILTER_SANITIZE_EMAIL);
        $value = filter_var($value, FILTER_VALIDATE_EMAIL) ?: '';
        $this->cleaned[$key] = $value;
        return $this;
    }

    public function url(string $key): self
    {
        $value = filter_var($this->data[$key] ?? '', FILTER_SANITIZE_URL);
        $value = filter_var($value, FILTER_VALIDATE_URL) ?: '';
        $this->cleaned[$key] = $value;
        return $this;
    }

    public function int(string $key, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): self
    {
        $value = filter_var($this->data[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false) {
            $value = 0;
            if ($this->strict) {
                $this->errors[$key] = 'Invalid integer';
            }
        }
        $this->cleaned[$key] = max($min, min($max, $value));
        return $this;
    }

    public function float(string $key, float $min = PHP_FLOAT_MIN, float $max = PHP_FLOAT_MAX): self
    {
        $value = filter_var($this->data[$key] ?? null, FILTER_VALIDATE_FLOAT);
        if ($value === false) {
            $value = 0.0;
            if ($this->strict) {
                $this->errors[$key] = 'Invalid float';
            }
        }
        $this->cleaned[$key] = max($min, min($max, $value));
        return $this;
    }

    public function bool(string $key): self
    {
        $value = $this->data[$key] ?? false;
        $this->cleaned[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        return $this;
    }

    public function array(string $key): self
    {
        $value = $this->data[$key] ?? [];
        $this->cleaned[$key] = is_array($value) ? $value : [$value];
        return $this;
    }

    public function enum(string $key, string $enumClass, mixed $default = null): self
    {
        $value = $this->data[$key] ?? null;
        try {
            $this->cleaned[$key] = $enumClass::from($value);
        } catch (\ValueError|\TypeError) {
            $this->cleaned[$key] = $default;
            if ($this->strict) {
                $this->errors[$key] = 'Invalid enum value';
            }
        }
        return $this;
    }

    public function custom(string $key, callable $callback): self
    {
        $this->cleaned[$key] = $callback($this->data[$key] ?? null);
        return $this;
    }

    public function raw(string $key): self
    {
        $this->cleaned[$key] = $this->data[$key] ?? null;
        return $this;
    }

    public function except(string ...$keys): self
    {
        foreach ($keys as $key) {
            unset($this->cleaned[$key]);
        }
        return $this;
    }

    public function only(string ...$keys): self
    {
        $this->cleaned = array_intersect_key($this->cleaned, array_flip($keys));
        return $this;
    }

    public function get(): array
    {
        return $this->cleaned;
    }

    public function getString(string $key): string
    {
        return (string) ($this->cleaned[$key] ?? '');
    }

    public function getInt(string $key): int
    {
        return (int) ($this->cleaned[$key] ?? 0);
    }

    public function getFloat(string $key): float
    {
        return (float) ($this->cleaned[$key] ?? 0.0);
    }

    public function getBool(string $key): bool
    {
        return (bool) ($this->cleaned[$key] ?? false);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }
}

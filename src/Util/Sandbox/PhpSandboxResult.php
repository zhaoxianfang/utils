<?php

declare(strict_types=1);

namespace zxf\Util\Sandbox;

/**
 * 沙箱执行结果类
 *
 * 用于封装和返回沙箱执行的结果信息
 *
 * @package PhpSandbox
 */
final class PhpSandboxResult
{
    private bool $success = false;
    private string $output = '';
    private string $error = '';
    private string $errorType = '';
    private float $executionTime = 0.0;
    private int $memoryUsed = 0;
    private int $peakMemory = 0;
    private string $identifier = '';
    private int $timestamp = 0;

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    public function getMemoryUsed(): int
    {
        return $this->memoryUsed;
    }

    public function getPeakMemory(): int
    {
        return $this->peakMemory;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    public function setOutput(string $output): self
    {
        $this->output = $output;
        return $this;
    }

    public function setError(string $error): self
    {
        $this->error = $error;
        return $this;
    }

    public function setErrorType(string $errorType): self
    {
        $this->errorType = $errorType;
        return $this;
    }

    public function setExecutionTime(float $executionTime): self
    {
        $this->executionTime = $executionTime;
        return $this;
    }

    public function setMemoryUsed(int $memoryUsed): self
    {
        $this->memoryUsed = $memoryUsed;
        return $this;
    }

    public function setPeakMemory(int $peakMemory): self
    {
        $this->peakMemory = $peakMemory;
        return $this;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function setTimestamp(int $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'error' => $this->error,
            'error_type' => $this->errorType,
            'execution_time' => $this->executionTime,
            'memory_used' => $this->memoryUsed,
            'peak_memory' => $this->peakMemory,
            'identifier' => $this->identifier,
            'timestamp' => $this->timestamp,
        ];
    }

    public function __toString(): string
    {
        if ($this->success) {
            return "执行成功 [{$this->identifier}]: {$this->executionTime}s, 内存: {$this->memoryUsed} bytes";
        } else {
            return "执行失败 [{$this->identifier}]: {$this->error}";
        }
    }
}

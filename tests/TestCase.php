<?php

declare(strict_types=1);

namespace zxf\Utils\Tests;

use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * 测试用例基类
 *
 * 约定：
 *  - 测试方法以 `test` 开头、public、无参数；
 *  - 每个测试独立，不依赖执行顺序；
 *  - 可用 setUp()/tearDown() 做前后置处理。
 */
abstract class TestCase
{
    /** @var list<array{0: string, 1: string, 2: string}> 执行过程中记录的失败项 [测试方法, 用例名, 原因] */
    private array $failures = [];

    private int $assertions = 0;

    /**
     * 测试前置处理
     */
    protected function setUp(): void
    {
    }

    /**
     * 测试后置处理
     */
    protected function tearDown(): void
    {
    }

    /**
     * 运行本用例中的所有测试方法
     *
     * @return array{passed: int, failed: int, failures: list<array{0: string, 1: string, 2: string}>}
     */
    final public function run(): array
    {
        $passed = 0;
        $failed = 0;

        $methods = $this->resolveTestMethods();

        foreach ($methods as $method) {
            $label = static::class . '::' . $method;

            try {
                $this->setUp();

                try {
                    $this->{$method}();
                } finally {
                    $this->tearDown();
                }

                $passed++;
            } catch (AssertionFailedException $e) {
                $failed++;
                $this->failures[] = [$label, $method, $e->getMessage()];
            } catch (Throwable $e) {
                $failed++;
                $this->failures[] = [$label, $method, sprintf(
                    '未捕获的 %s: %s（%s:%d）',
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                )];
            }
        }

        return [
            'passed'    => $passed,
            'failed'    => $failed,
            'failures'  => $this->failures,
            'assertions' => $this->assertions,
        ];
    }

    /**
     * 解析出所有测试方法（以 test 开头的 public 无参方法）
     *
     * @return list<string>
     */
    private function resolveTestMethods(): array
    {
        $reflection = new ReflectionClass($this);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! str_starts_with($method->getName(), 'test')) {
                continue;
            }
            if ($method->isStatic() || $method->isAbstract() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }
            $methods[] = $method->getName();
        }

        sort($methods);

        return $methods;
    }

    /**
     * 统计断言次数（供运行器汇总）
     */
    final public function countAssertion(): void
    {
        $this->assertions++;
    }
}

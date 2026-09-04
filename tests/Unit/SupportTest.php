<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use BadMethodCallException;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use zxf\Utils\Support\Defer;
use zxf\Utils\Support\Fluent;
use zxf\Utils\Support\Once;
use zxf\Utils\Support\Pipeline;
use zxf\Utils\Support\RateLimiter;
use zxf\Utils\Support\Result;
use zxf\Utils\Support\Retry;
use zxf\Utils\Support\Timer;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * Support 组件测试（Result / Pipeline / Defer / Once / Fluent / Retry / Timer / RateLimiter）
 */
class SupportTest extends TestCase
{
    /* ---------------- Result ---------------- */

    public function testResultOkFlow(): void
    {
        $result = Result::ok(10);

        Assert::true($result->isOk());
        Assert::false($result->isErr());
        Assert::same(10, $result->unwrap());
        Assert::same(10, $result->unwrapOr(0));
        Assert::same(20, $result->map(static fn (int $v) => $v * 2)->unwrap());
        Assert::same(11, $result->andThen(static fn (int $v) => Result::ok($v + 1))->unwrap());
        Assert::true($result->isOkAnd(static fn (int $v) => $v > 5));
        Assert::null($result->error());
    }

    public function testResultErrFlow(): void
    {
        $result = Result::err('bad');

        Assert::true($result->isErr());
        Assert::same('bad', $result->error());
        Assert::same('def', $result->unwrapOr('def'));
        Assert::same('got:bad', $result->unwrapOrElse(static fn (\Throwable $e) => 'got:' . $e->getMessage()));
        Assert::same('def', $result->mapOr('def', static fn ($v) => $v));
        Assert::true($result->isErrAnd(static fn (\Throwable $e) => $e->getMessage() === 'bad'));
        Assert::same(
            'wrapped:bad',
            $result->mapErr(static fn (\Throwable $e) => 'wrapped:' . $e->getMessage())->error()
        );
    }

    public function testResultMapPreservesErrorWithoutTypeError(): void
    {
        // 旧实现 map() 会把可能为 null 的 err 传给 err()，触发 TypeError
        Assert::doesNotThrow(static fn () => Result::err('x')->map(static fn ($v) => $v));
    }

    public function testResultUnwrapThrows(): void
    {
        Assert::throws(RuntimeException::class, static fn () => Result::err('bad')->unwrap());
        Assert::throws(RuntimeException::class, static fn () => Result::ok(1)->unwrapErr());
    }

    public function testResultAttempt(): void
    {
        Assert::same(2, Result::attempt(static fn () => 1 + 1)->unwrap());
        Assert::same('x', Result::attempt(static fn () => throw new RuntimeException('x'))->error());
    }

    public function testResultSerialisation(): void
    {
        Assert::same(['ok' => true, 'value' => 10], Result::ok(10)->toArray());
        Assert::same('{"ok":false,"error":"b"}', json_encode(Result::err('b')));
    }

    /* ---------------- Pipeline ---------------- */

    public function testPipelineChains(): void
    {
        $result = Pipeline::make(['  a ', ' b', ''])
            ->through(static fn (array $d) => array_map('trim', $d))
            ->when(true, static fn (array $d) => array_filter($d))
            ->then(static fn (array $d) => array_values($d));

        Assert::same(['a', 'b'], $result);
    }

    public function testPipelineTapDoesNotAlterData(): void
    {
        $seen = null;

        $result = Pipeline::make(5)
            ->tap(static function ($d) use (&$seen) {
                $seen = $d;
            })
            ->through(static fn (int $v) => $v + 1)
            ->then();

        Assert::same(5, $seen);
        Assert::same(6, $result);
    }

    public function testPipelinePeekReflectsLatestExecution(): void
    {
        $pipeline = Pipeline::make(1)->through(static fn (int $v) => $v + 1);

        Assert::same(1, $pipeline->peek());
        Assert::same(2, $pipeline->then());
        Assert::same(2, $pipeline->peek());
        Assert::same(1, $pipeline->initial());
    }

    public function testPipelineExceptionHandling(): void
    {
        $throwing = static fn () => Pipeline::make(5)
            ->through(static fn (int $v) => throw new RuntimeException('boom'));

        Assert::same('handled', $throwing()->catch(static fn (\Throwable $e, $d) => 'handled')->then());

        // 无处理器且不中断：跳过该管道，保留进入时的数据
        Assert::same(5, $throwing()->haltOnException(false)->then());

        // 默认中断
        Assert::throws(RuntimeException::class, static fn () => $throwing()->then());
    }

    public function testPipelineDestinationIsProtectedByCatch(): void
    {
        $result = Pipeline::make(5)
            ->catch(static fn (\Throwable $e, $d) => 'caught')
            ->then(static fn (int $v) => throw new RuntimeException('dest'));

        Assert::same('caught', $result);
    }

    /* ---------------- Defer ---------------- */

    public function testDeferExecutesInReverseOrder(): void
    {
        $log = [];

        Defer::make()
            ->add(static function () use (&$log) {
                $log[] = 'a';
            })
            ->add(static function () use (&$log) {
                $log[] = 'b';
            })
            ->execute();

        Assert::same(['b', 'a'], $log);
    }

    public function testDeferRejectsRegistrationAfterExecution(): void
    {
        $defer = Defer::make();
        $defer->execute();

        // 已执行后再注册会被静默丢弃，属用法错误，应显式报错
        Assert::throws(RuntimeException::class, static fn () => $defer->add(static fn () => null));
    }

    /* ---------------- Once ---------------- */

    public function testOnceExecutesOnlyOnce(): void
    {
        $counter = 0;
        $once = Once::make(static function () use (&$counter) {
            return ++$counter;
        });

        $once();
        $once();

        Assert::same(1, $once());
        Assert::same(1, $once->value());
        Assert::true($once->executed());
    }

    public function testOnceResetAllowsReexecution(): void
    {
        $counter = 0;
        $once = Once::make(static function () use (&$counter) {
            return ++$counter;
        });

        $once();
        $once->reset();

        Assert::same(2, $once());
    }

    /* ---------------- Fluent ---------------- */

    public function testFluentSetsPublicProperties(): void
    {
        $query = (new class extends Fluent {
            public string $table = '';
            public ?int $limit = null;
            protected string $secret = 'keep';
        })
            ->table('users')
            ->limit(10);

        Assert::same('users', $query->table);
        Assert::same(10, $query->limit);
    }

    public function testFluentRefusesNonPublicProperties(): void
    {
        $query = new class extends Fluent {
            protected string $secret = 'keep';
        };

        // 旧实现允许通过 __call 写入 protected 属性，等于绕过封装
        Assert::throws(BadMethodCallException::class, static fn () => $query->secret('hacked'));
    }

    public function testFluentValidatesPropertyType(): void
    {
        $query = new class extends Fluent {
            public string $table = '';
        };

        Assert::throws(InvalidArgumentException::class, static fn () => $query->table(null));
        Assert::throws(InvalidArgumentException::class, static fn () => $query->table(123));
    }

    public function testFluentFillIgnoresUnknownKeys(): void
    {
        $query = new class extends Fluent {
            public string $table = '';
            public int $limit = 0;
        };

        $query->fill(['table' => 'posts', 'limit' => 5, 'unknown' => 'ignored']);

        Assert::same('posts', $query->table);
        Assert::same(5, $query->limit);
    }

    public function testFluentWhenFilledSkipsEmptyValues(): void
    {
        $query = new class extends Fluent {
            public string $table = 'default';
        };

        $query->whenFilled('table', '');
        Assert::same('default', $query->table);

        $query->whenFilled('table', 'set');
        Assert::same('set', $query->table);
    }

    /* ---------------- Retry ---------------- */

    public function testRetrySucceedsAfterFailures(): void
    {
        $attempts = 0;

        $result = Retry::make(static function () use (&$attempts) {
            if (++$attempts < 3) {
                throw new RuntimeException('boom');
            }

            return "ok{$attempts}";
        })
            ->times(5)
            ->sleep(1)
            ->backoff(1.1)
            ->jitter()
            ->run();

        Assert::same('ok3', $result);
        Assert::same(3, $attempts);
    }

    /**
     * 旧实现 usleep(max(0, $delay) * 1000) 传入 float，
     * 而 usleep() 是内部函数，即使整数值的 float 也会抛 TypeError，
     * 导致任何需要等待的重试必然崩溃。
     */
    public function testRetrySleepAcceptsFractionalMilliseconds(): void
    {
        $counter = 0;

        $result = Assert::doesNotThrow(static function () use (&$counter) {
            return Retry::make(static function () use (&$counter) {
                return ++$counter;
            })
                ->times(6)
                ->sleep(33)
                ->backoff(1.7)
                ->jitter()
                ->when(static fn (int $v) => $v < 4)
                ->run();
        }, '小数退避不应触发 TypeError');

        Assert::same(4, $result);
    }

    public function testRetryThrowsAfterExhaustingAttempts(): void
    {
        Assert::throws(
            RuntimeException::class,
            static fn () => Retry::make(static fn () => throw new RuntimeException('always'))
                ->times(2)
                ->sleep(1)
                ->run()
        );
    }

    /* ---------------- Timer ---------------- */

    public function testTimerMeasuresElapsedTime(): void
    {
        $timer = Timer::start();
        Timer::sleep(0.02);

        Assert::true($timer->elapsed() >= 0.01);
        Assert::true($timer->elapsedMs() >= 10.0);
    }

    public function testTimerSleepRejectsNegative(): void
    {
        Assert::throws(InvalidArgumentException::class, static fn () => Timer::sleep(-1));
    }

    public function testTimerSleepZeroIsNoop(): void
    {
        Assert::doesNotThrow(static fn () => Timer::sleep(0));
    }

    public function testTimerTimeout(): void
    {
        $timer = Timer::withTimeout(0.02);
        Assert::false($timer->isTimedOut());

        Timer::sleep(0.04);
        Assert::true($timer->isTimedOut());
    }

    public function testTimerWithTimeoutRejectsNegative(): void
    {
        Assert::throws(InvalidArgumentException::class, static fn () => Timer::withTimeout(-1));
    }

    public function testTimerMeasure(): void
    {
        $measure = Timer::measure(static fn () => array_sum(range(1, 1000)));

        Assert::same(500500, $measure['result']);
        Assert::true($measure['seconds'] >= 0);
        Assert::true($measure['ms'] >= 0);
    }

    /* ---------------- RateLimiter ---------------- */

    protected function setUp(): void
    {
        RateLimiter::resetAll();
    }

    public function testRateLimiterAllowsUpToLimit(): void
    {
        $limiter = RateLimiter::make('t1', 3, 60);

        Assert::true($limiter->attempt());
        Assert::true($limiter->attempt());
        Assert::true($limiter->attempt());
        Assert::false($limiter->attempt());
    }

    public function testRateLimiterRemainingAndClear(): void
    {
        $limiter = RateLimiter::make('t2', 2, 60);

        Assert::same(2, $limiter->remaining());
        $limiter->attempt();
        Assert::same(1, $limiter->remaining());

        $limiter->clear();
        Assert::same(2, $limiter->remaining());
    }

    public function testRateLimiterAvailableIn(): void
    {
        $limiter = RateLimiter::make('t3', 1, 60);
        Assert::same(0, $limiter->availableIn());

        $limiter->attempt();
        Assert::true($limiter->availableIn() > 0);
    }
}

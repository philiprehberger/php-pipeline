<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline\Tests;

use PhilipRehberger\Pipeline\Exceptions\PipelineException;
use PhilipRehberger\Pipeline\PendingPipeline;
use PhilipRehberger\Pipeline\Pipeline;
use PhilipRehberger\Pipeline\Tests\Fixtures\AppendSuffixStage;
use PhilipRehberger\Pipeline\Tests\Fixtures\MultiplyByTwoStage;
use PhilipRehberger\Pipeline\Tests\Fixtures\ThrowingStage;
use PhilipRehberger\Pipeline\Tests\Fixtures\TrimStage;
use PhilipRehberger\Pipeline\Tests\Fixtures\UpperCaseStage;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    public function test_send_returns_pending_pipeline(): void
    {
        $pipeline = Pipeline::send('test');

        $this->assertInstanceOf(PendingPipeline::class, $pipeline);
    }

    public function test_passable_returns_unchanged_with_no_stages(): void
    {
        $result = Pipeline::send('unchanged')
            ->through([])
            ->process();

        $this->assertSame('unchanged', $result);
    }

    public function test_single_class_stage(): void
    {
        $result = Pipeline::send('hello')
            ->pipe(UpperCaseStage::class)
            ->thenReturn();

        $this->assertSame('HELLO', $result);
    }

    public function test_multiple_class_stages_via_through(): void
    {
        $result = Pipeline::send('  hello  ')
            ->through([
                TrimStage::class,
                UpperCaseStage::class,
                AppendSuffixStage::class,
            ])
            ->process();

        $this->assertSame('HELLO_suffix', $result);
    }

    public function test_callable_stages(): void
    {
        $result = Pipeline::send(5)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value + 10))
            ->pipe(fn (mixed $value, \Closure $next) => $next($value * 2))
            ->thenReturn();

        $this->assertSame(30, $result);
    }

    public function test_mixed_class_and_callable_stages(): void
    {
        $result = Pipeline::send('  hello  ')
            ->pipe(TrimStage::class)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value.' world'))
            ->pipe(UpperCaseStage::class)
            ->process();

        $this->assertSame('HELLO WORLD', $result);
    }

    public function test_when_adds_stage_if_condition_true(): void
    {
        $result = Pipeline::send('hello')
            ->when(true, UpperCaseStage::class)
            ->thenReturn();

        $this->assertSame('HELLO', $result);
    }

    public function test_when_skips_stage_if_condition_false(): void
    {
        $result = Pipeline::send('hello')
            ->when(false, UpperCaseStage::class)
            ->thenReturn();

        $this->assertSame('hello', $result);
    }

    public function test_unless_adds_stage_if_condition_false(): void
    {
        $result = Pipeline::send('hello')
            ->unless(false, UpperCaseStage::class)
            ->thenReturn();

        $this->assertSame('HELLO', $result);
    }

    public function test_unless_skips_stage_if_condition_true(): void
    {
        $result = Pipeline::send('hello')
            ->unless(true, UpperCaseStage::class)
            ->thenReturn();

        $this->assertSame('hello', $result);
    }

    public function test_stage_failure_throws_pipeline_exception(): void
    {
        $this->expectException(PipelineException::class);
        $this->expectExceptionMessage('Pipeline failed at stage ['.ThrowingStage::class.']');

        Pipeline::send('data')
            ->pipe(ThrowingStage::class)
            ->process();
    }

    public function test_on_failure_handler_catches_exception(): void
    {
        $result = Pipeline::send('original')
            ->pipe(ThrowingStage::class)
            ->onFailure(fn (\Throwable $e, mixed $passable) => 'recovered: '.$passable)
            ->process();

        $this->assertSame('recovered: original', $result);
    }

    public function test_pipeline_exception_contains_stage_name(): void
    {
        try {
            Pipeline::send('data')
                ->pipe(ThrowingStage::class)
                ->process();

            $this->fail('Expected PipelineException was not thrown.');
        } catch (PipelineException $e) {
            $this->assertSame(ThrowingStage::class, $e->stageName);
            $this->assertNotNull($e->getPrevious());
        }
    }

    public function test_process_and_then_return_are_equivalent(): void
    {
        $resultA = Pipeline::send(10)
            ->pipe(MultiplyByTwoStage::class)
            ->process();

        $resultB = Pipeline::send(10)
            ->pipe(MultiplyByTwoStage::class)
            ->thenReturn();

        $this->assertSame($resultA, $resultB);
        $this->assertSame(20, $resultA);
    }

    public function test_stages_execute_in_order(): void
    {
        $log = [];

        $result = Pipeline::send('start')
            ->pipe(function (mixed $value, \Closure $next) use (&$log) {
                $log[] = 'first';

                return $next($value.'.1');
            })
            ->pipe(function (mixed $value, \Closure $next) use (&$log) {
                $log[] = 'second';

                return $next($value.'.2');
            })
            ->pipe(function (mixed $value, \Closure $next) use (&$log) {
                $log[] = 'third';

                return $next($value.'.3');
            })
            ->thenReturn();

        $this->assertSame(['first', 'second', 'third'], $log);
        $this->assertSame('start.1.2.3', $result);
    }

    public function test_numeric_passable_through_stages(): void
    {
        $result = Pipeline::send(3)
            ->pipe(MultiplyByTwoStage::class)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value + 1))
            ->thenReturn();

        $this->assertSame(7, $result);
    }
}

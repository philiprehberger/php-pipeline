<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline\Tests;

use InvalidArgumentException;
use PhilipRehberger\Pipeline\Exceptions\CheckpointFailedException;
use PhilipRehberger\Pipeline\Exceptions\PipelineException;
use PhilipRehberger\Pipeline\PendingPipeline;
use PhilipRehberger\Pipeline\Pipeline;
use PhilipRehberger\Pipeline\ProfiledResult;
use PhilipRehberger\Pipeline\Tests\Fixtures\AppendSuffixStage;
use PhilipRehberger\Pipeline\Tests\Fixtures\MultiplyByTwoStage;
use PhilipRehberger\Pipeline\Tests\Fixtures\ThrowingStage;
use PhilipRehberger\Pipeline\Tests\Fixtures\TrimStage;
use PhilipRehberger\Pipeline\Tests\Fixtures\UpperCaseStage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function test_tap_does_not_modify_passable(): void
    {
        $result = Pipeline::send('hello')
            ->pipe(UpperCaseStage::class)
            ->tap(fn (string $value) => strtolower($value))
            ->thenReturn();

        $this->assertSame('HELLO', $result);
    }

    public function test_tap_callback_receives_current_value(): void
    {
        $captured = null;

        Pipeline::send('hello')
            ->pipe(UpperCaseStage::class)
            ->tap(function (string $value) use (&$captured) {
                $captured = $value;
            })
            ->thenReturn();

        $this->assertSame('HELLO', $captured);
    }

    public function test_catch_exception_catches_matching_type(): void
    {
        $result = Pipeline::send('original')
            ->pipe(fn (mixed $value, \Closure $next) => throw new RuntimeException('boom'))
            ->catchException(RuntimeException::class, fn (\Throwable $e, mixed $passable) => 'caught: '.$passable)
            ->thenReturn();

        $this->assertSame('caught: original', $result);
    }

    public function test_catch_exception_does_not_catch_non_matching_type(): void
    {
        $this->expectException(PipelineException::class);

        Pipeline::send('data')
            ->pipe(fn (mixed $value, \Closure $next) => throw new RuntimeException('boom'))
            ->catchException(InvalidArgumentException::class, fn (\Throwable $e, mixed $passable) => 'caught')
            ->thenReturn();
    }

    // --- Profiling tests ---

    public function test_process_with_profile_returns_profiled_result(): void
    {
        $result = Pipeline::send('hello')
            ->pipe(UpperCaseStage::class)
            ->pipe(AppendSuffixStage::class)
            ->processWithProfile();

        $this->assertInstanceOf(ProfiledResult::class, $result);
        $this->assertSame('HELLO_suffix', $result->value());
    }

    public function test_profiled_result_has_correct_stage_count(): void
    {
        $result = Pipeline::send('hello')
            ->pipe(UpperCaseStage::class)
            ->pipe(AppendSuffixStage::class)
            ->processWithProfile();

        $stages = $result->stages();
        $this->assertCount(2, $stages);
    }

    public function test_profiled_result_stage_entries_have_required_keys(): void
    {
        $result = Pipeline::send('hello')
            ->pipe(UpperCaseStage::class)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value.'!'))
            ->processWithProfile();

        foreach ($result->stages() as $stage) {
            $this->assertArrayHasKey('name', $stage);
            $this->assertArrayHasKey('duration_ms', $stage);
            $this->assertArrayHasKey('memory_delta', $stage);
            $this->assertIsString($stage['name']);
            $this->assertIsFloat($stage['duration_ms']);
            $this->assertIsInt($stage['memory_delta']);
        }
    }

    public function test_profiled_result_total_duration_is_sum(): void
    {
        $result = Pipeline::send(5)
            ->pipe(MultiplyByTwoStage::class)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value + 1))
            ->processWithProfile();

        $sum = 0.0;
        foreach ($result->stages() as $stage) {
            $sum += $stage['duration_ms'];
        }

        $this->assertEqualsWithDelta($sum, $result->totalDuration(), 0.0001);
    }

    public function test_profiled_result_slowest_stage(): void
    {
        $result = Pipeline::send('hello')
            ->pipe(function (mixed $value, \Closure $next) {
                // Fast stage
                return $next(strtoupper($value));
            })
            ->pipe(function (mixed $value, \Closure $next) {
                // Slower stage — do some work
                $x = 0;
                for ($i = 0; $i < 10000; $i++) {
                    $x += $i;
                }

                return $next($value.$x);
            })
            ->processWithProfile();

        $slowest = $result->slowestStage();
        $this->assertIsString($slowest);
        $this->assertNotEmpty($slowest);
    }

    public function test_profiled_result_stage_names(): void
    {
        $result = Pipeline::send('hello')
            ->pipe(UpperCaseStage::class)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value.'!'))
            ->processWithProfile();

        $stages = $result->stages();
        $this->assertSame(UpperCaseStage::class, $stages[0]['name']);
        $this->assertSame('Closure', $stages[1]['name']);
    }

    // --- Checkpoint tests ---

    public function test_checkpoint_passes_when_validator_returns_true(): void
    {
        $result = Pipeline::send(10)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value * 2))
            ->checkpoint(fn (mixed $value) => $value <= 100)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value + 1))
            ->process();

        $this->assertSame(21, $result);
    }

    public function test_checkpoint_fails_when_validator_returns_false(): void
    {
        $this->expectException(CheckpointFailedException::class);

        Pipeline::send(200)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value * 2))
            ->checkpoint(fn (mixed $value) => $value <= 100)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value + 1))
            ->process();
    }

    public function test_checkpoint_fails_when_validator_throws(): void
    {
        $this->expectException(CheckpointFailedException::class);
        $this->expectExceptionMessage('Pipeline checkpoint failed: Value too large');

        Pipeline::send(200)
            ->checkpoint(function (mixed $value) {
                throw new RuntimeException('Value too large');
            })
            ->process();
    }

    public function test_multiple_checkpoints(): void
    {
        $result = Pipeline::send(5)
            ->checkpoint(fn (mixed $value) => $value > 0)
            ->pipe(fn (mixed $value, \Closure $next) => $next($value * 3))
            ->checkpoint(fn (mixed $value) => $value < 100)
            ->process();

        $this->assertSame(15, $result);
    }

    // --- Template tests ---

    protected function setUp(): void
    {
        parent::setUp();
        Pipeline::clearTemplates();
    }

    public function test_register_and_create_from_template(): void
    {
        Pipeline::register('text-cleanup', function (PendingPipeline $p) {
            $p->pipe(TrimStage::class)
                ->pipe(UpperCaseStage::class);
        });

        $result = Pipeline::fromTemplate('text-cleanup')
            ->send('  hello  ')
            ->thenReturn();

        $this->assertSame('HELLO', $result);
    }

    public function test_has_template(): void
    {
        $this->assertFalse(Pipeline::hasTemplate('my-template'));

        Pipeline::register('my-template', fn (PendingPipeline $p) => $p->pipe(TrimStage::class));

        $this->assertTrue(Pipeline::hasTemplate('my-template'));
    }

    public function test_from_template_throws_for_unknown_template(): void
    {
        $this->expectException(PipelineException::class);
        $this->expectExceptionMessage('Pipeline template [unknown] is not registered.');

        Pipeline::fromTemplate('unknown');
    }

    public function test_template_can_add_multiple_stages(): void
    {
        Pipeline::register('full-process', function (PendingPipeline $p) {
            $p->pipe(TrimStage::class)
                ->pipe(UpperCaseStage::class)
                ->pipe(AppendSuffixStage::class);
        });

        $result = Pipeline::fromTemplate('full-process')
            ->send('  hello  ')
            ->thenReturn();

        $this->assertSame('HELLO_suffix', $result);
    }
}

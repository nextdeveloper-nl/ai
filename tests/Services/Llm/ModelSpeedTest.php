<?php

namespace NextDeveloper\AI\Tests\Services\Llm;

use NextDeveloper\AI\Services\Llm\ModelSpeed;
use PHPUnit\Framework\TestCase;

class ModelSpeedTest extends TestCase
{
    public function test_tokens_per_second_ignores_the_wait_for_the_first_token(): void
    {
        $this->assertSame(100.0, ModelSpeed::tokensPerSecond(100, 1500, 500));
        $this->assertSame(50.0, ModelSpeed::tokensPerSecond(100, 2000));
    }

    public function test_answers_that_are_too_short_are_not_measured(): void
    {
        $this->assertNull(ModelSpeed::tokensPerSecond(5, 1000));
        $this->assertNull(ModelSpeed::tokensPerSecond(100, 500, 480));
    }

    public function test_rating_follows_the_thresholds(): void
    {
        $this->assertSame(1, ModelSpeed::rating(19.9));
        $this->assertSame(2, ModelSpeed::rating(20));
        $this->assertSame(3, ModelSpeed::rating(99));
        $this->assertSame(4, ModelSpeed::rating(100));
        $this->assertSame(5, ModelSpeed::rating(250));
    }

    public function test_record_rates_by_the_median_and_keeps_other_capabilities(): void
    {
        $capabilities = ['streaming' => true, 'description' => 'x'];

        foreach (['2026-11-10' => 30, '2026-11-11' => 30, '2026-11-12' => 400] as $day => $tps) {
            $capabilities = ModelSpeed::record($capabilities, $tps, $day);
        }

        $this->assertSame(30.0, $capabilities['speed_tps']);
        $this->assertSame(2, $capabilities['speed']);
        $this->assertSame('x', $capabilities['description']);
        $this->assertCount(3, $capabilities['speed_samples']);
    }

    public function test_record_keeps_one_sample_per_day_and_only_the_last_seven(): void
    {
        $capabilities = ModelSpeed::record([], 10, '2026-11-01');
        $capabilities = ModelSpeed::record($capabilities, 60, '2026-11-01');

        $this->assertCount(1, $capabilities['speed_samples']);
        $this->assertSame(60.0, $capabilities['speed_tps']);

        for ($day = 2; $day <= 9; $day++) {
            $capabilities = ModelSpeed::record($capabilities, 60, sprintf('2026-11-%02d', $day));
        }

        $this->assertCount(ModelSpeed::KEEP_SAMPLES, $capabilities['speed_samples']);
        $this->assertSame('2026-11-03', $capabilities['speed_samples'][0]['day']);
    }
}

<?php

declare(strict_types=1);

namespace Monitoring\Tests\Unit;

use Monitoring\Support\Series;
use Monitoring\Support\State;
use PHPUnit\Framework\TestCase;

final class SeriesTest extends TestCase
{
    public function test_buckets_average_running_samples_and_count_uptime(): void
    {
        $sample = static fn (int $t, int $state, float $cpu, int $rx): array => ['t' => $t, 'state' => $state, 'cpu' => $cpu, 'memory' => 100, 'disk' => 50, 'rx' => $rx, 'tx' => 0];

        $points = Series::bucket([
            $sample(0, State::RUNNING, 10.0, 0),
            $sample(60, State::RUNNING, 30.0, 6000),
            $sample(120, State::OFFLINE, 0.0, 0),
            $sample(180, State::RUNNING, 50.0, 0),
        ], 0, 240, 2);

        $this->assertCount(2, $points);
        $this->assertSame(20.0, $points[0]['cpu']);
        $this->assertSame(100.0, $points[0]['rx']);
        $this->assertSame(1.0, $points[0]['up']);
        $this->assertSame(50.0, $points[1]['cpu']);
        $this->assertSame(0.5, $points[1]['up']);
        $this->assertSame(0.0, $points[1]['rx']);
    }
}

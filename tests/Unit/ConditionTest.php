<?php

declare(strict_types=1);

namespace Monitoring\Tests\Unit;

use Monitoring\Support\Condition;
use Monitoring\Support\State;
use PHPUnit\Framework\TestCase;

final class ConditionTest extends TestCase
{
    private const array LIMITS = ['cpu' => 200, 'memory' => 2048, 'disk' => 0];

    public function test_every_sample_has_to_break_the_threshold(): void
    {
        $high = ['state' => State::RUNNING, 'cpu' => 190.0, 'memory' => 1900, 'disk' => 100];
        $low = ['state' => State::RUNNING, 'cpu' => 20.0, 'memory' => 200, 'disk' => 100];

        $this->assertSame(['value' => 95.0, 'peak' => 95.0], Condition::check('cpu', 90, [$high, $high], self::LIMITS, 2));
        $this->assertNull(Condition::check('cpu', 90, [$low, $high], self::LIMITS, 2));
        $this->assertNull(Condition::check('cpu', 90, [$high], self::LIMITS, 2));
        $this->assertNull(Condition::check('disk', 90, [$high, $high], self::LIMITS, 2));
    }

    public function test_offline_ignores_unreachable_nodes(): void
    {
        $offline = ['state' => State::OFFLINE, 'cpu' => 0.0, 'memory' => 0, 'disk' => 0];
        $unknown = ['state' => State::UNREACHABLE, 'cpu' => 0.0, 'memory' => 0, 'disk' => 0];

        $this->assertNotNull(Condition::check('offline', 0, [$offline, $offline], self::LIMITS, 2));
        $this->assertNull(Condition::check('offline', 0, [$offline, $unknown], self::LIMITS, 2));
    }
}

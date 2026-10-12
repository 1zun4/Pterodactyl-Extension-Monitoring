<?php

declare(strict_types=1);

namespace Monitoring\Tests\Unit;

use Monitoring\Support\Capacity;
use PHPUnit\Framework\TestCase;

final class CapacityTest extends TestCase
{
    public function test_stacks_servers_other_processes_and_free_memory_on_the_panel_host(): void
    {
        $memory = Capacity::of(57344, 358400, 0, 131072, 500, 128702, ['total' => 128702, 'used' => 117970]);

        $this->assertSame(128702, $memory['total']);
        $this->assertTrue($memory['host']);
        $this->assertSame([
            ['key' => 'servers', 'mib' => 57344, 'percent' => 44.6],
            ['key' => 'other', 'mib' => 60626, 'percent' => 47.1],
            ['key' => 'free', 'mib' => 10732, 'percent' => 8.3],
        ], $memory['segments']);
        $this->assertSame(358400, $memory['allocated']);
        $this->assertSame(786432, $memory['allowed']);
        $this->assertSame(500, $memory['overallocate']);
        $this->assertSame(428032, $memory['left']);
        $this->assertSame(['physical'], $memory['warnings']);
    }

    public function test_disk_allocated_below_the_physical_size_does_not_warn(): void
    {
        $disk = Capacity::of(107520, 700784, 0, 898048, 500, null, ['total' => 898098, 'used' => 737230]);

        $this->assertSame([107520, 629710, 160868], array_column($disk['segments'], 'mib'));
        $this->assertSame(5388288, $disk['allowed']);
        $this->assertSame(4687504, $disk['left']);
        $this->assertSame([], $disk['warnings']);

        $this->assertSame(['physical'], Capacity::of(107520, 900000, 0, 898048, 500, null, ['total' => 898098, 'used' => 737230])['warnings']);
    }

    public function test_without_host_usage_compares_servers_with_the_node_capacity_only(): void
    {
        $memory = Capacity::of(512, 2048, 1, 4096, 0, 8192);

        $this->assertFalse($memory['host']);
        $this->assertSame(8192, $memory['total']);
        $this->assertSame([['key' => 'servers', 'mib' => 512, 'percent' => 6.3], ['key' => 'unused', 'mib' => 7680, 'percent' => 93.8]], $memory['segments']);
        $this->assertSame(4096, $memory['allowed']);
        $this->assertSame(2048, $memory['left']);
        $this->assertSame(1, $memory['unlimited']);
        $this->assertSame([], $memory['warnings']);

        $this->assertSame(4096, Capacity::of(0, 0, 0, 4096, 0)['total']);
    }

    public function test_clamps_servers_that_report_more_than_the_machine(): void
    {
        $capacity = Capacity::of(9000, 0, 0, 4096, 0, null, ['total' => 8192, 'used' => 6000]);

        $this->assertSame([8192, 0, 0], array_column($capacity['segments'], 'mib'));

        $below = Capacity::of(3000, 0, 0, 4096, 0, null, ['total' => 8192, 'used' => 2000]);
        $this->assertSame([3000, 0, 5192], array_column($below['segments'], 'mib'));
    }

    public function test_warns_when_less_than_a_tenth_is_left_to_allocate(): void
    {
        $this->assertSame(['low'], Capacity::of(0, 9500, 0, 10240, 0, 20480)['warnings']);
        $this->assertSame([], Capacity::of(0, 9200, 0, 10240, 0, 20480)['warnings']);
        $this->assertSame(['physical', 'low'], Capacity::of(0, 11000, 0, 10240, 0)['warnings']);
        $this->assertSame(-760, Capacity::of(0, 11000, 0, 10240, 0)['left']);
        $this->assertSame([], Capacity::of(0, 0, 0, 0, 0)['warnings']);
    }

    public function test_overallocation_matches_the_panel(): void
    {
        $this->assertSame(786432, Capacity::allowed(131072, 500));
        $this->assertSame(5388288, Capacity::allowed(898048, 500));
        $this->assertSame(1536, Capacity::allowed(1024, 50));
        $this->assertSame(1024, Capacity::allowed(1024, 0));
        $this->assertSame(1024, Capacity::allowed(1024, -1));
        $this->assertSame(0, Capacity::of(0, 0, 0, 1024, -1)['overallocate']);
    }

    public function test_cpu_counts_threads(): void
    {
        $this->assertSame(['threads' => 16, 'used' => 2.3, 'allocated' => 24.0, 'unlimited' => 3, 'percent' => 14.4], Capacity::cpu(230.4, 2400, 3, 16));
        $this->assertNull(Capacity::cpu(50, 100, 0, null)['percent']);
    }
}

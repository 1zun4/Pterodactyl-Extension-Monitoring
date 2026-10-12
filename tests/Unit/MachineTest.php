<?php

declare(strict_types=1);

namespace Monitoring\Tests\Unit;

use Monitoring\Support\Machine;
use PHPUnit\Framework\TestCase;

final class MachineTest extends TestCase
{
    public function test_hardware_needs_the_same_memory_within_one_percent_kernel_and_threads(): void
    {
        $memory = 131791044 * 1024;
        $panel = ['memory' => $memory, 'kernel' => '6.14.0-15-generic', 'threads' => 16];

        $this->assertTrue(Machine::sameHardware($panel, $panel));
        $this->assertTrue(Machine::sameHardware(['memory' => (int) ($memory * 0.995), 'kernel' => null, 'threads' => null], $panel));
        $this->assertTrue(Machine::sameHardware(['memory' => $memory, 'kernel' => "6.14.0-15-generic\n", 'threads' => 16], $panel));
        $this->assertFalse(Machine::sameHardware(['memory' => (int) ($memory * 0.98), 'kernel' => '6.14.0-15-generic', 'threads' => 16], $panel));
        $this->assertFalse(Machine::sameHardware(['memory' => $memory, 'kernel' => '6.8.0-45-generic', 'threads' => 16], $panel));
        $this->assertFalse(Machine::sameHardware(['memory' => $memory, 'kernel' => '6.14.0-15-generic', 'threads' => 8], $panel));
        $this->assertFalse(Machine::sameHardware(['memory' => null, 'kernel' => null, 'threads' => null], $panel));
        $this->assertFalse(Machine::sameHardware(['memory' => 0, 'kernel' => null, 'threads' => null], ['memory' => 0, 'kernel' => null, 'threads' => null]));
    }

    public function test_node_address_must_be_loopback_or_local(): void
    {
        $local = ['192.168.240.5', '2001:db8::10', '192.168.240.1'];

        $this->assertTrue(Machine::pointsHere(['127.0.1.1'], []));
        $this->assertTrue(Machine::pointsHere(['::1'], []));
        $this->assertTrue(Machine::pointsHere(['203.0.113.7', '192.168.240.1'], $local));
        $this->assertTrue(Machine::pointsHere(['2001:0db8:0000::0010'], $local));
        $this->assertFalse(Machine::pointsHere(['51.195.60.240'], $local));
        $this->assertFalse(Machine::pointsHere(['not an ip'], ['not an ip']));
        $this->assertFalse(Machine::pointsHere([], $local));
    }

    public function test_a_single_hardware_match_is_the_panel_node_and_several_need_an_address(): void
    {
        $this->assertSame([], Machine::panelNodes([], []));
        $this->assertSame([1], Machine::panelNodes([1], []));
        $this->assertSame([], Machine::panelNodes([1, 3], []));
        $this->assertSame([3], Machine::panelNodes([1, 3], [3]));
        $this->assertSame([], Machine::panelNodes([1, 3], [2]));
    }

    public function test_disk_is_known_from_the_data_directory_or_a_matching_size(): void
    {
        $this->assertTrue(Machine::sameDisk(true, null, 10240));
        $this->assertTrue(Machine::sameDisk(false, 898000, 898048));
        $this->assertFalse(Machine::sameDisk(false, 500000, 898048));
        $this->assertFalse(Machine::sameDisk(false, null, 898048));
    }
}

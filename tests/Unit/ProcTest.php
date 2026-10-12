<?php

declare(strict_types=1);

namespace Monitoring\Tests\Unit;

use Monitoring\Support\Proc;
use Monitoring\Support\Versions;
use PHPUnit\Framework\TestCase;

final class ProcTest extends TestCase
{
    public function test_reads_total_and_available_memory(): void
    {
        $meminfo = "MemTotal:       131072000 kB\nMemFree:         1000000 kB\nMemAvailable:   30000000 kB\n";

        $this->assertSame(['total' => 131072000 * 1024, 'available' => 30000000 * 1024], Proc::memory($meminfo));
        $this->assertNull(Proc::memory("MemTotal: 1 kB\n"));
    }

    public function test_reads_default_gateways(): void
    {
        $route = "Iface\tDestination\tGateway \tFlags\tRefCnt\tUse\tMetric\tMask\t\tMTU\tWindow\tIRTT\n"
            ."eth0\t00000000\t01F0A8C0\t0003\t0\t0\t0\t00000000\t0\t0\t0\n"
            ."eth0\t00F0A8C0\t00000000\t0001\t0\t0\t0\t00FFFFFF\t0\t0\t0\n";

        $this->assertSame([['interface' => 'eth0', 'gateway' => '192.168.240.1']], Proc::gateways($route));
        $this->assertSame([], Proc::gateways(''));
    }

    public function test_shortens_database_versions(): void
    {
        $this->assertSame('MariaDB 11.4.7', Versions::database('11.4.7-MariaDB-0ubuntu0.25.04.1', 'mysql'));
        $this->assertSame('MariaDB 10.11.6', Versions::database('5.5.5-10.11.6-MariaDB-1:10.11.6+maria~ubu2204', 'mariadb'));
        $this->assertSame('MySQL 8.0.36', Versions::database('8.0.36-0ubuntu0.22.04.1', 'mysql'));
        $this->assertSame('PostgreSQL 16.2', Versions::database('PostgreSQL 16.2 (Debian 16.2-1.pgdg120+2) on x86_64-pc-linux-gnu', 'pgsql'));
        $this->assertSame('SQLite 3.45.1', Versions::database('3.45.1', 'sqlite'));
    }
}

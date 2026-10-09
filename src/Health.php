<?php

declare(strict_types=1);

namespace Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Monitoring\Jobs\Heartbeat;
use Monitoring\Models\Alert;
use Monitoring\Models\Sample;
use Monitoring\Panel\Panel;
use Monitoring\Support\State;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Throwable;

/**
 * Everything the admin Monitoring page and the overview widget show.
 */
final readonly class Health
{
    public function __construct(private Panel $panel, private Settings $settings) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $latest = $this->latestSamples();
        $nodes = Cache::get(Collector::NODES, []);

        return [
            'servers' => ['running' => $latest->where('state', State::RUNNING)->count(), 'total' => Server::query()->count()],
            'nodes' => ['reachable' => count(array_filter($nodes, static fn (array $node): bool => $node['reachable'])), 'total' => Node::query()->count()],
            'alerts' => Alert::query()->whereNull('resolved_at')->count(),
            'scheduler' => $this->freshness(Cache::get(Collector::COLLECTED_AT), $this->settings->interval() * 2 + 1),
            'queue' => $this->freshness(Cache::get(Heartbeat::KEY), $this->settings->interval() * 2 + 2),
            'failed_jobs' => DB::table('failed_jobs')->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->summary() + [
            'interval' => $this->settings->interval(),
            'queues' => collect(['high', 'standard', 'low'])->mapWithKeys(fn (string $queue): array => [$queue => $this->attempt(static fn () => Queue::size($queue))])->all(),
            'versions' => [
                'panel' => $this->panel->version(),
                'php' => PHP_VERSION,
                'database' => $this->attempt(static fn () => DB::scalar('select version()')),
                'redis' => $this->attempt(static function (): ?string {
                    $info = Redis::connection()->command('info', ['server']);

                    return $info['redis_version'] ?? $info['Server']['redis_version'] ?? null;
                }),
            ],
            'host' => $this->host(),
            'node_list' => $this->nodes(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function nodes(): array
    {
        $status = Cache::get(Collector::NODES, []);
        $latest = $this->latestSamples()->keyBy('server_id');
        $wings = $this->attempt(fn () => $this->panel->latestWings());

        return Node::query()->orderBy('name')->get()->map(function (Node $node) use ($status, $latest, $wings): array {
            $servers = Server::query()->where('node_id', $node->id)->get(['id', 'memory', 'disk']);
            $samples = $servers->map(static fn (Server $server) => $latest->get($server->id))->filter();
            $system = ($status[$node->id]['reachable'] ?? true) ? $this->attempt(fn () => $this->panel->systemInformation($node)) : null;

            return [
                'id' => $node->id,
                'name' => $node->name,
                'reachable' => $status[$node->id]['reachable'] ?? null,
                'error' => $status[$node->id]['error'] ?? null,
                'down_since' => $status[$node->id]['down_since'] ?? null,
                'version' => $system['version'] ?? null,
                'latest' => $wings,
                'os' => $system['system']['os'] ?? null,
                'cpu_threads' => $system['system']['cpu_threads'] ?? null,
                'containers' => $system['docker']['containers'] ?? null,
                'servers' => ['running' => $samples->where('state', State::RUNNING)->count(), 'total' => $servers->count()],
                'memory' => ['used' => $samples->sum('memory'), 'allocated' => $servers->sum('memory'), 'total' => $node->memory],
                'disk' => ['used' => $samples->sum('disk'), 'allocated' => $servers->sum('disk'), 'total' => $node->disk],
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function host(): array
    {
        $memory = $this->memory();

        return [
            'load' => function_exists('sys_getloadavg') ? (sys_getloadavg() ?: null) : null,
            'cpus' => is_readable('/proc/cpuinfo') ? mb_substr_count((string) file_get_contents('/proc/cpuinfo'), "processor\t") ?: null : null,
            'memory' => $memory,
            'disk' => ['total' => @disk_total_space(storage_path()) ?: null, 'free' => @disk_free_space(storage_path()) ?: null],
            'uptime' => is_readable('/proc/uptime') ? (int) (float) file_get_contents('/proc/uptime') : null,
        ];
    }

    /**
     * Memory of the panel's container when it has a limit, otherwise of the host.
     *
     * @return array{used: int, total: int, container: bool}|null
     */
    private function memory(): ?array
    {
        $limit = @file_get_contents('/sys/fs/cgroup/memory.max');
        $current = @file_get_contents('/sys/fs/cgroup/memory.current');

        if ($limit !== false && $current !== false && mb_trim($limit) !== 'max') {
            return ['used' => (int) $current, 'total' => (int) $limit, 'container' => true];
        }

        $info = @file_get_contents('/proc/meminfo');

        if ($info === false || ! preg_match('/MemTotal:\s+(\d+)/', $info, $total) || ! preg_match('/MemAvailable:\s+(\d+)/', $info, $available)) {
            return null;
        }

        return ['used' => ((int) $total[1] - (int) $available[1]) * 1024, 'total' => (int) $total[1] * 1024, 'container' => false];
    }

    /** @return \Illuminate\Support\Collection<int, Sample> */
    private function latestSamples(): \Illuminate\Support\Collection
    {
        $at = Cache::get(Collector::COLLECTED_AT);

        return $at === null ? collect() : Sample::query()->where('sampled_at', CarbonImmutable::parse($at))->get()->toBase();
    }

    /** @return array{at: string|null, ok: bool} */
    private function freshness(?string $at, int $minutes): array
    {
        return ['at' => $at, 'ok' => $at !== null && CarbonImmutable::parse($at)->gte(now()->subMinutes($minutes))];
    }

    private function attempt(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}

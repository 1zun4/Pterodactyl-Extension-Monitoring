<?php

declare(strict_types=1);

namespace Monitoring\Panel;

use Illuminate\Support\Facades\Cache;
use Pterodactyl\Facades\Daemon;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\Daemon\DaemonConnection;
use Pterodactyl\Services\Helpers\SoftwareVersionService;

/**
 * The only place this extension touches Panel internals.
 */
final readonly class Panel
{
    public function __construct(private SoftwareVersionService $versions) {}

    /**
     * Live state of every server on a node, from one Wings request.
     *
     * @return array<string, array{state: string, cpu: float, memory: int, disk: int, rx: int, tx: int}> Keyed by server UUID.
     */
    public function serverStates(Node $node): array
    {
        $states = [];

        foreach (DaemonConnection::forNode($node)->request('GET', '/api/servers', ['timeout' => 10])->json() ?? [] as $server) {
            $usage = $server['utilization'] ?? [];
            $uuid = $server['configuration']['uuid'] ?? null;

            if (is_string($uuid)) {
                $states[$uuid] = [
                    'state' => (string) ($server['state'] ?? 'offline'),
                    'cpu' => (float) ($usage['cpu_absolute'] ?? 0),
                    'memory' => (int) ($usage['memory_bytes'] ?? 0),
                    'disk' => (int) ($usage['disk_bytes'] ?? 0),
                    'rx' => (int) ($usage['network']['rx_bytes'] ?? 0),
                    'tx' => (int) ($usage['network']['tx_bytes'] ?? 0),
                ];
            }
        }

        return $states;
    }

    /** @return array<string, mixed> */
    public function systemInformation(Node $node): array
    {
        return Cache::remember("ext:monitoring:system:{$node->id}", 60, static fn (): array => Daemon::node($node)->systemInformation(2));
    }

    public function latestWings(): string
    {
        return $this->versions->getDaemon();
    }

    public function version(): string
    {
        return (string) config('app.version');
    }
}

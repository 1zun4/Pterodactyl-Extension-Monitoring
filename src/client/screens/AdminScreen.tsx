import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContentBlock, Spinner, Table, TitledGreyBox, httpErrorToHuman, toast } from '@pterodactyl/sdk';
import { acknowledgeAlert, deleteGlobalRule, getAdmin, keys, saveGlobalRule, type AdminMonitoring, type Health, type NodeHealth, type RuleInput } from '../api';
import AlertsTable from '../components/AlertsTable';
import Pill from '../components/Pill';
import RulesBox from '../components/RulesBox';
import StatTiles from '../components/StatTiles';
import UsageBar from '../components/UsageBar';
import { formatAgo, formatBytes, formatDuration, formatMiB } from '../lib/format';

export default function AdminScreen() {
    const queryClient = useQueryClient();
    const data = useQuery({ queryKey: keys.admin, queryFn: getAdmin, refetchInterval: 60_000 });
    const update = (result: AdminMonitoring) => queryClient.setQueryData(keys.admin, result);
    const onError = (error: unknown) => toast.error(httpErrorToHuman(error));
    const interval = data.data?.health.interval ?? 5;

    const saving = useMutation({ mutationFn: ({ input, id }: { input: RuleInput; id?: number; done: () => void }) => saveGlobalRule(input, id), onSuccess: (result, { done }) => (update(result), done()), onError });
    const removal = useMutation({ mutationFn: ({ id }: { id: number; done: () => void }) => deleteGlobalRule(id), onSuccess: (result, { done }) => (update(result), done()), onError });
    const acknowledging = useMutation({ mutationFn: acknowledgeAlert, onSuccess: update, onError });

    return (
        <PageContentBlock title='Monitoring'>
            <header className='mon:mb-6'>
                <h1 className='mon:font-header mon:text-2xl mon:font-semibold mon:text-foreground'>Monitoring</h1>
                <p className='mon:mt-1 mon:text-sm mon:leading-relaxed mon:text-foreground/70'>Health of the panel, its nodes and servers. Sampled every {interval === 1 ? 'minute' : `${interval} minutes`}.</p>
            </header>
            {!data.data ? (
                data.isError ? <p className='mon:text-sm mon:text-destructive'>{httpErrorToHuman(data.error)}</p> : <Spinner centered size={Spinner.Size.LARGE} />
            ) : (
                <>
                    <StatTiles summary={data.data.health} />
                    <div className='mon:grid mon:gap-6'>
                        <section className='mon:min-w-0'>
                            <h2 className='mon:mb-3 mon:font-header mon:text-sm mon:font-semibold mon:uppercase mon:tracking-wide mon:text-foreground'>Nodes</h2>
                            <NodesTable nodes={data.data.health.node_list} />
                        </section>
                        <section className='mon:min-w-0'>
                            <h2 className='mon:mb-3 mon:font-header mon:text-sm mon:font-semibold mon:uppercase mon:tracking-wide mon:text-foreground'>Alerts</h2>
                            <AlertsTable alerts={data.data.alerts} showSubject canAcknowledge pending={acknowledging.isPending} onAcknowledge={(id) => acknowledging.mutate(id)} />
                        </section>
                        <div className='mon:grid mon:items-start mon:gap-6 mon:lg:grid-cols-3'>
                            <PanelBox health={data.data.health} />
                            <JobsBox health={data.data.health} />
                            <RulesBox
                                title='Rules for all servers'
                                rules={data.data.rules}
                                channels={data.data.channels}
                                canManage
                                global
                                saving={saving.isPending}
                                removing={removal.isPending}
                                onSave={(input, id, done) => saving.mutate({ input, id, done })}
                                onDelete={(id, done) => removal.mutate({ id, done })}
                            />
                        </div>
                    </div>
                </>
            )}
        </PageContentBlock>
    );
}

function NodesTable({ nodes }: { nodes: NodeHealth[] }) {
    return (
        <Table
            rows={nodes}
            keyOf={(node) => String(node.id)}
            emptyState='No nodes yet.'
            columns={[
                {
                    id: 'name',
                    label: 'Node',
                    cell: (node) => (
                        <div className='mon:min-w-0'>
                            <p className='mon:truncate mon:text-foreground'>{node.name}</p>
                            <p className='mon:truncate mon:text-xs mon:text-muted-foreground'>{node.reachable === false ? `Down since ${formatAgo(node.down_since)}` : [node.os, node.cpu_threads && `${node.cpu_threads} threads`].filter(Boolean).join(' · ')}</p>
                        </div>
                    ),
                },
                {
                    id: 'status',
                    label: 'Status',
                    cell: (node) => (node.reachable === null ? <Pill>Checking</Pill> : node.reachable ? <Pill tone='success'>Online</Pill> : <Pill tone='danger'>Offline</Pill>),
                },
                {
                    id: 'wings',
                    label: 'Wings',
                    cell: (node) => (
                        <span className='mon:flex mon:flex-wrap mon:items-center mon:gap-1'>
                            {node.version ?? '–'}
                            {node.version && node.latest && node.version.replace(/^v/, '') !== node.latest.replace(/^v/, '') && <Pill tone='warning'>{node.latest} available</Pill>}
                        </span>
                    ),
                },
                { id: 'servers', label: 'Running', cell: (node) => `${node.servers.running} / ${node.servers.total}` },
                {
                    id: 'usage',
                    label: 'Usage',
                    cell: (node) => (
                        <div className='mon:grid mon:min-w-40 mon:gap-2'>
                            <UsageBar label={`Memory ${formatMiB(node.memory.used)} of ${formatMiB(node.memory.total)}`} used={node.memory.used} total={node.memory.total} />
                            <UsageBar label={`Disk ${formatMiB(node.disk.used)} of ${formatMiB(node.disk.total)}`} used={node.disk.used} total={node.disk.total} />
                        </div>
                    ),
                },
            ]}
        />
    );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className='mon:flex mon:items-center mon:justify-between mon:gap-3'>
            <span className='mon:text-muted-foreground'>{label}</span>
            <span className='mon:truncate mon:text-right mon:text-foreground'>{children}</span>
        </div>
    );
}

function PanelBox({ health }: { health: Health }) {
    const { host, versions } = health;

    return (
        <TitledGreyBox title='Panel'>
            <div className='mon:grid mon:gap-3 mon:text-sm'>
                <Row label='Version'>{versions.panel}</Row>
                <Row label='PHP'>{versions.php}</Row>
                {versions.database && <Row label='Database'>{versions.database}</Row>}
                {versions.redis && <Row label='Redis'>{versions.redis}</Row>}
                {host.uptime !== null && <Row label='Host uptime'>{formatDuration(host.uptime)}</Row>}
                {host.load && <Row label='Load'>{host.load.map((value) => value.toFixed(2)).join(' · ') + (host.cpus ? ` (${host.cpus} CPUs)` : '')}</Row>}
                {host.memory && (
                    <UsageBar label={`${host.memory.container ? 'Container memory' : 'Memory'} ${formatBytes(host.memory.used)} of ${formatBytes(host.memory.total)}`} used={host.memory.used} total={host.memory.total} />
                )}
                {host.disk.total && host.disk.free !== null && (
                    <UsageBar label={`Storage ${formatBytes(host.disk.total - host.disk.free)} of ${formatBytes(host.disk.total)}`} used={host.disk.total - host.disk.free} total={host.disk.total} />
                )}
            </div>
        </TitledGreyBox>
    );
}

function JobsBox({ health }: { health: Health }) {
    const status = (ok: boolean) => (ok ? <Pill tone='success'>OK</Pill> : <Pill tone='danger'>Late</Pill>);

    return (
        <TitledGreyBox title='Background jobs'>
            <div className='mon:grid mon:gap-3 mon:text-sm'>
                <Row label={`Scheduler · ${formatAgo(health.scheduler.at)}`}>{status(health.scheduler.ok)}</Row>
                <Row label={`Queue worker · ${formatAgo(health.queue.at)}`}>{status(health.queue.ok)}</Row>
                <Row label='Waiting jobs'>
                    {Object.entries(health.queues)
                        .map(([queue, size]) => `${queue} ${size ?? '?'}`)
                        .join(' · ')}
                </Row>
                <Row label='Failed jobs'>{health.failed_jobs > 0 ? <Pill tone='warning'>{health.failed_jobs}</Pill> : '0'}</Row>
            </div>
        </TitledGreyBox>
    );
}

import { http } from '@pterodactyl/sdk';

export type Range = '1h' | '6h' | '24h' | '7d';
export type Metric = 'cpu' | 'memory' | 'disk' | 'offline' | 'node';
export type ChannelType = 'discord' | 'webhook' | 'ntfy' | 'telegram' | 'email';

export interface Point {
    t: number;
    cpu: number | null;
    memory: number | null;
    disk: number | null;
    rx: number | null;
    tx: number | null;
    up: number | null;
}

export interface History {
    range: Range;
    interval: number;
    points: Point[];
    uptime: number | null;
    limits: { cpu: number; memory: number; disk: number };
    current: { state: string; cpu: number; memory: number; disk: number; at: string } | null;
}

export interface Rule {
    id: number;
    metric: Metric;
    threshold: number;
    minutes: number;
    cooldown: number;
    enabled: boolean;
    channels: number[];
    description: string;
    owner: string;
    mine: boolean;
}

export type RuleInput = Pick<Rule, 'metric' | 'threshold' | 'minutes' | 'cooldown' | 'enabled' | 'channels'>;

export interface Alert {
    id: number;
    rule: string;
    metric: Metric;
    global: boolean;
    server: { name: string; identifier: string } | null;
    node: string | null;
    value: number;
    peak: number;
    started_at: string;
    resolved_at: string | null;
    acknowledged_at: string | null;
}

export interface ChannelOption {
    id: number;
    name: string;
    type: ChannelType;
}

export interface Channel extends ChannelOption {
    summary: string;
}

export interface ChannelInput {
    name: string;
    type: ChannelType;
    config: Record<string, string>;
}

export interface ServerMonitoring {
    rules: Rule[];
    alerts: Alert[];
    channels: ChannelOption[];
    push: { host: string; last_up: boolean | null; last_error: string | null; pushed_at: string | null } | null;
}

export interface Freshness {
    at: string | null;
    ok: boolean;
}

export interface Summary {
    servers: { running: number; total: number };
    nodes: { reachable: number; total: number };
    alerts: number;
    scheduler: Freshness;
    queue: Freshness;
    failed_jobs: number;
}

export interface NodeHealth {
    id: number;
    name: string;
    reachable: boolean | null;
    error: string | null;
    down_since: string | null;
    version: string | null;
    latest: string | null;
    os: string | null;
    cpu_threads: number | null;
    containers: { total: number; running: number } | null;
    servers: { running: number; total: number };
    memory: { used: number; allocated: number; total: number };
    disk: { used: number; allocated: number; total: number };
}

export interface Health extends Summary {
    interval: number;
    queues: Record<string, number | null>;
    versions: { panel: string; php: string; database: string | null; redis: string | null };
    host: {
        load: number[] | null;
        cpus: number | null;
        memory: { used: number; total: number; container: boolean } | null;
        disk: { total: number | null; free: number | null };
        uptime: number | null;
    };
    node_list: NodeHealth[];
}

export interface AdminMonitoring {
    health: Health;
    rules: Rule[];
    alerts: Alert[];
    channels: ChannelOption[];
}

const server = (id: string) => `/api/client/servers/${id}/extensions/monitoring`;
const client = '/api/client/extensions/monitoring';
const admin = '/api/admin/extensions/monitoring';

export const keys = {
    server: (id: string) => ['monitoring', 'server', id] as const,
    history: (id: string, range: Range) => ['monitoring', 'history', id, range] as const,
    adminHistory: (id: number, range: Range) => ['monitoring', 'admin-history', id, range] as const,
    channels: ['monitoring', 'channels'] as const,
    admin: ['monitoring', 'admin'] as const,
    summary: ['monitoring', 'summary'] as const,
};

export const getServer = async (id: string) => (await http.get<ServerMonitoring>(server(id))).data;
export const getHistory = async (id: string, range: Range) => (await http.get<History>(`${server(id)}/history`, { params: { range } })).data;
export const saveServerRule = async (id: string, rule: RuleInput, ruleId?: number) =>
    (ruleId ? await http.put<ServerMonitoring>(`${server(id)}/rules/${ruleId}`, rule) : await http.post<ServerMonitoring>(`${server(id)}/rules`, rule)).data;
export const deleteServerRule = async (id: string, ruleId: number) => (await http.delete<ServerMonitoring>(`${server(id)}/rules/${ruleId}`)).data;
export const acknowledgeServerAlert = async (id: string, alertId: number) => (await http.post<ServerMonitoring>(`${server(id)}/alerts/${alertId}/acknowledge`)).data;
export const savePush = async (id: string, url: string | null) => (await http.put<ServerMonitoring>(`${server(id)}/push`, { url })).data;

export const getChannels = async () => (await http.get<{ channels: Channel[] }>(`${client}/channels`)).data.channels;
export const saveChannel = async (channel: ChannelInput, channelId?: number) =>
    (channelId ? await http.put<{ channels: Channel[] }>(`${client}/channels/${channelId}`, channel) : await http.post<{ channels: Channel[] }>(`${client}/channels`, channel)).data.channels;
export const deleteChannel = async (channelId: number) => (await http.delete<{ channels: Channel[] }>(`${client}/channels/${channelId}`)).data.channels;
export const testChannel = async (channelId: number) => http.post(`${client}/channels/${channelId}/test`);

export const getAdmin = async () => (await http.get<AdminMonitoring>(admin)).data;
export const getSummary = async () => (await http.get<Summary>(`${admin}/summary`)).data;
export const getAdminHistory = async (id: number, range: Range) => (await http.get<History>(`${admin}/servers/${id}`, { params: { range } })).data;
export const saveGlobalRule = async (rule: RuleInput, ruleId?: number) =>
    (ruleId ? await http.put<AdminMonitoring>(`${admin}/rules/${ruleId}`, rule) : await http.post<AdminMonitoring>(`${admin}/rules`, rule)).data;
export const deleteGlobalRule = async (ruleId: number) => (await http.delete<AdminMonitoring>(`${admin}/rules/${ruleId}`)).data;
export const acknowledgeAlert = async (alertId: number) => (await http.post<AdminMonitoring>(`${admin}/alerts/${alertId}/acknowledge`)).data;

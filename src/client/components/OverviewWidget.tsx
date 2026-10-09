import { useQuery } from '@tanstack/react-query';
import { getSummary, keys } from '../api';
import StatTiles from './StatTiles';

export default function OverviewWidget() {
    const summary = useQuery({ queryKey: keys.summary, queryFn: getSummary, refetchInterval: 60_000, retry: false });

    return summary.data ? <StatTiles summary={summary.data} linked /> : null;
}

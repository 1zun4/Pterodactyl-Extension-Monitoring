import { expect, test } from 'vitest';
import { formatAgo, formatBytes, formatMiB } from './format';

test('formats sizes and ages', () => {
    expect(formatMiB(512)).toBe('512 MiB');
    expect(formatMiB(2048)).toBe('2.0 GiB');
    expect(formatBytes(1536)).toBe('1.5 KiB');
    expect(formatAgo(new Date(Date.now() - 5 * 60000).toISOString())).toBe('5 min ago');
    expect(formatAgo(null)).toBe('never');
});

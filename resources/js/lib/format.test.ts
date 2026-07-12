import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    formatCompact,
    formatMoney,
    formatQuantity,
    timeAgo,
} from '@/lib/format';

describe('formatQuantity', () => {
    it('groups digits deterministically (pinned locale — SSR/client safe)', () => {
        expect(formatQuantity(1200)).toBe('1,200');
        expect(formatQuantity('12500.5')).toBe('12,500.5');
    });

    it('caps at 4 decimal places', () => {
        expect(formatQuantity(1.123456)).toBe('1.1235');
    });
});

describe('formatCompact', () => {
    it('compacts large numbers for tight axes', () => {
        expect(formatCompact(1200)).toBe('1.2K');
        expect(formatCompact(0)).toBe('0');
    });
});

describe('formatMoney', () => {
    it('formats a known currency amount', () => {
        // Locale is pinned, so the grouped amount is stable regardless of runtime.
        expect(formatMoney(1234.5, 'MYR')).toContain('1,234.50');
    });

    it('falls back to "CODE amount" when Intl rejects a malformed code', () => {
        // A 2-letter code is malformed, so Intl throws and the catch runs (a
        // well-formed but unknown 3-letter code like ZZZ would NOT throw).
        expect(formatMoney(10, 'ZZ')).toBe('ZZ 10.00');
    });
});

describe('timeAgo', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('reports a relative time against now', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-07-12T12:00:00Z'));
        expect(timeAgo('2026-07-12T11:00:00Z')).toBe('1 hour ago');
    });

    it('returns empty for an unparseable timestamp', () => {
        expect(timeAgo('not-a-date')).toBe('');
    });
});

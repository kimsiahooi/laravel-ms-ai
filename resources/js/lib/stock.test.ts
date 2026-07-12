import { describe, expect, it } from 'vitest';
import { stockStatus } from '@/lib/stock';

describe('stockStatus', () => {
    it('is out of stock at or below zero', () => {
        expect(stockStatus(0, 5).key).toBe('out');
        expect(stockStatus(-1, 5).key).toBe('out');
    });

    it('is low at or below the reorder level (inclusive boundary)', () => {
        expect(stockStatus(4, 5).key).toBe('low');
        // Exactly at the reorder level counts as low — this must match the PHP
        // WarehouseItemData.needs_reorder boundary (`on_hand <= min_stock`) and the
        // "when on hand drops to this level…" copy. Guards the UI↔server drift bug.
        expect(stockStatus(5, 5).key).toBe('low');
    });

    it('is ok above the reorder level', () => {
        expect(stockStatus(6, 5).key).toBe('ok');
    });

    it('is ok when no (or a zero) reorder level is set', () => {
        expect(stockStatus(1, null).key).toBe('ok');
        expect(stockStatus(1, 0).key).toBe('ok');
    });
});

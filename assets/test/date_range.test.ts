import { describe, expect, it } from 'vitest';
import { DateTime } from 'luxon';
import { type DateRange, shiftRange } from '../src/date_range';

function shiftIso(start: string, end: string, direction: number): string[] {
    const zone = { zone: 'Europe/Prague' };
    const range: DateRange = [DateTime.fromISO(start, zone), DateTime.fromISO(end, zone)];

    return shiftRange(range, direction).map((day) => day.toISODate() ?? '');
}

describe('shiftRange', () => {
    it.each([
        ['a week', '2026-11-23', '2026-11-29', 1, ['2026-11-30', '2026-12-06']],
        ['a week back', '2026-11-23', '2026-11-29', -1, ['2026-11-16', '2026-11-22']],
        ['a single day', '2026-11-23', '2026-11-23', 1, ['2026-11-24', '2026-11-24']],
        ['30 days', '2026-10-25', '2026-11-23', 1, ['2026-11-24', '2026-12-23']],
        ['a week across the DST change', '2026-10-19', '2026-10-25', 1, ['2026-10-26', '2026-11-01']],
    ])('keeps the length of %s', (_, start, end, direction, expected) => {
        expect(shiftIso(start, end, direction)).toEqual(expected);
    });

    it.each([
        ['November to December', '2026-11-01', '2026-11-30', 1, ['2026-12-01', '2026-12-31']],
        ['March back to February', '2026-03-01', '2026-03-31', -1, ['2026-02-01', '2026-02-28']],
        ['January to February', '2026-01-01', '2026-01-31', 1, ['2026-02-01', '2026-02-28']],
        ['a quarter', '2026-10-01', '2026-12-31', 1, ['2027-01-01', '2027-03-31']],
        ['a year', '2026-01-01', '2026-12-31', -1, ['2025-01-01', '2025-12-31']],
    ])('moves whole calendar months: %s', (_, start, end, direction, expected) => {
        expect(shiftIso(start, end, direction)).toEqual(expected);
    });
});

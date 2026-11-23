import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DateTime } from 'luxon';
import { formatDate, parseFuzzyDate, parseTimeToken, type ParseOptions } from '../src/fuzzy_date_parser';

const CZECH: ParseOptions = { locale: 'cs' };
const CZECH_WITH_TIME: ParseOptions = { locale: 'cs', withTime: true };
const US_ENGLISH: ParseOptions = { locale: 'en-US' };

function parseToIso(input: string, options: ParseOptions): string | null {
    const parsed = parseFuzzyDate(input, options);
    if (parsed === null) {
        return null;
    }

    return options.withTime ? parsed.toFormat("yyyy-MM-dd'T'HH:mm") : parsed.toISODate();
}

describe('parseFuzzyDate', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 10, 23, 10, 0));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it.each([
        ['5.11.2026', '2026-11-05'],
        ['5. 11. 2026', '2026-11-05'],
        ['05.11.26', '2026-11-05'],
        ['5/11/2026', '2026-11-05'],
        ['2026-11-05', '2026-11-05'],
        ['5.11.', '2026-11-05'],
    ])('reads the Czech numeric date "%s"', (input, expected) => {
        expect(parseToIso(input, CZECH)).toBe(expected);
    });

    it.each([
        ['dnes', '2026-11-23'],
        ['Včera', '2026-11-22'],
        ['zitra', '2026-11-24'],
        ['pozítří', '2026-11-25'],
        ['yesterday', '2026-11-22'],
    ])('reads the relative day "%s"', (input, expected) => {
        expect(parseToIso(input, CZECH)).toBe(expected);
    });

    it('reads written month names', () => {
        expect(parseToIso('5 November 2026', CZECH)).toBe('2026-11-05');
    });

    it('reads month-first dates for US English', () => {
        expect(parseToIso('11/5/2026', US_ENGLISH)).toBe('2026-11-05');
        expect(parseToIso('11/5', US_ENGLISH)).toBe('2026-11-05');
    });

    it.each([
        ['5.11.2026 14:30', '2026-11-05T14:30'],
        ['5. 11. 2026 9:05', '2026-11-05T09:05'],
        ['5.11. 1430', '2026-11-05T14:30'],
        ['dnes 8', '2026-11-23T08:00'],
        ['14:30', '2026-11-23T14:30'],
        ['2026-11-05T14:30', '2026-11-05T14:30'],
    ])('reads the date and time "%s"', (input, expected) => {
        expect(parseToIso(input, CZECH_WITH_TIME)).toBe(expected);
    });

    it.each(['', '   ', 'abc', '32.13.2026', '5.11.2026 25:00'])('rejects "%s"', (input) => {
        expect(parseFuzzyDate(input, CZECH_WITH_TIME)).toBeNull();
    });

    it.each(['cs', 'en-US', 'en-GB', 'de'])('parses back what it formats for %s', (locale) => {
        const date = DateTime.fromISO('2026-03-04T07:45');

        const dateOnly = parseFuzzyDate(formatDate(date, { locale }), { locale });
        const withTime = parseFuzzyDate(formatDate(date, { locale, withTime: true }), { locale, withTime: true });

        expect(dateOnly?.toISODate()).toBe('2026-03-04');
        expect(withTime?.toFormat("yyyy-MM-dd'T'HH:mm")).toBe('2026-03-04T07:45');
    });

    it('shows the Czech date the way Czech users write it', () => {
        expect(formatDate(DateTime.fromISO('2026-03-04T07:45'), CZECH_WITH_TIME)).toBe('4. 3. 2026 07:45');
    });
});

describe('parseTimeToken', () => {
    it.each([
        ['9', { hour: 9, minute: 0 }],
        ['930', { hour: 9, minute: 30 }],
        ['0930', { hour: 9, minute: 30 }],
        ['9:30', { hour: 9, minute: 30 }],
        ['9.30', { hour: 9, minute: 30 }],
        ['9h30', { hour: 9, minute: 30 }],
        ['23:59', { hour: 23, minute: 59 }],
    ])('reads "%s"', (input, expected) => {
        expect(parseTimeToken(input)).toEqual(expected);
    });

    it.each(['24', '960', '12:60', '9:5', 'noon'])('rejects "%s"', (input) => {
        expect(parseTimeToken(input)).toBeNull();
    });
});

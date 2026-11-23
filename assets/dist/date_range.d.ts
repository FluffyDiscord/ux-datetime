import type { DateTime } from 'luxon';
export type DateRange = [DateTime, DateTime];
export declare function shiftRange([start, end]: DateRange, direction: number): DateRange;

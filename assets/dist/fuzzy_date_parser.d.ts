import { DateTime } from 'luxon';
export interface ParseOptions {
    locale?: string;
    withTime?: boolean;
}
export interface TimeOfDay {
    hour: number;
    minute: number;
}
export declare function getDateFormat(locale: string): string;
export declare function parseTimeToken(token: string): TimeOfDay | null;
export declare function formatTime({ hour, minute }: TimeOfDay): string;
export declare function parseFuzzyDate(input: string, { locale, withTime }?: ParseOptions): DateTime | null;
export declare function formatDate(dateTime: DateTime, { locale, withTime }?: ParseOptions): string;

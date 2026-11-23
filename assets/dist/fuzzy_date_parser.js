import { DateTime } from 'luxon';
import { fromString } from 'any-date-parser';
const FALLBACK_DATE_FORMAT = 'yyyy-MM-dd';
const TIME_FORMAT = 'HH:mm';
const DAY_FIRST_FORMATS_DATE = [
    'd.M.yyyy',
    'd. M. yyyy',
    'd.M.yy',
    'd. M. yy',
    'dd.MM.yyyy',
    'dd.MM.yy',
    'd/M/yyyy',
    'd/M/yy',
    'd-M-yyyy',
    'yyyy-MM-dd',
];
const MONTH_FIRST_FORMATS_DATE = [
    'M/d/yyyy',
    'M/d/yy',
    'MM/dd/yyyy',
    'yyyy-MM-dd',
];
const DATE_TIME_SUFFIXES = [' H:mm', ' HH:mm', ' H:m'];
const ISO_FORMATS_DATETIME = ["yyyy-MM-dd'T'HH:mm", "yyyy-MM-dd'T'HH:mm:ss"];
const RELATIVE_DAY_OFFSETS = {
    'předevčírem': -2,
    'predevcirem': -2,
    'včera': -1,
    'vcera': -1,
    'yesterday': -1,
    'dnes': 0,
    'today': 0,
    'zítra': 1,
    'zitra': 1,
    'tomorrow': 1,
    'pozítří': 2,
    'pozitri': 2,
};
const ANY_DATE_PARSER_FALLBACK_LOCALE = 'en-US';
const NUMERIC_ONLY_PATTERN = /^[\d\s./:-]+$/;
const DAY_MONTH_PATTERN = /^(\d{1,2})[./]\s*(\d{1,2})\.?$/;
const DATE_AND_TIME_PATTERN = /^(.*\S)\s+(\S+)$/;
const YEAR_TOKEN_PATTERN = /y+/;
export function getDateFormat(locale) {
    const localeFormat = DateTime.parseFormatForOpts(DateTime.DATE_SHORT, { locale }) ?? FALLBACK_DATE_FORMAT;
    return localeFormat.replace(YEAR_TOKEN_PATTERN, 'yyyy');
}
function isMonthFirst(locale) {
    return /^M/.test(getDateFormat(locale));
}
function getDateFormats(locale) {
    const localeFormats = isMonthFirst(locale) ? MONTH_FIRST_FORMATS_DATE : DAY_FIRST_FORMATS_DATE;
    return [getDateFormat(locale), ...localeFormats];
}
function getDateTimeFormats(locale) {
    const dateTimeFormats = getDateFormats(locale).flatMap((format) => DATE_TIME_SUFFIXES.map((suffix) => format + suffix));
    return [...dateTimeFormats, ...ISO_FORMATS_DATETIME];
}
function normalizeInput(input) {
    return input.replace(/\s+/g, ' ').trim();
}
function parseWithFormats(input, formats, locale) {
    for (const format of formats) {
        const dateTime = DateTime.fromFormat(input, format, { locale });
        if (dateTime.isValid) {
            return dateTime;
        }
    }
    return null;
}
export function parseTimeToken(token) {
    const separated = token.trim().match(/^(\d{1,2})[:.h](\d{2})$/);
    if (separated) {
        const hour = Number(separated[1]);
        const minute = Number(separated[2]);
        return hour < 24 && minute < 60 ? { hour, minute } : null;
    }
    const digits = token.trim().match(/^(\d{1,4})$/);
    if (!digits) {
        return null;
    }
    const raw = digits[1];
    if (raw.length <= 2) {
        const hour = Number(raw);
        return hour < 24 ? { hour, minute: 0 } : null;
    }
    const hour = Number(raw.slice(0, -2));
    const minute = Number(raw.slice(-2));
    return hour < 24 && minute < 60 ? { hour, minute } : null;
}
export function formatTime({ hour, minute }) {
    return DateTime.fromObject({ hour, minute }).toFormat(TIME_FORMAT);
}
function parseRelativeDay(token) {
    const offset = RELATIVE_DAY_OFFSETS[token.toLowerCase()];
    if (offset === undefined) {
        return null;
    }
    return DateTime.now().startOf('day').plus({ days: offset });
}
function parseDayAndMonth(token, locale) {
    const dayMonth = token.match(DAY_MONTH_PATTERN);
    if (!dayMonth) {
        return null;
    }
    const [first, second] = [Number(dayMonth[1]), Number(dayMonth[2])];
    const [day, month] = isMonthFirst(locale) ? [second, first] : [first, second];
    const dateTime = DateTime.fromObject({ year: DateTime.now().year, month, day });
    return dateTime.isValid ? dateTime : null;
}
function parseDateToken(token, locale) {
    return parseWithFormats(token, getDateFormats(locale), locale)
        ?? parseDayAndMonth(token, locale)
        ?? parseRelativeDay(token);
}
function parseLoosely(input, locale, withTime) {
    if (!withTime) {
        return parseDateToken(input, locale);
    }
    const split = input.match(DATE_AND_TIME_PATTERN);
    const splitTime = split ? parseTimeToken(split[2]) : null;
    const splitDate = split && splitTime ? parseDateToken(split[1], locale) : null;
    if (splitDate && splitTime) {
        return splitDate.set({ ...splitTime, second: 0, millisecond: 0 });
    }
    const time = parseTimeToken(input);
    if (time) {
        return DateTime.now().set({ ...time, second: 0, millisecond: 0 });
    }
    return parseDateToken(input, locale);
}
function parseWithAnyDateParser(input, locale) {
    const isNumericOnly = NUMERIC_ONLY_PATTERN.test(input);
    if (isNumericOnly) {
        return null;
    }
    for (const parserLocale of [locale, ANY_DATE_PARSER_FALLBACK_LOCALE]) {
        const result = fromString(input, parserLocale);
        if (result.isValid()) {
            return DateTime.fromJSDate(result);
        }
    }
    return null;
}
export function parseFuzzyDate(input, { locale = 'en', withTime = false } = {}) {
    const normalized = normalizeInput(input);
    if (!normalized) {
        return null;
    }
    const formats = withTime ? [...getDateTimeFormats(locale), ...getDateFormats(locale)] : getDateFormats(locale);
    return parseWithFormats(normalized, formats, locale)
        ?? parseLoosely(normalized, locale, withTime)
        ?? parseWithAnyDateParser(normalized, locale);
}
export function formatDate(dateTime, { locale = 'en', withTime = false } = {}) {
    const dateFormat = getDateFormat(locale);
    const format = withTime ? `${dateFormat} ${TIME_FORMAT}` : dateFormat;
    return dateTime.setLocale(locale).toFormat(format);
}

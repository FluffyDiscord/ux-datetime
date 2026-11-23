import { DateTime, type DurationLikeObject, Info } from 'luxon';
import FieldController from './field_controller.js';
import { formatDate, parseFuzzyDate } from './fuzzy_date_parser.js';

type Draft = (DateTime | null)[];
type DayRange = [DateTime, DateTime];

const ISO_DATE_FORMAT = 'yyyy-MM-dd';
const ISO_DATE_TIME_FORMAT = "yyyy-MM-dd'T'HH:mm";
const VIEWPORT_MARGIN = 8;
const POPOVER_ABOVE_CLASS = 'ux-datetime__popover--above';
const FALLBACK_TESTID = 'ux-datetime';
const LOCALE_WEEKS = { useLocaleWeeks: true };

const KEY_OFFSETS: Record<string, DurationLikeObject> = {
    ArrowLeft: { days: -1 },
    ArrowRight: { days: 1 },
    ArrowUp: { weeks: -1 },
    ArrowDown: { weeks: 1 },
    PageUp: { months: -1 },
    PageDown: { months: 1 },
};

export default class PickerController extends FieldController {
    static targets = ['popover', 'months', 'preset', 'previousMonth', 'nextMonth', 'monthTemplate', 'weekdayTemplate', 'dayTemplate'];

    static values = {
        withTime: Boolean,
        submitOnApply: Boolean,
    };

    declare readonly popoverTarget: HTMLElement;
    declare readonly monthsTarget: HTMLElement;
    declare readonly presetTargets: HTMLButtonElement[];
    declare readonly previousMonthTarget: HTMLButtonElement;
    declare readonly nextMonthTarget: HTMLButtonElement;
    declare readonly monthTemplateTarget: HTMLTemplateElement;
    declare readonly weekdayTemplateTarget: HTMLTemplateElement;
    declare readonly dayTemplateTarget: HTMLTemplateElement;
    declare readonly withTimeValue: boolean;
    declare readonly submitOnApplyValue: boolean;

    #draft: Draft = [];
    #viewMonth: DateTime = DateTime.now().startOf('month');
    #focusedDay: DateTime | null = null;
    #suppressNextOpen = false;

    open(): void {
        if (this.#suppressNextOpen) {
            this.#suppressNextOpen = false;

            return;
        }

        if (!this.popoverTarget.hidden) {
            return;
        }

        this.#loadDraftFromInputs();

        const anchor = this.#draft.find((date) => date !== null) ?? this.#today();
        this.#viewMonth = anchor.startOf('month');
        this.#focusedDay = anchor.startOf('day');

        this.popoverTarget.hidden = false;
        this.#setExpanded(true);
        this.#renderCalendar();
        this.#fitPopover();
    }

    close(): void {
        if (this.popoverTarget.hidden) {
            return;
        }

        this.popoverTarget.hidden = true;
        this.#setExpanded(false);
        this.#loadDraftFromInputs();
        this.#renderFields();
    }

    dismiss(event: KeyboardEvent): void {
        if (this.popoverTarget.hidden) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const hadFocusInside = this.popoverTarget.contains(document.activeElement);
        this.close();

        if (hadFocusInside) {
            this.#returnFocus();
        }
    }

    closeOnOutsideClick(event: Event): void {
        const clickedInside = event.composedPath().includes(this.element);
        if (clickedInside) {
            return;
        }

        this.close();
    }

    showPreviousMonth(): void {
        this.#viewMonth = this.#viewMonth.minus({ months: 1 });
        this.#renderCalendar();
    }

    showNextMonth(): void {
        this.#viewMonth = this.#viewMonth.plus({ months: 1 });
        this.#renderCalendar();
    }

    focusCalendar(): void {
        this.open();
        this.#focusDayButton();
    }

    pickDay(event: Event): void {
        const button = event.currentTarget as HTMLButtonElement;
        const day = this.#fromIso(button.dataset.date ?? '');
        this.#focusedDay = day;

        if (!this.#isRange()) {
            this.#apply([this.#withTimeOf(day, this.#draft[0])]);

            return;
        }

        const [start, end] = this.#draft;
        const startsNewRange = start === null || end !== null;
        if (!startsNewRange) {
            this.#apply([start, day]);

            return;
        }

        this.#draft = [day, null];
        this.#resetValidity();
        this.#renderFields();
        this.#renderCalendar();
        this.#focusDayButton();
    }

    navigateDays(event: KeyboardEvent): void {
        const button = (event.target as HTMLElement).closest<HTMLButtonElement>('button[data-date]');
        if (button === null) {
            return;
        }

        const target = this.#getKeyboardTarget(this.#fromIso(button.dataset.date ?? ''), event.key);
        if (target === null) {
            return;
        }

        event.preventDefault();

        const isAllowed = this.#isDayAllowed(target);
        if (!isAllowed) {
            return;
        }

        this.#focusedDay = target;
        this.#showMonthOf(target);
        this.#focusDayButton();
    }

    applyPreset(event: Event): void {
        const button = event.currentTarget as HTMLButtonElement;
        const presetRange = this.#getPresetRange(button.dataset.preset ?? '');
        if (presetRange === null) {
            return;
        }

        const [start, end] = presetRange;
        const range = this.#isRange() ? [start, end] : [this.#withTimeOf(start, this.#draft[0])];

        this.#apply(range);
    }

    clear(): void {
        this.#apply(this.inputTargets.map(() => null));
    }

    commitTyped(event: Event): void {
        this.#commitField(event.target as HTMLInputElement);

        if (!this.popoverTarget.hidden) {
            this.#renderCalendar();
        }
    }

    applyTyped(event: Event): void {
        const isValid = this.#commitField(event.target as HTMLInputElement);
        if (!isValid) {
            return;
        }

        this.#submitDraft();
    }

    protected syncFromInputs(): void {
        const isOpen = !this.popoverTarget.hidden;
        if (isOpen) {
            return;
        }

        this.#loadDraftFromInputs();
        this.#renderFields();
    }

    #isRange(): boolean {
        return this.inputTargets.length > 1;
    }

    #today(): DateTime {
        return DateTime.now().setLocale(this.locale).startOf('day');
    }

    #fromIso(value: string): DateTime {
        return DateTime.fromISO(value, { locale: this.locale });
    }

    #getLimit(value: string): DateTime | null {
        const limit = this.#fromIso(value);

        return limit.isValid ? limit : null;
    }

    #isAllowed(date: DateTime): boolean {
        const min = this.#getLimit(this.minValue);
        const max = this.#getLimit(this.maxValue);
        const isBeforeMin = min !== null && date < min;
        const isAfterMax = max !== null && date > max;

        return !isBeforeMin && !isAfterMax;
    }

    #isDayAllowed(day: DateTime): boolean {
        const min = this.#getLimit(this.minValue);
        const max = this.#getLimit(this.maxValue);
        const endsBeforeMin = min !== null && day.endOf('day') < min;
        const startsAfterMax = max !== null && day.startOf('day') > max;

        return !endsBeforeMin && !startsAfterMax;
    }

    #commitField(field: HTMLInputElement): boolean {
        const index = this.fieldTargets.indexOf(field);
        const typed = field.value.trim();
        const isEmpty = typed === '';
        const parsed = isEmpty ? null : parseFuzzyDate(typed, { locale: this.locale, withTime: this.withTimeValue });
        const date = parsed === null ? null : this.#normalizeDate(parsed);
        const isAllowed = date !== null && this.#isAllowed(date);
        const isValid = isEmpty || isAllowed;

        this.markValidity(field, isValid);

        if (!isValid) {
            return false;
        }

        this.#draft[index] = date;
        this.#writeInputs();
        this.#renderFields();

        return true;
    }

    #apply(range: Draft): void {
        this.#draft = range;
        this.#submitDraft();
    }

    #submitDraft(): void {
        const hadFocusInside = this.popoverTarget.contains(document.activeElement);

        this.#writeInputs();
        this.close();
        this.#resetValidity();
        this.#renderFields();

        if (hadFocusInside) {
            this.#returnFocus();
        }

        if (this.submitOnApplyValue) {
            this.inputTargets[0].form?.requestSubmit();
        }
    }

    #returnFocus(): void {
        const [field] = this.fieldTargets;
        const isFocused = document.activeElement === field;
        if (isFocused) {
            return;
        }

        this.#suppressNextOpen = true;
        field.focus();
    }

    #setExpanded(isExpanded: boolean): void {
        for (const field of this.fieldTargets) {
            field.setAttribute('aria-expanded', String(isExpanded));
        }
    }

    #loadDraftFromInputs(): void {
        this.#draft = this.inputTargets.map((input) => {
            const date = this.#fromIso(input.value);

            return date.isValid ? date : null;
        });
    }

    #writeInputs(): void {
        this.#draft = this.#getOrderedDraft();

        const format = this.withTimeValue ? ISO_DATE_TIME_FORMAT : ISO_DATE_FORMAT;

        this.inputTargets.forEach((input, index) => {
            const date = this.#draft[index] ?? null;

            this.writeInput(input, date === null ? '' : date.toFormat(format));
        });
    }

    #getOrderedDraft(): Draft {
        const [start, end] = this.#draft;
        const isReversed = this.#isRange() && start && end && end < start;

        return isReversed ? [end, start] : [...this.#draft];
    }

    #getOrderedIsoDays(): (string | null)[] {
        return this.#getOrderedDraft().map((date) => date?.toISODate() ?? null);
    }

    #normalizeDate(date: DateTime): DateTime {
        return this.withTimeValue ? date.set({ second: 0, millisecond: 0 }) : date.startOf('day');
    }

    #withTimeOf(day: DateTime, timeSource: DateTime | null | undefined): DateTime {
        if (!this.withTimeValue) {
            return day;
        }

        const source = timeSource ?? day.startOf('day');

        return day.set({ hour: source.hour, minute: source.minute });
    }

    #getPresetRange(key: string): DayRange | null {
        const today = this.#today();
        const thisWeek = today.startOf('week', LOCALE_WEEKS);
        const thisMonth = today.startOf('month');
        const thisYear = today.startOf('year');

        const ranges: Record<string, DayRange> = {
            today: [today, today],
            yesterday: [today.minus({ days: 1 }), today.minus({ days: 1 })],
            tomorrow: [today.plus({ days: 1 }), today.plus({ days: 1 })],
            this_week: [thisWeek, thisWeek.plus({ days: 6 })],
            last_week: [thisWeek.minus({ weeks: 1 }), thisWeek.minus({ days: 1 })],
            this_month: [thisMonth, thisMonth.endOf('month').startOf('day')],
            last_month: [thisMonth.minus({ months: 1 }), thisMonth.minus({ days: 1 })],
            last_30_days: [today.minus({ days: 29 }), today],
            this_year: [thisYear, thisYear.endOf('year').startOf('day')],
            last_year: [thisYear.minus({ years: 1 }), thisYear.minus({ days: 1 })],
        };

        return ranges[key] ?? null;
    }

    #getKeyboardTarget(day: DateTime, key: string): DateTime | null {
        if (key === 'Home') {
            return day.startOf('week', LOCALE_WEEKS);
        }

        if (key === 'End') {
            return day.startOf('week', LOCALE_WEEKS).plus({ days: 6 });
        }

        const offset = KEY_OFFSETS[key];

        return offset === undefined ? null : day.plus(offset);
    }

    #showMonthOf(day: DateTime): void {
        const month = day.startOf('month');
        const lastVisibleMonth = this.#viewMonth.plus({ months: this.#getMonthCount() - 1 });

        if (month < this.#viewMonth) {
            this.#viewMonth = month;
        }

        if (month > lastVisibleMonth) {
            this.#viewMonth = month.minus({ months: this.#getMonthCount() - 1 });
        }

        this.#renderCalendar();
    }

    #focusDayButton(): void {
        const focusable = this.monthsTarget.querySelector<HTMLButtonElement>('button[data-date][tabindex="0"]');

        focusable?.focus();
    }

    #resetValidity(): void {
        for (const field of this.fieldTargets) {
            this.markValidity(field, true);
        }
    }

    #renderFields(): void {
        const ordered = this.#getOrderedDraft();

        this.fieldTargets.forEach((field, index) => {
            const keepsTypedText = field.validity.customError;
            if (keepsTypedText) {
                return;
            }

            const date = ordered[index] ?? null;

            field.value = date === null ? '' : formatDate(date, { locale: this.locale, withTime: this.withTimeValue });
        });
    }

    #getMonthCount(): number {
        return this.#isRange() ? 2 : 1;
    }

    #renderCalendar(): void {
        const months: HTMLElement[] = [];

        for (let offset = 0; offset < this.#getMonthCount(); offset++) {
            months.push(this.#buildMonth(this.#viewMonth.plus({ months: offset })));
        }

        this.monthsTarget.replaceChildren(...months);
        this.#ensureFocusableDay();
        this.#renderMonthNavigation();
        this.#renderPresets();
    }

    #ensureFocusableDay(): void {
        const hasFocusableDay = this.monthsTarget.querySelector('button[data-date][tabindex="0"]') !== null;
        if (hasFocusableDay) {
            return;
        }

        const firstEnabledDay = this.monthsTarget.querySelector<HTMLButtonElement>('button[data-date]:not([data-outside]):not(:disabled)');
        firstEnabledDay?.setAttribute('tabindex', '0');
    }

    #renderMonthNavigation(): void {
        const previousMonthLastDay = this.#viewMonth.minus({ days: 1 });
        const nextMonthFirstDay = this.#viewMonth.plus({ months: this.#getMonthCount() });

        this.previousMonthTarget.disabled = !this.#isDayAllowed(previousMonthLastDay);
        this.nextMonthTarget.disabled = !this.#isDayAllowed(nextMonthFirstDay);
    }

    #renderPresets(): void {
        const [start, end] = this.#getOrderedIsoDays();

        for (const preset of this.presetTargets) {
            const presetRange = this.#getPresetRange(preset.dataset.preset ?? '');
            if (presetRange === null) {
                preset.hidden = true;

                continue;
            }

            const [presetStart, presetEnd] = presetRange.map((date) => date.toISODate());
            const isActive = start === presetStart && (end ?? start) === presetEnd;
            const isAllowed = presetRange.every((date) => this.#isDayAllowed(date));

            preset.setAttribute('aria-pressed', String(isActive));
            preset.hidden = !isAllowed;
        }
    }

    #buildMonth(monthStart: DateTime): HTMLElement {
        const locale = this.locale;
        const month = this.#cloneTemplate(this.monthTemplateTarget);
        const name = month.querySelector('[data-month-name]') as HTMLElement;
        const grid = month.querySelector('[data-month-grid]') as HTMLElement;

        name.textContent = monthStart.setLocale(locale).toFormat('LLLL yyyy');

        const weekdays = Info.weekdays('short', { locale });
        const weekStartIndex = Info.getStartOfWeek({ locale }) - 1;
        const orderedWeekdays = [...weekdays.slice(weekStartIndex), ...weekdays.slice(0, weekStartIndex)];

        for (const weekday of orderedWeekdays) {
            const head = this.#cloneTemplate(this.weekdayTemplateTarget);
            head.textContent = weekday;
            grid.append(head);
        }

        const lastDay = monthStart.endOf('month');
        let day = monthStart.setLocale(locale).startOf('week', LOCALE_WEEKS);

        while (day <= lastDay) {
            grid.append(this.#buildDay(day, monthStart.month));
            day = day.plus({ days: 1 });
        }

        return month;
    }

    #buildDay(day: DateTime, month: number): HTMLElement {
        const [start, end] = this.#getOrderedIsoDays();
        const iso = day.toISODate() ?? '';
        const button = this.#cloneTemplate(this.dayTemplateTarget) as HTMLButtonElement;
        const isOutside = day.month !== month;
        const isToday = iso === this.#today().toISODate();
        const isEdge = iso === start || iso === end;
        const isAfterStart = start !== null && iso > start;
        const isBeforeEnd = end !== null && iso < end;
        const isFocused = !isOutside && iso === this.#focusedDay?.toISODate();
        const testid = this.element.dataset.testid ?? FALLBACK_TESTID;

        button.textContent = String(day.day);
        button.dataset.date = iso;
        button.setAttribute('data-testid', `${testid}-day-${iso}`);
        button.setAttribute('aria-label', day.toLocaleString(DateTime.DATE_HUGE));
        button.setAttribute('aria-pressed', String(isEdge));
        button.setAttribute('tabindex', isFocused ? '0' : '-1');
        button.toggleAttribute('data-outside', isOutside);
        button.toggleAttribute('aria-hidden', isOutside);
        button.toggleAttribute('data-today', isToday);
        button.toggleAttribute('data-edge', isEdge);
        button.toggleAttribute('data-in-range', isAfterStart && isBeforeEnd);
        button.disabled = isOutside || !this.#isDayAllowed(day);

        if (isToday) {
            button.setAttribute('aria-current', 'date');
        }

        return button;
    }

    #fitPopover(): void {
        const popover = this.popoverTarget;
        popover.style.left = '';
        popover.classList.remove(POPOVER_ABOVE_CLASS);

        const bounds = popover.getBoundingClientRect();
        const fieldBounds = this.element.getBoundingClientRect();
        const overflowRight = bounds.right - (window.innerWidth - VIEWPORT_MARGIN);
        if (overflowRight > 0) {
            const rightAlignedShift = bounds.right - fieldBounds.right;
            const shift = Math.min(Math.max(rightAlignedShift, overflowRight), bounds.left - VIEWPORT_MARGIN);
            popover.style.left = `${-shift}px`;
        }

        const overflowsBottom = bounds.bottom > window.innerHeight - VIEWPORT_MARGIN;
        const fitsAbove = fieldBounds.top - bounds.height > VIEWPORT_MARGIN;
        if (overflowsBottom && fitsAbove) {
            popover.classList.add(POPOVER_ABOVE_CLASS);
        }
    }

    #cloneTemplate(template: HTMLTemplateElement): HTMLElement {
        return template.content.firstElementChild?.cloneNode(true) as HTMLElement;
    }
}

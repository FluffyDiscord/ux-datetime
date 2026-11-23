import { DateTime, Info } from 'luxon';
import { shiftRange } from './date_range.js';
import FieldController from './field_controller.js';
import { formatDate, parseFuzzyDate } from './fuzzy_date_parser.js';
const ISO_DATE_FORMAT = 'yyyy-MM-dd';
const ISO_DATE_TIME_FORMAT = "yyyy-MM-dd'T'HH:mm";
const VIEWPORT_MARGIN = 8;
const POPOVER_ABOVE_CLASS = 'ux-datetime__popover--above';
const FALLBACK_TESTID = 'ux-datetime';
const LOCALE_WEEKS = { useLocaleWeeks: true };
const KEY_OFFSETS = {
    ArrowLeft: { days: -1 },
    ArrowRight: { days: 1 },
    ArrowUp: { weeks: -1 },
    ArrowDown: { weeks: 1 },
    PageUp: { months: -1 },
    PageDown: { months: 1 },
};
export default class PickerController extends FieldController {
    static { this.targets = [
        'popover',
        'months',
        'preset',
        'previousMonth',
        'nextMonth',
        'previousPeriod',
        'nextPeriod',
        'monthTemplate',
        'weekdayTemplate',
        'dayTemplate',
    ]; }
    static { this.values = {
        withTime: Boolean,
        submitOnApply: Boolean,
        allowedDates: Array,
    }; }
    #draft = [];
    #viewMonth = DateTime.now().startOf('month');
    #focusedDay = null;
    #suppressNextOpen = false;
    #allowedDays = null;
    #sortedAllowedDays = [];
    allowedDatesValueChanged() {
        this.#sortedAllowedDays = this.hasAllowedDatesValue ? [...this.allowedDatesValue].sort() : [];
        this.#allowedDays = this.hasAllowedDatesValue ? new Set(this.#sortedAllowedDays) : null;
        this.#renderPeriodButtons();
    }
    connect() {
        const isFocusedBeforeConnect = this.element.contains(document.activeElement);
        if (isFocusedBeforeConnect) {
            this.open();
        }
    }
    open() {
        if (this.#suppressNextOpen) {
            this.#suppressNextOpen = false;
            return;
        }
        if (!this.popoverTarget.hidden) {
            return;
        }
        this.#loadDraftFromInputs();
        const anchor = this.#draft.find((date) => date !== null) ?? this.#getDefaultDay();
        this.#viewMonth = anchor.startOf('month');
        this.#focusedDay = anchor.startOf('day');
        this.popoverTarget.hidden = false;
        this.#setExpanded(true);
        this.#renderCalendar();
        this.#fitPopover();
    }
    close() {
        if (this.popoverTarget.hidden) {
            return;
        }
        this.popoverTarget.hidden = true;
        this.#setExpanded(false);
        this.#loadDraftFromInputs();
        this.#renderFields();
    }
    dismiss(event) {
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
    closeOnOutsideClick(event) {
        const clickedInside = event.composedPath().includes(this.element);
        if (clickedInside) {
            return;
        }
        this.close();
    }
    showPreviousMonth() {
        this.#viewMonth = this.#viewMonth.minus({ months: 1 });
        this.#renderCalendar();
    }
    showNextMonth() {
        this.#viewMonth = this.#viewMonth.plus({ months: 1 });
        this.#renderCalendar();
    }
    focusCalendar() {
        this.open();
        this.#focusDayButton();
    }
    showPreviousPeriod() {
        this.#shiftPeriod(-1);
    }
    showNextPeriod() {
        this.#shiftPeriod(1);
    }
    focusFieldAt(event) {
        const clickedField = this.fieldTargets.includes(event.target);
        const clickedButton = event.target.closest('button') !== null;
        if (clickedField || clickedButton) {
            return;
        }
        const field = this.#getFieldForClick(event.clientX);
        if (field.disabled) {
            return;
        }
        event.preventDefault();
        field.focus();
        field.setSelectionRange(field.value.length, field.value.length);
        this.open();
    }
    pickDay(event) {
        const button = event.currentTarget;
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
    navigateDays(event) {
        const button = event.target.closest('button[data-date]');
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
    applyPreset(event) {
        const [start, end] = this.#getPresetRange(event.currentTarget);
        const range = this.#isRange() ? [start, end] : [this.#withTimeOf(start, this.#draft[0])];
        this.#apply(range);
    }
    clear() {
        this.#apply(this.inputTargets.map(() => null));
    }
    commitTyped(event) {
        this.#commitField(event.target);
        if (!this.popoverTarget.hidden) {
            this.#renderCalendar();
        }
    }
    applyTyped(event) {
        const isValid = this.#commitField(event.target);
        if (!isValid) {
            return;
        }
        this.#submitDraft();
    }
    syncFromInputs() {
        const isOpen = !this.popoverTarget.hidden;
        if (isOpen) {
            return;
        }
        this.#loadDraftFromInputs();
        this.#renderFields();
    }
    #isRange() {
        return this.inputTargets.length > 1;
    }
    #today() {
        return DateTime.now().setLocale(this.locale).startOf('day');
    }
    #fromIso(value) {
        return DateTime.fromISO(value, { locale: this.locale });
    }
    #getLimit(value) {
        const limit = this.#fromIso(value);
        return limit.isValid ? limit : null;
    }
    #isAllowed(date) {
        const min = this.#getLimit(this.minValue);
        const max = this.#getLimit(this.maxValue);
        const isBeforeMin = min !== null && date < min;
        const isAfterMax = max !== null && date > max;
        return !isBeforeMin && !isAfterMax && this.#isListedDay(date);
    }
    #isDayAllowed(day) {
        return this.#isWithinLimits(day) && this.#isListedDay(day);
    }
    #isWithinLimits(day) {
        const min = this.#getLimit(this.minValue);
        const max = this.#getLimit(this.maxValue);
        const endsBeforeMin = min !== null && day.endOf('day') < min;
        const startsAfterMax = max !== null && day.startOf('day') > max;
        if (endsBeforeMin || startsAfterMax) {
            return false;
        }
        if (this.#allowedDays === null) {
            return true;
        }
        const iso = day.toISODate() ?? '';
        const firstAllowedDay = this.#sortedAllowedDays.at(0) ?? '';
        const lastAllowedDay = this.#sortedAllowedDays.at(-1) ?? '';
        return iso >= firstAllowedDay && iso <= lastAllowedDay;
    }
    #isListedDay(day) {
        if (this.#allowedDays === null) {
            return true;
        }
        return this.#allowedDays.has(day.toISODate() ?? '');
    }
    #getDefaultDay() {
        const today = this.#today();
        if (this.#allowedDays === null) {
            return today;
        }
        const todayIso = today.toISODate() ?? '';
        const nextAllowedDay = this.#sortedAllowedDays.find((day) => day >= todayIso) ?? this.#sortedAllowedDays.at(-1);
        return nextAllowedDay === undefined ? today : this.#fromIso(nextAllowedDay);
    }
    #commitField(field) {
        const index = this.fieldTargets.indexOf(field);
        const typed = field.value.trim();
        const isEmpty = typed === '';
        const parsed = isEmpty ? null : parseFuzzyDate(typed, { locale: this.locale, withTime: this.withTimeValue });
        const date = parsed === null ? null : this.#normalizeDate(parsed);
        const isAllowed = date !== null && this.#isAllowed(date);
        const isValid = isEmpty || isAllowed;
        const message = date === null ? field.dataset.invalidMessage : field.dataset.unavailableMessage;
        this.markValidity(field, isValid, message);
        if (!isValid) {
            return false;
        }
        this.#draft[index] = date;
        this.#writeInputs();
        this.#renderFields();
        return true;
    }
    #apply(range) {
        this.#draft = range;
        this.#submitDraft();
    }
    #submitDraft() {
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
    #returnFocus() {
        const [field] = this.fieldTargets;
        const isFocused = document.activeElement === field;
        if (isFocused) {
            return;
        }
        this.#suppressNextOpen = true;
        field.focus();
    }
    #getFieldForClick(clientX) {
        const emptyField = this.fieldTargets.find((field) => field.value === '');
        if (emptyField) {
            return emptyField;
        }
        const distances = this.fieldTargets.map((field) => {
            const bounds = field.getBoundingClientRect();
            return Math.abs(clientX - (bounds.left + bounds.width / 2));
        });
        const nearestIndex = distances.indexOf(Math.min(...distances));
        return this.fieldTargets[nearestIndex];
    }
    #setExpanded(isExpanded) {
        for (const field of this.fieldTargets) {
            field.setAttribute('aria-expanded', String(isExpanded));
        }
    }
    #loadDraftFromInputs() {
        this.#draft = this.inputTargets.map((input) => {
            const date = this.#fromIso(input.value);
            return date.isValid ? date : null;
        });
    }
    #writeInputs() {
        this.#draft = this.#getOrderedDraft();
        const format = this.withTimeValue ? ISO_DATE_TIME_FORMAT : ISO_DATE_FORMAT;
        this.inputTargets.forEach((input, index) => {
            const date = this.#draft[index] ?? null;
            this.writeInput(input, date === null ? '' : date.toFormat(format));
        });
    }
    #getOrderedDraft() {
        const [start, end] = this.#draft;
        const isReversed = this.#isRange() && start && end && end < start;
        return isReversed ? [end, start] : [...this.#draft];
    }
    #getOrderedIsoDays() {
        return this.#getOrderedDraft().map((date) => date?.toISODate() ?? null);
    }
    #normalizeDate(date) {
        return this.withTimeValue ? date.set({ second: 0, millisecond: 0 }) : date.startOf('day');
    }
    #withTimeOf(day, timeSource) {
        if (!this.withTimeValue) {
            return day;
        }
        const source = timeSource ?? day.startOf('day');
        return day.set({ hour: source.hour, minute: source.minute });
    }
    #getPresetRange(preset) {
        return [this.#fromIso(preset.dataset.from ?? ''), this.#fromIso(preset.dataset.to ?? '')];
    }
    #getKeyboardTarget(day, key) {
        if (key === 'Home') {
            return day.startOf('week', LOCALE_WEEKS);
        }
        if (key === 'End') {
            return day.startOf('week', LOCALE_WEEKS).plus({ days: 6 });
        }
        const offset = KEY_OFFSETS[key];
        if (offset === undefined) {
            return null;
        }
        let target = day.plus(offset);
        while (this.#isWithinLimits(target) && !this.#isListedDay(target)) {
            target = target.plus(offset);
        }
        return target;
    }
    #showMonthOf(day) {
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
    #focusDayButton() {
        const focusable = this.monthsTarget.querySelector('button[data-date][tabindex="0"]');
        focusable?.focus();
    }
    #resetValidity() {
        for (const field of this.fieldTargets) {
            this.markValidity(field, true);
        }
    }
    #renderFields() {
        const ordered = this.#getOrderedDraft();
        this.fieldTargets.forEach((field, index) => {
            const keepsTypedText = field.validity.customError;
            if (keepsTypedText) {
                return;
            }
            const date = ordered[index] ?? null;
            field.value = date === null ? '' : formatDate(date, { locale: this.locale, withTime: this.withTimeValue });
        });
        this.#renderPeriodButtons();
    }
    #getAppliedRange() {
        const [start, end] = this.inputTargets.map((input) => this.#fromIso(input.value));
        const isComplete = this.#isRange() && start.isValid && end.isValid;
        return isComplete ? [start, end] : null;
    }
    #getShiftedRange(direction) {
        const range = this.#getAppliedRange();
        if (range === null) {
            return null;
        }
        const shifted = shiftRange(range, direction);
        const isAllowed = shifted.every((day) => this.#isDayAllowed(day));
        return isAllowed ? shifted : null;
    }
    #shiftPeriod(direction) {
        const shifted = this.#getShiftedRange(direction);
        if (shifted === null) {
            return;
        }
        this.#apply(shifted);
    }
    #renderPeriodButtons() {
        const isFieldDisabled = this.fieldTargets[0].disabled;
        for (const button of this.previousPeriodTargets) {
            button.disabled = isFieldDisabled || this.#getShiftedRange(-1) === null;
        }
        for (const button of this.nextPeriodTargets) {
            button.disabled = isFieldDisabled || this.#getShiftedRange(1) === null;
        }
    }
    #getMonthCount() {
        return this.#isRange() ? 2 : 1;
    }
    #renderCalendar() {
        const months = [];
        for (let offset = 0; offset < this.#getMonthCount(); offset++) {
            months.push(this.#buildMonth(this.#viewMonth.plus({ months: offset })));
        }
        this.monthsTarget.replaceChildren(...months);
        this.#ensureFocusableDay();
        this.#renderMonthNavigation();
        this.#renderPresets();
    }
    #ensureFocusableDay() {
        const hasFocusableDay = this.monthsTarget.querySelector('button[data-date][tabindex="0"]') !== null;
        if (hasFocusableDay) {
            return;
        }
        const firstEnabledDay = this.monthsTarget.querySelector('button[data-date]:not([data-outside]):not(:disabled)');
        firstEnabledDay?.setAttribute('tabindex', '0');
    }
    #renderMonthNavigation() {
        const previousMonthLastDay = this.#viewMonth.minus({ days: 1 });
        const nextMonthFirstDay = this.#viewMonth.plus({ months: this.#getMonthCount() });
        this.previousMonthTarget.disabled = !this.#isWithinLimits(previousMonthLastDay);
        this.nextMonthTarget.disabled = !this.#isWithinLimits(nextMonthFirstDay);
    }
    #renderPresets() {
        const [start, end] = this.#getOrderedIsoDays();
        for (const preset of this.presetTargets) {
            const presetRange = this.#getPresetRange(preset);
            const isActive = start === preset.dataset.from && (end ?? start) === preset.dataset.to;
            const isAllowed = presetRange.every((date) => this.#isDayAllowed(date));
            preset.setAttribute('aria-pressed', String(isActive));
            preset.hidden = !isAllowed;
        }
    }
    #buildMonth(monthStart) {
        const locale = this.locale;
        const month = this.#cloneTemplate(this.monthTemplateTarget);
        const name = month.querySelector('[data-month-name]');
        const grid = month.querySelector('[data-month-grid]');
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
    #buildDay(day, month) {
        const [start, end] = this.#getOrderedIsoDays();
        const iso = day.toISODate() ?? '';
        const button = this.#cloneTemplate(this.dayTemplateTarget);
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
    #fitPopover() {
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
    #cloneTemplate(template) {
        return template.content.firstElementChild?.cloneNode(true);
    }
}

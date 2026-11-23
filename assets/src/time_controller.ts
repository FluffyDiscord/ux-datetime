import { DateTime } from 'luxon';
import FieldController from './field_controller.js';
import { formatTime, parseTimeToken, type TimeOfDay } from './fuzzy_date_parser.js';

const MINUTES_PER_DAY = 24 * 60;
const HTML_TIME_LENGTH = 5;

export default class TimeController extends FieldController {
    static values = {
        stepMinutes: { type: Number, default: 15 },
    };

    declare readonly stepMinutesValue: number;

    commitTyped(event: Event): void {
        this.#commit(event.target as HTMLInputElement);
    }

    stepUp(event: Event): void {
        this.#step(event.target as HTMLInputElement, 1);
    }

    stepDown(event: Event): void {
        this.#step(event.target as HTMLInputElement, -1);
    }

    protected syncFromInputs(): void {
        const [input] = this.inputTargets;
        const [field] = this.fieldTargets;
        const keepsTypedText = field.validity.customError;
        if (keepsTypedText) {
            return;
        }

        const time = parseTimeToken(input.value.slice(0, HTML_TIME_LENGTH));

        field.value = time === null ? '' : formatTime(time);
    }

    #commit(field: HTMLInputElement): void {
        const typed = field.value.trim();
        const isEmpty = typed === '';
        const time = isEmpty ? null : parseTimeToken(typed);
        const isAllowed = time !== null && this.#isAllowed(time);
        const isValid = isEmpty || isAllowed;

        this.markValidity(field, isValid);

        if (!isValid) {
            return;
        }

        const value = time === null ? '' : formatTime(time);
        field.value = value;
        this.writeInput(this.inputTargets[0], value);
    }

    #step(field: HTMLInputElement, direction: number): void {
        const now = DateTime.now();
        const current = parseTimeToken(field.value) ?? { hour: now.hour, minute: now.minute };
        const minutes = current.hour * 60 + current.minute;
        const step = this.stepMinutesValue;
        const snapped = direction > 0 ? Math.floor(minutes / step) * step + step : Math.ceil(minutes / step) * step - step;
        const wrapped = (snapped + MINUTES_PER_DAY) % MINUTES_PER_DAY;

        field.value = formatTime({ hour: Math.floor(wrapped / 60), minute: wrapped % 60 });
        this.#commit(field);
    }

    #isAllowed(time: TimeOfDay): boolean {
        const formatted = formatTime(time);
        const isBeforeMin = this.minValue !== '' && formatted < this.minValue;
        const isAfterMax = this.maxValue !== '' && formatted > this.maxValue;

        return !isBeforeMin && !isAfterMax;
    }
}

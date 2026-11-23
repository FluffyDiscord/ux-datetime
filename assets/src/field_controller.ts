import { Controller } from '@hotwired/stimulus';

const FALLBACK_LOCALE = 'en';

export default abstract class FieldController extends Controller<HTMLElement> {
    static targets: string[] = ['input', 'field'];

    static values: typeof Controller.values = {
        values: Array,
        locale: String,
        min: String,
        max: String,
    };

    declare readonly inputTargets: HTMLInputElement[];
    declare readonly fieldTargets: HTMLInputElement[];
    declare readonly localeValue: string;
    declare readonly minValue: string;
    declare readonly maxValue: string;

    valuesValueChanged(): void {
        this.syncFromInputs();
    }

    blockInvalidSubmit(event: SubmitEvent): void {
        const form = event.target as HTMLFormElement;
        const sitsInsideForm = form.contains(this.element);
        const submitsWithForm = this.inputTargets.some((input) => input.form === form);
        if (!sitsInsideForm && !submitsWithForm) {
            return;
        }

        const invalidField = this.fieldTargets.find((field) => !field.checkValidity());
        if (invalidField === undefined) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        invalidField.focus();
    }

    protected abstract syncFromInputs(): void;

    protected get locale(): string {
        const locale = this.localeValue || document.documentElement.lang || FALLBACK_LOCALE;

        return locale.replace('_', '-');
    }

    protected markValidity(field: HTMLInputElement, isValid: boolean, message = field.dataset.invalidMessage): void {
        field.setAttribute('aria-invalid', String(!isValid));
        field.setCustomValidity(isValid ? '' : (message ?? ''));
    }

    protected writeInput(input: HTMLInputElement, value: string): void {
        if (input.value === value) {
            return;
        }

        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

import { Controller } from '@hotwired/stimulus';
const FALLBACK_LOCALE = 'en';
export default class FieldController extends Controller {
    static { this.targets = ['input', 'field']; }
    static { this.values = {
        values: Array,
        locale: String,
        min: String,
        max: String,
    }; }
    valuesValueChanged() {
        this.syncFromInputs();
    }
    blockInvalidSubmit(event) {
        const form = event.target;
        const belongsToForm = form.contains(this.element);
        if (!belongsToForm) {
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
    get locale() {
        const locale = this.localeValue || document.documentElement.lang || FALLBACK_LOCALE;
        return locale.replace('_', '-');
    }
    markValidity(field, isValid) {
        field.setAttribute('aria-invalid', String(!isValid));
        field.setCustomValidity(isValid ? '' : (field.dataset.invalidMessage ?? ''));
    }
    writeInput(input, value) {
        if (input.value === value) {
            return;
        }
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

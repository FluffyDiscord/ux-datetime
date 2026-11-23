import { Controller } from '@hotwired/stimulus';
export default abstract class FieldController extends Controller<HTMLElement> {
    static targets: string[];
    static values: typeof Controller.values;
    readonly inputTargets: HTMLInputElement[];
    readonly fieldTargets: HTMLInputElement[];
    readonly localeValue: string;
    readonly minValue: string;
    readonly maxValue: string;
    valuesValueChanged(): void;
    blockInvalidSubmit(event: SubmitEvent): void;
    protected abstract syncFromInputs(): void;
    protected get locale(): string;
    protected markValidity(field: HTMLInputElement, isValid: boolean, message?: string | undefined): void;
    protected writeInput(input: HTMLInputElement, value: string): void;
}

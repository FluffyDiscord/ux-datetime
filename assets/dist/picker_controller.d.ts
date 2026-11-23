import FieldController from './field_controller.js';
export default class PickerController extends FieldController {
    #private;
    static targets: string[];
    static values: {
        withTime: BooleanConstructor;
        submitOnApply: BooleanConstructor;
    };
    readonly popoverTarget: HTMLElement;
    readonly monthsTarget: HTMLElement;
    readonly presetTargets: HTMLButtonElement[];
    readonly previousMonthTarget: HTMLButtonElement;
    readonly nextMonthTarget: HTMLButtonElement;
    readonly monthTemplateTarget: HTMLTemplateElement;
    readonly weekdayTemplateTarget: HTMLTemplateElement;
    readonly dayTemplateTarget: HTMLTemplateElement;
    readonly withTimeValue: boolean;
    readonly submitOnApplyValue: boolean;
    open(): void;
    close(): void;
    dismiss(event: KeyboardEvent): void;
    closeOnOutsideClick(event: Event): void;
    showPreviousMonth(): void;
    showNextMonth(): void;
    focusCalendar(): void;
    pickDay(event: Event): void;
    navigateDays(event: KeyboardEvent): void;
    applyPreset(event: Event): void;
    clear(): void;
    commitTyped(event: Event): void;
    applyTyped(event: Event): void;
    protected syncFromInputs(): void;
}

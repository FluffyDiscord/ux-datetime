import FieldController from './field_controller.js';
export default class TimeController extends FieldController {
    #private;
    static values: {
        stepMinutes: {
            type: NumberConstructor;
            default: number;
        };
    };
    readonly stepMinutesValue: number;
    commitTyped(event: Event): void;
    stepUp(event: Event): void;
    stepDown(event: Event): void;
    protected syncFromInputs(): void;
}

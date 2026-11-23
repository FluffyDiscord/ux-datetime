# Changelog

## 0.2.0

Nothing to change on upgrade. Existing options and markup work as before.

### Added

- `allowed_dates` option and `allowedDates` component prop: only the listed days can be picked, typed or submitted. Form types also reject other days on the server.
- `period_buttons` on `DateRangePickerType` and `periodButtons` prop: previous / next period buttons that move a range by its own length.
- `submit_on_apply` option on every form type.
- `input_attr` option, and an `attr` key on each component input: attributes for the hidden inputs, such as `data-model`.
- `--ux-datetime-period-button-size` variable.

### Changed

- `attr.class` on a form type goes on the field's wrapper.
- `attr.form` on a form type goes on the hidden inputs.
- A field joined to a `<form>` through `form=` blocks that form's submit while its text is invalid.
- A typed date outside `min` / `max` says `This date can't be picked.` instead of the format hint.

## 0.1.2

### Fixed

- A click anywhere in the field focuses an input and opens the calendar.

## 0.1.1

### Fixed

- The range field fits its dates, the inputs leave room for the caret, and the Tabler theme fills the active preset.
- Range inputs fall back to an 11ch text width where `field-sizing` is missing.

## 0.1.0

First release.

- Date, date-time, time and date-range picker form types.
- Stimulus picker and time controllers with free-form typing.
- 22 built-in presets, plus your own in one class.
- `min` / `max` from validator constraints.
- Global switch for every `single_text` date field, and the Sylius admin integration.
- CSS-variable theming with Tabler and daisyUI themes.

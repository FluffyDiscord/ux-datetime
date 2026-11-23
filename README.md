# UX Datetime

Toggl-style date, date-time, time and date-range picker for Symfony forms. Stimulus controller plus Twig component, no CSS framework needed.

Sylius users: see [Sylius](#sylius). After [Setup](#setup) the whole admin uses the picker.

## Features

- [Four form types](#form-types) — submit exactly what Symfony's `DateType`, `DateTimeType` and `TimeType` expect
- [Free-form typing](#typing) — `5.11.`, `dnes`, `tomorrow 14:30`, `930` all work
- [Global switch](#global-switch) — every `single_text` date, date-time and time field becomes the picker
- [Sylius admin](#sylius) — every admin date field and grid date filter, the shop untouched
- [Live Components](#live-components) — survives re-renders without losing typed text
- [Theming](#theming) — every colour, size and spacing is a CSS variable, plus [Tabler and daisyUI presets](#presets)
- [Keyboard and screen readers](#keyboard)

## Installation

```shell
composer require fluffydiscord/ux-datetime
```

## Setup

### Vite (vite-plugin-symfony)

Symfony Flex writes steps 1 and 2 on `composer require`. Check them, then run the install.

1. Install the package and its two libraries:

`package.json`
```diff
  "devDependencies": {
+     "@fluffydiscord/ux-datetime": "file:vendor/fluffydiscord/ux-datetime/assets",
+     "any-date-parser": "^2.2",
+     "luxon": "^3.7",
```

```shell
bun install
```

2. Register the controllers and the stylesheet:

`assets/controllers.json`
```json
{
    "controllers": {
        "@fluffydiscord/ux-datetime": {
            "picker": {
                "enabled": true,
                "fetch": "eager",
                "autoimport": {
                    "@fluffydiscord/ux-datetime/dist/styles.css": true,
                    "@fluffydiscord/ux-datetime/dist/presets/tabler.css": false,
                    "@fluffydiscord/ux-datetime/dist/presets/daisyui.css": false
                }
            },
            "time": {
                "enabled": true,
                "fetch": "eager"
            }
        }
    },
    "entrypoints": []
}
```

3. Make sure Stimulus reads `controllers.json`:

`vite.config.js`
```js
symfonyPlugin({ stimulus: true }),
```

`assets/bootstrap.js`
```js
import { startStimulusApp } from 'vite-plugin-symfony/stimulus/helpers';

const app = startStimulusApp();
```

Webpack Encore works the same way: steps 1 and 2, with `@symfony/stimulus-bridge` instead of step 3.

### AssetMapper

Symfony Flex adds the `controllers.json` entry for you. Then pull the two libraries into the importmap:

```shell
php bin/console importmap:require luxon any-date-parser
```

Without Flex, add the `controllers.json` block from [Vite](#vite-vite-plugin-symfony) step 2 by hand.

## Form types

```php
use FluffyDiscord\UxDatetime\Form\Type\DatePickerType;
use FluffyDiscord\UxDatetime\Form\Type\DateRangePickerType;
use FluffyDiscord\UxDatetime\Form\Type\DateTimePickerType;
use FluffyDiscord\UxDatetime\Form\Type\TimePickerType;

$builder
    ->add('deliveredOn', DatePickerType::class, [
        'min' => new \DateTimeImmutable('today'),
    ])
    ->add('startsAt', DateTimePickerType::class, [
        'input' => 'datetime_immutable',
        'presets' => ['today', 'tomorrow'],
    ])
    ->add('opensAt', TimePickerType::class)
    ->add('period', DateRangePickerType::class, [
        'required' => false,
    ]);
```

Each single type is the native type with `widget: single_text`, so every native option works (`input`, `model_timezone`, `view_timezone`, `with_seconds`…). The submitted values are the HTML5 ones: `2026-11-05`, `2026-11-05T14:30`, `14:30`.

| Option | Default | Meaning |
|---|---|---|
| `min` | `null` | Earliest allowed value. `DateTimeInterface` or a string in the HTML5 format. Also read from `attr.min`. |
| `max` | `null` | Latest allowed value. Same formats as `min`. |
| `presets` | `null` | Preset buttons to show. `null` = `today`, `yesterday`, `tomorrow` for one date; the full list for a range. |
| `ux_datetime` | `null` | `true` / `false` forces the picker on or off for this field. `null` = follow [the global switch](#global-switch). |

Days, presets and typed dates outside `min` / `max` are blocked in the browser.

> Validate the limits on the server too: a `#[Assert\Range]` or `#[Assert\GreaterThanOrEqual]` on the entity or DTO.

### Date range

`DateRangePickerType` has two `DatePickerType` children, `from` and `to`, and renders them as one field with a two-month calendar. Its data is `['from' => ..., 'to' => ...]`.

| Option | Default | Meaning |
|---|---|---|
| `from_name` / `to_name` | `from` / `to` | Child names. |
| `options` | `[]` | Options for both children, e.g. `['input' => 'datetime_immutable']`. |
| `from_options` / `to_options` | `[]` | Options for one child. |
| `presets` | `null` | Same as above. |
| `min` / `max` | `null` | Applied to both children. |

Map it onto two entity fields with `inherit_data`:

```php
$builder->add('period', DateRangePickerType::class, [
    'inherit_data' => true,
    'from_name' => 'startsAt',
    'to_name' => 'endsAt',
]);
```

The browser swaps the dates when the end comes first.

### Preset buttons

`today`, `yesterday`, `tomorrow`, `this_week`, `last_week`, `this_month`, `last_month`, `last_30_days`, `this_year`, `last_year`. A preset outside `min` / `max` hides itself.

## Typing

The field reads what people type, then shows it in the locale's short format (`5. 11. 2026` in Czech, `11/5/2026` in US English):

| Typed | Result |
|---|---|
| `5.11.2026`, `5/11/26`, `2026-11-05` | 5 November 2026 |
| `5.11.` | 5 November this year |
| `dnes`, `včera`, `zítra`, `today`, `tomorrow` | relative day |
| `5 November 2026` | written month names |
| `5.11. 1430`, `dnes 8` | date with time |
| `9`, `930`, `9:30`, `9.30`, `9h30` | time |

Text it can't read stays in the field, turns red and blocks the form submit. In the time field, Up and Down move the time by 15 minutes.

## Global switch

`config/packages/ux_datetime.yaml`
```yaml
ux_datetime:
    replace_native_widgets: true
```

Every `DateType`, `DateTimeType` and `TimeType` with `widget: single_text` (and `html5` left on) now renders the picker. A `DateTimeType` split into `date_widget` / `time_widget` gets a date picker and a time field. Turn one field back with `'ux_datetime' => false`.

## Sylius

Nothing to configure beyond [Setup](#setup). Turn on the [Tabler preset](#presets) so the picker matches the admin. With `SyliusAdminBundle` installed:

- every admin date, date-time and time field uses the picker, including the time half Sylius splits out of `DateTimeType`;
- every admin grid `date` filter becomes one range field that applies on pick and keeps `criteria[x][from|to][date]`;
- grid date filters read the typed days as whole days in the admin's timezone;
- the shop keeps its native inputs.

```yaml
ux_datetime:
    sylius:
        admin: true
        timezone: null
```

| Option | Default | Meaning |
|---|---|---|
| `sylius.admin` | `true` | Turns the whole Sylius integration on or off. |
| `sylius.timezone` | `null` | Timezone admins pick filter days in. `null` = `sylius_grid.timezone`, then PHP's default. |

The date filter turns `from` into local midnight and `to` into the next local midnight, then converts both to PHP's default timezone, which is how Doctrine stores the dates. For a `DATE` column, skip the shift:

```php
use FluffyDiscord\UxDatetime\Sylius\LocalTimeDateFilter;

$grid->addFilter(DateFilter::create('receivedAt')->addOption(LocalTimeDateFilter::DATE_ONLY_OPTION, true));
```

> A date filter template you set yourself in `sylius_grid.templates.filter.date` wins over the bundle's.

## Live Components

Nothing to do. The visible fields sit in `data-live-ignore`; the hidden inputs carry the value, send `input` and `change` for the model, and update the visible text when a re-render brings a new value.

## Twig component

The form theme renders `<twig:UxDatetime:Picker>`. Use it directly for a field outside a form type:

```twig
<twig:UxDatetime:Picker
    inputs="{{ [{name: 'from', value: from}, {name: 'to', value: to}] }}"
    submitOnApply="{{ true }}"
/>
```

Props: `inputs` (one = single date, two = range), `withTime`, `presets`, `submitOnApply`, `fieldId`, `required`, `disabled`, `invalid`, `min`, `max`, `testid`. The time field is `<twig:UxDatetime:Time input="{{ {name: 'at', value: at} }}" stepMinutes="{{ 30 }}"/>`.

## Theming

Set any `--ux-datetime-*` variable on `:root`, on a wrapper, or on `.ux-datetime` itself:

```css
:root {
    --ux-datetime-accent: #e11d48;
    --ux-datetime-radius: 0;
}

.dark {
    --ux-datetime-background: #111827;
    --ux-datetime-color: #f9fafb;
}
```

The markup uses only `ux-datetime__*` classes. Restyle a part through them when a variable isn't enough.

| Variable | Default | Styles |
|---|---|---|
| `--ux-datetime-font-family` | `inherit` | All text |
| `--ux-datetime-font-size` | `0.875rem` | Field, presets |
| `--ux-datetime-font-size-small` | `0.75rem` | Days |
| `--ux-datetime-font-size-tiny` | `0.625rem` | Weekday names |
| `--ux-datetime-font-weight-bold` | `600` | Month names, selected day and preset |
| `--ux-datetime-line-height` | `1.25rem` | Field |
| `--ux-datetime-color` | `#1f2937` | Text |
| `--ux-datetime-muted-color` | `#6b7280` | Icon, placeholder, weekday names, Clear |
| `--ux-datetime-background` | `#ffffff` | Field |
| `--ux-datetime-border-color` | `#d1d5db` | Field border |
| `--ux-datetime-border-width` | `1px` | Field and popover border |
| `--ux-datetime-radius` | `0.375rem` | Field, presets, Clear |
| `--ux-datetime-gap` | `0.5rem` | Space between icon and inputs, under month names |
| `--ux-datetime-gap-small` | `0.25rem` | Space between presets |
| `--ux-datetime-input-padding-y` | `0.5625rem` | Field |
| `--ux-datetime-input-padding-x` | `0.75rem` | Field |
| `--ux-datetime-input-min-width` | `7rem` | Each text input |
| `--ux-datetime-accent` | `#2563eb` | Focus border, selected days, today ring |
| `--ux-datetime-accent-contrast` | `#ffffff` | Text on selected days |
| `--ux-datetime-accent-soft` | accent at 15 % | Days inside a range, active preset |
| `--ux-datetime-focus-ring` | `0 0 0 0.25rem` accent at 25 % | Field focus shadow |
| `--ux-datetime-hover-background` | `#f3f4f6` | Hovered day, preset, button |
| `--ux-datetime-invalid-color` | `#dc2626` | Border of an invalid field |
| `--ux-datetime-disabled-background` | `#f3f4f6` | Disabled field |
| `--ux-datetime-disabled-opacity` | `0.35` | Blocked days and buttons |
| `--ux-datetime-popover-background` | field background | Popover |
| `--ux-datetime-popover-border-color` | field border colour | Popover border, preset divider |
| `--ux-datetime-popover-radius` | `0.5rem` | Popover |
| `--ux-datetime-popover-shadow` | `0 0.75rem 2rem rgb(0 0 0 / 0.15)` | Popover |
| `--ux-datetime-popover-offset` | `0.25rem` | Gap between field and popover |
| `--ux-datetime-popover-padding` | `0.75rem` | Popover |
| `--ux-datetime-z-index` | `1060` | Popover |
| `--ux-datetime-day-size` | `2rem` | Day cells, month arrows |
| `--ux-datetime-day-gap` | `2px` | Between day cells |
| `--ux-datetime-day-radius` | `--ux-datetime-radius` | Day cells |
| `--ux-datetime-month-gap` | `1.5rem` | Between the two range months |
| `--ux-datetime-preset-padding-y` | `0.375rem` | Presets, Clear |
| `--ux-datetime-preset-padding-x` | `0.75rem` | Presets, Clear |

### Presets

Two optional stylesheets map the variables onto a framework's own:

| File | Maps onto |
|---|---|
| `dist/presets/tabler.css` | Tabler `--tblr-*`, falling back to Bootstrap 5.3 `--bs-*` (Sylius admin) |
| `dist/presets/daisyui.css` | daisyUI 5 `--color-*`, `--radius-*` |

Turn one on in `assets/controllers.json`:

```diff
  "@fluffydiscord/ux-datetime/dist/styles.css": true,
- "@fluffydiscord/ux-datetime/dist/presets/tabler.css": false,
+ "@fluffydiscord/ux-datetime/dist/presets/tabler.css": true,
```

They follow the framework's dark mode, because the variables resolve on the picker element.

## Keyboard

| Key | Where | Does |
|---|---|---|
| Down | date field | opens the calendar and moves focus to the selected day |
| Arrows | calendar | previous / next day, same day previous / next week |
| Page Up / Page Down | calendar | same day previous / next month |
| Home / End | calendar | first / last day of the week |
| Enter | date field | applies the typed text and closes |
| Enter / Space | calendar | picks the day |
| Esc | anywhere in the picker | closes and puts focus back on the field |
| Up / Down | time field | ± 15 minutes |

Days carry their full date as `aria-label`, today has `aria-current="date"`, picked days `aria-pressed="true"`.

## Translations

Czech and English ship in the `ux_datetime` domain. Override or add a locale in `translations/ux_datetime.<locale>.yaml`. Month and weekday names, the week start and the date format come from the browser's `Intl` for the request locale.

Not covered:

- 12-hour clock: times show as `14:30`;
- server-side `min` / `max` checks: use validation constraints.

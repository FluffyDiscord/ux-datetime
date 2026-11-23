# UX Datetime

Toggl-style date, date-time, time and date-range picker for Symfony forms. Stimulus controller plus Twig component, no CSS framework needed.

Sylius users: see [Sylius](#sylius). After [Setup](#setup) the whole admin uses the picker.

## Features

- [Four form types](#form-types) — submit exactly what Symfony's `DateType`, `DateTimeType` and `TimeType` expect
- [Presets](#presets) — 22 built-in ranges as a PHP enum, plus your own in one class
- [Limits](#limits) — `min` / `max` come from the field's `LessThan`, `GreaterThan` and `Range` constraints
- [Free-form typing](#typing) — `5.11.`, `dnes`, `tomorrow 14:30`, `930` all work
- [Global switch](#global-switch) — every `single_text` date, date-time and time field becomes the picker
- [Sylius admin](#sylius) — every admin date field and grid date filter, the shop untouched
- [Live Components](#live-components) — survives re-renders without losing typed text
- [Theming](#theming) — every colour, size and spacing is a CSS variable, plus [Tabler and daisyUI themes](#theme-presets)
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
use FluffyDiscord\UxDatetime\Preset\Preset;

$builder
    ->add('deliveredOn', DatePickerType::class, [
        'min' => new \DateTimeImmutable('today'),
    ])
    ->add('startsAt', DateTimePickerType::class, [
        'input' => 'datetime_immutable',
        'presets' => [Preset::Today, Preset::Tomorrow],
    ])
    ->add('opensAt', TimePickerType::class)
    ->add('period', DateRangePickerType::class, [
        'required' => false,
    ]);
```

Each single type is the native type with `widget: single_text`, so every native option works (`input`, `model_timezone`, `view_timezone`, `with_seconds`…). The submitted values are the HTML5 ones: `2026-11-05`, `2026-11-05T14:30`, `14:30`.

| Option | Default | Meaning |
|---|---|---|
| `min` | `null` | Earliest allowed value. `DateTimeInterface` or a string in the HTML5 format. Also read from `attr.min`. `null` = [from the constraints](#limits). |
| `max` | `null` | Latest allowed value. Same formats as `min`. |
| `presets` | `null` | [Preset](#presets) buttons to show: `Preset` cases and preset names, mixed. `null` = `presets.default_date`, or `presets.default_range` for a range. |
| `ux_datetime` | `null` | `true` / `false` forces the picker on or off for this field. `null` = follow [the global switch](#global-switch). |

Days, presets and typed dates outside `min` / `max` are blocked in the browser.

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

## Presets

Pass `Preset` cases, preset names, or both:

```php
use FluffyDiscord\UxDatetime\Preset\Preset;

$builder->add('period', DateRangePickerType::class, [
    'presets' => [Preset::ThisMonth, Preset::LastMonth, 'fiscal_year'],
]);
```

A preset outside `min` / `max` hides itself. A name nobody registered throws an exception that lists the registered names. On a single-date field a range preset picks its first day.

| Case | Name | Range |
|---|---|---|
| `Today` | `today` | today |
| `Yesterday` | `yesterday` | yesterday |
| `Tomorrow` | `tomorrow` | tomorrow |
| `ThisWeek` | `this_week` | this week; the locale decides whether it starts on Monday or Sunday |
| `LastWeek` | `last_week` | the week before |
| `NextWeek` | `next_week` | the week after |
| `ThisMonth` | `this_month` | 1st to last day of this month |
| `LastMonth` | `last_month` | the month before |
| `NextMonth` | `next_month` | the month after |
| `Last7Days` | `last_7_days` | 6 days ago to today |
| `Last14Days` | `last_14_days` | 13 days ago to today |
| `Last30Days` | `last_30_days` | 29 days ago to today |
| `Last90Days` | `last_90_days` | 89 days ago to today |
| `Last365Days` | `last_365_days` | 364 days ago to today |
| `Next7Days` | `next_7_days` | today to 6 days ahead |
| `Next30Days` | `next_30_days` | today to 29 days ahead |
| `ThisQuarter` | `this_quarter` | this calendar quarter |
| `LastQuarter` | `last_quarter` | the quarter before |
| `ThisYear` | `this_year` | 1 January to 31 December |
| `LastYear` | `last_year` | the year before |
| `MonthToDate` | `month_to_date` | 1st of this month to today |
| `YearToDate` | `year_to_date` | 1 January to today |

The server works out every range when it renders the page, in the [`timezone`](#configuration) setting. A page left open past midnight keeps yesterday's ranges until it reloads.

### Your own preset

Write one class. Autoconfiguration registers it under its name:

`src/Preset/FiscalYearPreset.php`
```php
namespace App\Preset;

use FluffyDiscord\UxDatetime\Attribute\AsDatetimePreset;
use FluffyDiscord\UxDatetime\Preset\DateRange;
use FluffyDiscord\UxDatetime\Preset\PresetContext;
use FluffyDiscord\UxDatetime\Preset\PresetInterface;
use Symfony\Component\Translation\TranslatableMessage;

#[AsDatetimePreset]
class FiscalYearPreset implements PresetInterface
{
    public function getName(): string
    {
        return 'fiscal_year';
    }

    public function getLabel(): TranslatableMessage
    {
        return new TranslatableMessage('Fiscal year');
    }

    public function resolve(PresetContext $context): DateRange
    {
        $today = $context->getToday();
        $start = $today->modify('first day of april this year');
        if ($start > $today) {
            $start = $start->modify('-1 year');
        }

        return new DateRange($start, $start->modify('+1 year -1 day'));
    }
}
```

`PresetContext` has `getToday()`, `getStartOfWeek()`, `getStartOfMonth()`, `getStartOfQuarter()` and `getStartOfYear()`. A plain string label is shown as it is. A preset with the name of a built-in one replaces it.

## Limits

Leave `min` and `max` out and the picker reads them from the validation constraints on the mapped property:

```php
#[Assert\LessThan('tomorrow Europe/Prague')]
private ?\DateTimeImmutable $receivedAt = null;
```

That field gets `max` = today in Prague: future days are disabled and the Tomorrow preset is hidden.

- `LessThan`, `LessThanOrEqual`, `GreaterThan`, `GreaterThanOrEqual` and `Range` count, on the property or in the field's `constraints` option.
- A `property_path` into a child object works: `details.receivedAt` reads the constraints of the `details` property's class.
- Relative values (`today`, `tomorrow`) are worked out at render, in the timezone the value names, else in the [`timezone`](#configuration) setting.
- Date fields turn an exclusive limit into the nearest whole day: `LessThan('tomorrow')` → `max` = today. Date-time fields use the exact moment.
- Constraints with a `propertyPath`, and constraints outside the form's validation groups, are skipped.
- `min` / `max` options and `attr.min` / `attr.max` win over the constraints.

> The browser check is only a convenience. The constraint still validates on the server.

## Configuration

`config/packages/ux_datetime.yaml`
```yaml
ux_datetime:
    replace_native_widgets: false
    timezone: null
    presets:
        default_date: [today, yesterday, tomorrow]
        default_range: [today, yesterday, this_week, last_week, this_month, last_month, last_30_days, this_quarter, last_quarter, this_year, last_year]
    sylius:
        admin: true
```

| Option | Default | Meaning |
|---|---|---|
| `replace_native_widgets` | `false` | See [Global switch](#global-switch). |
| `timezone` | `null` | Timezone of "today" for presets, relative limits and Sylius grid filters. `null` = PHP's default; Sylius filters use `sylius_grid.timezone` first. |
| `presets.default_date` | see above | Presets of single-date fields without a `presets` option. |
| `presets.default_range` | see above | Presets of range fields and Sylius grid filters. |
| `sylius.admin` | `true` | See [Sylius](#sylius). |

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

Every `DateType`, `DateTimeType` and `TimeType` with `widget: single_text` (and `html5` left on) now renders the picker. A `DateTimeType` split into `date_widget` / `time_widget` gets a date picker and a time field side by side. Turn one field back with `'ux_datetime' => false`.

## Sylius

Nothing to configure beyond [Setup](#setup). Turn on the [Tabler theme](#theme-presets) so the picker matches the admin. With `SyliusAdminBundle` installed:

- every admin date, date-time and time field uses the picker, including the time half Sylius splits out of `DateTimeType`;
- every admin grid `date` filter becomes one range field that applies on pick and keeps `criteria[x][from|to][date]`;
- grid date filters read the typed days as whole days in the admin's timezone;
- the shop keeps its native inputs.

```yaml
ux_datetime:
    timezone: Europe/Prague
    sylius:
        admin: true
```

| Option | Default | Meaning |
|---|---|---|
| `sylius.admin` | `true` | Turns the whole Sylius integration on or off. |
| `timezone` | `null` | Timezone admins pick filter days in. `null` = `sylius_grid.timezone`, then PHP's default. |

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

Props: `inputs` (one = single date, two = range), `withTime`, `presets`, `timezone`, `submitOnApply`, `fieldId`, `required`, `disabled`, `invalid`, `min`, `max`, `testid`. The time field is `<twig:UxDatetime:Time input="{{ {name: 'at', value: at} }}" stepMinutes="{{ 30 }}"/>`.

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
| `--ux-datetime-input-min-width` | `7rem` | Single date, date-time and time inputs; range inputs fit their text |
| `--ux-datetime-text-padding-x` | `0.1875rem` | Inside each text input: room for the caret, space around the range dash |
| `--ux-datetime-range-input-width` | `11ch` | Each range date's text width in browsers without `field-sizing` (Firefox, older Safari) |
| `--ux-datetime-field-min-height` | `auto` | Field, to match taller inputs |
| `--ux-datetime-field-shadow` | `none` | Field shadow |
| `--ux-datetime-accent` | `#2563eb` | Focus border, selected days, today ring |
| `--ux-datetime-accent-contrast` | `#ffffff` | Text on selected days |
| `--ux-datetime-accent-soft` | accent at 15 % | Days inside a range, active preset |
| `--ux-datetime-focus-ring` | `0 0 0 0.25rem` accent at 25 % | Field focus shadow |
| `--ux-datetime-focus-border-color` | accent | Field border while focused |
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
| `--ux-datetime-preset-active-background` | accent at 15 % | Active preset |
| `--ux-datetime-preset-active-color` | text colour | Active preset |

### Theme presets

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

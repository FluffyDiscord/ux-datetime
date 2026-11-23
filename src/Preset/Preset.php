<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Preset;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

enum Preset: string implements PresetInterface
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Tomorrow = 'tomorrow';
    case ThisWeek = 'this_week';
    case LastWeek = 'last_week';
    case NextWeek = 'next_week';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case NextMonth = 'next_month';
    case Last7Days = 'last_7_days';
    case Last14Days = 'last_14_days';
    case Last30Days = 'last_30_days';
    case Last90Days = 'last_90_days';
    case Last365Days = 'last_365_days';
    case Next7Days = 'next_7_days';
    case Next30Days = 'next_30_days';
    case ThisQuarter = 'this_quarter';
    case LastQuarter = 'last_quarter';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';
    case MonthToDate = 'month_to_date';
    case YearToDate = 'year_to_date';

    public function getName(): string
    {
        return $this->value;
    }

    public function getLabel(): TranslatableInterface
    {
        return new TranslatableMessage('preset.' . $this->value, [], 'ux_datetime');
    }

    public function resolve(PresetContext $context): DateRange
    {
        $today = $context->getToday();

        return match ($this) {
            self::Today => DateRange::day($today),
            self::Yesterday => DateRange::day($today->modify('-1 day')),
            self::Tomorrow => DateRange::day($today->modify('+1 day')),
            self::ThisWeek, self::LastWeek, self::NextWeek => $this->resolveWeek($context->getStartOfWeek()),
            self::ThisMonth, self::LastMonth, self::NextMonth => $this->resolveMonth($context->getStartOfMonth()),
            self::ThisQuarter, self::LastQuarter => $this->resolveQuarter($context->getStartOfQuarter()),
            self::ThisYear, self::LastYear => $this->resolveYear($context->getStartOfYear()),
            self::MonthToDate => new DateRange($context->getStartOfMonth(), $today),
            self::YearToDate => new DateRange($context->getStartOfYear(), $today),
            default => $this->resolveDayCount($today),
        };
    }

    private function resolveWeek(\DateTimeImmutable $thisWeek): DateRange
    {
        $start = match ($this) {
            self::LastWeek => $thisWeek->modify('-7 days'),
            self::NextWeek => $thisWeek->modify('+7 days'),
            default => $thisWeek,
        };

        return new DateRange($start, $start->modify('+6 days'));
    }

    private function resolveMonth(\DateTimeImmutable $thisMonth): DateRange
    {
        $start = match ($this) {
            self::LastMonth => $thisMonth->modify('-1 month'),
            self::NextMonth => $thisMonth->modify('+1 month'),
            default => $thisMonth,
        };

        return new DateRange($start, $start->modify('last day of this month'));
    }

    private function resolveQuarter(\DateTimeImmutable $thisQuarter): DateRange
    {
        $start = self::LastQuarter === $this ? $thisQuarter->modify('-3 months') : $thisQuarter;

        return new DateRange($start, $start->modify('+3 months -1 day'));
    }

    private function resolveYear(\DateTimeImmutable $thisYear): DateRange
    {
        $start = self::LastYear === $this ? $thisYear->modify('-1 year') : $thisYear;

        return new DateRange($start, $start->modify('+1 year -1 day'));
    }

    private function resolveDayCount(\DateTimeImmutable $today): DateRange
    {
        $lastDays = match ($this) {
            self::Last7Days => 7,
            self::Last14Days => 14,
            self::Last30Days => 30,
            self::Last90Days => 90,
            self::Last365Days => 365,
            default => null,
        };

        if (null !== $lastDays) {
            return new DateRange($today->modify(sprintf('-%d days', $lastDays - 1)), $today);
        }

        $nextDays = self::Next7Days === $this ? 7 : 30;

        return new DateRange($today, $today->modify(sprintf('+%d days', $nextDays - 1)));
    }
}

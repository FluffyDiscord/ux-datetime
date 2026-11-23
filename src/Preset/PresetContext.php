<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Preset;

readonly class PresetContext
{
    private const DAYS_PER_WEEK = 7;
    private const MONTHS_PER_QUARTER = 3;
    private const ISO_MONDAY = 1;
    private const ISO_SUNDAY = 7;
    private const INTL_SUNDAY = 1;

    /**
     * @param int<1, 7> $firstDayOfWeek ISO day number, 1 = Monday
     */
    public function __construct(
        private \DateTimeImmutable $today,
        private int $firstDayOfWeek = self::ISO_MONDAY,
    ) {}

    public static function create(\DateTimeZone $timezone, string $locale): self
    {
        return new self(new \DateTimeImmutable('today', $timezone), self::getFirstDayOfWeek($locale));
    }

    public function getToday(): \DateTimeImmutable
    {
        return $this->today;
    }

    public function getStartOfWeek(): \DateTimeImmutable
    {
        $dayOfWeek = (int) $this->today->format('N');
        $daysSinceWeekStart = ($dayOfWeek - $this->firstDayOfWeek + self::DAYS_PER_WEEK) % self::DAYS_PER_WEEK;

        return $this->today->modify(sprintf('-%d days', $daysSinceWeekStart));
    }

    public function getStartOfMonth(): \DateTimeImmutable
    {
        return $this->today->modify('first day of this month');
    }

    public function getStartOfQuarter(): \DateTimeImmutable
    {
        $month = (int) $this->today->format('n');
        $quarterIndex = intdiv($month - 1, self::MONTHS_PER_QUARTER);
        $quarterStartMonth = ($quarterIndex * self::MONTHS_PER_QUARTER) + 1;

        return $this->today->setDate((int) $this->today->format('Y'), $quarterStartMonth, 1);
    }

    public function getStartOfYear(): \DateTimeImmutable
    {
        return $this->today->setDate((int) $this->today->format('Y'), 1, 1);
    }

    /**
     * @return int<1, 7>
     */
    private static function getFirstDayOfWeek(string $locale): int
    {
        $hasIntl = class_exists(\IntlCalendar::class);
        if (!$hasIntl) {
            return self::ISO_MONDAY;
        }

        $firstDay = \IntlCalendar::createInstance(null, $locale)?->getFirstDayOfWeek();
        if (!is_int($firstDay)) {
            return self::ISO_MONDAY;
        }

        if (self::INTL_SUNDAY === $firstDay) {
            return self::ISO_SUNDAY;
        }

        /** @var int<1, 7> */
        return $firstDay - 1;
    }
}

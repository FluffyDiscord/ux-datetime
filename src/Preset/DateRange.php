<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Preset;

readonly class DateRange
{
    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private \DateTimeImmutable $from,
        private \DateTimeImmutable $to,
    ) {}

    public static function day(\DateTimeImmutable $day): self
    {
        return new self($day, $day);
    }

    public function getFrom(): \DateTimeImmutable
    {
        return $this->from;
    }

    public function getTo(): \DateTimeImmutable
    {
        return $this->to;
    }

    public function getFromDate(): string
    {
        return $this->from->format(self::DATE_FORMAT);
    }

    public function getToDate(): string
    {
        return $this->to->format(self::DATE_FORMAT);
    }
}

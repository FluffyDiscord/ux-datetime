<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<\DateTimeInterface, \DateTimeInterface>
 */
class AllowedDatesTransformer implements DataTransformerInterface
{
    public const INVALID_MESSAGE = 'This date is not available.';

    private const DATE_FORMAT = 'Y-m-d';

    /**
     * @param array<array-key, string|\DateTimeInterface>|\Closure(): iterable<string|\DateTimeInterface> $allowedDates
     */
    public function __construct(
        private readonly array|\Closure $allowedDates,
        private readonly \DateTimeZone $timezone,
    ) {}

    /**
     * @return list<string>
     */
    public function getDates(): array
    {
        $allowedDates = $this->allowedDates instanceof \Closure ? ($this->allowedDates)() : $this->allowedDates;
        $dates = [];

        foreach ($allowedDates as $date) {
            $dates[] = $this->toIsoDate($date);
        }

        $uniqueDates = array_unique($dates);
        sort($uniqueDates);

        return $uniqueDates;
    }

    public function transform(mixed $value): mixed
    {
        return $value;
    }

    public function reverseTransform(mixed $value): mixed
    {
        if (!$value instanceof \DateTimeInterface) {
            return $value;
        }

        $day = $this->toIsoDate($value);
        $isAllowed = in_array($day, $this->getDates(), strict: true);
        if (!$isAllowed) {
            throw new TransformationFailedException(
                sprintf('The date "%s" is not in the allowed dates.', $day),
                invalidMessage: self::INVALID_MESSAGE,
            );
        }

        return $value;
    }

    private function toIsoDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($date)
                ->setTimezone($this->timezone)
                ->format(self::DATE_FORMAT);
        }

        $parsed = is_string($date) ? \DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $date) : false;
        $isIsoDate = false !== $parsed && $parsed->format(self::DATE_FORMAT) === $date;
        if (!$isIsoDate) {
            throw new \InvalidArgumentException(sprintf(
                'Allowed dates must be DateTimeInterface objects or "Y-m-d" strings, got "%s".',
                is_string($date) ? $date : get_debug_type($date),
            ));
        }

        return $date;
    }
}

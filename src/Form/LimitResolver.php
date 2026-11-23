<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form;

use Symfony\Component\Form\FormInterface;

class LimitResolver
{
    private const FORMATS = [
        'date' => 'Y-m-d',
        'datetime' => 'Y-m-d\TH:i',
        'time' => 'H:i',
    ];

    public function __construct(
        private readonly FieldConstraints $fieldConstraints = new FieldConstraints(),
        private readonly ConstraintBounds $constraintBounds = new ConstraintBounds(),
        private readonly ?string $timezone = null,
    ) {}

    /**
     * @param array<array-key, mixed> $options
     *
     * @return array{min: string|null, max: string|null}
     */
    public function resolve(FormInterface $form, array $options, string $mode): array
    {
        /** @var array<string, mixed> $attr */
        $attr = $options['attr'];

        /** @var string|null $viewTimezone */
        $viewTimezone = $options['view_timezone'];
        $timezone = new \DateTimeZone($viewTimezone ?? $this->timezone ?? date_default_timezone_get());

        $explicit = [
            'min' => $this->format($options['min'] ?? $attr['min'] ?? null, $mode, $timezone),
            'max' => $this->format($options['max'] ?? $attr['max'] ?? null, $mode, $timezone),
        ];
        if ('time' === $mode) {
            return $explicit;
        }

        $derived = ['min' => [], 'max' => []];
        foreach ($this->fieldConstraints->getValidated($form) as $constraint) {
            foreach ($this->constraintBounds->getBounds($constraint) as $side => $bound) {
                $derived[$side][] = $this->formatBound($bound['at'], $bound['isExclusive'], $side, $mode, $timezone);
            }
        }

        return [
            'min' => $explicit['min'] ?? ([] === $derived['min'] ? null : max($derived['min'])),
            'max' => $explicit['max'] ?? ([] === $derived['max'] ? null : min($derived['max'])),
        ];
    }

    /**
     * @param 'min'|'max' $side
     */
    private function formatBound(
        \DateTimeImmutable $bound,
        bool $isExclusive,
        string $side,
        string $mode,
        \DateTimeZone $timezone,
    ): string {
        if ('datetime' === $mode) {
            return $bound->setTimezone($timezone)->format(self::FORMATS[$mode]);
        }

        $step = 'max' === $side ? '-1 second' : '+1 second';
        $edge = $isExclusive ? $bound->modify($step) : $bound;
        $day = $edge->setTime(0, 0);
        $startsAfterMidnight = 'min' === $side && $day < $edge;

        return ($startsAfterMidnight ? $day->modify('+1 day') : $day)->format(self::FORMATS[$mode]);
    }

    private function format(mixed $limit, string $mode, \DateTimeZone $timezone): ?string
    {
        if (!$limit instanceof \DateTimeInterface) {
            return is_string($limit) ? $limit : null;
        }

        return \DateTimeImmutable::createFromInterface($limit)
            ->setTimezone($timezone)
            ->format(self::FORMATS[$mode]);
    }
}

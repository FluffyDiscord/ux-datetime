<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AbstractComparison;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Range;

class ConstraintBounds
{
    public function __construct(
        private readonly ?string $timezone = null,
    ) {}

    /**
     * @return array<'min'|'max', array{at: \DateTimeImmutable, isExclusive: bool}>
     */
    public function getBounds(Constraint $constraint): array
    {
        $isDynamic = $this->isDynamic($constraint);
        $bounds = match (true) {
            $isDynamic => [],
            $constraint instanceof LessThan => ['max' => [$constraint->value, true]],
            $constraint instanceof LessThanOrEqual => ['max' => [$constraint->value, false]],
            $constraint instanceof GreaterThan => ['min' => [$constraint->value, true]],
            $constraint instanceof GreaterThanOrEqual => ['min' => [$constraint->value, false]],
            $constraint instanceof Range => ['min' => [$constraint->min, false], 'max' => [$constraint->max, false]],
            default => [],
        };

        $resolved = [];
        foreach ($bounds as $side => $bound) {
            $at = $this->toDateTime($bound[0]);
            if (null !== $at) {
                $resolved[$side] = ['at' => $at, 'isExclusive' => $bound[1]];
            }
        }

        return $resolved;
    }

    private function isDynamic(Constraint $constraint): bool
    {
        if ($constraint instanceof AbstractComparison) {
            return null !== $constraint->propertyPath;
        }

        if ($constraint instanceof Range) {
            return null !== $constraint->minPropertyPath || null !== $constraint->maxPropertyPath;
        }

        return false;
    }

    private function toDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone($this->timezone ?? date_default_timezone_get()));
        } catch (\Exception) {
            return null;
        }
    }
}

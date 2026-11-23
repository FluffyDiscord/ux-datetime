<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Sylius;

use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

class LocalTimeDateFilter implements FilterInterface
{
    public const DATE_ONLY_OPTION = 'date_only';

    private const BOUND_FORMAT = '!Y-m-d H:i';
    private const START_OF_DAY = '00:00';

    public function __construct(
        private readonly FilterInterface $dateFilter,
        private readonly ?string $localTimezone = null,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        $isDateOnly = true === ($options[self::DATE_ONLY_OPTION] ?? false);
        if ($isDateOnly) {
            $this->dateFilter->apply($dataSource, $name, $data, ['inclusive_to' => true] + $options);

            return;
        }

        $fromDate = $this->getBoundPart($data, 'from', 'date');
        $fromTime = $this->getBoundPart($data, 'from', 'time');
        $toDate = $this->getBoundPart($data, 'to', 'date');
        $toTime = $this->getBoundPart($data, 'to', 'time');

        $from = $this->getStorageBound($fromDate, '' === $fromTime ? self::START_OF_DAY : $fromTime);

        $isWholeLastDay = '' === $toTime;
        $to = $isWholeLastDay
            ? $this->getStorageBound($toDate, self::START_OF_DAY, '+1 day')
            : $this->getStorageBound($toDate, $toTime);

        if ($isWholeLastDay) {
            $options['inclusive_to'] = false;
        }

        $this->dateFilter->apply($dataSource, $name, array_filter(['from' => $from, 'to' => $to]), $options);
    }

    /**
     * @return array{date: string, time: string}|null
     */
    private function getStorageBound(string $date, string $time, string $shift = '+0 days'): ?array
    {
        $storageTimezone = new \DateTimeZone(date_default_timezone_get());
        $localTimezone = null === $this->localTimezone ? $storageTimezone : new \DateTimeZone($this->localTimezone);

        $localDateTime = \DateTimeImmutable::createFromFormat(self::BOUND_FORMAT, $date . ' ' . $time, $localTimezone);
        if (false === $localDateTime) {
            return null;
        }

        $storageDateTime = $localDateTime->modify($shift)->setTimezone($storageTimezone);

        return ['date' => $storageDateTime->format('Y-m-d'), 'time' => $storageDateTime->format('H:i')];
    }

    private function getBoundPart(mixed $data, string $bound, string $part): string
    {
        if (!is_array($data)) {
            return '';
        }

        $hasBound = is_array($data[$bound] ?? null);
        if (!$hasBound) {
            return '';
        }

        $isString = is_string($data[$bound][$part] ?? null);

        return $isString ? $data[$bound][$part] : '';
    }
}

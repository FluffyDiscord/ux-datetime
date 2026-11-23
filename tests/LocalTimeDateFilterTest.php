<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests;

use FluffyDiscord\UxDatetime\Sylius\LocalTimeDateFilter;
use FluffyDiscord\UxDatetime\Tests\Fixtures\Sylius\RecordingFilter;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Grid\Data\DataSourceInterface;

class LocalTimeDateFilterTest extends TestCase
{
    private string $defaultTimezone;

    private RecordingFilter $dateFilter;

    protected function setUp(): void
    {
        $this->defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $this->dateFilter = new RecordingFilter();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
    }

    public function testWholeLocalDaysBecomeAStorageTimeRangeEndingAtTheNextMidnight(): void
    {
        $this->applyFilter(
            'Europe/Prague',
            [
                'from' => ['date' => '2026-11-01', 'time' => ''],
                'to' => ['date' => '2026-11-30', 'time' => ''],
            ],
            ['inclusive_to' => true],
        );

        self::assertSame(
            [
                'from' => ['date' => '2026-10-31', 'time' => '23:00'],
                'to' => ['date' => '2026-11-30', 'time' => '23:00'],
            ],
            $this->dateFilter->data,
        );
        self::assertFalse($this->dateFilter->options['inclusive_to']);
    }

    public function testTypedTimesAreShiftedAndKeepTheConfiguredInclusiveness(): void
    {
        $this->applyFilter(
            'Europe/Prague',
            [
                'from' => ['date' => '2026-07-01', 'time' => '08:30'],
                'to' => ['date' => '2026-07-01', 'time' => '17:00'],
            ],
            ['inclusive_to' => true],
        );

        self::assertSame(
            [
                'from' => ['date' => '2026-07-01', 'time' => '06:30'],
                'to' => ['date' => '2026-07-01', 'time' => '15:00'],
            ],
            $this->dateFilter->data,
        );
        self::assertTrue($this->dateFilter->options['inclusive_to']);
    }

    public function testEmptyAndInvalidBoundsAreDropped(): void
    {
        $this->applyFilter(null, [
            'from' => ['date' => 'not a date'],
            'to' => ['date' => '2026-11-30'],
        ]);

        self::assertSame(['to' => ['date' => '2026-12-01', 'time' => '00:00']], $this->dateFilter->data);
    }

    public function testDateOnlyColumnsSkipTheShiftAndIncludeTheLastDay(): void
    {
        $data = ['from' => ['date' => '2026-11-01'], 'to' => ['date' => '2026-11-30']];

        $this->applyFilter('Europe/Prague', $data, [LocalTimeDateFilter::DATE_ONLY_OPTION => true]);

        self::assertSame($data, $this->dateFilter->data);
        self::assertTrue($this->dateFilter->options['inclusive_to']);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    private function applyFilter(?string $timezone, array $data, array $options = []): void
    {
        $filter = new LocalTimeDateFilter($this->dateFilter, $timezone);

        $filter->apply(self::createStub(DataSourceInterface::class), 'receivedAt', $data, $options);
    }
}

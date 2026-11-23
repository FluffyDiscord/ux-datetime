<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests\Fixtures;

use FluffyDiscord\UxDatetime\Attribute\AsDatetimePreset;
use FluffyDiscord\UxDatetime\Preset\DateRange;
use FluffyDiscord\UxDatetime\Preset\PresetContext;
use FluffyDiscord\UxDatetime\Preset\PresetInterface;

#[AsDatetimePreset]
class FiscalYearPreset implements PresetInterface
{
    public function getName(): string
    {
        return 'fiscal_year';
    }

    public function getLabel(): string
    {
        return 'Fiscal year';
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

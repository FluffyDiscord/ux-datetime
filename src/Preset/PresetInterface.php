<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Preset;

use Symfony\Contracts\Translation\TranslatableInterface;

interface PresetInterface
{
    public function getName(): string;

    public function getLabel(): string|TranslatableInterface;

    public function resolve(PresetContext $context): DateRange;
}

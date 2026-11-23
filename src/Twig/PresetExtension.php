<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Twig;

use FluffyDiscord\UxDatetime\Preset\PresetRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PresetExtension extends AbstractExtension
{
    public function __construct(
        private readonly PresetRegistry $presetRegistry,
    ) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('ux_datetime_presets', $this->presetRegistry->resolve(...))];
    }
}

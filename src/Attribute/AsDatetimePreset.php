<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsDatetimePreset
{
    public const TAG = 'ux_datetime.preset';
}

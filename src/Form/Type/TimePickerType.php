<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\TimeType;

class TimePickerType extends AbstractPickerType
{
    public function getParent(): string
    {
        return TimeType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'ux_time_picker';
    }
}

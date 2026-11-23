<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class DateTimePickerType extends AbstractPickerType
{
    public function getParent(): string
    {
        return DateTimeType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'ux_date_time_picker';
    }
}

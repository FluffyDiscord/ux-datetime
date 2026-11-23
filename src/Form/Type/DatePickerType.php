<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateType;

class DatePickerType extends AbstractPickerType
{
    public function getParent(): string
    {
        return DateType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'ux_date_picker';
    }
}

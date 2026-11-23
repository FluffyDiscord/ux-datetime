<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests\Fixtures;

use FluffyDiscord\UxDatetime\Form\Type\DatePickerType;
use FluffyDiscord\UxDatetime\Form\Type\DateRangePickerType;
use FluffyDiscord\UxDatetime\Form\Type\DateTimePickerType;
use FluffyDiscord\UxDatetime\Form\Type\TimePickerType;
use FluffyDiscord\UxDatetime\Preset\Preset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;

class DemoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DatePickerType::class, [
                'input' => 'datetime_immutable',
                'min' => new \DateTimeImmutable('2026-01-01'),
                'max' => '2026-12-31',
            ])
            ->add('dateTime', DateTimePickerType::class, [
                'input' => 'datetime_immutable',
                'presets' => [Preset::Today, 'fiscal_year'],
            ])
            ->add('time', TimePickerType::class, [
                'input' => 'string',
            ])
            ->add('range', DateRangePickerType::class, [
                'required' => false,
                'options' => ['input' => 'string'],
            ])
            ->add('nativeDate', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('splitDateTime', DateTimeType::class, [
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('optedOut', TimeType::class, [
                'widget' => 'single_text',
                'ux_datetime' => false,
                'required' => false,
            ]);
    }
}

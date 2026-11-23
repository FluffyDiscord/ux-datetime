<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form\Type;

use FluffyDiscord\UxDatetime\Form\Extension\PickerTypeExtension;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateRangePickerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, mixed> $sharedOptions */
        $sharedOptions = $options['options'];
        $sharedOptions['required'] = $options['required'];
        $sharedOptions['min'] = $options['min'];
        $sharedOptions['max'] = $options['max'];
        $sharedOptions['presets'] = $options['presets'];

        /** @var array<string, mixed> $fromOptions */
        $fromOptions = $options['from_options'];

        /** @var array<string, mixed> $toOptions */
        $toOptions = $options['to_options'];

        /** @var string $fromName */
        $fromName = $options['from_name'];

        /** @var string $toName */
        $toName = $options['to_name'];

        $builder
            ->add($fromName, DatePickerType::class, array_replace($sharedOptions, $fromOptions))
            ->add($toName, DatePickerType::class, array_replace($sharedOptions, $toOptions));
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var string $fromName */
        $fromName = $options['from_name'];

        /** @var string $toName */
        $toName = $options['to_name'];

        /** @var array{inputs: list<array{name: string, value: mixed}>, presets: list<mixed>|null, timezone: string|null, min: string|null, max: string|null} $fromPicker */
        $fromPicker = $view->children[$fromName]->vars[PickerTypeExtension::VIEW_VARIABLE];

        /** @var array{inputs: list<array{name: string, value: mixed}>, min: string|null, max: string|null} $toPicker */
        $toPicker = $view->children[$toName]->vars[PickerTypeExtension::VIEW_VARIABLE];

        $view->vars['compound'] = false;
        $view->vars[PickerTypeExtension::VIEW_VARIABLE] = [
            'mode' => 'date',
            'inputs' => [...$fromPicker['inputs'], ...$toPicker['inputs']],
            'presets' => $fromPicker['presets'],
            'timezone' => $fromPicker['timezone'],
            'min' => $fromPicker['min'] ?? $toPicker['min'],
            'max' => $toPicker['max'] ?? $fromPicker['max'],
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'from_name' => 'from',
            'to_name' => 'to',
            'options' => [],
            'from_options' => [],
            'to_options' => [],
            'presets' => null,
            'min' => null,
            'max' => null,
            'error_bubbling' => false,
        ]);

        $resolver->setAllowedTypes('from_name', 'string');
        $resolver->setAllowedTypes('to_name', 'string');
        $resolver->setAllowedTypes('options', 'array');
        $resolver->setAllowedTypes('from_options', 'array');
        $resolver->setAllowedTypes('to_options', 'array');
        $resolver->setAllowedTypes('presets', ['null', 'array']);
        $resolver->setAllowedTypes('min', ['null', 'string', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('max', ['null', 'string', \DateTimeInterface::class]);
    }

    public function getBlockPrefix(): string
    {
        return 'ux_datetime_range';
    }
}

<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form\Extension;

use FluffyDiscord\UxDatetime\Form\AllowedDatesTransformer;
use FluffyDiscord\UxDatetime\Form\LimitResolver;
use FluffyDiscord\UxDatetime\Preset\PresetInterface;
use FluffyDiscord\UxDatetime\Preset\PresetRegistry;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PickerTypeExtension extends AbstractTypeExtension
{
    public const BLOCK_PREFIX = 'ux_datetime';
    public const VIEW_VARIABLE = 'ux_datetime';

    private const MODES = [
        'date' => 'date',
        'datetime-local' => 'datetime',
        'time' => 'time',
    ];

    public function __construct(
        private readonly PresetRegistry $presetRegistry = new PresetRegistry(),
        private readonly LimitResolver $limitResolver = new LimitResolver(),
        private readonly bool $replaceNativeWidgets = false,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?string $syliusAdminPathPrefix = null,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'ux_datetime' => null,
            'presets' => null,
            'min' => null,
            'max' => null,
            'allowed_dates' => null,
            'submit_on_apply' => false,
            'input_attr' => [],
            'block_prefix' => fn (Options $options, ?string $blockPrefix): ?string => $this->isPickerEnabled($options)
                ? self::BLOCK_PREFIX
                : $blockPrefix,
        ]);

        $resolver->setAllowedTypes('ux_datetime', ['null', 'bool']);
        $resolver->setAllowedTypes('presets', ['null', 'array']);
        $resolver->setAllowedTypes('min', ['null', 'string', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('max', ['null', 'string', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('allowed_dates', ['null', 'array', \Closure::class]);
        $resolver->setAllowedTypes('submit_on_apply', 'bool');
        $resolver->setAllowedTypes('input_attr', 'array');
        $resolver->setNormalizer('presets', $this->normalizePresets(...));
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowedDatesTransformer = $this->createAllowedDatesTransformer($options);
        if (null === $allowedDatesTransformer) {
            return;
        }

        $builder->addModelTransformer($allowedDatesTransformer, forceAppend: true);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $isPicker = self::BLOCK_PREFIX === $options['block_prefix'];
        if (!$isPicker) {
            return;
        }

        /** @var string $type */
        $type = $view->vars['type'];
        $mode = self::MODES[$type];

        $limits = $this->limitResolver->resolve($form, $options, $mode);
        $allowedDatesTransformer = $this->createAllowedDatesTransformer($options);

        $view->vars[self::VIEW_VARIABLE] = [
            'mode' => $mode,
            'inputs' => [[
                'name' => $view->vars['full_name'],
                'value' => $view->vars['value'],
                'attr' => $options['input_attr'],
            ]],
            'presets' => $options['presets'],
            'timezone' => $options['view_timezone'],
            'min' => $limits['min'],
            'max' => $limits['max'],
            'allowed_dates' => $allowedDatesTransformer?->getDates(),
            'submit_on_apply' => $options['submit_on_apply'],
        ];
    }

    public static function getExtendedTypes(): iterable
    {
        return [DateType::class, DateTimeType::class, TimeType::class];
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function createAllowedDatesTransformer(array $options): ?AllowedDatesTransformer
    {
        /** @var array<array-key, string|\DateTimeInterface>|\Closure(): iterable<string|\DateTimeInterface>|null $allowedDates */
        $allowedDates = $options['allowed_dates'];
        if (null === $allowedDates) {
            return null;
        }

        /** @var string|null $viewTimezone */
        $viewTimezone = $options['view_timezone'];
        $timezone = new \DateTimeZone($viewTimezone ?? date_default_timezone_get());

        return new AllowedDatesTransformer($allowedDates, $timezone);
    }

    private function isPickerEnabled(Options $options): bool
    {
        $isSingleText = 'single_text' === $options['widget'];
        if (!$isSingleText) {
            return false;
        }

        $isHtml5 = true === $options['html5'];
        if (!$isHtml5) {
            return false;
        }

        /** @var bool|null $requested */
        $requested = $options['ux_datetime'];
        if (null !== $requested) {
            return $requested;
        }

        if ($this->replaceNativeWidgets) {
            return true;
        }

        return $this->isSyliusAdminRequest();
    }

    private function isSyliusAdminRequest(): bool
    {
        if (null === $this->syliusAdminPathPrefix) {
            return false;
        }

        $request = $this->requestStack?->getMainRequest();
        if (null === $request) {
            return false;
        }

        return str_starts_with($request->getPathInfo(), $this->syliusAdminPathPrefix);
    }

    /**
     * @param array<array-key, mixed>|null $presets
     *
     * @return list<PresetInterface>|null
     */
    private function normalizePresets(Options $options, ?array $presets): ?array
    {
        if (null === $presets) {
            return null;
        }

        /** @var list<PresetInterface|string> $presetList */
        $presetList = array_values($presets);

        return $this->presetRegistry->getAll($presetList);
    }
}

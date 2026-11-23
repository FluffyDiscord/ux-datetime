<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
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

    private const LIMIT_FORMATS = [
        'date' => 'Y-m-d',
        'datetime' => 'Y-m-d\TH:i',
        'time' => 'H:i',
    ];

    public function __construct(
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
            'block_prefix' => fn (Options $options, ?string $blockPrefix): ?string => $this->isPickerEnabled($options)
                ? self::BLOCK_PREFIX
                : $blockPrefix,
        ]);

        $resolver->setAllowedTypes('ux_datetime', ['null', 'bool']);
        $resolver->setAllowedTypes('presets', ['null', 'string[]']);
        $resolver->setAllowedTypes('min', ['null', 'string', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('max', ['null', 'string', \DateTimeInterface::class]);
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

        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'];

        /** @var string|null $viewTimezone */
        $viewTimezone = $options['view_timezone'];

        $view->vars[self::VIEW_VARIABLE] = [
            'mode' => $mode,
            'inputs' => [['name' => $view->vars['full_name'], 'value' => $view->vars['value']]],
            'presets' => $options['presets'],
            'min' => $this->formatLimit($options['min'] ?? $attr['min'] ?? null, $mode, $viewTimezone),
            'max' => $this->formatLimit($options['max'] ?? $attr['max'] ?? null, $mode, $viewTimezone),
        ];
    }

    public static function getExtendedTypes(): iterable
    {
        return [DateType::class, DateTimeType::class, TimeType::class];
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

    private function formatLimit(mixed $limit, string $mode, ?string $viewTimezone): ?string
    {
        if (!$limit instanceof \DateTimeInterface) {
            return is_string($limit) ? $limit : null;
        }

        $timezone = new \DateTimeZone($viewTimezone ?? date_default_timezone_get());

        return \DateTimeImmutable::createFromInterface($limit)
            ->setTimezone($timezone)
            ->format(self::LIMIT_FORMATS[$mode]);
    }
}

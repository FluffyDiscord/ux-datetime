<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Preset;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatableInterface;

class PresetRegistry
{
    public const DEFAULT_PRESETS = [
        'date' => ['today', 'yesterday', 'tomorrow'],
        'range' => [
            'today',
            'yesterday',
            'this_week',
            'last_week',
            'this_month',
            'last_month',
            'last_30_days',
            'this_quarter',
            'last_quarter',
            'this_year',
            'last_year',
        ],
    ];

    /** @var array<string, PresetInterface> */
    private array $presets = [];

    /**
     * @param iterable<PresetInterface> $customPresets
     * @param array{date: list<PresetInterface|string>, range: list<PresetInterface|string>} $defaultPresets
     */
    public function __construct(
        iterable $customPresets = [],
        private readonly array $defaultPresets = self::DEFAULT_PRESETS,
        private readonly ?string $timezone = null,
        private readonly ?RequestStack $requestStack = null,
        private readonly string $defaultLocale = 'en',
    ) {
        foreach (Preset::cases() as $preset) {
            $this->presets[$preset->getName()] = $preset;
        }

        foreach ($customPresets as $preset) {
            $this->presets[$preset->getName()] = $preset;
        }
    }

    public function get(PresetInterface|string $preset): PresetInterface
    {
        if ($preset instanceof PresetInterface) {
            return $preset;
        }

        $registered = $this->presets[$preset] ?? null;
        if (null === $registered) {
            throw new \InvalidArgumentException(sprintf(
                'The date picker preset "%s" does not exist. Registered presets: "%s".',
                $preset,
                implode('", "', array_keys($this->presets)),
            ));
        }

        return $registered;
    }

    /**
     * @param list<PresetInterface|string> $presets
     *
     * @return list<PresetInterface>
     */
    public function getAll(array $presets): array
    {
        return array_map($this->get(...), $presets);
    }

    /**
     * @param list<PresetInterface|string>|null $presets
     *
     * @return list<array{name: string, label: string|TranslatableInterface, from: string, to: string}>
     */
    public function resolve(?array $presets, bool $isRange, ?string $timezone = null): array
    {
        $defaultPresets = $this->defaultPresets[$isRange ? 'range' : 'date'];
        $locale = $this->requestStack?->getCurrentRequest()?->getLocale() ?? $this->defaultLocale;
        $zone = new \DateTimeZone($timezone ?? $this->timezone ?? date_default_timezone_get());
        $context = PresetContext::create($zone, $locale);
        $resolved = [];

        foreach ($this->getAll($presets ?? $defaultPresets) as $preset) {
            $range = $preset->resolve($context);
            $resolved[] = [
                'name' => $preset->getName(),
                'label' => $preset->getLabel(),
                'from' => $range->getFromDate(),
                'to' => $isRange ? $range->getToDate() : $range->getFromDate(),
            ];
        }

        return $resolved;
    }
}

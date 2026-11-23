<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests\Fixtures\Sylius;

use FluffyDiscord\UxDatetime\UxDatetimeBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SyliusGridBundle extends AbstractBundle
{
    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator
            ->parameters()
            ->set('sylius.grid.templates.filter', ['date' => UxDatetimeBundle::SYLIUS_DEFAULT_GRID_FILTER_TEMPLATE])
            ->set('sylius_grid.timezone', null);

        $configurator
            ->services()
            ->set('sylius.grid_filter.date', RecordingFilter::class)
            ->public();
    }
}

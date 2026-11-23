<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests\Fixtures\Sylius;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SyliusAdminBundle extends AbstractBundle
{
    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->parameters()->set('sylius_admin.path_name', 'admin');
    }
}

<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime;

use FluffyDiscord\UxDatetime\Attribute\AsDatetimePreset;
use FluffyDiscord\UxDatetime\Form\ConstraintBounds;
use FluffyDiscord\UxDatetime\Form\Extension\PickerTypeExtension;
use FluffyDiscord\UxDatetime\Form\FieldConstraints;
use FluffyDiscord\UxDatetime\Form\LimitResolver;
use FluffyDiscord\UxDatetime\Preset\PresetRegistry;
use FluffyDiscord\UxDatetime\Sylius\LocalTimeDateFilter;
use FluffyDiscord\UxDatetime\Twig\PresetExtension;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

class UxDatetimeBundle extends AbstractBundle implements CompilerPassInterface
{
    public const FORM_THEME = '@UxDatetime/form_theme.html.twig';
    public const SYLIUS_GRID_FILTER_TEMPLATE = '@UxDatetime/sylius/grid_filter_date.html.twig';
    public const SYLIUS_DEFAULT_GRID_FILTER_TEMPLATE = '@SyliusAdmin/shared/grid/filter/date.html.twig';
    public const SYLIUS_ADMIN_PARAMETER = 'ux_datetime.sylius_admin';

    private const SYLIUS_GRID_FILTER_TEMPLATES_PARAMETER = 'sylius.grid.templates.filter';
    private const SYLIUS_GRID_TIMEZONE_PARAMETER = 'sylius_grid.timezone';
    private const SYLIUS_DATE_FILTER_SERVICE = 'sylius.grid_filter.date';

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();
        $root = $rootNode->children();

        $root
            ->booleanNode('replace_native_widgets')
            ->info('Render every single_text DateType, DateTimeType and TimeType as the picker.')
            ->defaultFalse();

        $root
            ->scalarNode('timezone')
            ->info('Timezone of "today" for presets, relative limits and Sylius grid filters. Null = the PHP default.')
            ->defaultNull();

        $presets = $root->arrayNode('presets')->addDefaultsIfNotSet()->children();

        $presets
            ->arrayNode('default_date')
            ->info('Preset names for single-date fields without a presets option.')
            ->scalarPrototype()
            ->end()
            ->defaultValue(PresetRegistry::DEFAULT_PRESETS['date']);

        $presets
            ->arrayNode('default_range')
            ->info('Preset names for range fields without a presets option.')
            ->scalarPrototype()
            ->end()
            ->defaultValue(PresetRegistry::DEFAULT_PRESETS['range']);

        $sylius = $root->arrayNode('sylius')->addDefaultsIfNotSet()->children();

        $sylius
            ->booleanNode('admin')
            ->info('Use the picker for every Sylius admin date field and grid date filter.')
            ->defaultTrue();
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('twig', ['form_themes' => [self::FORM_THEME]]);

        $isAssetMapperInstalled = interface_exists(AssetMapperInterface::class);
        if (!$isAssetMapperInstalled) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [$this->getPath() . '/assets/dist' => '@fluffydiscord/ux-datetime'],
            ],
        ]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        /** @var bool $replaceNativeWidgets */
        $replaceNativeWidgets = $config['replace_native_widgets'];

        /** @var string|null $timezone */
        $timezone = $config['timezone'];

        /** @var array{default_date: list<string>, default_range: list<string>} $presetConfig */
        $presetConfig = $config['presets'];

        /** @var array{admin: bool} $syliusConfig */
        $syliusConfig = $config['sylius'];

        $isSyliusAdmin = $syliusConfig['admin'] && $this->isSyliusAdminInstalled($container);

        $container->registerAttributeForAutoconfiguration(
            AsDatetimePreset::class,
            static function (ChildDefinition $definition): void {
                $definition->addTag(AsDatetimePreset::TAG);
            },
        );

        $services = $configurator->services();

        $services
            ->set(PresetRegistry::class)
            ->args([
                tagged_iterator(AsDatetimePreset::TAG),
                ['date' => $presetConfig['default_date'], 'range' => $presetConfig['default_range']],
                $timezone,
                service('request_stack')->nullOnInvalid(),
                param('kernel.default_locale'),
            ]);

        $services
            ->set(PresetExtension::class)
            ->args([service(PresetRegistry::class)])
            ->tag('twig.extension');

        $services
            ->set(FieldConstraints::class)
            ->args([service('validator')->nullOnInvalid()]);

        $services
            ->set(ConstraintBounds::class)
            ->args([$timezone]);

        $services
            ->set(LimitResolver::class)
            ->args([service(FieldConstraints::class), service(ConstraintBounds::class), $timezone]);

        $services
            ->set(PickerTypeExtension::class)
            ->args([
                service(PresetRegistry::class),
                service(LimitResolver::class),
                $replaceNativeWidgets,
                service('request_stack')->nullOnInvalid(),
                $isSyliusAdmin ? '/%sylius_admin.path_name%' : null,
            ])
            ->tag('form.type_extension');

        if (!$isSyliusAdmin) {
            return;
        }

        $configurator->parameters()->set(self::SYLIUS_ADMIN_PARAMETER, true);

        $configurator
            ->services()
            ->set(LocalTimeDateFilter::class)
            ->decorate(self::SYLIUS_DATE_FILTER_SERVICE)
            ->args([
                service('.inner'),
                $timezone ?? param(self::SYLIUS_GRID_TIMEZONE_PARAMETER),
            ]);
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass($this, PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
    }

    public function process(ContainerBuilder $container): void
    {
        $isSyliusAdmin = $container->hasParameter(self::SYLIUS_ADMIN_PARAMETER);
        if (!$isSyliusAdmin) {
            return;
        }

        $hasGridTemplates = $container->hasParameter(self::SYLIUS_GRID_FILTER_TEMPLATES_PARAMETER);
        if (!$hasGridTemplates) {
            return;
        }

        /** @var array<string, string> $templates */
        $templates = $container->getParameter(self::SYLIUS_GRID_FILTER_TEMPLATES_PARAMETER);
        $dateTemplate = $templates['date'] ?? null;
        if (self::SYLIUS_DEFAULT_GRID_FILTER_TEMPLATE !== $dateTemplate) {
            return;
        }

        $templates['date'] = self::SYLIUS_GRID_FILTER_TEMPLATE;
        $container->setParameter(self::SYLIUS_GRID_FILTER_TEMPLATES_PARAMETER, $templates);
    }

    private function isSyliusAdminInstalled(ContainerBuilder $container): bool
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        $hasAdmin = array_key_exists('SyliusAdminBundle', $bundles);
        $hasGrid = array_key_exists('SyliusGridBundle', $bundles);

        return $hasAdmin && $hasGrid;
    }
}

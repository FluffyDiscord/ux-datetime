<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests\Fixtures;

use FluffyDiscord\UxDatetime\Tests\Fixtures\Sylius\SyliusAdminBundle;
use FluffyDiscord\UxDatetime\Tests\Fixtures\Sylius\SyliusGridBundle;
use FluffyDiscord\UxDatetime\UxDatetimeBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $uxDatetimeConfig
     */
    public function __construct(
        private readonly array $uxDatetimeConfig = [],
        private readonly bool $withSylius = false,
    ) {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new StimulusBundle();
        yield new UxDatetimeBundle();

        if ($this->withSylius) {
            yield new SyliusAdminBundle();
            yield new SyliusGridBundle();
        }
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sprintf('%s/ux-datetime-tests/%s/cache', sys_get_temp_dir(), $this->getConfigHash());
    }

    public function getLogDir(): string
    {
        return sprintf('%s/ux-datetime-tests/%s/log', sys_get_temp_dir(), $this->getConfigHash());
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'form' => true,
            'csrf_protection' => false,
            'http_method_override' => false,
            'default_locale' => 'cs',
            'translator' => ['fallbacks' => ['en']],
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['default_path' => __DIR__ . '/templates']);
        $container->extension('twig_component', [
            'anonymous_template_directory' => 'components/',
            'defaults' => ['FluffyDiscord\UxDatetime\Tests\Fixtures\\' => 'components/'],
        ]);
        $container->extension('ux_datetime', $this->uxDatetimeConfig);

        $container
            ->services()
            ->set(DemoController::class)
            ->autowire()
            ->autoconfigure();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('demo', '/demo')->controller(DemoController::class);
        $routes->add('admin_demo', '/admin/demo')->controller(DemoController::class);
    }

    private function getConfigHash(): string
    {
        return md5(serialize([$this->uxDatetimeConfig, $this->withSylius]));
    }
}

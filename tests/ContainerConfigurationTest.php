<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests;

use FluffyDiscord\UxDatetime\Sylius\LocalTimeDateFilter;
use FluffyDiscord\UxDatetime\Tests\Fixtures\Sylius\RecordingFilter;
use FluffyDiscord\UxDatetime\Tests\Fixtures\TestKernel;
use FluffyDiscord\UxDatetime\UxDatetimeBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class ContainerConfigurationTest extends KernelTestCase
{
    public function testWithoutSyliusTheGridFilterIsNotTouched(): void
    {
        self::bootKernel();

        self::assertFalse(self::getContainer()->has('sylius.grid_filter.date'));
        self::assertFalse(self::getContainer()->hasParameter(UxDatetimeBundle::SYLIUS_ADMIN_PARAMETER));
    }

    public function testSyliusAdminGetsTheRangeFilterTemplateAndTheLocalTimeFilter(): void
    {
        self::bootKernel(['sylius' => true]);

        /** @var array<string, string> $templates */
        $templates = self::getContainer()->getParameter('sylius.grid.templates.filter');

        self::assertSame(UxDatetimeBundle::SYLIUS_GRID_FILTER_TEMPLATE, $templates['date']);
        self::assertInstanceOf(LocalTimeDateFilter::class, self::getContainer()->get('sylius.grid_filter.date'));
    }

    public function testSyliusIntegrationTurnsOff(): void
    {
        self::bootKernel(['sylius' => true, 'ux_datetime' => ['sylius' => ['admin' => false]]]);

        /** @var array<string, string> $templates */
        $templates = self::getContainer()->getParameter('sylius.grid.templates.filter');

        self::assertSame(UxDatetimeBundle::SYLIUS_DEFAULT_GRID_FILTER_TEMPLATE, $templates['date']);
        self::assertInstanceOf(RecordingFilter::class, self::getContainer()->get('sylius.grid_filter.date'));
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        /** @var array<string, mixed> $config */
        $config = $options['ux_datetime'] ?? [];

        return new TestKernel($config, true === ($options['sylius'] ?? false));
    }
}

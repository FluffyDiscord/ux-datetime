<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests;

use FluffyDiscord\UxDatetime\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

class PickerFormTest extends WebTestCase
{
    private const PICKER = '[data-controller="fluffydiscord--ux-datetime--picker"]';
    private const TIME = '[data-controller="fluffydiscord--ux-datetime--time"]';
    private const PICKER_VALUE_PREFIX = 'data-fluffydiscord--ux-datetime--picker-';

    public function testPickerTypesRenderThePickerAndNativeTypesStayNative(): void
    {
        $crawler = static::createClient()->request('GET', '/demo');

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter(self::PICKER));
        self::assertCount(1, $crawler->filter(self::TIME));
        self::assertCount(1, $crawler->filter('input[type="date"][name="demo[nativeDate]"]'));
        self::assertCount(1, $crawler->filter('input[type="time"][name="demo[optedOut]"]'));

        $datePicker = $crawler->filter('[data-testid="demo_date"]');
        self::assertSame('2026-01-01', $datePicker->attr(self::PICKER_VALUE_PREFIX . 'min-value'));
        self::assertSame('2026-12-31', $datePicker->attr(self::PICKER_VALUE_PREFIX . 'max-value'));
        self::assertSame('d. m. rrrr', $datePicker->filter('#demo_date')->attr('placeholder'));

        $dateTimePicker = $crawler->filter('[data-testid="demo_dateTime"]');
        self::assertSame('true', $dateTimePicker->attr(self::PICKER_VALUE_PREFIX . 'with-time-value'));
        self::assertSame(
            ['today'],
            $dateTimePicker
                ->filter('[data-preset]')
                ->each(static fn (Crawler $preset): ?string => $preset->attr('data-preset')),
        );

        $rangeInputs = $crawler->filter('[data-testid="demo_range"] input[type="hidden"]');
        self::assertSame(
            ['demo[range][from]', 'demo[range][to]'],
            $rangeInputs->each(static fn (Crawler $input): ?string => $input->attr('name')),
        );
    }

    public function testFormTypesSubmitTheValuesTheNativeTypesExpect(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/demo');

        $client->submit($crawler
            ->selectButton('Save')
            ->form([
                'demo[date]' => '2026-11-05',
                'demo[dateTime]' => '2026-11-05T14:30',
                'demo[time]' => '09:30',
                'demo[range][from]' => '2026-11-01',
                'demo[range][to]' => '2026-11-30',
            ]));

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                'date' => '2026-11-05 00:00',
                'dateTime' => '2026-11-05 14:30',
                'time' => '09:30:00',
                'range' => ['from' => '2026-11-01', 'to' => '2026-11-30'],
                'nativeDate' => null,
                'splitDateTime' => null,
                'optedOut' => null,
            ],
            $this->getJson($client),
        );
    }

    public function testGlobalSwitchTurnsEverySingleTextWidgetIntoThePicker(): void
    {
        $crawler = static::createClient(['ux_datetime' => ['replace_native_widgets' => true]])->request('GET', '/demo');

        self::assertCount(5, $crawler->filter(self::PICKER));
        self::assertCount(2, $crawler->filter(self::TIME));
        self::assertCount(
            1,
            $crawler->filter('[data-testid="demo_splitDateTime_time"] input[name="demo[splitDateTime][time]"]'),
        );
        self::assertCount(1, $crawler->filter('input[type="time"][name="demo[optedOut]"]'));
    }

    public function testSyliusAdminGetsThePickerAndTheShopStaysNative(): void
    {
        $client = static::createClient(['sylius' => true]);

        $adminPage = $client->request('GET', '/admin/demo');
        self::assertCount(5, $adminPage->filter(self::PICKER));
        self::assertCount(2, $adminPage->filter(self::TIME));

        $shopPage = $client->request('GET', '/demo');
        self::assertCount(1, $shopPage->filter('input[type="date"][name="demo[nativeDate]"]'));
        self::assertCount(1, $shopPage->filter('input[type="time"][name="demo[splitDateTime][time]"]'));
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

    /**
     * @return array<string, mixed>
     */
    private function getJson(KernelBrowser $client): array
    {
        /** @var array<string, mixed> */
        return json_decode(
            (string) $client->getResponse()->getContent(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}

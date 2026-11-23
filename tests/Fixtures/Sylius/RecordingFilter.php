<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests\Fixtures\Sylius;

use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

class RecordingFilter implements FilterInterface
{
    public mixed $data = null;

    /** @var array<string, mixed> */
    public array $options = [];

    /**
     * @param array<string, mixed> $options
     */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        $this->data = $data;
        $this->options = $options;
    }
}

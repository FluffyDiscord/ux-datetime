<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class LimitsData
{
    #[Assert\LessThan('tomorrow')]
    public ?\DateTimeImmutable $deliveredOn = null;

    #[Assert\LessThan('tomorrow')]
    public ?\DateTimeImmutable $overridden = null;
}

<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Tests\Fixtures;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoController extends AbstractController
{
    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(DemoType::class);
        $form->handleRequest($request);

        $isSaved = $form->isSubmitted() && $form->isValid();
        if (!$isSaved) {
            return $this->render('demo.html.twig', ['form' => $form]);
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();

        return $this->json(array_map($this->normalize(...), $data));
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_array($value)) {
            return array_map($this->normalize(...), $value);
        }

        return $value;
    }
}

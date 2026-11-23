<?php

declare(strict_types=1);

namespace FluffyDiscord\UxDatetime\Form;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;

class FieldConstraints
{
    public function __construct(
        private readonly ?MetadataFactoryInterface $metadataFactory = null,
    ) {}

    /**
     * @return list<Constraint>
     */
    public function getValidated(FormInterface $form): array
    {
        $field = $this->getField($form);
        $groups = $this->toGroups($form->getRoot()->getConfig()->getOption('validation_groups'));

        /** @var list<Constraint> $formConstraints */
        $formConstraints = $field->getConfig()->getOption('constraints', []);
        $constraints = [...$formConstraints, ...$this->getPropertyConstraints($field)];

        return array_values(array_filter(
            $constraints,
            static fn (Constraint $constraint): bool => [] !== array_intersect($constraint->groups ?? [], $groups),
        ));
    }

    private function getField(FormInterface $form): FormInterface
    {
        $parent = $form->getParent();
        if (null === $parent) {
            return $form;
        }

        $isDateTimeHalf = $parent->getConfig()->getType()->getInnerType() instanceof DateTimeType;

        return $isDateTimeHalf ? $parent : $form;
    }

    /**
     * @return list<Constraint>
     */
    private function getPropertyConstraints(FormInterface $field): array
    {
        $elements = $field->getPropertyPath()?->getElements() ?? [];
        $property = array_pop($elements);
        $class = array_reduce($elements, $this->getPropertyClass(...), $this->getOwnerClass($field));
        if (null === $this->metadataFactory || null === $class || null === $property) {
            return [];
        }

        $metadata = $this->metadataFactory->getMetadataFor($class);
        if (!$metadata instanceof ClassMetadataInterface) {
            return [];
        }

        $constraints = [];
        foreach ($metadata->getPropertyMetadata($property) as $propertyMetadata) {
            array_push($constraints, ...$propertyMetadata->getConstraints());
        }

        return $constraints;
    }

    /**
     * @return class-string|null
     */
    private function getOwnerClass(FormInterface $field): ?string
    {
        $owner = $field->getParent();
        while (null !== $owner && $owner->getConfig()->getInheritData()) {
            $owner = $owner->getParent();
        }

        /** @var class-string|null */
        return $owner?->getConfig()->getDataClass();
    }

    /**
     * @param class-string|null $class
     *
     * @return class-string|null
     */
    private function getPropertyClass(?string $class, string $property): ?string
    {
        $hasProperty = null !== $class && property_exists($class, $property);
        $type = $hasProperty ? (new \ReflectionProperty($class, $property))->getType() : null;
        $isClass = $type instanceof \ReflectionNamedType && !$type->isBuiltin();

        /** @var class-string|null */
        return $isClass ? $type->getName() : null;
    }

    /**
     * @return list<string>
     */
    private function toGroups(mixed $validationGroups): array
    {
        if (!is_array($validationGroups)) {
            return [Constraint::DEFAULT_GROUP];
        }

        return array_values(array_filter($validationGroups, is_string(...)));
    }
}

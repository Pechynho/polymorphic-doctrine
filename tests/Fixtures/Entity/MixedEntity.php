<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pechynho\PolymorphicDoctrine\Attributes\DynamicPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Attributes\EntityWithPolymorphicRelations;
use Pechynho\PolymorphicDoctrine\Attributes\ExplicitPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueInterface;

#[ORM\Entity]
#[EntityWithPolymorphicRelations]
class MixedEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[DynamicPolymorphicProperty(mapping: [
        'post' => Post::class,
        'tag' => Tag::class,
    ])]
    public PolymorphicValueInterface $dynamicRef;

    #[ExplicitPolymorphicProperty(mapping: [
        'post' => Post::class,
        'tag' => ['fqcn' => Tag::class, 'idPropertyType' => 'string'],
    ])]
    public PolymorphicValueInterface $explicitRef;
}

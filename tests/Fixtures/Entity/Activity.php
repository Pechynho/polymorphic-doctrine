<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pechynho\PolymorphicDoctrine\Attributes\EntityWithPolymorphicRelations;
use Pechynho\PolymorphicDoctrine\Attributes\ExplicitPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueInterface;

#[ORM\Entity]
#[EntityWithPolymorphicRelations]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ExplicitPolymorphicProperty(mapping: [
        'post' => Post::class,
        'user' => User::class,
    ])]
    public PolymorphicValueInterface $subject;

    #[ORM\Column(length: 255)]
    public string $description = '';
}

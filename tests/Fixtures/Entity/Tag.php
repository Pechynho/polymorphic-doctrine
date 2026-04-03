<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Tag
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 64)]
    public ?string $id = null;

    #[ORM\Column(length: 255)]
    public string $label = '';
}

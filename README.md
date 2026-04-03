# Polymorphic Doctrine

A Symfony bundle that brings polymorphic relations to Doctrine ORM. It allows a single entity property to reference different entity types — with type safety, IDE support, and optional foreign key constraints.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## The Problem

Doctrine ORM doesn't natively support a property that needs to reference multiple unrelated entity types. Imagine a `Payment` entity with a `subject` property that can point to either an `EshopItem` or a `Subscription`. Standard Doctrine associations require a fixed target type.

This bundle solves it with two approaches:

| Mode | DB Columns | Foreign Keys | Best For |
|------|-----------|--------------|----------|
| **Dynamic** | `{property}_type` + `{property}_id` | No | Flexible scenarios where FK constraints aren't needed |
| **Explicit** | `{property}_type` + one ID column per mapped type | Yes | Scenarios requiring referential integrity |

## Installation

```bash
composer require pechynho/polymorphic-doctrine
```

Without Symfony Flex, add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Pechynho\PolymorphicDoctrine\PechynhoPolymorphicDoctrineBundle::class => ['all' => true],
];
```

## Quick Start

### 1. Mark the entity and define polymorphic properties

```php
use Doctrine\ORM\Mapping as ORM;
use Pechynho\PolymorphicDoctrine\Attributes\DynamicPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Attributes\EntityWithPolymorphicRelations;
use Pechynho\PolymorphicDoctrine\Attributes\ExplicitPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueInterface;

#[ORM\Entity]
#[EntityWithPolymorphicRelations]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[DynamicPolymorphicProperty([
        'eshop_item' => EshopItem::class,
        'subscription' => Subscription::class,
    ])]
    public PolymorphicValueInterface $dynamicSubject;

    #[ExplicitPolymorphicProperty([
        'eshop_item' => EshopItem::class,
        'subscription' => Subscription::class,
    ])]
    public PolymorphicValueInterface $explicitSubject;
}
```

### 2. Generate reference classes (explicit mode only)

```bash
php bin/console pechynho:polymorphic-doctrine:generate-reference-classes
```

### 3. Work with polymorphic values

```php
class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PolymorphicValueFactoryInterface $polymorphicValueFactory,
    ) {}

    public function createPayment(EshopItem $item): void
    {
        $payment = new Payment();

        // Initialize with a value
        $payment->dynamicSubject = $this->polymorphicValueFactory->create(
            Payment::class, 'dynamicSubject', $item
        );

        // Initialize as null
        $payment->explicitSubject = $this->polymorphicValueFactory->create(
            Payment::class, 'explicitSubject'
        );

        $this->em->persist($payment);
        $this->em->flush();
    }
}
```

## Polymorphic Modes

### Dynamic Mode

Creates two columns per polymorphic property:

| id | dynamic_subject_type | dynamic_subject_id |
|----|----------------------|--------------------|
| 1  | eshop_item           | 123                |
| 2  | subscription         | 456                |
| 3  | NULL                 | NULL               |

**Pros:** Simple setup, no class generation needed.
**Cons:** No foreign keys — the database cannot enforce referential integrity.

### Explicit Mode

Creates a type column plus a dedicated ID column for each mapped entity type:

| id | explicit_subject_type | explicit_subject_eshop_item_id | explicit_subject_subscription_id |
|----|-----------------------|--------------------------------|----------------------------------|
| 1  | eshop_item            | 123                            | NULL                             |
| 2  | subscription          | NULL                           | 456                              |
| 3  | NULL                  | NULL                           | NULL                             |

**Pros:** Foreign key on each ID column, referential integrity enforced by the database.
**Cons:** Requires reference class generation, more columns per table.

## Working with Polymorphic Values

### Creating and Initializing

```php
// With a value
$payment->subject = $this->polymorphicValueFactory->create(Payment::class, 'subject', $entity);

// As null
$payment->subject = $this->polymorphicValueFactory->create(Payment::class, 'subject');
```

### Updating

```php
// Set a new value
$payment->subject->update($newEntity);

// Set to null
$payment->subject->setNull();
// or: $payment->subject->update(null);
```

### Reading

```php
// Check status
$payment->subject->isNull();       // Is the value null?
$payment->subject->isResolvable(); // Does it have a valid type and ID?
$payment->subject->isLoaded();     // Is the entity already loaded in memory?

// Get the entity (lazy-loaded from DB on first access)
$entity = $payment->subject->getValue();

if ($entity instanceof EshopItem) {
    // ...
} elseif ($entity instanceof Subscription) {
    // ...
}
```

> **Tip:** Always check `isNull()` before calling `getValue()`.

### Type-Narrowing with `getValueAs()`

The `getValueAs()` method provides a convenient way to retrieve the referenced entity with a narrowed return type. It accepts a `class-string<T>` and returns `T`, giving you full IDE autocompletion and static analysis support (PHPStan / Psalm).

```php
// Instead of manual instanceof checks:
$item = $payment->subject->getValueAs(EshopItem::class);
// $item is now typed as EshopItem — full IDE support, no manual narrowing needed
```

The method throws a `ReferenceResolutionException` if:
- The value is `null` — always check `isNull()` first, or handle the exception.
- The resolved entity is not an instance of the requested class.

## Searching Polymorphic Values

The bundle provides two approaches for querying polymorphic properties via QueryBuilder.

### Applier — Simple Approach

Directly modifies the QueryBuilder. Best for straightforward queries.

```php
class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PolymorphicSearchExprApplierFactoryInterface $applierFactory,
    ) {}

    public function findByEntity(object $entity): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')->from(Payment::class, 'p');

        $applier = $this->applierFactory->create(Payment::class, 'dynamicSubject', 'p');
        $applier->eq($qb, $entity);

        return $qb->getQuery()->getResult();
    }

    public function findByType(string $entityClass): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')->from(Payment::class, 'p');

        $applier = $this->applierFactory->create(Payment::class, 'explicitSubject', 'p');
        $applier->isInstanceOf($qb, $entityClass);

        return $qb->getQuery()->getResult();
    }
}
```

### Builder — Advanced Approach

Returns expression objects with parameters that can be composed into complex queries.

```php
$builder = $this->builderFactory->create(Payment::class, 'dynamicSubject', 'p');

$eqResult = $builder->eq($item);
$instanceOfResult = $builder->isInstanceOf(EshopItem::class);

$qb->where($qb->expr()->orX($eqResult->expr, $instanceOfResult->expr));

foreach ([$eqResult, $instanceOfResult] as $result) {
    foreach ($result->params as $key => $value) {
        $qb->setParameter($key, $value);
    }
}
```

### Available Operations

| Method | Description |
|--------|-------------|
| `eq($entity)` | Matches the given entity |
| `neq($entity)` | Does not match the given entity |
| `in(...$entities)` | Matches any of the given entities |
| `notIn(...$entities)` | Does not match any of the given entities |
| `isNull()` | Value is null |
| `isNotNull()` | Value is not null |
| `isInstanceOf(...$classes)` | Is an instance of the given type(s) |
| `isNotInstanceOf(...$classes)` | Is not an instance of the given type(s) |

## Configuration

```yaml
# config/packages/pechynho_polymorphic_doctrine.yaml
pechynho_polymorphic_doctrine:
    # Directory for generated reference classes (explicit mode)
    references_directory: '%kernel.cache_dir%/pechynho/polymorphic-doctrine/references'

    # Namespace for generated classes
    references_namespace: 'Pechynho\PolymorphicDoctrine\AutogeneratedReference'

    # Entity discovery settings
    discover:
        cache_directory: '%kernel.cache_dir%/pechynho/polymorphic-doctrine/discover'
        directories:
            - '%kernel.project_dir%/src'
```

## API Reference

### Attributes

#### `#[EntityWithPolymorphicRelations]`

Marks an entity as containing polymorphic relations. Required for entity discovery.

#### `#[DynamicPolymorphicProperty(array $mapping)]`

Defines a dynamic polymorphic property (two columns: type + ID).

| Parameter | Type | Description |
|-----------|------|-------------|
| `$mapping` | `array` | Map of type keys to entity class names |
| `$idProperty` | `?string` | Custom ID property name |
| `$enableDiscriminatorIndex` | `?bool` | Add index on discriminator column |
| `$enablePairIndex` | `?bool` | Add index on type + ID pair |

#### `#[ExplicitPolymorphicProperty(array $mapping)]`

Defines an explicit polymorphic property (type column + dedicated ID column per mapped type).

| Parameter | Type | Description |
|-----------|------|-------------|
| `$mapping` | `array` | Map of type keys to entity class names or detailed config |
| `$idProperty` | `?string` | Default ID property name |
| `$idPropertyType` | `?string` | Default ID property type |
| `$onDelete` | `?string` | Foreign key ON DELETE action |
| `$onUpdate` | `?string` | Foreign key ON UPDATE action |
| `$enableDiscriminatorIndex` | `?bool` | Add index on discriminator column |
| `$enablePairIndex` | `?bool` | Add index on type + ID pair |

Per-type detailed configuration in the mapping:

```php
#[ExplicitPolymorphicProperty([
    'product' => Product::class,
    'service' => [
        'fqcn' => Service::class,
        'idProperty' => 'serviceId',
        'onDelete' => 'CASCADE',
    ],
])]
public PolymorphicValueInterface $subject;
```

### PolymorphicValueInterface

| Method | Return Type | Description |
|--------|-------------|-------------|
| `isNull()` | `bool` | Is the value null? |
| `isResolvable()` | `bool` | Can the value be resolved to an entity? |
| `isLoaded()` | `bool` | Is the entity already loaded in memory? |
| `getValue()` | `?object` | Returns the referenced entity (lazy-loaded) |
| `getValueAs(string $fqcn)` | `T` | Returns the entity narrowed to the given type, or throws |
| `update(?object $value)` | `void` | Updates the reference to another entity or null |
| `setNull()` | `void` | Sets the reference to null |

### Services

| Service | Description |
|---------|-------------|
| `PolymorphicValueFactoryInterface` | Creates polymorphic value instances |
| `PolymorphicSearchExprBuilderFactoryInterface` | Creates search expression builders |
| `PolymorphicSearchExprApplierFactoryInterface` | Creates appliers that directly modify QueryBuilder |

## Console Commands

```bash
# Generate reference classes for explicit polymorphic properties
php bin/console pechynho:polymorphic-doctrine:generate-reference-classes

# Clear cache (discovery + reference classes)
php bin/console pechynho:polymorphic-doctrine:cache-clear
```

> **Important:** After any change to explicit polymorphic property definitions, you must re-run the reference class generation command.

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Reference classes not found (explicit mode) | Run `php bin/console pechynho:polymorphic-doctrine:generate-reference-classes` then `php bin/console cache:clear` |
| Polymorphic properties not working | Verify the `#[EntityWithPolymorphicRelations]` attribute is on the entity, the bundle is registered, and discovery directories are configured |
| Slow queries | Enable indexes via `enableDiscriminatorIndex` and `enablePairIndex` on your attributes |

## Requirements

- PHP >= 8.4
- Symfony 6.4 / 7.x / 8.x
- Doctrine ORM 2.19+ or 3.3+

## License

MIT — see [LICENSE](LICENSE).

## Author

**Jan Pech** — [pechynho@gmail.com](mailto:pechynho@gmail.com)

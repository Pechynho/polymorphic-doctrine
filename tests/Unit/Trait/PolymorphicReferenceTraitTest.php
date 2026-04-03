<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Trait;

use Pechynho\PolymorphicDoctrine\Contract\PolymorphicPropertyValueResolverInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use PHPUnit\Framework\TestCase;

final class PolymorphicReferenceTraitTest extends TestCase
{
    public function testInitialStateIsNotLoaded(): void
    {
        $ref = new DynamicPolymorphicReference();

        self::assertFalse($ref->isLoaded());
    }

    public function testSetNullSetsLoadedTrue(): void
    {
        $ref = new DynamicPolymorphicReference();
        $ref->setNull();

        self::assertTrue($ref->isLoaded());
        self::assertTrue($ref->isNull());
    }

    public function testIsNullWithNullDiscriminator(): void
    {
        $ref = new DynamicPolymorphicReference();

        self::assertTrue($ref->isNull());
        self::assertTrue($ref->isLoaded());
    }

    public function testIsResolvableReturnsTrueWhenDiscriminatorIsNull(): void
    {
        $ref = new DynamicPolymorphicReference();

        self::assertTrue($ref->isResolvable());
    }

    public function testIsResolvableReturnsFalseWithoutResolverAndNonNullDiscriminator(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '1');

        self::assertFalse($ref->isResolvable());
    }

    public function testGetValueReturnsNullWhenDiscriminatorIsNull(): void
    {
        $ref = new DynamicPolymorphicReference();

        self::assertNull($ref->getValue());
    }

    public function testIsNullTriggersLoadAndReturnsFalse(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '1');
        $entity = new \stdClass();

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);
        $resolver->expects(self::once())
            ->method('loadProperty')
            ->willReturn($entity);

        $metadata = $this->createDynamicMetadata();

        $ref->setResolver($resolver);
        $ref->setMetadata($metadata);

        self::assertFalse($ref->isNull());
    }

    public function testUpdateThrowsLogicExceptionWithoutResolver(): void
    {
        $ref = new DynamicPolymorphicReference();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot update value: missing resolver or metadata.');

        $ref->update(new \stdClass());
    }

    public function testUpdateCallsResolverSetProperty(): void
    {
        $ref = new DynamicPolymorphicReference();
        $entity = new \stdClass();
        $metadata = $this->createDynamicMetadata();

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);
        $resolver->expects(self::once())
            ->method('setProperty')
            ->with($ref, $metadata, $entity);

        $ref->setResolver($resolver);
        $ref->setMetadata($metadata);
        $ref->update($entity);

        self::assertTrue($ref->isLoaded());
        self::assertSame($entity, $ref->getValue());
    }

    public function testUpdateWithNullCallsSetNull(): void
    {
        $ref = new DynamicPolymorphicReference();
        $metadata = $this->createDynamicMetadata();

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);
        $resolver->expects(self::once())
            ->method('setProperty')
            ->with($ref, $metadata, null);

        $ref->setResolver($resolver);
        $ref->setMetadata($metadata);
        $ref->update(null);

        self::assertTrue($ref->isNull());
    }

    public function testGetValueLazyLoadsOnFirstAccess(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '1');
        $entity = new \stdClass();
        $metadata = $this->createDynamicMetadata();

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);
        $resolver->expects(self::once())
            ->method('loadProperty')
            ->willReturn($entity);

        $ref->setResolver($resolver);
        $ref->setMetadata($metadata);

        $value1 = $ref->getValue();
        $value2 = $ref->getValue();

        self::assertSame($entity, $value1);
        self::assertSame($entity, $value2);
    }

    public function testSetNullWithoutResolverDoesNotThrow(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '1');
        $ref->setNull();

        self::assertTrue($ref->isNull());
        // Without resolver, setNull() doesn't clear discriminator/id fields
        // It only sets the internal __loaded=true and __value=null
    }

    public function testGetValueThrowsRuntimeExceptionWhenNotResolvable(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '1');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot get value: the reference is not resolvable.');

        $ref->getValue();
    }

    public function testGetValueAsReturnsTypedValue(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '1');
        $entity = new \stdClass();
        $metadata = $this->createDynamicMetadata();

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);
        $resolver->method('loadProperty')->willReturn($entity);

        $ref->setResolver($resolver);
        $ref->setMetadata($metadata);

        self::assertSame($entity, $ref->getValueAs(\stdClass::class));
    }

    public function testGetValueAsThrowsWhenNull(): void
    {
        $ref = new DynamicPolymorphicReference();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the value is null');

        $ref->getValueAs(\stdClass::class);
    }

    public function testGetValueAsThrowsWhenWrongType(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '1');
        $entity = new \stdClass();
        $metadata = $this->createDynamicMetadata();

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);
        $resolver->method('loadProperty')->willReturn($entity);

        $ref->setResolver($resolver);
        $ref->setMetadata($metadata);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot cast value to');

        $ref->getValueAs(\ArrayObject::class);
    }

    public function testSetResolverAndSetMetadata(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '1');
        $metadata = $this->createDynamicMetadata();

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);
        $resolver->method('loadProperty')->willReturn(new \stdClass());

        $ref->setResolver($resolver);
        $ref->setMetadata($metadata);

        self::assertTrue($ref->isResolvable());
    }

    private function createDynamicMetadata(): DynamicPropertyMetadata
    {
        return new DynamicPropertyMetadata(
            property: 'subject',
            mapping: ['post' => new DynamicRelationMetadata(fqcn: \stdClass::class, idProperty: 'id')],
            enableDiscriminatorIndex: true,
            enablePairIndex: true,
        );
    }
}

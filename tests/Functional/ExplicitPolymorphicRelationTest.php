<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Functional;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprApplierFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\AbstractKernelTestCase;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Activity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\User;
use Webmozart\Assert\Assert;

final class ExplicitPolymorphicRelationTest extends AbstractKernelTestCase
{
    private EntityManagerInterface $em;
    private PolymorphicValueFactoryInterface $valueFactory;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $generator = self::getContainer()->get('test.'.ReferenceClassGeneratorInterface::class);
        Assert::isInstanceOf($generator, ReferenceClassGeneratorInterface::class);
        $generator->generate();
    }

    protected function setUp(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite extension is not available.');
        }

        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($em, EntityManagerInterface::class);
        $this->em = $em;

        $this->valueFactory = self::getService(PolymorphicValueFactoryInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $allMetadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($allMetadata);
    }

    protected function tearDown(): void
    {
        if (!isset($this->em)) {
            return;
        }
        $this->em->clear();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    public function testPersistAndLoadWithPostTarget(): void
    {
        $post = new Post();
        $post->title = 'Hello';
        $this->em->persist($post);
        $this->em->flush();

        $activity = new Activity();
        $activity->description = 'Created post';
        $activity->subject = $this->valueFactory->create(Activity::class, 'subject', $post);
        $this->em->persist($activity);
        $this->em->flush();

        $activityId = $activity->id;
        $this->em->clear();

        $loaded = $this->em->find(Activity::class, $activityId);
        self::assertNotNull($loaded);
        self::assertFalse($loaded->subject->isNull());
        $value = $loaded->subject->getValue();
        self::assertInstanceOf(Post::class, $value);
        self::assertSame($post->id, $value->id);
    }

    public function testPersistAndLoadWithUserTarget(): void
    {
        $user = new User();
        $user->name = 'Jane';
        $this->em->persist($user);
        $this->em->flush();

        $activity = new Activity();
        $activity->description = 'Created user';
        $activity->subject = $this->valueFactory->create(Activity::class, 'subject', $user);
        $this->em->persist($activity);
        $this->em->flush();

        $activityId = $activity->id;
        $this->em->clear();

        $loaded = $this->em->find(Activity::class, $activityId);
        self::assertNotNull($loaded);
        $value = $loaded->subject->getValue();
        self::assertInstanceOf(User::class, $value);
        self::assertSame($user->id, $value->id);
    }

    public function testPersistNullSubject(): void
    {
        $activity = new Activity();
        $activity->description = 'Orphan';
        $activity->subject = $this->valueFactory->create(Activity::class, 'subject');
        $this->em->persist($activity);
        $this->em->flush();

        $activityId = $activity->id;
        $this->em->clear();

        $loaded = $this->em->find(Activity::class, $activityId);
        self::assertNotNull($loaded);
        self::assertTrue($loaded->subject->isNull());
        self::assertNull($loaded->subject->getValue());
    }

    public function testUpdateSubjectFromPostToUser(): void
    {
        $post = new Post();
        $post->title = 'A';
        $user = new User();
        $user->name = 'B';
        $this->em->persist($post);
        $this->em->persist($user);
        $this->em->flush();

        $activity = new Activity();
        $activity->description = 'Switch';
        $activity->subject = $this->valueFactory->create(Activity::class, 'subject', $post);
        $this->em->persist($activity);
        $this->em->flush();

        $activity->subject->update($user);
        $this->em->flush();

        $activityId = $activity->id;
        $this->em->clear();

        $loaded = $this->em->find(Activity::class, $activityId);
        self::assertNotNull($loaded);
        $value = $loaded->subject->getValue();
        self::assertInstanceOf(User::class, $value);
        self::assertSame($user->id, $value->id);
    }

    public function testUpdateSubjectToNull(): void
    {
        $post = new Post();
        $post->title = 'X';
        $this->em->persist($post);
        $this->em->flush();

        $activity = new Activity();
        $activity->description = 'Nullify';
        $activity->subject = $this->valueFactory->create(Activity::class, 'subject', $post);
        $this->em->persist($activity);
        $this->em->flush();

        $activity->subject->setNull();
        $this->em->flush();

        $activityId = $activity->id;
        $this->em->clear();

        $loaded = $this->em->find(Activity::class, $activityId);
        self::assertNotNull($loaded);
        self::assertTrue($loaded->subject->isNull());
    }

    public function testForeignKeyConstraintExists(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $allMetadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = $schemaTool->getSchemaFromMetadata($allMetadata);

        $activityTable = $schema->getTable('activity');
        $fks = $activityTable->getForeignKeys();

        self::assertNotEmpty($fks, 'Activity table should have FK constraints for explicit polymorphic property');

        $fkTableNames = array_map(static fn (ForeignKeyConstraint $fk): string => $fk->getForeignTableName(), $fks);
        $fkTableNamesLower = array_map(strtolower(...), $fkTableNames);
        self::assertContains('post', $fkTableNamesLower);
        self::assertContains('user', $fkTableNamesLower);
    }

    public function testQueryEq(): void
    {
        $post = new Post();
        $post->title = 'Q1';
        $user = new User();
        $user->name = 'Q2';
        $this->em->persist($post);
        $this->em->persist($user);
        $this->em->flush();

        $a1 = new Activity();
        $a1->description = 'act1';
        $a1->subject = $this->valueFactory->create(Activity::class, 'subject', $post);

        $a2 = new Activity();
        $a2->description = 'act2';
        $a2->subject = $this->valueFactory->create(Activity::class, 'subject', $user);

        $this->em->persist($a1);
        $this->em->persist($a2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Activity::class, 'subject', 'a');

        $qb = $this->em->createQueryBuilder()->select('a')->from(Activity::class, 'a');
        $applier->eq($qb, $post);

        /** @var list<Activity> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('act1', $results[0]->description);
    }

    public function testQueryIsNull(): void
    {
        $a1 = new Activity();
        $a1->description = 'with_null';
        $a1->subject = $this->valueFactory->create(Activity::class, 'subject');
        $this->em->persist($a1);

        $post = new Post();
        $post->title = 'P';
        $this->em->persist($post);
        $this->em->flush();

        $a2 = new Activity();
        $a2->description = 'with_value';
        $a2->subject = $this->valueFactory->create(Activity::class, 'subject', $post);
        $this->em->persist($a2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Activity::class, 'subject', 'a');

        $qb = $this->em->createQueryBuilder()->select('a')->from(Activity::class, 'a');
        $applier->isNull($qb);

        /** @var list<Activity> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('with_null', $results[0]->description);
    }

    public function testQueryNeq(): void
    {
        $post = new Post();
        $post->title = 'N1';
        $user = new User();
        $user->name = 'N2';
        $this->em->persist($post);
        $this->em->persist($user);
        $this->em->flush();

        $a1 = new Activity();
        $a1->description = 'neq_post';
        $a1->subject = $this->valueFactory->create(Activity::class, 'subject', $post);
        $a2 = new Activity();
        $a2->description = 'neq_user';
        $a2->subject = $this->valueFactory->create(Activity::class, 'subject', $user);

        $this->em->persist($a1);
        $this->em->persist($a2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Activity::class, 'subject', 'a');

        $qb = $this->em->createQueryBuilder()->select('a')->from(Activity::class, 'a');
        $applier->neq($qb, $post);

        /** @var list<Activity> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('neq_user', $results[0]->description);
    }

    public function testQueryIsNotNull(): void
    {
        $post = new Post();
        $post->title = 'NN';
        $this->em->persist($post);
        $this->em->flush();

        $a1 = new Activity();
        $a1->description = 'notnull_has';
        $a1->subject = $this->valueFactory->create(Activity::class, 'subject', $post);
        $a2 = new Activity();
        $a2->description = 'notnull_null';
        $a2->subject = $this->valueFactory->create(Activity::class, 'subject');

        $this->em->persist($a1);
        $this->em->persist($a2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Activity::class, 'subject', 'a');

        $qb = $this->em->createQueryBuilder()->select('a')->from(Activity::class, 'a');
        $applier->isNotNull($qb);

        /** @var list<Activity> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('notnull_has', $results[0]->description);
    }

    public function testQueryIn(): void
    {
        $post = new Post();
        $post->title = 'IN1';
        $user = new User();
        $user->name = 'IN2';
        $this->em->persist($post);
        $this->em->persist($user);
        $this->em->flush();

        $a1 = new Activity();
        $a1->description = 'in_post';
        $a1->subject = $this->valueFactory->create(Activity::class, 'subject', $post);
        $a2 = new Activity();
        $a2->description = 'in_user';
        $a2->subject = $this->valueFactory->create(Activity::class, 'subject', $user);
        $a3 = new Activity();
        $a3->description = 'in_null';
        $a3->subject = $this->valueFactory->create(Activity::class, 'subject');

        $this->em->persist($a1);
        $this->em->persist($a2);
        $this->em->persist($a3);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Activity::class, 'subject', 'a');

        $qb = $this->em->createQueryBuilder()->select('a')->from(Activity::class, 'a');
        $applier->in($qb, $post, $user);

        /** @var list<Activity> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(2, $results);
    }

    public function testQueryIsInstanceOf(): void
    {
        $post = new Post();
        $post->title = 'P';
        $user = new User();
        $user->name = 'U';
        $this->em->persist($post);
        $this->em->persist($user);
        $this->em->flush();

        $a1 = new Activity();
        $a1->description = 'post_activity';
        $a1->subject = $this->valueFactory->create(Activity::class, 'subject', $post);

        $a2 = new Activity();
        $a2->description = 'user_activity';
        $a2->subject = $this->valueFactory->create(Activity::class, 'subject', $user);

        $this->em->persist($a1);
        $this->em->persist($a2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Activity::class, 'subject', 'a');

        $qb = $this->em->createQueryBuilder()->select('a')->from(Activity::class, 'a');
        $applier->isInstanceOf($qb, Post::class);

        /** @var list<Activity> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('post_activity', $results[0]->description);
    }
}

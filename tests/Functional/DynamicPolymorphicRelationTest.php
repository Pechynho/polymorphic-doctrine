<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprApplierFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\AbstractKernelTestCase;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Comment;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\User;
use Webmozart\Assert\Assert;

final class DynamicPolymorphicRelationTest extends AbstractKernelTestCase
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

        $comment = new Comment();
        $comment->text = 'Great post!';
        $comment->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $this->em->persist($comment);
        $this->em->flush();

        $commentId = $comment->id;
        $this->em->clear();

        $loaded = $this->em->find(Comment::class, $commentId);
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

        $comment = new Comment();
        $comment->text = 'Nice!';
        $comment->subject = $this->valueFactory->create(Comment::class, 'subject', $user);
        $this->em->persist($comment);
        $this->em->flush();

        $commentId = $comment->id;
        $this->em->clear();

        $loaded = $this->em->find(Comment::class, $commentId);
        self::assertNotNull($loaded);
        $value = $loaded->subject->getValue();
        self::assertInstanceOf(User::class, $value);
        self::assertSame($user->id, $value->id);
    }

    public function testPersistNullSubject(): void
    {
        $comment = new Comment();
        $comment->text = 'Orphan';
        $comment->subject = $this->valueFactory->create(Comment::class, 'subject');
        $this->em->persist($comment);
        $this->em->flush();

        $commentId = $comment->id;
        $this->em->clear();

        $loaded = $this->em->find(Comment::class, $commentId);
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

        $comment = new Comment();
        $comment->text = 'Switch';
        $comment->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $this->em->persist($comment);
        $this->em->flush();

        $comment->subject->update($user);
        $this->em->flush();

        $commentId = $comment->id;
        $this->em->clear();

        $loaded = $this->em->find(Comment::class, $commentId);
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

        $comment = new Comment();
        $comment->text = 'Nullify';
        $comment->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $this->em->persist($comment);
        $this->em->flush();

        $comment->subject->setNull();
        $this->em->flush();

        $commentId = $comment->id;
        $this->em->clear();

        $loaded = $this->em->find(Comment::class, $commentId);
        self::assertNotNull($loaded);
        self::assertTrue($loaded->subject->isNull());
    }

    public function testLazyLoadingBehavior(): void
    {
        $post = new Post();
        $post->title = 'Lazy';
        $this->em->persist($post);
        $this->em->flush();

        $comment = new Comment();
        $comment->text = 'Lazy test';
        $comment->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $this->em->persist($comment);
        $this->em->flush();

        $commentId = $comment->id;
        $this->em->clear();

        $loaded = $this->em->find(Comment::class, $commentId);
        self::assertNotNull($loaded);
        self::assertFalse($loaded->subject->isLoaded());

        $loaded->subject->getValue();
        self::assertTrue($loaded->subject->isLoaded());
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

        $c1 = new Comment();
        $c1->text = 'comment1';
        $c1->subject = $this->valueFactory->create(Comment::class, 'subject', $post);

        $c2 = new Comment();
        $c2->text = 'comment2';
        $c2->subject = $this->valueFactory->create(Comment::class, 'subject', $user);

        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Comment::class, 'subject', 'c');

        $qb = $this->em->createQueryBuilder()->select('c')->from(Comment::class, 'c');
        $applier->eq($qb, $post);

        /** @var list<Comment> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('comment1', $results[0]->text);
    }

    public function testQueryIsNull(): void
    {
        $c1 = new Comment();
        $c1->text = 'with_null';
        $c1->subject = $this->valueFactory->create(Comment::class, 'subject');
        $this->em->persist($c1);

        $post = new Post();
        $post->title = 'P';
        $this->em->persist($post);
        $this->em->flush();

        $c2 = new Comment();
        $c2->text = 'with_value';
        $c2->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $this->em->persist($c2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Comment::class, 'subject', 'c');

        $qb = $this->em->createQueryBuilder()->select('c')->from(Comment::class, 'c');
        $applier->isNull($qb);

        /** @var list<Comment> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('with_null', $results[0]->text);
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

        $c1 = new Comment();
        $c1->text = 'neq_post';
        $c1->subject = $this->valueFactory->create(Comment::class, 'subject', $post);

        $c2 = new Comment();
        $c2->text = 'neq_user';
        $c2->subject = $this->valueFactory->create(Comment::class, 'subject', $user);

        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Comment::class, 'subject', 'c');

        $qb = $this->em->createQueryBuilder()->select('c')->from(Comment::class, 'c');
        $applier->neq($qb, $post);

        /** @var list<Comment> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('neq_user', $results[0]->text);
    }

    public function testQueryIsNotNull(): void
    {
        $post = new Post();
        $post->title = 'NN';
        $this->em->persist($post);
        $this->em->flush();

        $c1 = new Comment();
        $c1->text = 'notnull_has';
        $c1->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $c2 = new Comment();
        $c2->text = 'notnull_null';
        $c2->subject = $this->valueFactory->create(Comment::class, 'subject');

        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Comment::class, 'subject', 'c');

        $qb = $this->em->createQueryBuilder()->select('c')->from(Comment::class, 'c');
        $applier->isNotNull($qb);

        /** @var list<Comment> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('notnull_has', $results[0]->text);
    }

    public function testQueryIsNotInstanceOf(): void
    {
        $post = new Post();
        $post->title = 'NI';
        $user = new User();
        $user->name = 'NI';
        $this->em->persist($post);
        $this->em->persist($user);
        $this->em->flush();

        $c1 = new Comment();
        $c1->text = 'notinst_post';
        $c1->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $c2 = new Comment();
        $c2->text = 'notinst_user';
        $c2->subject = $this->valueFactory->create(Comment::class, 'subject', $user);

        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Comment::class, 'subject', 'c');

        $qb = $this->em->createQueryBuilder()->select('c')->from(Comment::class, 'c');
        $applier->isNotInstanceOf($qb, Post::class);

        /** @var list<Comment> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('notinst_user', $results[0]->text);
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

        $c1 = new Comment();
        $c1->text = 'in_post';
        $c1->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $c2 = new Comment();
        $c2->text = 'in_user';
        $c2->subject = $this->valueFactory->create(Comment::class, 'subject', $user);
        $c3 = new Comment();
        $c3->text = 'in_null';
        $c3->subject = $this->valueFactory->create(Comment::class, 'subject');

        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->persist($c3);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Comment::class, 'subject', 'c');

        $qb = $this->em->createQueryBuilder()->select('c')->from(Comment::class, 'c');
        $applier->in($qb, $post, $user);

        /** @var list<Comment> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(2, $results);
    }

    public function testQueryNotIn(): void
    {
        $post = new Post();
        $post->title = 'NIN';
        $user = new User();
        $user->name = 'NIN';
        $this->em->persist($post);
        $this->em->persist($user);
        $this->em->flush();

        $c1 = new Comment();
        $c1->text = 'notin_post';
        $c1->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $c2 = new Comment();
        $c2->text = 'notin_user';
        $c2->subject = $this->valueFactory->create(Comment::class, 'subject', $user);

        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Comment::class, 'subject', 'c');

        $qb = $this->em->createQueryBuilder()->select('c')->from(Comment::class, 'c');
        $applier->notIn($qb, $post);

        /** @var list<Comment> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('notin_user', $results[0]->text);
    }

    public function testQueryIsInstanceOfMultipleClasses(): void
    {
        $post = new Post();
        $post->title = 'MI';
        $user = new User();
        $user->name = 'MI';
        $this->em->persist($post);
        $this->em->persist($user);
        $this->em->flush();

        $c1 = new Comment();
        $c1->text = 'multi_post';
        $c1->subject = $this->valueFactory->create(Comment::class, 'subject', $post);
        $c2 = new Comment();
        $c2->text = 'multi_user';
        $c2->subject = $this->valueFactory->create(Comment::class, 'subject', $user);
        $c3 = new Comment();
        $c3->text = 'multi_null';
        $c3->subject = $this->valueFactory->create(Comment::class, 'subject');

        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->persist($c3);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Comment::class, 'subject', 'c');

        $qb = $this->em->createQueryBuilder()->select('c')->from(Comment::class, 'c');
        $applier->isInstanceOf($qb, Post::class, User::class);

        /** @var list<Comment> $results */
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

        $c1 = new Comment();
        $c1->text = 'post_comment';
        $c1->subject = $this->valueFactory->create(Comment::class, 'subject', $post);

        $c2 = new Comment();
        $c2->text = 'user_comment';
        $c2->subject = $this->valueFactory->create(Comment::class, 'subject', $user);

        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->flush();
        $this->em->clear();

        $applierFactory = self::getService(PolymorphicSearchExprApplierFactoryInterface::class);
        $applier = $applierFactory->create(Comment::class, 'subject', 'c');

        $qb = $this->em->createQueryBuilder()->select('c')->from(Comment::class, 'c');
        $applier->isInstanceOf($qb, Post::class);

        /** @var list<Comment> $results */
        $results = $qb->getQuery()->getResult();
        self::assertCount(1, $results);
        self::assertSame('post_comment', $results[0]->text);
    }
}

<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\AbstractKernelTestCase;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Activity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Comment;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;

final class PolymorphicEventListenerIntegrationTest extends AbstractKernelTestCase
{
    private EntityManagerInterface $em;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $generator = self::getContainer()->get('test.'.ReferenceClassGeneratorInterface::class);
        \Webmozart\Assert\Assert::isInstanceOf($generator, ReferenceClassGeneratorInterface::class);
        $generator->generate();
    }

    protected function setUp(): void
    {
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        \Webmozart\Assert\Assert::isInstanceOf($em, EntityManagerInterface::class);
        $this->em = $em;
    }

    public function testLoadClassMetadataMapsEmbeddableForDynamicProperty(): void
    {
        $classMetadata = $this->em->getClassMetadata(Comment::class);

        self::assertArrayHasKey('subject.discriminator', $classMetadata->fieldMappings);
        self::assertArrayHasKey('subject.id', $classMetadata->fieldMappings);
    }

    public function testLoadClassMetadataMapsEmbeddableForExplicitProperty(): void
    {
        $classMetadata = $this->em->getClassMetadata(Activity::class);

        self::assertArrayHasKey('subject.discriminator', $classMetadata->fieldMappings);
        self::assertArrayHasKey('subject.postId', $classMetadata->fieldMappings);
        self::assertArrayHasKey('subject.userId', $classMetadata->fieldMappings);
    }

    public function testPostGenerateSchemaTableCreatesIndexes(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $allMetadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = $schemaTool->getSchemaFromMetadata($allMetadata);

        $commentTable = $schema->getTable('comment');
        $indexColumns = [];
        foreach ($commentTable->getIndexes() as $index) {
            $indexColumns[] = $index->getColumns();
        }
        $flatColumns = array_merge(...$indexColumns);

        self::assertContains('subject_discriminator', $flatColumns);
    }

    public function testPostGenerateSchemaTableCreatesForeignKeysForExplicit(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $allMetadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = $schemaTool->getSchemaFromMetadata($allMetadata);

        $activityTable = $schema->getTable('activity');
        $fks = $activityTable->getForeignKeys();

        self::assertNotEmpty($fks, 'Activity table should have foreign key constraints');
    }

    public function testPostLoadInitializesReference(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite extension is not available.');
        }

        $schemaTool = new SchemaTool($this->em);
        $allMetadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($allMetadata);

        try {
            $post = new Post();
            $post->title = 'Test';
            $this->em->persist($post);
            $this->em->flush();

            $conn = $this->em->getConnection();
            $conn->insert('comment', [
                'text' => 'Hello',
                'subject_discriminator' => 'post',
                'subject_id' => (string) $post->id,
            ]);

            $this->em->clear();

            $comment = $this->em->getRepository(Comment::class)->findOneBy(['text' => 'Hello']);
            self::assertNotNull($comment);
            self::assertTrue($comment->subject->isResolvable());
        } finally {
            $schemaTool->dropSchema($allMetadata);
        }
    }
}

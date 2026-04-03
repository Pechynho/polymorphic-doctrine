<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Model\ClassMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitRelationMetadata;
use Pechynho\PolymorphicDoctrine\ReferenceClassGenerator;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Activity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Tag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ReferenceClassGeneratorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/polymorphic-test-gen-'.uniqid();
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if ($fs->exists($this->tmpDir)) {
            $fs->remove($this->tmpDir);
        }
    }

    public function testGenerateCreatesClassFile(): void
    {
        $metadata = $this->createExplicitMetadata();
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->method('getAllMetadata')->willReturn([
            Activity::class => new ClassMetadata(['subject' => $metadata]),
        ]);

        $generator = new ReferenceClassGenerator($metadataProvider, $this->tmpDir, new Filesystem());
        $generator->generate();

        self::assertFileExists($metadata->referencePath);
    }

    public function testGenerateSkipsExistingFiles(): void
    {
        $metadata = $this->createExplicitMetadata();
        $fs = new Filesystem();
        $fs->mkdir(\dirname($metadata->referencePath));
        $fs->dumpFile($metadata->referencePath, '<?php // existing');

        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->method('getAllMetadata')->willReturn([
            Activity::class => new ClassMetadata(['subject' => $metadata]),
        ]);

        $generator = new ReferenceClassGenerator($metadataProvider, $this->tmpDir, $fs);
        $generator->generate();

        $content = file_get_contents($metadata->referencePath);
        self::assertIsString($content);
        self::assertStringContainsString('// existing', $content);
    }

    public function testClearRemovesAndRecreatesDirectory(): void
    {
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $fs = new Filesystem();
        $fs->dumpFile($this->tmpDir.'/test.php', '<?php');

        $generator = new ReferenceClassGenerator($metadataProvider, $this->tmpDir, $fs);
        $generator->clear();

        self::assertDirectoryExists($this->tmpDir);
        self::assertFileDoesNotExist($this->tmpDir.'/test.php');
    }

    public function testClearNoopWhenDirectoryDoesNotExist(): void
    {
        $nonExistentDir = $this->tmpDir.'/nonexistent';
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);

        $generator = new ReferenceClassGenerator($metadataProvider, $nonExistentDir, new Filesystem());
        $generator->clear();

        self::assertDirectoryDoesNotExist($nonExistentDir);
    }

    public function testGeneratedClassContent(): void
    {
        $metadata = $this->createExplicitMetadata();
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);

        $generator = new ReferenceClassGenerator($metadataProvider, $this->tmpDir, new Filesystem());
        $generator->generateClass($metadata);

        $content = file_get_contents($metadata->referencePath);
        self::assertIsString($content);
        self::assertStringContainsString('#[Embeddable]', $content);
        self::assertStringContainsString('PolymorphicValueInterface', $content);
        self::assertStringContainsString('PolymorphicReferenceInterface', $content);
        self::assertStringContainsString('PolymorphicReferenceTrait', $content);
        self::assertStringContainsString('$discriminator', $content);
        self::assertStringContainsString('$postId', $content);
    }

    public function testGeneratedClassWithStringIdType(): void
    {
        $metadata = new ExplicitPropertyMetadata(
            property: 'subject',
            mapping: [
                'tag' => new ExplicitRelationMetadata(
                    fqcn: Tag::class,
                    idProperty: 'id',
                    idPropertyType: 'string',
                    propertyName: 'tagId',
                    columnName: 'tag_id',
                    onDelete: 'RESTRICT',
                    onUpdate: 'RESTRICT',
                    enablePairIndex: true,
                ),
            ],
            referenceFqcn: Tag::class,
            referencePath: $this->tmpDir.'/SubjectRef__str123.php',
            enableDiscriminatorIndex: true,
        );

        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $generator = new ReferenceClassGenerator($metadataProvider, $this->tmpDir, new Filesystem());
        $generator->generateClass($metadata);

        $content = file_get_contents($metadata->referencePath);
        self::assertIsString($content);
        self::assertStringContainsString('$tagId', $content);
        self::assertStringContainsString("type: 'string'", $content);
        self::assertStringContainsString('length: 64', $content);
    }

    public function testGenerateClassThrowsForInvalidFqcn(): void
    {
        $metadata = new ExplicitPropertyMetadata(
            property: 'subject',
            mapping: [],
            /* @phpstan-ignore argument.type */
            referenceFqcn: 'NoNamespace',
            referencePath: $this->tmpDir.'/NoNamespace.php',
            enableDiscriminatorIndex: true,
        );

        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $generator = new ReferenceClassGenerator($metadataProvider, $this->tmpDir, new Filesystem());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must contain a namespace');

        $generator->generateClass($metadata);
    }

    public function testGenerateCreatesDirectoryIfMissing(): void
    {
        $newDir = $this->tmpDir.'/subdir';
        $metadata = $this->createExplicitMetadata();
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->method('getAllMetadata')->willReturn([
            Activity::class => new ClassMetadata(['subject' => $metadata]),
        ]);

        $generator = new ReferenceClassGenerator($metadataProvider, $newDir, new Filesystem());
        $generator->generate();

        self::assertDirectoryExists($newDir);
    }

    private function createExplicitMetadata(): ExplicitPropertyMetadata
    {
        return new ExplicitPropertyMetadata(
            property: 'subject',
            mapping: [
                'post' => new ExplicitRelationMetadata(
                    fqcn: Post::class,
                    idProperty: 'id',
                    idPropertyType: 'int',
                    propertyName: 'postId',
                    columnName: 'post_id',
                    onDelete: 'RESTRICT',
                    onUpdate: 'RESTRICT',
                    enablePairIndex: true,
                ),
            ],
            referenceFqcn: Post::class,
            referencePath: $this->tmpDir.'/SubjectReference__abc123.php',
            enableDiscriminatorIndex: true,
        );
    }
}

<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Document\Serialization\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Security\EncryptionOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentSerializationPlanBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_a_serialization_plan_from_document_state(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $plan = (new DocumentSerializationPlanBuilder())->build($document);

        self::assertSame(1.4, $plan->fileStructure->version);
        self::assertSame(1, $plan->fileStructure->trailer->rootObjectId);
        self::assertSame(3, $plan->fileStructure->trailer->infoObjectId);
        self::assertNull($plan->fileStructure->trailer->encryptObjectId);
        self::assertSame($document->getDocumentId(), $plan->fileStructure->trailer->documentId);
        $objects = is_array($plan->objects) ? $plan->objects : iterator_to_array($plan->objects, false);
        self::assertSame([1, 2, 3], array_slice(array_map(
            static fn (object $object): int => $object->id,
            $objects,
        ), 0, 3));
    }

    #[Test]
    public function it_omits_the_info_object_for_profiles_without_info_dictionary(): void
    {
        $document = new Document(profile: Profile::pdfA4());

        $plan = (new DocumentSerializationPlanBuilder())->build($document);

        self::assertNull($plan->fileStructure->trailer->infoObjectId);
    }

    #[Test]
    public function it_includes_encryption_data_when_document_encryption_is_enabled(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->encrypt(new EncryptionOptions('user', 'owner'));

        $plan = (new DocumentSerializationPlanBuilder())->build($document);

        self::assertNotNull($plan->fileStructure->trailer->encryptObjectId);
        self::assertNotNull($plan->encryption);
        self::assertNotNull($plan->encryption->profile);
        self::assertNotNull($plan->encryption->securityHandlerData);
    }
}

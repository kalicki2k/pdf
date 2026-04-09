<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentSerializationPlanBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_a_serialization_plan_from_document_state(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $plan = (new DocumentSerializationPlanBuilder())->build($document);

        self::assertSame(1.4, $plan->version);
        self::assertSame(1, $plan->trailer->rootObjectId);
        self::assertSame(3, $plan->trailer->infoObjectId);
        self::assertNull($plan->trailer->encryptObjectId);
        self::assertSame($document->getDocumentId(), $plan->trailer->documentId);
        self::assertSame([1, 2, 3], array_slice(array_map(
            static fn (object $object): int => $object->id,
            $plan->objects,
        ), 0, 3));
    }

    #[Test]
    public function it_omits_the_info_object_for_profiles_without_info_dictionary(): void
    {
        $document = new Document(profile: Profile::pdfA4());

        $plan = (new DocumentSerializationPlanBuilder())->build($document);

        self::assertNull($plan->trailer->infoObjectId);
    }

    #[Test]
    public function it_includes_encryption_data_when_document_encryption_is_enabled(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->encrypt(new EncryptionOptions('user', 'owner'));

        $plan = (new DocumentSerializationPlanBuilder())->build($document);

        self::assertNotNull($plan->trailer->encryptObjectId);
        self::assertNotNull($plan->encryptionProfile);
        self::assertNotNull($plan->securityHandlerData);
    }
}

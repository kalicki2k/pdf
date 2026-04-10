<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\TaggedPdf;

use InvalidArgumentException;
use Kalle\Pdf\Document;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Page\Annotation\LinkAnnotation;
use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Internal\TaggedPdf\StructElem;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StructElemTest extends TestCase
{
    #[Test]
    public function it_renders_an_empty_structure_element(): void
    {
        $structElem = new StructElem(4, 'Document');

        self::assertSame(
            "4 0 obj\n<< /Type /StructElem /S /Document /K [] >>\nendobj\n",
            $structElem->render(),
        );
    }

    #[Test]
    public function it_adds_kids_and_renders_their_references(): void
    {
        $structElem = new StructElem(10, 'P');
        $firstChild = new StructElem(11, 'Span');
        $secondChild = new StructElem(12, 'Span');

        $result = $structElem->addKid($firstChild)->addKid($secondChild);

        self::assertSame($structElem, $result);
        self::assertSame(
            "10 0 obj\n<< /Type /StructElem /S /P /K [11 0 R 12 0 R] >>\nendobj\n",
            $structElem->render(),
        );
    }

    #[Test]
    public function it_rejects_unknown_structure_tags(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Tag 'UnknownTag' is not allowed.");

        new StructElem(12, 'UnknownTag');
    }

    #[Test]
    public function it_accepts_table_structure_tags(): void
    {
        self::assertStringContainsString('/S /Table', (new StructElem(12, 'Table'))->render());
        self::assertStringContainsString('/S /TR', (new StructElem(13, 'TR'))->render());
        self::assertStringContainsString('/S /TH', (new StructElem(14, 'TH'))->render());
        self::assertStringContainsString('/S /TD', (new StructElem(15, 'TD'))->render());
        self::assertStringContainsString('/S /Figure', (new StructElem(16, 'Figure'))->render());
    }

    #[Test]
    public function it_renders_alt_text_for_structure_elements(): void
    {
        $structElem = new StructElem(12, 'Figure');
        $structElem->setAltText('Illustration');

        self::assertStringContainsString('/Alt (Illustration)', $structElem->render());
    }

    #[Test]
    public function it_renders_table_scope_and_span_attributes(): void
    {
        $structElem = new StructElem(12, 'TH');
        $structElem
            ->setScope('Row')
            ->setRowSpan(2)
            ->setColSpan(3);

        self::assertStringContainsString('/A << /O /Table /Scope /Row /RowSpan 2 /ColSpan 3 >>', $structElem->render());
    }

    #[Test]
    public function it_renders_object_references_alongside_marked_content(): void
    {
        $document = new Document(profile: Profile::standard(1.7));
        $page = $document->addPage();
        $annotation = new LinkAnnotation(
            12,
            $page,
            10,
            20,
            80,
            12,
            LinkTarget::externalUrl('https://example.com'),
        );
        $structElem = new StructElem(11, 'Link');
        $structElem
            ->setMarkedContent(0, $page)
            ->addObjectReference($annotation, $page);

        self::assertSame(
            "11 0 obj\n"
            . "<< /Type /StructElem /S /Link /Pg 4 0 R /K [0 << /Type /OBJR /Obj 12 0 R /Pg 4 0 R >>] >>\n"
            . "endobj\n",
            $structElem->render(),
        );
    }

    #[Test]
    public function it_can_render_alt_text_with_an_explicit_object_string_encryptor(): void
    {
        $structElem = new StructElem(12, 'Figure');
        $structElem->setAltText('Illustration');

        $rendered = $structElem->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                12,
            ),
        );

        self::assertStringStartsWith("12 0 obj\n<< /Type /StructElem /S /Figure", $rendered);
        self::assertStringNotContainsString('(Illustration)', $rendered);
    }
}

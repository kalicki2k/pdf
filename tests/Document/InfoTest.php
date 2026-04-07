<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Info;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InfoTest extends TestCase
{
    #[Test]
    public function it_renders_required_metadata_fields(): void
    {
        $document = new Document(
            profile: \Kalle\Pdf\Profile::standard(1.0),
            title: 'Spec',
            author: 'Kalle',
        );
        $info = new Info(3, $document);

        $rendered = $info->render();

        self::assertStringStartsWith("3 0 obj\n<< /Title (Spec) /Author (Kalle)", $rendered);
        self::assertStringContainsString('/Creator (' . $document->getCreator() . ')', $rendered);
        self::assertStringContainsString('/Producer (' . $document->getProducer() . ')', $rendered);
        self::assertMatchesRegularExpression("/\\/CreationDate \\(D:\\d{14}[+-]\\d{2}'\\d{2}'\\)/", $rendered);
        self::assertMatchesRegularExpression("/\\/ModDate \\(D:\\d{14}[+-]\\d{2}'\\d{2}'\\)/", $rendered);
        self::assertStringNotContainsString('/Subject (', $rendered);
        self::assertStringNotContainsString('/Keywords (', $rendered);
        self::assertStringNotContainsString('/Lang (', $rendered);
        self::assertStringEndsWith("endobj\n", $rendered);
    }

    #[Test]
    public function it_renders_optional_subject_keywords_and_language_metadata(): void
    {
        $document = new Document(
            profile: \Kalle\Pdf\Profile::standard(1.4),
            title: 'Spec',
            author: 'Kalle',
            subject: 'Testing',
            language: 'de-DE',
        );
        $document->addKeyword('pdf')->addKeyword('tests');
        $info = new Info(5, $document);

        $rendered = $info->render();

        self::assertStringContainsString('/Subject (Testing)', $rendered);
        self::assertStringContainsString('/Keywords (pdf, tests)', $rendered);
        self::assertStringNotContainsString('/Lang (de-DE)', $rendered);
    }

    #[Test]
    public function it_allows_custom_creator_and_producer_metadata(): void
    {
        $document = new Document(
            profile: \Kalle\Pdf\Profile::standard(1.0),
            title: 'Spec',
            author: 'Kalle',
            creator: 'Acme Invoice Service',
        );
        $document->setProducer('kalle/pdf 1.0');
        $info = new Info(3, $document);

        $rendered = $info->render();

        self::assertStringContainsString('/Creator (Acme Invoice Service)', $rendered);
        self::assertStringContainsString('/Producer (kalle/pdf 1.0)', $rendered);
    }

    #[Test]
    public function it_keeps_author_and_creator_as_distinct_metadata_roles(): void
    {
        $document = new Document(
            profile: \Kalle\Pdf\Profile::standard(1.0),
            title: 'Spec',
            author: 'DEIN FIRMENNAME',
            creator: 'Rechnungsservice',
            creatorTool: 'Backoffice Export',
        );
        $info = new Info(3, $document);

        $rendered = $info->render();

        self::assertStringContainsString('/Author (DEIN FIRMENNAME)', $rendered);
        self::assertStringContainsString('/Creator (Rechnungsservice)', $rendered);
        self::assertStringNotContainsString('/Creator (Backoffice Export)', $rendered);
    }
}

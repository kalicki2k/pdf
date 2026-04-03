<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use Kalle\Pdf\Core\Document;
use Kalle\Pdf\Core\Info;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InfoTest extends TestCase
{
    #[Test]
    public function it_renders_required_metadata_fields(): void
    {
        $document = new Document(title: 'Spec', author: 'Kalle');
        $info = new Info(3, $document);

        $rendered = $info->render();

        self::assertStringStartsWith("3 0 obj\n<< /Title (Spec) /Author (Kalle)", $rendered);
        self::assertStringContainsString('/Creator (kalle/pdf)', $rendered);
        self::assertStringContainsString('/Producer (kalle/pdf)', $rendered);
        self::assertMatchesRegularExpression('/\/CreationDate \(D:\d{14}\)/', $rendered);
        self::assertStringNotContainsString('/Subject (', $rendered);
        self::assertStringNotContainsString('/Keywords (', $rendered);
        self::assertStringNotContainsString('/Lang (', $rendered);
        self::assertStringEndsWith("endobj\n", $rendered);
    }

    #[Test]
    public function it_renders_optional_subject_keywords_and_language_metadata(): void
    {
        $document = new Document(
            version: 1.4,
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
        self::assertStringContainsString('/Lang (de-DE)', $rendered);
    }
}

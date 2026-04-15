<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentBuildException;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class DocumentBuildExceptionTest extends TestCase
{
    public function testItBuildsMessageAndHintFromValidationFailure(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID,
                'PDF/A output intent "Press Condition" is not plausible for an RGB ICC profile.',
            ),
        );

        self::assertSame('PDF/A-1b', $exception->profileName);
        self::assertSame(
            'Use the default PDF/A output intent or pass ->pdfaOutputIntent(...) with a readable ICC profile that matches the document color usage.',
            $exception->hint,
        );
        self::assertStringContainsString('Document build failed for profile PDF/A-1b.', $exception->getMessage());
        self::assertStringContainsString('Reason: PDF/A output intent "Press Condition" is not plausible for an RGB ICC profile.', $exception->getMessage());
        self::assertStringContainsString('Hint: Use the default PDF/A output intent', $exception->getMessage());
    }

    public function testItOmitsHintWhenResolverReturnsNull(): void
    {
        $previous = new InvalidArgumentException('Some unrelated validation failure.');
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::standard()),
            $previous,
        );

        self::assertNull($exception->hint);
        self::assertSame($previous, $exception->getPrevious());
        self::assertStringNotContainsString('Hint:', $exception->getMessage());
    }
}

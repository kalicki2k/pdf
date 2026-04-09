<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Render\RenderContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RenderContextTest extends TestCase
{
    #[Test]
    public function it_restores_the_previous_context_after_scoped_rendering(): void
    {
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );

        $outerEncrypted = RenderContext::runWith(
            $encryptor,
            static fn (): string => RenderContext::runInObject(
                7,
                static function (): string {
                    $beforeNestedCall = RenderContext::encryptString('Hello');
                    self::assertNotNull($beforeNestedCall);

                    RenderContext::runInObject(9, static fn (): ?string => RenderContext::encryptString('Hello'));

                    $afterNestedCall = RenderContext::encryptString('Hello');
                    self::assertNotNull($afterNestedCall);
                    self::assertSame($beforeNestedCall, $afterNestedCall);

                    return $afterNestedCall;
                },
            ),
        );

        self::assertNotNull($outerEncrypted);
        self::assertNull(RenderContext::encryptString('Hello'));
    }
}

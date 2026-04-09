<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\ObjectStringEncryptor;
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
                    $beforeNestedCall = RenderContext::currentStringEncryptor();
                    self::assertInstanceOf(ObjectStringEncryptor::class, $beforeNestedCall);

                    RenderContext::runInObject(
                        9,
                        static fn (): ?ObjectStringEncryptor => RenderContext::currentStringEncryptor(),
                    );

                    $afterNestedCall = RenderContext::currentStringEncryptor();
                    self::assertInstanceOf(ObjectStringEncryptor::class, $afterNestedCall);
                    self::assertSame(
                        $beforeNestedCall->encrypt('Hello'),
                        $afterNestedCall->encrypt('Hello'),
                    );

                    return $afterNestedCall->encrypt('Hello');
                },
            ),
        );

        self::assertNotNull($outerEncrypted);
        self::assertNull(RenderContext::currentStringEncryptor());
    }
}

<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Document\Preparation\DocumentDeferredRendering;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentDeferredRenderingTest extends TestCase
{
    #[Test]
    public function it_releases_registered_deferred_callbacks_once(): void
    {
        $deferredRendering = new DocumentDeferredRendering();
        $headerRenderer = static function (): void {
        };
        $footerRenderer = static function (): void {
        };
        $renderFinalizer = static function (): void {
        };

        $deferredRendering->addHeaderRenderer($headerRenderer);
        $deferredRendering->addFooterRenderer($footerRenderer);
        $deferredRendering->registerRenderFinalizer($renderFinalizer);

        self::assertCount(1, $deferredRendering->releaseHeaderRenderers());
        self::assertCount(1, $deferredRendering->releaseFooterRenderers());
        self::assertCount(1, $deferredRendering->releaseRenderFinalizers());
        self::assertSame([], $deferredRendering->releaseHeaderRenderers());
        self::assertSame([], $deferredRendering->releaseFooterRenderers());
        self::assertSame([], $deferredRendering->releaseRenderFinalizers());
    }
}

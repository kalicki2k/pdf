<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Profile;

/**
 * @internal Applies document render-time lifecycle steps before the PDF is serialized.
 */
final class DocumentRenderLifecycle
{
    /**
     * @param list<callable(): void> $finalizers
     */
    public function applyDeferredRenderFinalizers(array &$finalizers): void
    {
        if ($finalizers === []) {
            return;
        }

        foreach ($finalizers as $finalizer) {
            $finalizer();
        }

        $finalizers = [];
    }

    /**
     * @param list<callable(Page, int, int): void> $headerRenderers
     * @param list<callable(Page, int, int): void> $footerRenderers
     * @param list<Page> $pages
     * @param callable(callable(): void): void $renderInArtifactContext
     */
    public function applyDeferredPageDecorators(
        array &$headerRenderers,
        array &$footerRenderers,
        array $pages,
        callable $renderInArtifactContext,
    ): void {
        if ($headerRenderers === [] && $footerRenderers === []) {
            return;
        }

        $totalPages = count($pages);

        foreach ($pages as $index => $page) {
            $pageNumber = $index + 1;
            $this->runDeferredPageDecorators($headerRenderers, $page, $pageNumber, $totalPages, $renderInArtifactContext);
            $this->runDeferredPageDecorators($footerRenderers, $page, $pageNumber, $totalPages, $renderInArtifactContext);
        }

        $headerRenderers = [];
        $footerRenderers = [];
    }

    public function assertRenderRequirements(
        Profile $profile,
        ?string $title,
        ?string $language,
        bool $hasStructure,
    ): void {
        $this->assertRequiredDocumentTitle($profile, $title);
        $this->assertRequiredDocumentLanguage($profile, $language);
        $this->assertRequiredDocumentStructure($profile, $hasStructure);
    }

    /**
     * @param list<callable(Page, int, int): void> $renderers
     * @param callable(callable(): void): void $renderInArtifactContext
     */
    private function runDeferredPageDecorators(
        array $renderers,
        Page $page,
        int $pageNumber,
        int $totalPages,
        callable $renderInArtifactContext,
    ): void {
        $renderInArtifactContext(function () use ($renderers, $page, $pageNumber, $totalPages): void {
            foreach ($renderers as $renderer) {
                $renderer($page, $pageNumber, $totalPages);
            }
        });
    }

    private function assertRequiredDocumentTitle(Profile $profile, ?string $title): void
    {
        if (!$profile->requiresDocumentTitle()) {
            return;
        }

        if ($title !== null && $title !== '') {
            return;
        }

        throw new InvalidArgumentException(sprintf('Profile %s requires a document title.', $profile->name()));
    }

    private function assertRequiredDocumentLanguage(Profile $profile, ?string $language): void
    {
        if (!$profile->requiresDocumentLanguage()) {
            return;
        }

        if ($language !== null && $language !== '') {
            return;
        }

        throw new InvalidArgumentException(sprintf('Profile %s requires a document language.', $profile->name()));
    }

    private function assertRequiredDocumentStructure(Profile $profile, bool $hasStructure): void
    {
        if (!$profile->requiresDocumentStructure()) {
            return;
        }

        if ($hasStructure) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s requires tagged content in the current implementation.',
            $profile->name(),
        ));
    }
}

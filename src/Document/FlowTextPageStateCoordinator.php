<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Closure;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Text\TextOptions;

final readonly class FlowTextPageStateCoordinator
{
    /**
     * @param list<PageAnnotation> $currentAnnotations
     * @param array{contents: string, annotations: list<PageAnnotation>, textMarkedContentIds: list<int>} $textResult
     * @param Closure(string, string): string $appendPageContent
     * @return array{contents: string, annotations: list<PageAnnotation>, cursorY: float, cursorYIsTopBoundary: bool}
     */
    public function applyFlowTextResult(
        string $currentContents,
        array $currentAnnotations,
        TextFlow $textFlow,
        TextOptions $options,
        float $startY,
        int $lineCount,
        array $textResult,
        Closure $appendPageContent,
    ): array {
        return [
            'contents' => $appendPageContent($currentContents, $textResult['contents']),
            'annotations' => [...$currentAnnotations, ...$textResult['annotations']],
            'cursorY' => $textFlow->nextCursorY($options, $startY, $lineCount),
            'cursorYIsTopBoundary' => false,
        ];
    }

    /**
     * @param list<PageAnnotation> $currentAnnotations
     * @param array{contents: string, annotations: list<PageAnnotation>, textMarkedContentIds?: list<int>} $textResult
     * @param Closure(string, string): string $appendPageContent
     * @return array{contents: string, annotations: list<PageAnnotation>}
     */
    public function appendRenderedText(
        string $currentContents,
        array $currentAnnotations,
        array $textResult,
        Closure $appendPageContent,
    ): array {
        return [
            'contents' => $appendPageContent($currentContents, $textResult['contents']),
            'annotations' => [...$currentAnnotations, ...$textResult['annotations']],
        ];
    }
}

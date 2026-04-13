<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function array_key_first;
use function count;

use Kalle\Pdf\Debug\Debugger;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

use function spl_object_id;
use function strlen;

final readonly class SimpleTextShaper implements TextShaper
{
    private const SHAPE_CACHE_LIMIT = 16;

    /**
     * @var list<ScriptTextShaper>
     */
    private array $scriptShapers;

    public function __construct(
        private ScriptResolver $scriptResolver = new SimpleScriptResolver(),
        ?ArabicScriptTextShaper $arabicTextShaper = null,
        ?DevanagariScriptTextShaper $devanagariTextShaper = null,
        ?BengaliScriptTextShaper $bengaliTextShaper = null,
        ?GujaratiScriptTextShaper $gujaratiTextShaper = null,
        ?DefaultScriptTextShaper $defaultScriptTextShaper = null,
        private ?Debugger $debugger = null,
    ) {
        $this->scriptShapers = [
            $arabicTextShaper ?? new ArabicScriptTextShaper(),
            $devanagariTextShaper ?? new DevanagariScriptTextShaper(),
            $bengaliTextShaper ?? new BengaliScriptTextShaper(),
            $gujaratiTextShaper ?? new GujaratiScriptTextShaper(),
            $defaultScriptTextShaper ?? new DefaultScriptTextShaper(),
        ];
    }

    /**
     * @return list<ShapedTextRun>
     */
    public function shape(
        string $text,
        TextDirection $baseDirection = TextDirection::LTR,
        StandardFontDefinition | EmbeddedFontDefinition | null $font = null,
    ): array {
        /** @var array<string, list<ShapedTextRun>> $shapeCache */
        static $shapeCache = [];

        $cacheKey = $this->shapeCacheKey($text, $baseDirection, $font);

        if (isset($shapeCache[$cacheKey])) {
            /** @var list<ShapedTextRun> $cachedRuns */
            $cachedRuns = $shapeCache[$cacheKey];
            unset($shapeCache[$cacheKey]);
            $shapeCache[$cacheKey] = $cachedRuns;

            return $cachedRuns;
        }

        $debugger = $this->debugger ?? Debugger::disabled();
        $resolveScope = $debugger->startPerformanceScope('text.shape.resolve', [
            'text_length' => strlen($text),
        ]);
        $scriptRuns = $this->scriptResolver->resolve($text, $baseDirection);
        $resolveScope->stop([
            'text_length' => strlen($text),
            'run_count' => count($scriptRuns),
        ]);
        /** @var list<ShapedTextRun> $runs */
        $runs = [];

        foreach ($scriptRuns as $run) {
            $shapeScope = $debugger->startPerformanceScope('text.shape.run.' . $run->script->value, [
                'direction' => $run->direction->value,
                'text_length' => strlen($run->text),
            ]);
            $runs[] = $this->scriptTextShaperFor($run->script)->shape($run, $font);
            $shapeScope->stop([
                'direction' => $run->direction->value,
                'text_length' => strlen($run->text),
            ]);
        }

        $shapeCache[$cacheKey] = $runs;

        if (count($shapeCache) > self::SHAPE_CACHE_LIMIT) {
            $oldestKey = array_key_first($shapeCache);
            unset($shapeCache[$oldestKey]);
        }

        return $runs;
    }

    private function shapeCacheKey(
        string $text,
        TextDirection $baseDirection,
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
    ): string {
        $fontKey = match (true) {
            $font instanceof StandardFontDefinition => 'standard:' . $font->name,
            $font instanceof EmbeddedFontDefinition => 'embedded:' . spl_object_id($font),
            default => 'none',
        };

        return $baseDirection->value . "\0" . $fontKey . "\0" . $text;
    }

    private function scriptTextShaperFor(TextScript $script): ScriptTextShaper
    {
        foreach ($this->scriptShapers as $scriptShaper) {
            if ($scriptShaper->supports($script)) {
                return $scriptShaper;
            }
        }

        return new DefaultScriptTextShaper();
    }
}

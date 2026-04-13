<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

final readonly class SimpleTextShaper implements TextShaper
{
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
        $debugger = $this->debugger ?? Debugger::disabled();
        $resolveScope = $debugger->startPerformanceScope('text.shape.resolve', [
            'text_length' => strlen($text),
        ]);
        $scriptRuns = $this->scriptResolver->resolve($text, $baseDirection);
        $resolveScope->stop([
            'text_length' => strlen($text),
            'run_count' => count($scriptRuns),
        ]);
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

        return $runs;
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

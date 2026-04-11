<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

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
        ?DefaultScriptTextShaper $defaultScriptTextShaper = null,
    ) {
        $this->scriptShapers = [
            $arabicTextShaper ?? new ArabicScriptTextShaper(),
            $devanagariTextShaper ?? new DevanagariScriptTextShaper(),
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
        $runs = [];

        foreach ($this->scriptResolver->resolve($text, $baseDirection) as $run) {
            $runs[] = $this->scriptTextShaperFor($run->script)->shape($run, $font);
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

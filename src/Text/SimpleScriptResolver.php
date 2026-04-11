<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function array_pop;
use function preg_match;
use function preg_split;

final readonly class SimpleScriptResolver implements ScriptResolver
{
    public function __construct(
        private BidiResolver $bidiResolver = new SimpleBidiResolver(),
    ) {
    }

    /**
     * @return list<ScriptRun>
     */
    public function resolve(string $text, TextDirection $baseDirection = TextDirection::LTR): array
    {
        $runs = [];

        foreach ($this->bidiResolver->resolve($text, $baseDirection) as $bidiRun) {
            foreach (preg_split('//u', $bidiRun->text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                $script = $this->scriptForCharacter($character);
                $lastRun = array_pop($runs);

                if ($script === TextScript::COMMON || $script === TextScript::INHERITED) {
                    if ($lastRun !== null && $lastRun->direction === $bidiRun->direction) {
                        $runs[] = new ScriptRun($lastRun->text . $character, $lastRun->direction, $lastRun->script);

                        continue;
                    }

                    if ($lastRun !== null) {
                        $runs[] = $lastRun;
                    }

                    $runs[] = new ScriptRun($character, $bidiRun->direction, TextScript::COMMON);

                    continue;
                }

                if ($lastRun === null) {
                    $runs[] = new ScriptRun($character, $bidiRun->direction, $script);

                    continue;
                }

                if (
                    $lastRun->direction === $bidiRun->direction
                    && ($lastRun->script === TextScript::COMMON || $lastRun->script === TextScript::INHERITED)
                ) {
                    $runs[] = new ScriptRun($lastRun->text . $character, $lastRun->direction, $script);

                    continue;
                }

                if ($lastRun->direction === $bidiRun->direction && $lastRun->script === $script) {
                    $runs[] = new ScriptRun($lastRun->text . $character, $lastRun->direction, $lastRun->script);

                    continue;
                }

                $runs[] = $lastRun;
                $runs[] = new ScriptRun($character, $bidiRun->direction, $script);
            }
        }

        return $runs;
    }

    private function scriptForCharacter(string $character): TextScript
    {
        return match (true) {
            preg_match('/\p{Latin}/u', $character) === 1 => TextScript::LATIN,
            preg_match('/[\x{0590}-\x{05FF}]/u', $character) === 1 => TextScript::HEBREW,
            preg_match('/[\x{0600}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFC}]/u', $character) === 1 => TextScript::ARABIC,
            preg_match('/[\x{0900}-\x{097F}]/u', $character) === 1 => TextScript::DEVANAGARI,
            preg_match('/[\p{Common}]/u', $character) === 1 => TextScript::COMMON,
            preg_match('/[\p{Inherited}]/u', $character) === 1 => TextScript::INHERITED,
            default => TextScript::UNKNOWN,
        };
    }
}

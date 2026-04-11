<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function array_pop;
use function count;
use function max;
use function preg_match;
use function preg_split;
use function str_contains;

/**
 * @phpstan-type BidiEntry array{
 *     character: string,
 *     type: BidiCharacterType,
 *     direction: TextDirection,
 *     level: int,
 *     isolateSequence: int
 * }
 * @phpstan-type EmbeddingState array{
 *     level: int,
 *     direction: TextDirection,
 *     isolateSequence: int,
 *     overrideDirection: ?TextDirection
 * }
 */
final readonly class SimpleBidiResolver implements BidiResolver
{
    public function __construct(
        private BidiMirroring $mirroring = new BidiMirroring(),
    ) {
    }

    /**
     * @return list<BidiRun>
     */
    public function resolve(string $text, TextDirection $baseDirection = TextDirection::LTR): array
    {
        if ($text === '') {
            return [];
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        /** @var list<BidiEntry> $entries */
        $entries = $this->buildEntries($characters, $baseDirection);

        if ($entries === []) {
            return [];
        }

        $this->resolveWeakAndNeutralDirections($entries, $baseDirection);
        $this->applyBracketDirections($entries, $baseDirection);
        $this->applyMirroredCharacters($entries);

        /** @var list<BidiRun> $runs */
        $runs = [];

        foreach ($entries as $entry) {
            if ($entry['character'] === '') {
                continue;
            }

            $lastRun = array_pop($runs);

            if ($lastRun === null) {
                $runs[] = new BidiRun($entry['character'], $entry['direction'], $entry['level'], $entry['isolateSequence']);

                continue;
            }

            if (
                $lastRun->direction === $entry['direction']
                && $lastRun->embeddingLevel === $entry['level']
                && $lastRun->isolateSequence === $entry['isolateSequence']
            ) {
                $runs[] = new BidiRun(
                    $lastRun->text . $entry['character'],
                    $lastRun->direction,
                    $lastRun->embeddingLevel,
                    $lastRun->isolateSequence,
                );

                continue;
            }

            $runs[] = $lastRun;
            $runs[] = new BidiRun($entry['character'], $entry['direction'], $entry['level'], $entry['isolateSequence']);
        }

        return array_values($this->reorderRunsVisually($runs, $baseDirection));
    }

    /**
     * @param list<string> $characters
     * @return list<BidiEntry>
     */
    private function buildEntries(array $characters, TextDirection $baseDirection): array
    {
        /** @var list<EmbeddingState> $embeddingStack */
        $embeddingStack = [[
            'level' => $this->baseEmbeddingLevel($baseDirection),
            'direction' => $baseDirection,
            'isolateSequence' => 0,
            'overrideDirection' => null,
        ]];
        $nextIsolateSequence = 1;
        /** @var list<BidiEntry> $entries */
        $entries = [];

        foreach ($characters as $index => $character) {
            if ($character === "\u{200E}" || $character === "\u{200F}") {
                $currentEmbedding = $embeddingStack[count($embeddingStack) - 1];
                $entries[] = [
                    'character' => '',
                    'type' => $character === "\u{200E}" ? BidiCharacterType::LTR : BidiCharacterType::RTL,
                    'direction' => $character === "\u{200E}" ? TextDirection::LTR : TextDirection::RTL,
                    'level' => $currentEmbedding['level'],
                    'isolateSequence' => $currentEmbedding['isolateSequence'],
                ];

                continue;
            }

            if ($this->isControlCharacter($character)) {
                $this->applyControlCharacter(
                    $character,
                    $embeddingStack,
                    $baseDirection,
                    $characters,
                    $index,
                    $nextIsolateSequence,
                );

                continue;
            }

            $currentEmbedding = $embeddingStack[count($embeddingStack) - 1];
            $overrideDirection = $currentEmbedding['overrideDirection'];
            $entries[] = [
                'character' => $character,
                'type' => $overrideDirection !== null
                    ? $this->typeForOverrideDirection($overrideDirection)
                    : $this->typeForCharacter($character),
                'direction' => $overrideDirection ?? $currentEmbedding['direction'],
                'level' => $currentEmbedding['level'],
                'isolateSequence' => $currentEmbedding['isolateSequence'],
            ];
        }

        return $entries;
    }

    /**
     * @param array<int, EmbeddingState> $embeddingStack
     * @param list<string> $characters
     */
    private function applyControlCharacter(
        string $character,
        array &$embeddingStack,
        TextDirection $baseDirection,
        array $characters,
        int $index,
        int &$nextIsolateSequence,
    ): void {
        match ($character) {
            "\u{202A}" => $this->pushEmbedding($embeddingStack, TextDirection::LTR),
            "\u{202B}" => $this->pushEmbedding($embeddingStack, TextDirection::RTL),
            "\u{202D}" => $this->pushEmbedding($embeddingStack, TextDirection::LTR, null, TextDirection::LTR),
            "\u{202E}" => $this->pushEmbedding($embeddingStack, TextDirection::RTL, null, TextDirection::RTL),
            "\u{2066}" => $this->pushEmbedding($embeddingStack, TextDirection::LTR, $nextIsolateSequence++),
            "\u{2067}" => $this->pushEmbedding($embeddingStack, TextDirection::RTL, $nextIsolateSequence++),
            "\u{2068}" => $this->pushEmbedding(
                $embeddingStack,
                $this->firstStrongDirection($characters, $index + 1, $baseDirection),
                $nextIsolateSequence++,
            ),
            "\u{202C}", "\u{2069}" => $this->popEmbedding($embeddingStack),
            default => null,
        };
    }

    /**
     * @param array<int, EmbeddingState> $embeddingStack
     */
    private function popEmbedding(array &$embeddingStack): void
    {
        if (count($embeddingStack) > 1) {
            array_pop($embeddingStack);
        }
    }

    /**
     * @param array<int, EmbeddingState> $embeddingStack
     */
    private function pushEmbedding(
        array &$embeddingStack,
        TextDirection $direction,
        ?int $isolateSequence = null,
        ?TextDirection $overrideDirection = null,
    ): void {
        $currentEmbedding = $embeddingStack[count($embeddingStack) - 1];
        $currentLevel = $currentEmbedding['level'];
        $embeddingStack[] = [
            'level' => $this->nextEmbeddingLevel($currentLevel, $direction),
            'direction' => $direction,
            'isolateSequence' => $isolateSequence ?? $currentEmbedding['isolateSequence'],
            'overrideDirection' => $overrideDirection,
        ];
    }

    /**
     * @param list<string> $characters
     */
    private function firstStrongDirection(array $characters, int $startIndex, TextDirection $fallback): TextDirection
    {
        $count = count($characters);

        for ($index = $startIndex; $index < $count; $index++) {
            $type = $this->typeForCharacter($characters[$index]);

            if ($type === BidiCharacterType::LTR) {
                return TextDirection::LTR;
            }

            if ($type === BidiCharacterType::RTL) {
                return TextDirection::RTL;
            }
        }

        return $fallback;
    }

    public function typeForCharacter(string $character): BidiCharacterType
    {
        if ($this->isControlCharacter($character)) {
            return BidiCharacterType::CONTROL;
        }

        if ($this->isNonSpacingMark($character)) {
            return BidiCharacterType::NONSPACING_MARK;
        }

        if ($this->isRtlCharacter($character)) {
            return BidiCharacterType::RTL;
        }

        if ($this->isLtrCharacter($character)) {
            return BidiCharacterType::LTR;
        }

        if ($this->isArabicIndicNumber($character)) {
            return BidiCharacterType::ARABIC_NUMBER;
        }

        if ($this->isEuropeanNumber($character)) {
            return BidiCharacterType::EUROPEAN_NUMBER;
        }

        if ($this->isSeparatorCharacter($character)) {
            return BidiCharacterType::SEPARATOR;
        }

        if ($this->isWhitespaceCharacter($character)) {
            return BidiCharacterType::WHITESPACE;
        }

        return BidiCharacterType::NEUTRAL;
    }

    private function isLtrCharacter(string $character): bool
    {
        return preg_match('/[\p{Latin}\p{Greek}\p{Cyrillic}]/u', $character) === 1;
    }

    private function isRtlCharacter(string $character): bool
    {
        return preg_match('/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFD}\x{FE70}-\x{FEFC}]/u', $character) === 1;
    }

    private function isEuropeanNumber(string $character): bool
    {
        return preg_match('/\pN/u', $character) === 1;
    }

    private function isArabicIndicNumber(string $character): bool
    {
        return preg_match('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', $character) === 1;
    }

    private function isNonSpacingMark(string $character): bool
    {
        return preg_match('/\p{Mn}/u', $character) === 1;
    }

    private function isSeparatorCharacter(string $character): bool
    {
        return preg_match('/[.,:\/\-\+\x{066B}\x{066C}]/u', $character) === 1;
    }

    private function isWhitespaceCharacter(string $character): bool
    {
        return preg_match('/\s/u', $character) === 1;
    }

    private function isControlCharacter(string $character): bool
    {
        return preg_match('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $character) === 1;
    }

    private function typeForOverrideDirection(TextDirection $direction): BidiCharacterType
    {
        return $direction === TextDirection::RTL
            ? BidiCharacterType::RTL
            : BidiCharacterType::LTR;
    }

    private function baseEmbeddingLevel(TextDirection $baseDirection): int
    {
        return $baseDirection === TextDirection::RTL ? 1 : 0;
    }

    private function nextEmbeddingLevel(int $currentLevel, TextDirection $direction): int
    {
        if ($direction === TextDirection::LTR) {
            return $currentLevel % 2 === 0 ? $currentLevel + 2 : $currentLevel + 1;
        }

        return $currentLevel % 2 === 0 ? $currentLevel + 1 : $currentLevel + 2;
    }

    private function directionForLevel(int $level): TextDirection
    {
        return $level % 2 === 0 ? TextDirection::LTR : TextDirection::RTL;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function resolveWeakAndNeutralDirections(array &$entries, TextDirection $baseDirection): void
    {
        $resolvedDirection = $baseDirection;
        $count = count($entries);

        for ($index = 0; $index < $count; $index++) {
            $entry = $entries[$index];

            if ($entry['type'] === BidiCharacterType::LTR) {
                $this->replaceEntryDirection($entries, $index, TextDirection::LTR);
                $resolvedDirection = TextDirection::LTR;

                continue;
            }

            if ($entry['type'] === BidiCharacterType::RTL) {
                $this->replaceEntryDirection($entries, $index, TextDirection::RTL);
                $resolvedDirection = TextDirection::RTL;

                continue;
            }

            if ($entry['type'] === BidiCharacterType::NONSPACING_MARK) {
                $direction = $this->resolveNonSpacingMarkDirection($entries, $index, $resolvedDirection);
                $this->replaceEntryDirection($entries, $index, $direction);

                continue;
            }

            if ($entry['type'] === BidiCharacterType::EUROPEAN_NUMBER) {
                $direction = $this->resolveEuropeanNumberDirection($entries, $index, $resolvedDirection);
                $this->replaceEntryDirection($entries, $index, $direction);
                $resolvedDirection = $direction;

                continue;
            }

            if ($entry['type'] === BidiCharacterType::ARABIC_NUMBER) {
                $this->replaceEntryDirection($entries, $index, TextDirection::RTL);
                $resolvedDirection = TextDirection::RTL;

                continue;
            }

            if ($entry['type'] === BidiCharacterType::SEPARATOR) {
                $direction = $this->resolveSeparatorDirection($entries, $index, $resolvedDirection);
                $this->replaceEntryDirection($entries, $index, $direction);

                continue;
            }

            $direction = $this->resolveNeutralDirection($entries, $index, $resolvedDirection);
            $this->replaceEntryDirection($entries, $index, $direction);
        }
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function replaceEntryDirection(array &$entries, int $index, TextDirection $direction): void
    {
        $entries[$index] = [
            'character' => $entries[$index]['character'],
            'type' => $entries[$index]['type'],
            'direction' => $direction,
            'level' => $entries[$index]['level'],
            'isolateSequence' => $entries[$index]['isolateSequence'],
        ];
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function replaceEntryCharacter(array &$entries, int $index, string $character): void
    {
        $entries[$index] = [
            'character' => $character,
            'type' => $entries[$index]['type'],
            'direction' => $entries[$index]['direction'],
            'level' => $entries[$index]['level'],
            'isolateSequence' => $entries[$index]['isolateSequence'],
        ];
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function resolveNonSpacingMarkDirection(array $entries, int $index, TextDirection $fallback): TextDirection
    {
        $previousDirection = $this->previousResolvedDirection($entries, $index);

        if ($previousDirection !== null) {
            return $previousDirection;
        }

        return $fallback;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function resolveEuropeanNumberDirection(array $entries, int $index, TextDirection $fallback): TextDirection
    {
        $previousNumberType = $this->previousNumberType($entries, $index);
        $nextNumberType = $this->nextNumberType($entries, $index);

        if (
            $previousNumberType === BidiCharacterType::ARABIC_NUMBER
            || $nextNumberType === BidiCharacterType::ARABIC_NUMBER
        ) {
            return TextDirection::RTL;
        }

        $previousStrong = $this->previousStrongDirection($entries, $index);

        if ($previousStrong === TextDirection::RTL) {
            return TextDirection::RTL;
        }

        return TextDirection::LTR;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function resolveSeparatorDirection(array $entries, int $index, TextDirection $fallback): TextDirection
    {
        $previousType = $this->previousNumberType($entries, $index);
        $nextType = $this->nextNumberType($entries, $index);

        if (
            $this->isNumberType($previousType)
            && $this->isNumberType($nextType)
        ) {
            return ($previousType === BidiCharacterType::ARABIC_NUMBER || $nextType === BidiCharacterType::ARABIC_NUMBER)
                ? TextDirection::RTL
                : TextDirection::LTR;
        }

        $previousDirection = $this->previousResolvedDirection($entries, $index);
        $nextDirection = $this->nextResolvedDirection($entries, $index);

        if ($previousDirection !== null && $nextDirection !== null && $previousDirection === $nextDirection) {
            return $previousDirection;
        }

        return $previousDirection ?? $nextDirection ?? $fallback;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function resolveNeutralDirection(array $entries, int $index, TextDirection $fallback): TextDirection
    {
        $previousDirection = $this->previousResolvedDirection($entries, $index);
        $nextDirection = $this->nextResolvedDirection($entries, $index);

        if ($previousDirection !== null && $nextDirection !== null && $previousDirection === $nextDirection) {
            return $previousDirection;
        }

        if ($previousDirection !== null || $nextDirection !== null) {
            return $previousDirection ?? $nextDirection;
        }

        $previousStrong = $this->previousStrongDirection($entries, $index);
        $nextStrong = $this->nextStrongDirection($entries, $index);

        if ($previousStrong !== null && $nextStrong !== null && $previousStrong === $nextStrong) {
            return $previousStrong;
        }

        if ($previousStrong !== null && $nextStrong !== null) {
            return $this->directionForLevel($entries[$index]['level']);
        }

        return $previousStrong ?? $nextStrong ?? $fallback;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function previousResolvedDirection(array $entries, int $index): ?TextDirection
    {
        $currentLevel = $entries[$index]['level'];
        $currentIsolateSequence = $entries[$index]['isolateSequence'];

        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            if (
                $entries[$cursor]['level'] !== $currentLevel
                || $entries[$cursor]['isolateSequence'] !== $currentIsolateSequence
            ) {
                continue;
            }

            return $entries[$cursor]['direction'];
        }

        return null;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function nextResolvedDirection(array $entries, int $index): ?TextDirection
    {
        $count = count($entries);
        $currentLevel = $entries[$index]['level'];
        $currentIsolateSequence = $entries[$index]['isolateSequence'];

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            if (
                $entries[$cursor]['level'] !== $currentLevel
                || $entries[$cursor]['isolateSequence'] !== $currentIsolateSequence
            ) {
                continue;
            }

            return $entries[$cursor]['direction'];
        }

        return null;
    }

    private function isNumberType(?BidiCharacterType $type): bool
    {
        return $type === BidiCharacterType::EUROPEAN_NUMBER
            || $type === BidiCharacterType::ARABIC_NUMBER;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function previousNumberType(array $entries, int $index): ?BidiCharacterType
    {
        $currentLevel = $entries[$index]['level'];
        $currentIsolateSequence = $entries[$index]['isolateSequence'];

        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            if (
                $entries[$cursor]['level'] !== $currentLevel
                || $entries[$cursor]['isolateSequence'] !== $currentIsolateSequence
            ) {
                continue;
            }

            $type = $entries[$cursor]['type'];

            if ($this->isNumberType($type)) {
                return $type;
            }

            if (
                $type !== BidiCharacterType::WHITESPACE
                && $type !== BidiCharacterType::SEPARATOR
                && $type !== BidiCharacterType::NONSPACING_MARK
            ) {
                break;
            }
        }

        return null;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function nextNumberType(array $entries, int $index): ?BidiCharacterType
    {
        $count = count($entries);
        $currentLevel = $entries[$index]['level'];
        $currentIsolateSequence = $entries[$index]['isolateSequence'];

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            if (
                $entries[$cursor]['level'] !== $currentLevel
                || $entries[$cursor]['isolateSequence'] !== $currentIsolateSequence
            ) {
                continue;
            }

            $type = $entries[$cursor]['type'];

            if ($this->isNumberType($type)) {
                return $type;
            }

            if (
                $type !== BidiCharacterType::WHITESPACE
                && $type !== BidiCharacterType::SEPARATOR
                && $type !== BidiCharacterType::NONSPACING_MARK
            ) {
                break;
            }
        }

        return null;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function previousStrongDirection(array $entries, int $index): ?TextDirection
    {
        $currentLevel = $entries[$index]['level'];
        $currentIsolateSequence = $entries[$index]['isolateSequence'];

        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            if (
                $entries[$cursor]['level'] !== $currentLevel
                || $entries[$cursor]['isolateSequence'] !== $currentIsolateSequence
            ) {
                continue;
            }

            $type = $entries[$cursor]['type'];

            if ($type === BidiCharacterType::LTR) {
                return TextDirection::LTR;
            }

            if ($type === BidiCharacterType::RTL) {
                return TextDirection::RTL;
            }
        }

        return null;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function nextStrongDirection(array $entries, int $index): ?TextDirection
    {
        $count = count($entries);
        $currentLevel = $entries[$index]['level'];
        $currentIsolateSequence = $entries[$index]['isolateSequence'];

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            if (
                $entries[$cursor]['level'] !== $currentLevel
                || $entries[$cursor]['isolateSequence'] !== $currentIsolateSequence
            ) {
                continue;
            }

            $type = $entries[$cursor]['type'];

            if ($type === BidiCharacterType::LTR) {
                return TextDirection::LTR;
            }

            if ($type === BidiCharacterType::RTL) {
                return TextDirection::RTL;
            }
        }

        return null;
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function applyBracketDirections(array &$entries, TextDirection $baseDirection): void
    {
        /** @var list<array{open: int, close: int}> $pairs */
        $pairs = [];
        /** @var list<array{character: string, index: int}> $stack */
        $stack = [];

        foreach ($entries as $index => $entry) {
            if ($this->isOpeningBracket($entry['character'])) {
                $stack[] = ['character' => $entry['character'], 'index' => $index];

                continue;
            }

            if (!$this->isClosingBracket($entry['character'])) {
                continue;
            }

            /** @var array{character: string, index: int}|null $lastOpen */
            $lastOpen = array_pop($stack);

            if ($lastOpen === null || !$this->isMatchingBracket($lastOpen['character'], $entry['character'])) {
                continue;
            }

            $pairs[] = ['open' => $lastOpen['index'], 'close' => $index];
        }

        foreach ($pairs as $pair) {
            $innerDirection = $this->innerStrongDirection($entries, $pair['open'] + 1, $pair['close'] - 1) ?? $baseDirection;

            $this->replaceEntryDirection($entries, $pair['open'], $innerDirection);
            $this->replaceEntryDirection($entries, $pair['close'], $innerDirection);
        }
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function applyMirroredCharacters(array &$entries): void
    {
        foreach ($entries as $index => $entry) {
            if (
                $entry['direction'] === TextDirection::RTL
                && $this->mirroring->isMirrorable($entry['character'])
            ) {
                $this->replaceEntryCharacter($entries, $index, $this->mirroring->mirror($entry['character']));
            }
        }
    }

    /**
     * @param array<int, BidiEntry> $entries
     */
    private function innerStrongDirection(array $entries, int $start, int $end): ?TextDirection
    {
        if (!isset($entries[$start])) {
            return null;
        }

        $currentLevel = $entries[$start]['level'];
        $currentIsolateSequence = $entries[$start]['isolateSequence'];

        for ($index = $start; $index <= $end; $index++) {
            if (!isset($entries[$index])) {
                continue;
            }

            if (
                $entries[$index]['level'] !== $currentLevel
                || $entries[$index]['isolateSequence'] !== $currentIsolateSequence
            ) {
                continue;
            }

            $type = $entries[$index]['type'];

            if ($type === BidiCharacterType::LTR) {
                return TextDirection::LTR;
            }

            if ($type === BidiCharacterType::RTL) {
                return TextDirection::RTL;
            }
        }

        return null;
    }

    private function isOpeningBracket(string $character): bool
    {
        return str_contains('([{<', $character);
    }

    private function isClosingBracket(string $character): bool
    {
        return str_contains(')]}>', $character);
    }

    private function isMatchingBracket(string $opening, string $closing): bool
    {
        return match ($opening) {
            '(' => $closing === ')',
            '[' => $closing === ']',
            '{' => $closing === '}',
            '<' => $closing === '>',
            default => false,
        };
    }

    /**
     * @param array<int, BidiRun> $runs
     * @return array<int, BidiRun>
     */
    private function reorderRunsVisually(array $runs, TextDirection $baseDirection): array
    {
        if ($runs === []) {
            return [];
        }

        $maxLevel = 0;

        foreach ($runs as $run) {
            $maxLevel = max($maxLevel, $run->embeddingLevel);
        }

        $minOddLevel = $baseDirection === TextDirection::RTL ? 1 : 0;

        for ($level = $maxLevel; $level > $minOddLevel; $level--) {
            $segmentStart = null;

            foreach ($runs as $index => $run) {
                if ($run->embeddingLevel >= $level) {
                    $segmentStart ??= $index;

                    continue;
                }

                if ($segmentStart !== null) {
                    $this->reverseRunSegment($runs, $segmentStart, $index - 1);
                    $segmentStart = null;
                }
            }

            if ($segmentStart !== null) {
                $this->reverseRunSegment($runs, $segmentStart, count($runs) - 1);
            }
        }

        return $runs;
    }

    /**
     * @param array<int, BidiRun> $runs
     */
    private function reverseRunSegment(array &$runs, int $start, int $end): void
    {
        while ($start < $end) {
            $temporary = $runs[$start];
            $runs[$start] = $runs[$end];
            $runs[$end] = $temporary;
            $start++;
            $end--;
        }
    }
}

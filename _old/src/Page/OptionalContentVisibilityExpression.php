<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function array_map;
use function array_values;
use function implode;
use function sort;

use InvalidArgumentException;

final readonly class OptionalContentVisibilityExpression
{
    private const string TYPE_ALIAS = 'alias';
    private const string TYPE_AND = 'And';
    private const string TYPE_OR = 'Or';
    private const string TYPE_NOT = 'Not';

    /**
     * @param list<self> $operands
     */
    private function __construct(
        private string $type,
        private ?string $groupAlias = null,
        private array $operands = [],
    ) {
    }

    public static function alias(string $groupAlias): self
    {
        if ($groupAlias === '') {
            throw new InvalidArgumentException('Optional content visibility expression alias must not be empty.');
        }

        return new self(self::TYPE_ALIAS, $groupAlias);
    }

    public static function and(self ...$operands): self
    {
        if ($operands === []) {
            throw new InvalidArgumentException('Optional content visibility expression And requires at least one operand.');
        }

        return new self(self::TYPE_AND, null, array_values($operands));
    }

    public static function or(self ...$operands): self
    {
        if ($operands === []) {
            throw new InvalidArgumentException('Optional content visibility expression Or requires at least one operand.');
        }

        return new self(self::TYPE_OR, null, array_values($operands));
    }

    public static function not(self $operand): self
    {
        return new self(self::TYPE_NOT, null, [$operand]);
    }

    public function isAlias(): bool
    {
        return $this->type === self::TYPE_ALIAS;
    }

    public function groupAlias(): ?string
    {
        return $this->groupAlias;
    }

    /**
     * @return list<self>
     */
    public function operands(): array
    {
        return $this->operands;
    }

    public function operatorToken(): ?string
    {
        return match ($this->type) {
            self::TYPE_AND => '/And',
            self::TYPE_OR => '/Or',
            self::TYPE_NOT => '/Not',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function referencedAliases(): array
    {
        if ($this->isAlias()) {
            return [$this->groupAlias ?? ''];
        }

        $aliases = [];

        foreach ($this->operands as $operand) {
            $aliases = [...$aliases, ...$operand->referencedAliases()];
        }

        sort($aliases);

        return $aliases;
    }

    public function key(): string
    {
        if ($this->isAlias()) {
            return 'alias:' . $this->groupAlias;
        }

        return $this->type . '(' . implode(',', array_map(
            static fn (self $operand): string => $operand->key(),
            $this->operands,
        )) . ')';
    }
}

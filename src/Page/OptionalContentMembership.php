<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function array_unique;
use function implode;
use function in_array;
use function sort;

use InvalidArgumentException;

final readonly class OptionalContentMembership
{
    public const POLICY_ANY_ON = 'AnyOn';
    public const POLICY_ALL_ON = 'AllOn';

    /**
     * @param list<string> $groupAliases
     */
    public function __construct(
        public string $name,
        public array $groupAliases,
        public string $visibilityPolicy = self::POLICY_ANY_ON,
        public ?OptionalContentVisibilityExpression $visibilityExpression = null,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Optional content membership name must not be empty.');
        }

        if ($this->groupAliases === []) {
            throw new InvalidArgumentException('Optional content membership must reference at least one optional content group alias.');
        }

        foreach ($this->groupAliases as $groupAlias) {
            if ($groupAlias === '') {
                throw new InvalidArgumentException('Optional content membership group aliases must not be empty.');
            }
        }

        if (array_unique($this->groupAliases) !== $this->groupAliases) {
            throw new InvalidArgumentException('Optional content membership group aliases must be unique.');
        }

        if (!in_array($this->visibilityPolicy, self::policies(), true)) {
            throw new InvalidArgumentException('Optional content membership visibility policy must be AnyOn or AllOn.');
        }

        if ($this->visibilityExpression !== null) {
            foreach ($this->visibilityExpression->referencedAliases() as $groupAlias) {
                if (!in_array($groupAlias, $this->groupAliases, true)) {
                    throw new InvalidArgumentException('Optional content visibility expressions must only reference declared membership group aliases.');
                }
            }
        }
    }

    public function key(): string
    {
        $aliases = $this->groupAliases;
        sort($aliases);

        return $this->name
            . ':' . $this->visibilityPolicy
            . ':' . implode(',', $aliases)
            . ':' . ($this->visibilityExpression?->key() ?? 'no-ve');
    }

    /**
     * @return list<string>
     */
    public static function policies(): array
    {
        return [
            self::POLICY_ANY_ON,
            self::POLICY_ALL_ON,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

interface FontDefinition
{
    public function getId(): int;

    public function getBaseFont(): string;

    public function supportsText(string $text): bool;

    public function encodeText(string $text): string;

    public function render(): string;
}

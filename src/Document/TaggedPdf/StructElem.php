<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

use function implode;
use function str_replace;

final readonly class StructElem
{
    /**
     * @param list<int> $kidObjectIds
     * @param list<string>|null $kidEntries
     */
    public function __construct(
        private string $tag,
        private int $parentObjectId,
        private array $kidObjectIds = [],
        private ?int $pageObjectId = null,
        private ?string $altText = null,
        private ?int $markedContentId = null,
        private ?array $kidEntries = null,
        private ?string $scope = null,
        private ?int $rowSpan = null,
        private ?int $colSpan = null,
    ) {
    }

    public function objectContents(): string
    {
        $entries = [
            '/Type /StructElem',
            '/S /' . $this->tag,
            '/P ' . $this->parentObjectId . ' 0 R',
        ];

        if ($this->pageObjectId !== null) {
            $entries[] = '/Pg ' . $this->pageObjectId . ' 0 R';
        }

        if ($this->altText !== null) {
            $entries[] = '/Alt ' . $this->pdfString($this->altText);
        }

        $tableAttributes = ['/O /Table'];

        if ($this->scope !== null) {
            $tableAttributes[] = '/Scope /' . $this->scope;
        }

        if ($this->rowSpan !== null) {
            $tableAttributes[] = '/RowSpan ' . $this->rowSpan;
        }

        if ($this->colSpan !== null) {
            $tableAttributes[] = '/ColSpan ' . $this->colSpan;
        }

        if (count($tableAttributes) > 1) {
            $entries[] = '/A << ' . implode(' ', $tableAttributes) . ' >>';
        }

        if ($this->markedContentId !== null) {
            $entries[] = '/K ' . $this->markedContentId;
        } elseif ($this->kidEntries !== null) {
            $entries[] = '/K [' . implode(' ', $this->kidEntries) . ']';
        } else {
            $references = implode(' ', array_map(
                static fn (int $objectId): string => $objectId . ' 0 R',
                $this->kidObjectIds,
            ));
            $entries[] = '/K [' . $references . ']';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }
}

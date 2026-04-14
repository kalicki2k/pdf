<?php

declare(strict_types=1);

namespace Kalle\Pdf\Xml;

use InvalidArgumentException;

use function array_map;
use function count;
use function implode;
use function str_repeat;

final readonly class XmlSerializer
{
    public function serialize(XmlDocument $document, bool $pretty = true): string
    {
        $declaration = '<?xml version="' . $document->version . '" encoding="' . $document->encoding . '"'
            . ($document->standalone ? ' standalone="yes"' : '')
            . '?>';

        if (!$pretty) {
            return $declaration . $this->serializeElementCompact($document->root);
        }

        return $declaration . "\n" . $this->serializeElementPretty($document->root) . "\n";
    }

    private function serializeElementCompact(XmlElement $element): string
    {
        $attributes = $this->serializeAttributes($element);

        if ($element->children === []) {
            return '<' . $element->name . $attributes . '/>';
        }

        return '<' . $element->name . $attributes . '>'
            . $this->serializeChildrenCompact($element->children)
            . '</' . $element->name . '>';
    }

    /**
     * @param list<XmlNode> $children
     */
    private function serializeChildrenCompact(array $children): string
    {
        $serialized = [];

        foreach ($children as $child) {
            $serialized[] = match (true) {
                $child instanceof XmlElement => $this->serializeElementCompact($child),
                $child instanceof XmlText => $this->escapeText($child->value),
                default => throw new InvalidArgumentException('Unsupported XML node type.'),
            };
        }

        return implode('', $serialized);
    }

    private function serializeElementPretty(XmlElement $element, int $depth = 0): string
    {
        $indent = str_repeat('  ', $depth);
        $attributes = $this->serializeAttributes($element);

        if ($element->children === []) {
            return $indent . '<' . $element->name . $attributes . '/>';
        }

        if ($this->hasOnlyTextChildren($element)) {
            return $indent . '<' . $element->name . $attributes . '>'
                . $this->serializeChildrenCompact($element->children)
                . '</' . $element->name . '>';
        }

        $children = array_map(
            fn (XmlNode $child): string => $this->serializePrettyChild($child, $depth + 1),
            $element->children,
        );

        return $indent . '<' . $element->name . $attributes . '>' . "\n"
            . implode("\n", $children) . "\n"
            . $indent . '</' . $element->name . '>';
    }

    private function serializePrettyChild(XmlNode $node, int $depth): string
    {
        return match (true) {
            $node instanceof XmlElement => $this->serializeElementPretty($node, $depth),
            $node instanceof XmlText => str_repeat('  ', $depth) . $this->escapeText($node->value),
            default => throw new InvalidArgumentException('Unsupported XML node type.'),
        };
    }

    private function serializeAttributes(XmlElement $element): string
    {
        if ($element->attributes === []) {
            return '';
        }

        $pairs = [];

        foreach ($element->attributes as $name => $value) {
            $pairs[] = $name . '="' . $this->escapeAttribute($value) . '"';
        }

        return ' ' . implode(' ', $pairs);
    }

    private function hasOnlyTextChildren(XmlElement $element): bool
    {
        foreach ($element->children as $child) {
            if (!$child instanceof XmlText) {
                return false;
            }
        }

        return count($element->children) > 0;
    }

    private function escapeText(string $value): string
    {
        return $this->escape($value, false);
    }

    private function escapeAttribute(string $value): string
    {
        return $this->escape($value, true);
    }

    private function escape(string $value, bool $attribute): string
    {
        $escaped = str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $value,
        );

        if (!$attribute) {
            return $escaped;
        }

        return str_replace(
            ['"', "'"],
            ['&quot;', '&apos;'],
            $escaped,
        );
    }
}

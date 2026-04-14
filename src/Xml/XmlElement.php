<?php

declare(strict_types=1);

namespace Kalle\Pdf\Xml;

use InvalidArgumentException;

use function array_key_exists;

final readonly class XmlElement implements XmlNode
{
    /**
     * @param array<string, string> $attributes
     * @param list<XmlNode> $children
     */
    public function __construct(
        public string $name,
        public array $attributes = [],
        public array $children = [],
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('XML element name must not be empty.');
        }

        foreach ($this->attributes as $attributeName => $attributeValue) {
            if ($attributeName === '') {
                throw new InvalidArgumentException('XML attribute name must not be empty.');
            }

            if (!is_string($attributeValue)) {
                throw new InvalidArgumentException('XML attribute values must be strings.');
            }
        }
    }

    public function withAttribute(string $name, string $value): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('XML attribute name must not be empty.');
        }

        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self(
            name: $this->name,
            attributes: $attributes,
            children: $this->children,
        );
    }

    /**
     * @param array<string, string> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        $updated = $this;

        foreach ($attributes as $name => $value) {
            $updated = $updated->withAttribute($name, $value);
        }

        return $updated;
    }

    public function withChild(XmlNode $child): self
    {
        return new self(
            name: $this->name,
            attributes: $this->attributes,
            children: [...$this->children, $child],
        );
    }

    /**
     * @param list<XmlNode> $children
     */
    public function withChildren(array $children): self
    {
        return new self(
            name: $this->name,
            attributes: $this->attributes,
            children: [...$this->children, ...$children],
        );
    }

    public function withText(string $text): self
    {
        return $this->withChild(new XmlText($text));
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }
}

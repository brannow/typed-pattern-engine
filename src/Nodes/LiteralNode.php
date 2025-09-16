<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes;

use TypedPatternEngine\Nodes\Interfaces\LiteralNodeInterface;
use TypedPatternEngine\Types\TypeRegistryInterface;

final class LiteralNode extends NamedAstNode implements LiteralNodeInterface
{
    public const TYPE = 'literal';

    public function __construct(
        private readonly string $text
    ) {}

    public function getBoundary(): string
    {
        return $this->text;
    }

    public function getFirstBoundary(): string
    {
        return $this->text;
    }

    protected function generateRegex(): string
    {
        return preg_quote($this->text, '/');
    }

    public function generate(array $values): string
    {
        return $this->text;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getNodeType(): string
    {
        return LiteralNode::TYPE;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => $this->getNodeType(),
            'text' => $this->text
        ];
    }

    public static function fromArray(array $data, NodeRegistryInterface $nodeRegistry, TypeRegistryInterface $typeRegistry): static
    {
        $node = new self($data['text']);
        $node->setRegex($data['regex'] ?? null);
        return $node;
    }
}

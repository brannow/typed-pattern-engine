<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes;

use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\BoundaryProviderInterface;
use TypedPatternEngine\Nodes\Interfaces\LiteralNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NestedNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NodeTreeInterface;
use TypedPatternEngine\Nodes\Interfaces\NodeValidationInterface;
use TypedPatternEngine\Types\TypeRegistry;
use RuntimeException;

abstract class AstNode implements AstNodeInterface
{
    private ?string $regex = null;
    private ?NodeTreeInterface $parent = null;

    /**
     * Cached boundary for performance
     */
    private ?string $cachedBoundary = null;

    /**
     * Generate Regex from Pattern via AST
     *
     * @return string
     */
    public function toRegex(): string
    {
        if ($this->regex === null) {
            $this->regex = $this->generateRegex();
        }
        return $this->regex;
    }

    /**
     * @internal set regex from hydration
     * @param string|null $regex
     * @return void
     */
    protected function setRegex(?string $regex): void
    {
        $this->regex = $regex;
    }

    public function setParent(?NodeTreeInterface $parent): void
    {
        if ($parent === $this) {
            throw new RuntimeException("Cannot set self as parent");
        }
        $this->parent = $parent;
        // Clear cached boundary when parent changes
        $this->cachedBoundary = null;
    }

    public function getParent(): ?NodeTreeInterface
    {
        return $this->parent;
    }

    /**
     * @return bool Delegates to parent unless overridden (e.g., SubSequenceNode)
     */
    public function isOptional(): bool
    {
        // SubSequenceNode overrides this to return true
        return $this->parent?->isOptional() ?? false;
    }

    /**
     * @return void
     */
    public function validateTreeContext(): void
    {
        // Default implementation - subclasses override as needed
    }

    public function validateEntireTree(): void
    {
        $this->validateTreeContext();
        if ($this instanceof NodeTreeInterface) {
            foreach ($this->getChildren() as $child) {
                $child->validateEntireTree();
            }
        }
    }

    final protected function getNextBoundary(): ?string
    {
        return $this->cachedBoundary ??= $this->calculateNextBoundary();
    }

    /**
     * must live in AstNode so we can cascade through every parent object
     *
     * @return string|null
     */
    protected function calculateNextBoundary(): ?string
    {
        $parent = $this->getParent();
        if (!$parent instanceof NodeTreeInterface) {
            return null;
        }

        $siblings = $parent->getChildren();
        $myIndex = array_search($this, $siblings, true);

        if ($myIndex === false) {
            return null;
        }

        for ($i = $myIndex + 1, $count = count($siblings); $i < $count; $i++) {
            $sibling = $siblings[$i];

            if ($sibling instanceof BoundaryProviderInterface) {
                $boundary = $sibling->getFirstBoundary();
                if ($boundary !== null) {
                    return $boundary;
                }
            }
        }

        // Delegate to parent
        return ($parent instanceof AstNode) ? $parent->getNextBoundary() : null;
    }

    public function toArray(): array
    {
        return [
            'regex' => $this->toRegex()
        ];
    }

    abstract protected function generateRegex(): string;

    /**
     * @param array<string, mixed> $values
     * @return string
     */
    abstract public function generate(array $values): string;
    abstract public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static;
}

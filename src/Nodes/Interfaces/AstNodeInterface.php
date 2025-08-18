<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes\Interfaces;

use TypedPatternEngine\Types\TypeRegistry;

/**
 * Minimal interface for all AST nodes.
 * Additional capabilities are provided by composing other interfaces.
 */
interface AstNodeInterface extends NodeValidationInterface
{
    /**
     * @return string generate Regex once
     */
    public function toRegex(): string;

    /**
     * generate string from assoc data array
     *
     * @param array<string, mixed> $values
     * @return string
     */
    public function generate(array $values): string;

    public function isOptional(): bool;
    public function setParent(?NodeTreeInterface $parent): void;
    public function getParent(): ?NodeTreeInterface;

    /**
     * @return array<string, string|array<string, string>>
     */
    public function toArray(): array;

    /**
     * @param array<string, string|array<string, string>> $data
     * @param TypeRegistry|null $typeRegistry
     * @return static
     */
    public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static;
}

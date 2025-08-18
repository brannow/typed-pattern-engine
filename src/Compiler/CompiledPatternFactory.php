<?php declare(strict_types=1);

namespace TypedPatternEngine\Compiler;

use TypedPatternEngine\Nodes\AstNode;
use TypedPatternEngine\Nodes\GroupNode;
use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NodeTreeInterface;
use TypedPatternEngine\Nodes\Interfaces\TypeNodeInterface;
use TypedPatternEngine\Nodes\LiteralNode;
use TypedPatternEngine\Nodes\SequenceNode;
use TypedPatternEngine\Nodes\SubSequenceNode;
use TypedPatternEngine\Types\TypeRegistry;
use TypedPatternEngine\Exception\PatternCompilationException;
use TypedPatternEngine\Exception\PatternValidationException;
use TypedPatternEngine\Exception\PatternSyntaxException;
use TypedPatternEngine\Exception\TypeSystemException;

/**
 * Factory for creating and hydrating CompiledPattern instances
 */
final class CompiledPatternFactory
{
    public function __construct(
        private readonly TypeRegistry $typeRegistry
    ) {}

    /**
     * Create a new CompiledPattern from an AST
     *
     * @param string $pattern
     * @param AstNode $ast
     * @return CompiledPattern
     * @throws PatternSyntaxException
     * @throws TypeSystemException
     */
    public function create(string $pattern, AstNode $ast): CompiledPattern
    {
        $namedGroups = [];
        $groupTypes = [];
        $groupConstraints = [];

        $this->extractGroupInfo($ast, $namedGroups, $groupTypes, $groupConstraints);

        $regex = '/^' . $ast->toRegex() . '$/';

        return new CompiledPattern(
            $pattern,
            $regex,
            $ast,
            $namedGroups,
            $groupTypes,
            $groupConstraints,
            $this->typeRegistry
        );
    }

    /**
     * Convert CompiledPattern to cacheable array
     * @return array<string, mixed>
     */
    public function dehydrate(CompiledPattern $pattern): array
    {
        return [
            'version' => '1.0',
            'pattern' => $pattern->getPattern(),
            'regex' => $pattern->getRegex(),
            'ast' => $this->serializeAst($pattern->getAst()),
            'namedGroups' => $pattern->getNamedGroups(),
            'groupTypes' => $pattern->getGroupTypes(),
            'groupConstraints' => $pattern->getGroupConstraints(),
        ];
    }

    /**
     * Recreate CompiledPattern from cached data
     * Uses the current TypeRegistry instance
     * @param array<string, mixed> $data
     * @throws PatternCompilationException
     * @throws PatternValidationException
     */
    public function hydrate(array $data): CompiledPattern
    {
        $ast = $this->deserializeAst($data['ast']);

        return new CompiledPattern(
            $data['pattern'],
            $data['regex'],
            $ast,
            $data['namedGroups'],
            $data['groupTypes'],
            $data['groupConstraints'],
            $this->typeRegistry
        );
    }

    /**
     * Serialize AST to array structure
     * @return array<string, mixed>
     */
    private function serializeAst(AstNodeInterface $node): array
    {
        return $node->toArray();
    }

    /**
     * Deserialize AST from array structure
     * @param array<string, mixed> $data
     * @throws PatternCompilationException
     * @throws PatternValidationException
     */
    private function deserializeAst(array $data): AstNodeInterface
    {
        return match ($data['type']) {
            'literal' => LiteralNode::fromArray($data, $this->typeRegistry),
            'group' => GroupNode::fromArray($data, $this->typeRegistry),
            'sequence' => SequenceNode::fromArray($data, $this->typeRegistry),
            'subsequence' => SubSequenceNode::fromArray($data, $this->typeRegistry),
            default => throw new PatternCompilationException(
                "Unknown node type during deserialization: " . $data['type'],
                'cached_pattern',
                'deserialization'
            )
        };
    }

    /**
     * Extract group information from AST
     * @param AstNodeInterface $node
     * @param array<string, string> $namedGroups
     * @param array<string, string> $groupTypes
     * @param array<string, array<string, mixed>> $groupConstraints
     * @return void
     * @throws PatternSyntaxException
     * @throws TypeSystemException
     */
    private function extractGroupInfo(
        AstNodeInterface $node,
        array &$namedGroups,
        array &$groupTypes,
        array &$groupConstraints
    ): void {
        if ($node instanceof TypeNodeInterface) {
            $groupId = $node->getGroupId();
            $namedGroups[$groupId] = $node->getName();
            $groupTypes[$node->getName()] = $node->getType()->getDefaultName();
            $groupConstraints[$node->getName()] = $node->getType()->getConstraintArguments();
        } elseif ($node instanceof NodeTreeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->extractGroupInfo($child, $namedGroups, $groupTypes, $groupConstraints);
            }
        }
    }
}

<?php declare(strict_types=1);

namespace TypedPatternEngine\Compiler;

use Throwable;
use TypedPatternEngine\Exception\PatternCompilationException;
use TypedPatternEngine\Exception\PatternSyntaxException;
use TypedPatternEngine\Exception\PatternValidationException;
use TypedPatternEngine\Exception\TypeSystemException;
use TypedPatternEngine\Nodes\AstNode;
use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NodeTreeInterface;
use TypedPatternEngine\Nodes\Interfaces\TypeNodeInterface;
use TypedPatternEngine\Nodes\NodeRegistryInterface;
use TypedPatternEngine\Types\TypeRegistryInterface;

/**
 * Factory for creating and hydrating CompiledPattern instances
 */
final class CompiledPatternFactory
{
    public function __construct(
        private readonly NodeRegistryInterface $nodeRegistry,
        private readonly TypeRegistryInterface $typeRegistry
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
        return $this->nodeRegistry->getNodeByData($data, $this->typeRegistry);
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

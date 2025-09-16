<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes;

use TypedPatternEngine\Exception\PatternCompilationException;
use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\BoundaryProviderInterface;
use TypedPatternEngine\Nodes\Interfaces\NestedNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NodeGroupAwareInterface;
use TypedPatternEngine\Types\TypeRegistryInterface;

abstract class NestedAstNode extends NamedAstNode implements NestedNodeInterface
{
    /** @var AstNodeInterface[] */
    protected array $children = [];

    private ?array $groupNamesCache = null;

    final public function __construct()
    {}


    public function getBoundary(): ?string
    {
        return null; // Nested nodes don't directly provide boundaries
    }

    public function getFirstBoundary(): ?string
    {
        foreach ($this->children as $child) {
            if ($child instanceof BoundaryProviderInterface) {
                $boundary = $child->getFirstBoundary();
                if ($boundary !== null) {
                    return $boundary;
                }
            }
        }
        return null;
    }

    public function addChild(AstNodeInterface $node): void
    {
        if ($node->getParent() === null) {
            $node->setParent($this);
        }
        $this->children[] = $node;
    }

    /**
     * @return AstNodeInterface[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @return array<string>
     */
    public function getGroupNames(): array
    {
        return $this->groupNamesCache ??= $this->generateGroupNameList();
    }

    private function generateGroupNameList(): array
    {
        $names = [];
        foreach ($this->children as $child) {
            if ($child instanceof NodeGroupAwareInterface)
                $names = array_merge($names, $child->getGroupNames());
        }
        return $names;
    }

    protected function generateRegex(): string
    {
        $regex = '';
        foreach ($this->children as $child) {
            $regex .= $child->toRegex();
        }
        return $regex;
    }

    /**
     * @return string[]|string[][]
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => $this->getNodeType(),
            'children' => array_map(
                fn($child) => $child->toArray(),
                $this->children
            )
        ];
    }

    /**
     * @param array<string, string|array<string, string|array<string, string>>> $data
     * @param NodeRegistryInterface $nodeRegistry
     * @param TypeRegistryInterface $typeRegistry
     * @return static
     */
    public static function fromArray(array $data, NodeRegistryInterface $nodeRegistry, TypeRegistryInterface $typeRegistry): static
    {
        $node = new static();
        foreach ($data['children'] as $childData) {
            $childNode = self::createNodeFromArray($childData, $nodeRegistry, $typeRegistry);
            if ($childNode instanceof AstNode) {
                $childNode->setRegex($childData['regex'] ?? null);
            }
            $node->addChild($childNode);
        }
        $node->setRegex($data['regex'] ?? null);
        return $node;
    }

    /**
     * @param array<string, string|array<string, string>> $data
     * @param NodeRegistryInterface $nodeRegistry
     * @param TypeRegistryInterface $typeRegistry
     * @return AstNodeInterface
     */
    private static function createNodeFromArray(array $data, NodeRegistryInterface $nodeRegistry, TypeRegistryInterface $typeRegistry): AstNodeInterface
    {
        return $nodeRegistry->getNodeByData($data, $typeRegistry);
    }
}

<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes;

use Throwable;
use TypedPatternEngine\Exception\PatternCompilationException;
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;
use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NamedNodeInterface;
use TypedPatternEngine\Types\TypeRegistryInterface;

class NodeRegistry implements NodeRegistryInterface
{
    /**
     * @var array<string, class-string<AstNodeInterface>>
     */
    private array $nodeMap = [];

    public function __construct()
    {
        $this->registerDefaultNodes();
    }


    protected function registerDefaultNodes(): void
    {
        $this->nodeMap[SequenceNode::TYPE] = SequenceNode::class;
        $this->nodeMap[SubSequenceNode::TYPE] = SubSequenceNode::class;
        $this->nodeMap[GroupNode::TYPE] = GroupNode::class;
        $this->nodeMap[LiteralNode::TYPE] = LiteralNode::class;
    }

    /**
     * @param string $type
     * @param mixed ...$arguments
     * @return AstNodeInterface
     */
    public function getNodeByType(string $type, mixed ...$arguments): AstNodeInterface
    {
        $nodeClass = $this->getNodeClassByType($type);
        return new $nodeClass(...$arguments);
    }

    /**
     * @param array $data
     * @param TypeRegistryInterface $typeRegistry
     * @return AstNodeInterface
     */
    public function getNodeByData(array $data, TypeRegistryInterface $typeRegistry): AstNodeInterface
    {
        if (empty($data['type'])) {
            throw new PatternCompilationException(
                "Missing node type in data: " . implode(', ', array_keys($data['type'])) . ' found',
                'cached_pattern',
                'deserialization'
            );
        }

        try {
            return $this->getNodeClassByType($data['type'])::fromArray($data, $this, $typeRegistry);

        } catch (Throwable $e) {
            throw new PatternCompilationException(
                "Unknown node type: " . $data['type'],
                'cached_pattern',
                'deserialization',
                previous: $e
            );
        }
    }

    /**
     * @param string $type
     * @return class-string<AstNodeInterface>
     */
    public function getNodeClassByType(string $type): string
    {
        return $this->nodeMap[$type] ?? throw new PatternEngineInvalidArgumentException('Node with type \''. $type .'\' not found, available are: ' . implode(', ',array_keys($this->nodeMap)));
    }

    /**
     * @param class-string<AstNodeInterface> $nodeClass
     * @return void
     */
    public function registerNode(string $nodeClass): void
    {
        if (is_a($nodeClass, NamedNodeInterface::class, true)) {
            $this->nodeMap[$nodeClass::TYPE] = $nodeClass;
        } else {
            throw new PatternEngineInvalidArgumentException('Node \''. $nodeClass .'\' must be implement ' . NamedNodeInterface::class);
        }
    }
}

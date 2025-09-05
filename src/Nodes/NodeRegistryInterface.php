<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes;

use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Types\TypeRegistryInterface;

interface NodeRegistryInterface
{
    /**
     * @param string $type
     * @param mixed ...$arguments
     * @return AstNodeInterface
     */
    public function getNodeByType(string $type, mixed ...$arguments): AstNodeInterface;

    /**
     * @param array $data
     * @param TypeRegistryInterface $typeRegistry
     * @return AstNodeInterface
     */
    public function getNodeByData(array $data, TypeRegistryInterface $typeRegistry): AstNodeInterface;

    /**
     * @param string $type
     * @return class-string<AstNodeInterface>
     */
    public function getNodeClassByType(string $type): string;
    /**
     * @param string $nodeClass
     * @return void
     */
    public function registerNode(string $nodeClass): void;
}

<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes\Interfaces;

interface NodeTreeInterface extends NamedNodeInterface, NodeGroupAwareInterface, BoundaryProviderInterface
{
    /**
     * add a new children
     *
     * @param AstNodeInterface $node
     * @return void
     */
    public function addChild(AstNodeInterface $node): void;

    /**
     * @return AstNodeInterface[] children nodes
     */
    public function getChildren(): array;
}

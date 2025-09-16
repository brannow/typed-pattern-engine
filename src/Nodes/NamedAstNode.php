<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes;

use TypedPatternEngine\Nodes\Interfaces\NamedNodeInterface;

abstract class NamedAstNode extends AstNode implements NamedNodeInterface
{
    /**
     * Get the node type name for serialization
     */
    abstract function getNodeType(): string;
}

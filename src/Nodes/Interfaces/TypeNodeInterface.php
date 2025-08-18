<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes\Interfaces;

use TypedPatternEngine\Types\TypeInterface;

interface TypeNodeInterface extends NamedNodeInterface, NodeGroupAwareInterface
{
    /**
     * @return TypeInterface
     */
    public function getType(): TypeInterface;

    /**
     * @return string type var name
     */
    public function getName(): string;

    /**
     * @internal
     * @param string $id
     * @return void
     */
    public function setGroupId(string $id): void;

    /**
     * internal group ID
     * @return string
     */
    public function getGroupId(): string;

    public function isGreedy(): bool;
}

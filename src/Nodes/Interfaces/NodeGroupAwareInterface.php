<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes\Interfaces;

interface NodeGroupAwareInterface
{
    /**
     * @return string[] collect all groupNames (g1, g2 ... so on)
     */
    public function getGroupNames(): array;
}

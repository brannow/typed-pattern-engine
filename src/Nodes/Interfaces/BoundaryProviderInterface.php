<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes\Interfaces;

interface BoundaryProviderInterface
{
    /**
     * Get the boundary this node provides (if any)
     * @return string|null
     */
    public function getBoundary(): ?string;

    /**
     * Get the first boundary from this node or its children
     * @return string|null
     */
    public function getFirstBoundary(): ?string;
}

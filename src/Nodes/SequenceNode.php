<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes;

class SequenceNode extends NestedAstNode
{
    /**
     * generate string from assoc data array
     *
     * @param array<string, mixed> $values
     * @return string
     */
    public function generate(array $values): string
    {
        $result = '';
        foreach ($this->children as $child) {
            $result .= $child->generate($values);
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getNodeType(): string
    {
        return 'sequence';
    }
}

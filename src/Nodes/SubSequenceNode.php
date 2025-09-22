<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes;

use Throwable;
use TypedPatternEngine\Exception\PatternSyntaxException;
use TypedPatternEngine\Nodes\Interfaces\NodeTreeInterface;
use TypedPatternEngine\Nodes\Interfaces\TypeNodeInterface;
use TypedPatternEngine\Types\TypeInterface;

final class SubSequenceNode extends SequenceNode
{
    public const TYPE = 'subsequence';

    private ?array $activationRequirements = null;

    /**
     * @return string[]|TypeInterface[]
     */
    private function generateActivationRequirements(): array
    {
        $requirements = [];
        foreach ($this->children as $child) {
            if ($child instanceof TypeNodeInterface) {
                $requirements[$child->getGroupId()] = [
                    'name' => $child->getName(),
                    'type' => $child->getType()
                ];
            } elseif ($child instanceof SubSequenceNode) {
                $requirements = $requirements + $child->getActivationRequirements();
            }
        }
        return $requirements;
    }

    /**
     * @return string[]|TypeInterface[]
     */
    protected function getActivationRequirements(): array
    {
        return $this->activationRequirements ??= $this->generateActivationRequirements();
    }

    /**
     * @return string
     */
    protected function generateRegex(): string
    {
        // Make the entire subsequence optional by default
        return '(?:' . parent::generateRegex() . ')?';
    }

    /**
     * Generate output for this subsequence.
     * Implements all-or-nothing semantics: ALL required groups within must have values
     * for the subsequence to be included.
     */
    public function generate(array $values): string
    {
        $requirementsGiven = [];
        foreach ($this->getActivationRequirements() as $key => $requirement) {
            $value = $values[$requirement['name']] ?? null;
            /** @var TypeInterface $type */
            $type = $requirement['type'];

            try {
                $testValue = $type->parseValue($value);
                // is default value, omit
                if ($type->isDefaultValue($testValue)) {
                    $requirementsGiven[$key] = null;
                } else {
                    $requirementsGiven[$key] = true;
                }
            } catch (Throwable) {
                $requirementsGiven[$key] = null;
            }
        }

        // all sub Groups are failing or defaulting, so we don't need to print default values
        if (empty(array_filter($requirementsGiven))) {
            return '';
        }

        // All requirements can be satisfied, generate the full subsequence
        return parent::generate($values);
    }

    /**
     * SubSequence nodes are always optional
     */
    public function isOptional(): bool
    {
        return true; // Breaks the parent delegation chain
    }

    /**
     * @return void
     * @throws PatternSyntaxException
     */
    public function validateTreeContext(): void
    {
        if (empty($this->children)) {
            throw new PatternSyntaxException(
                "Empty optional subsequence '()' is not allowed. Optional sections must contain at least one element (group or literal).",
                'unknown'
            );
        }

        parent::validateTreeContext();
    }

    public function getNodeType(): string
    {
        return SubSequenceNode::TYPE;
    }
}

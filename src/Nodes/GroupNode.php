<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes;

use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;
use TypedPatternEngine\Nodes\Interfaces\TypeNodeInterface;
use TypedPatternEngine\Types\Constrains\DefaultConstraint;
use TypedPatternEngine\Types\TypeInterface;
use TypedPatternEngine\Types\TypeRegistry;
use TypedPatternEngine\Exception\PatternRuntimeException;
use TypedPatternEngine\Exception\PatternValidationException;
use TypedPatternEngine\Exception\TypeSystemException;

final class GroupNode extends NamedAstNode implements TypeNodeInterface
{
    private string $groupId = '';
    private readonly TypeRegistry $typeRegistry;
    private readonly TypeInterface $type;

    /**
     * @param string $name
     * @param string $typeName
     * @param array<string, mixed> $constraints
     * @param TypeRegistry|null $typeRegistry
     * @throws TypeSystemException
     * @throws PatternValidationException
     */
    public function __construct(
        private readonly string $name, // variable name
        string $typeName, // concrete str / string / int / integer ...
        array  $constraints = [], // ['constraintName' => 'value', ...]
        ?TypeRegistry $typeRegistry = null
    ) {
        $this->typeRegistry = $typeRegistry ?? throw new PatternValidationException("TypeRegistry not provided in GroupNode");
        $this->type = $this->typeRegistry->getTypeObject($typeName, $constraints);
    }

    /**
     * @return void
     * @throws PatternRuntimeException|TypeSystemException
     */
    public function validateTreeContext(): void
    {
        if (!$this->isOptional() && ($constraint = $this->type->getConstraint(DefaultConstraint::NAME)) !== null) {
            $defaultValue = $constraint->getValue();

            throw new PatternRuntimeException(
                "Default constraint cannot be used on required group '$this->name'. " .
                "Make the group optional: {".$this->name.":".$this->type->getName()."(". DefaultConstraint::NAME ."=$defaultValue)}? or place it in an optional section: (-{".$this->name.":".$this->type->getName()."(". DefaultConstraint::NAME ."=$defaultValue)})",
                $this->name,
                $defaultValue,
                DefaultConstraint::NAME,
                $defaultValue
            );
        }
    }

    public function getGroupNames(): array
    {
        return [$this->name];
    }

    public function getNodeType(): string
    {
        return 'group';
    }

    /**
     * @return TypeInterface
     */
    public function getType(): TypeInterface
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isGreedy(): bool
    {
        return $this->type->isGreedy();
    }

    /**
     * @param string $id
     * @return void
     */
    public function setGroupId(string $id): void
    {
        $this->groupId = $id;
    }

    /**
     * @return string
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    protected function generateRegex(): string
    {
        $type = $this->getType();

        // Get the base pattern with constraints applied
        $pattern = $type->getConstrainedPattern();

        // Let the type handle boundary application if it's greedy
        if ($type->isGreedy()) {
            $boundary = $this->determineBoundary();
            $pattern = $type->applyBoundary($pattern, $boundary);
        }

        return '(?P<' . $this->groupId . '>' . $pattern . ')';
    }

    /**
     * @return string|null
     */
    private function determineBoundary(): ?string
    {
        // Simplified - just determine WHAT the boundary is, not HOW to apply it
        $parent = $this->getParent();

        if ($parent instanceof SubSequenceNode) {
            // Inside subsequence: only look within
            return $this->calculateNextBoundary();
        }

        // Regular sequence: look for any next boundary
        return $this->getNextBoundary();
    }

    /**
     * @param array<string, mixed> $values
     * @return string
     * @throws PatternEngineInvalidArgumentException
     */
    public function generate(array $values): string
    {
        $validatedValue = $this->type->parseValue($values[$this->name] ?? null);
        return $this->type->serialize($validatedValue);
    }

    /**
     * @return array<string, mixed>
     * @throws TypeSystemException
     */
    public function toArray(): array
    {
        return [
            'regex' => $this->toRegex(),
            'name' => $this->name,
            'type' => $this->getNodeType(),
            'type_name' => $this->type->getName(),
            'type_constraints' => $this->type->getConstraintArguments(),
            'groupId' => $this->groupId
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param TypeRegistry|null $typeRegistry
     * @return static
     * @throws PatternValidationException
     */
    public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static
    {
        $node = new self(
            $data['name'],
            $data['type_name'],
            $data['type_constraints'] ,
            $typeRegistry ?? throw new PatternValidationException("TypeRegistry not provided in GroupNode at Hydration")
        );
        $node->setRegex($data['regex'] ?? null);
        $node->setGroupId($data['groupId']);
        return $node;
    }
}

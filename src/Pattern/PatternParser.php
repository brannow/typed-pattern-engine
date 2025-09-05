<?php declare(strict_types=1);

namespace TypedPatternEngine\Pattern;

use TypedPatternEngine\Exception\PatternSyntaxException;
use TypedPatternEngine\Exception\PatternValidationException;
use TypedPatternEngine\Exception\TypeSystemException;
use TypedPatternEngine\Nodes\AstNode;
use TypedPatternEngine\Nodes\GroupNode;
use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\CompilationPhaseAwareInterface;
use TypedPatternEngine\Nodes\Interfaces\LiteralNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NodeTreeInterface;
use TypedPatternEngine\Nodes\LiteralNode;
use TypedPatternEngine\Nodes\NodeRegistryInterface;
use TypedPatternEngine\Nodes\SequenceNode;
use TypedPatternEngine\Nodes\SubSequenceNode;
use TypedPatternEngine\Pattern\Helper\PatternGroupCounter;
use TypedPatternEngine\Pattern\Helper\PatternGroupCounterInterface;
use TypedPatternEngine\Types\TypeRegistry;
use TypedPatternEngine\Types\TypeRegistryInterface;

final class PatternParser
{
    private int $pos = 0;

    /**
     * @param NodeRegistryInterface $nodeRegistry
     * @param TypeRegistry $typeRegistry
     * @param string $pattern
     * @param PatternGroupCounterInterface|null $groupCounter
     */
    public function __construct(
        private readonly NodeRegistryInterface $nodeRegistry,
        private readonly TypeRegistryInterface $typeRegistry,
        private readonly string       $pattern = '',
        private readonly ?PatternGroupCounterInterface $groupCounter = new PatternGroupCounter()
    )
    {}

    /**
     * Parse a pattern string into an AST
     * @param SequenceNode|null $rootNode
     * @return SequenceNode
     * @throws PatternSyntaxException
     * @throws TypeSystemException
     * @throws PatternValidationException
     */
    public function parse(?SequenceNode $rootNode = null): SequenceNode
    {
        $root = $rootNode ?? $this->nodeRegistry->getNodeByType(SequenceNode::TYPE);
        while ($this->pos < strlen($this->pattern)) {
            $node = $this->parseNext();
            if ($node !== null) {
                $root->addChild($node);
            }
        }

        $this->finalizeTree($root);

        return $root;
    }

    /**
     * @throws TypeSystemException
     * @throws PatternSyntaxException
     * @throws PatternValidationException
     */
    private function parseNext(): ?AstNodeInterface
    {
        if ($this->pos >= strlen($this->pattern)) {
            return null;
        }

        $char = $this->pattern[$this->pos];

        // Check for group
        if ($char === '{') {
            return $this->parseGroup();
        }

        // Check for optional section - parentheses always mark optional content
        if ($char === '(') {
            return $this->parseSubSequence();
        }

        // Parse literal text until next special character
        return $this->parseLiteral();
    }

    /**
     * @throws TypeSystemException
     * @throws PatternSyntaxException
     * @throws PatternValidationException
     */
    private function parseGroup(): AstNode
    {
        $start = $this->pos;
        $this->pos++; // Skip {

        $content = '';
        $depth = 1;

        while ($this->pos < strlen($this->pattern) && $depth > 0) {
            $char = $this->pattern[$this->pos];
            if ($char === '{') {
                $depth++;
                $content .= $char;
            } elseif ($char === '}') {
                $depth--;
                if ($depth > 0) {
                    $content .= $char;
                }
            } else {
                $content .= $char;
            }
            $this->pos++;
        }

        if ($depth > 0) {
            throw new PatternSyntaxException("Unclosed group", $this->pattern, $start);
        }

        // Parse group content
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):([a-zA-Z]+)(?:\(([^)]+)\))?$/', $content, $matches)) {
            throw new PatternSyntaxException("Invalid group syntax: $content", $this->pattern, $start);
        }

        $name = $matches[1];
        $type = $matches[2];
        $constraints = isset($matches[3]) ? $this->parseConstraints($matches[3]) : [];
        // Create group node (always required - optionality handled by SubSequence wrapper)
        /** @var GroupNode $node */
        $node = $this->nodeRegistry->getNodeByType(GroupNode::TYPE, $name, $type, $constraints, $this->typeRegistry);
        // Assign group ID
        $node->setGroupId('g' . $this->groupCounter->increaseCounter());

        // Syntax normalization: {group}? → ({group})
        if ($this->pos < strlen($this->pattern) && $this->pattern[$this->pos] === '?') {
            $this->pos++;
            /** @var SubSequenceNode $subSequence */
            $subSequence = $this->nodeRegistry->getNodeByType(SubSequenceNode::TYPE);
            $subSequence->addChild($node);
            return $subSequence;
        }

        return $node;
    }

    /**
     * @return NodeTreeInterface
     * @throws PatternValidationException
     * @throws PatternSyntaxException
     * @throws TypeSystemException
     */
    private function parseSubSequence(): NodeTreeInterface
    {
        $start = $this->pos;
        $this->pos++; // Skip (

        // Find matching closing parenthesis
        $depth = 1;
        $endPos = $this->pos;

        while ($endPos < strlen($this->pattern) && $depth > 0) {
            if ($this->pattern[$endPos] === '(') {
                $depth++;
            } elseif ($this->pattern[$endPos] === ')') {
                $depth--;
            }
            $endPos++;
        }

        if ($depth > 0) {
            throw new PatternSyntaxException("Unclosed optional subsequence", $this->pattern, $start);
        }

        // Extract content between parentheses
        $content = substr($this->pattern, $this->pos, $endPos - $this->pos - 1);

        // Parse the content recursively (but don't reset the group counter!)
        $parser = new PatternParser($this->nodeRegistry, $this->typeRegistry, $content, $this->groupCounter);
        /** @var SubSequenceNode $node */
        $node = $this->nodeRegistry->getNodeByType(SubSequenceNode::TYPE);
        $node = $parser->parse($node);

        // SubSequenceNode is inherently optional by design - no need to mark it
        // The node type itself indicates that this section is optional

        // Jump straight to the endPos of the entire SubSection
        $this->pos = $endPos;

        return $node;
    }

    /**
     * @throws PatternSyntaxException
     */
    private function parseLiteral(): LiteralNodeInterface
    {
        $start = $this->pos;
        $literal = '';

        while ($this->pos < strlen($this->pattern)) {
            $char = $this->pattern[$this->pos];

            // Stop at special characters (parentheses and curly braces)
            if ($char === '{' || $char === '(') {
                break;
            }

            $literal .= $char;
            $this->pos++;
        }

        if ($literal === '') {
            throw new PatternSyntaxException("Empty literal", $this->pattern, $start);
        }

        /** @var LiteralNodeInterface $node */
        $node = $this->nodeRegistry->getNodeByType(LiteralNode::TYPE, $literal);
        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseConstraints(string $constraintStr): array
    {
        $constraints = [];
        $pairs = $this->splitConstraints($constraintStr);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (str_contains($pair, '=')) {
                [$key, $value] = explode('=', $pair, 2);
                $constraints[trim($key)] = trim($value);
            }
        }

        return $constraints;
    }

    /**
     * @return array<string>
     */
    private function splitConstraints(string $constraintStr): array
    {
        $pairs = [];
        $current = '';
        $inQuotes = false;
        $escapeNext = false;

        for ($i = 0; $i < strlen($constraintStr); $i++) {
            $char = $constraintStr[$i];

            if ($escapeNext) {
                $current .= $char;
                $escapeNext = false;
                continue;
            }

            if ($char === '\\') {
                $current .= $char;
                $escapeNext = true;
                continue;
            }

            if ($char === '"') {
                $inQuotes = !$inQuotes;
                $current .= $char;
                continue;
            }

            if ($char === ',' && !$inQuotes) {
                if (trim($current) !== '') {
                    $pairs[] = trim($current);
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $pairs[] = trim($current);
        }

        return $pairs;
    }

    /**
     * Signal that tree construction is complete
     * @param AstNodeInterface $root
     * @return void
     */
    private function finalizeTree(AstNodeInterface $root): void
    {
        // Signal that tree construction is complete
        if ($root instanceof CompilationPhaseAwareInterface) {
            $root->onTreeComplete();
        }
    }
}

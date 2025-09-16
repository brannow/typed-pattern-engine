<?php declare(strict_types=1);

namespace TypedPatternEngine\Heuristic\Analyzer;

use TypedPatternEngine\Heuristic\Analyzer\Type\TypeAnalyzer;
use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\LiteralNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NodeTreeInterface;
use TypedPatternEngine\Nodes\Interfaces\TypeNodeInterface;
use TypedPatternEngine\Nodes\SubSequenceNode;
use TypedPatternEngine\Exception\PatternValidationException;
use TypedPatternEngine\Exception\PatternSyntaxException;

class NodeAnalyzer
{
    /**
     * Analyze a node and extract heuristic properties
     *
     * @param AstNodeInterface $node
     * @return AnalyzerResult
     * @throws PatternValidationException
     */
    public static function analyzeNode(AstNodeInterface $node): AnalyzerResult
    {
        return match (true) {
            $node instanceof LiteralNodeInterface => self::analyzeLiteralNode($node),
            $node instanceof TypeNodeInterface => self::analyzeTypeNode($node),
            $node instanceof NodeTreeInterface => self::analyzeTreeNode($node),
            default => throw new PatternValidationException('Unknown node in Heuristic parser detected: ' . $node::class)
        };
    }

    /**
     * Analyze a type node (Group)
     *
     * @param TypeNodeInterface $typeNode
     * @return AnalyzerResult
     * @throws PatternSyntaxException
     */
    private static function analyzeTypeNode(TypeNodeInterface $typeNode): AnalyzerResult
    {
        // Simple heuristic:
        // - Optional types have minLen = 0
        // - Required types have minLen = 1
        // - All types have maxLen = 1000 (reasonable upper bound)
        $minLen = $typeNode->isOptional() ? 0 : 1;
        $maxLen = 1000;

        // Get the type for allowed chars analysis
        $type = $typeNode->getType();
        $typeAnalysis = TypeAnalyzer::analyzeType($type);

        // Types don't have literals
        $literals = [];

        return new AnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            literals: $literals,
            allowedChars: $typeAnalysis->getAllowedChars(),
            prefix: null,
            suffix: null
        );
    }

    /**
     * Analyze a tree node (Sequence or SubSequence)
     *
     * @param NodeTreeInterface $node
     * @return AnalyzerResult
     * @throws PatternValidationException
     */
    private static function analyzeTreeNode(NodeTreeInterface $node): AnalyzerResult
    {
        $isOptional = $node instanceof SubSequenceNode;

        $minLen = 0;
        $maxLen = 0;
        $allowedChars = [];
        $literals = [];
        $prefix = null;
        $suffix = null;

        $children = $node->getChildren();
        if (empty($children)) {
            // Empty sequence - shouldn't happen but handle gracefully
            return new AnalyzerResult(
                minLen: 0,
                maxLen: 0,
                literals: [],
                allowedChars: [],
                prefix: null,
                suffix: null
            );
        }

        $childAnalyses = [];
        foreach ($children as $child) {
            $childAnalyses[] = self::analyzeNode($child);
        }

        // Calculate cumulative properties
        foreach ($childAnalyses as $i => $childAnalysis) {
            // Accumulate lengths
            $minLen += $childAnalysis->getMinLen();
            $maxLen += $childAnalysis->getMaxLen();

            // Merge allowed chars (union)
            $allowedChars = $allowedChars + $childAnalysis->getAllowedChars();

            // Merge literals with proper required/optional handling
            foreach ($childAnalysis->getLiterals() as $literalText => $isRequired) {
                if (!isset($literals[$literalText])) {
                    // If this tree node is optional, all its literals become optional
                    $literals[$literalText] = $isOptional ? false : $isRequired;
                } elseif ($isRequired && !$isOptional) {
                    // Upgrade to required if not already and tree isn't optional
                    $literals[$literalText] = true;
                }
            }

            // Set prefix from first child
            if ($i === 0 && $childAnalysis->getPrefix() !== null) {
                $prefix = $childAnalysis->getPrefix();
            }

            // Set suffix from last child (but only if it's required)
            if ($i === count($childAnalyses) - 1 && $childAnalysis->getSuffix() !== null) {
                // Only use suffix if this child contributes to minLen (i.e., is required)
                if ($childAnalysis->getMinLen() > 0) {
                    $suffix = $childAnalysis->getSuffix();
                }
                // If last child is optional, we don't have a definite suffix
            }
        }

        // If entire tree is optional, min length is 0
        if ($isOptional) {
            $minLen = 0;
        }

        return new AnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            literals: $literals,
            allowedChars: $allowedChars,
            prefix: $prefix,
            suffix: $suffix
        );
    }

    /**
     * Analyze a literal node
     *
     * @param LiteralNodeInterface $node
     * @return AnalyzerResult
     */
    private static function analyzeLiteralNode(LiteralNodeInterface $node): AnalyzerResult
    {
        $text = $node->getText();
        $literalTextLength = strlen($text);

        // Simple heuristic:
        // - Optional literals have minLen = 0
        // - Required literals have minLen = text length
        // - maxLen is always the text length for literals (they're fixed)
        $minLen = $node->isOptional() ? 0 : $literalTextLength;
        $maxLen = $literalTextLength; // Literals have fixed length

        // Build allowed chars from literal text
        $allowedChars = [];
        for ($i = 0; $i < $literalTextLength; $i++) {
            $allowedChars[ord($text[$i])] = true;
        }

        return new AnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            literals: [$text => !$node->isOptional()], // Required if not optional
            allowedChars: $allowedChars,
            prefix: $text,
            suffix: $text
        );
    }
}

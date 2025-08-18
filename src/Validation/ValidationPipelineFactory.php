<?php declare(strict_types=1);

namespace TypedPatternEngine\Validation;

final class ValidationPipelineFactory
{
    public function create(): ValidationPipeline
    {
        $pipeline = new ValidationPipeline();
        
        // Add validators in order of execution
        $pipeline->addValidator(new TreeContextValidator());
        $pipeline->addValidator(new DuplicateGroupValidator());
        $pipeline->addValidator(new ConstraintValidator());
        $pipeline->addValidator(new GreedyValidator());
        
        return $pipeline;
    }
}

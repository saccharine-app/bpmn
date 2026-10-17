<?php

namespace Saccharine\Bpmn\Services;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowExpressionEvaluator
{
    protected ExpressionLanguage $expressionEngine;

    public function __construct()
    {
        // Instantiates the isolated, sandboxed interpreter environment
        $this->expressionEngine = new ExpressionLanguage();
    }

    /**
     * Evaluates a text expression against an array of execution data variables.
     */
    public function evaluate(string $expression, array $userData): bool
    {
        try {
            // The engine looks inside $userData to evaluate strings like "total > 100"
            return (bool) $this->expressionEngine->evaluate($expression, $userData);
        } catch (Throwable $e) {
            // Fallback safety layer: if an analyst writes broken logic in the canvas panel,
            // we catch the exception, log it natively, and stall the path cleanly
            Log::error("BPMN Expression Evaluation Failed: '{$expression}'", [
                'error' => $e->getMessage(),
                'payload' => $userData
            ]);
            
            return false;
        }
    }
}
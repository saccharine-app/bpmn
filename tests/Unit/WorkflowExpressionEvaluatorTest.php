<?php

use Saccharine\Bpmn\Services\WorkflowExpressionEvaluator;

it('evaluates valid basic math and boolean expressions', function () {
    $evaluator = new WorkflowExpressionEvaluator();

    $userData = ['total' => 150, 'status' => 'approved'];

    // Test basic numeric math evaluation
    expect($evaluator->evaluate('total > 100', $userData))->toBeTrue();
    expect($evaluator->evaluate('total < 50', $userData))->toBeFalse();

    // Test standard string matching
    expect($evaluator->evaluate('status == "approved"', $userData))->toBeTrue();
    expect($evaluator->evaluate('status != "approved"', $userData))->toBeFalse();
});

it('evaluates complex logical conjunctions and array selections', function () {
    $evaluator = new WorkflowExpressionEvaluator();

    $userData = [
        'score' => 750,
        'role' => 'manager',
        'items_count' => 3
    ];

    // Test AND syntax structures
    expect($evaluator->evaluate('score > 700 and role == "manager"', $userData))->toBeTrue();
    
    // Test native array 'in' checks
    expect($evaluator->evaluate('role in ["manager", "admin"]', $userData))->toBeTrue();
    expect($evaluator->evaluate('role in ["developer", "guest"]', $userData))->toBeFalse();
});

it('gracefully handles and logs invalid expression syntax without crashing', function () {
    $evaluator = new WorkflowExpressionEvaluator();
    
    $userData = ['total' => 100];

    // Intentionally garbage syntax that cannot be interpreted
    // It should return false and safely log the error instead of throwing a fatal crash
    expect($evaluator->evaluate('total >> malformed_code === !!', $userData))->toBeFalse();
});
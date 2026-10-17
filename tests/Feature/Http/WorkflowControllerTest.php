<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Models\WorkflowNode;

it('accepts an xml payload and compiles a new workflow version', function () {
    // Setup an existing definition to attach the version to
    $definition = WorkflowDefinition::create([
        'name' => 'Client Onboarding',
        'key'  => 'client-onboarding'
    ]);

    // A simple valid BPMN XML string
    $xmlString = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <bpmn:definitions xmlns:bpmn="http://omg.org/spec/BPMN/20100524/MODEL" id="Definitions_1">
      <bpmn:process id="Process_1" isExecutable="true">
        <bpmn:startEvent id="Start_1" />
      </bpmn:process>
    </bpmn:definitions>
    XML;

    // Simulate an external POST request from the host app's frontend
    $response = $this->postJson("/api/bpmn/workflows/{$definition->id}/versions", [
        'xml' => $xmlString
    ]);

    // Assert the API responded correctly
    $response->assertStatus(201)
             ->assertJson([
                 'success' => true,
                 'message' => 'Workflow version compiled successfully.'
             ]);

    // Assert the database state updated correctly
    expect(WorkflowVersion::count())->toBe(1);
    
    $version = WorkflowVersion::first();
    expect($version->version)->toBe(1)
            ->and($version->bpmn_xml)->toBe($xmlString);

    // Verify the parser actually ran using the controller's dependency injection
    expect(WorkflowNode::count())->toBe(1);
    expect(WorkflowNode::first()->bpmn_element_id)->toBe('Start_1');
});
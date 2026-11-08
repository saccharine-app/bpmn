<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Saccharine\Bpmn\Models\ProcessDefinition;
use Saccharine\Bpmn\Models\ProcessVersion;
use Saccharine\Bpmn\Models\ProcessNode;

it('accepts an xml payload and compiles a new process version', function () {
    // Create a user (or super admin)
    $user = new class extends Authenticatable {
        protected $guarded = [];
    };
    $user->id = 1;

    // Setup an existing definition to attach the version to
    $definition = ProcessDefinition::create([
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
    $response = $this->actingAs($user)
        ->postJson(route('bpmn.definitions.versions.store', $definition), [
            'xml' => $xmlString
        ]);

    // Assert the API responded correctly
    $response->assertStatus(201)
             ->assertJson([
                 'success' => true,
                 'message' => 'Process version compiled successfully.'
             ]);

    // Assert the database state updated correctly
    expect(ProcessVersion::count())->toBe(1);
    
    $version = ProcessVersion::first();
    expect($version->version)->toBe(1)
            ->and($version->bpmn_xml)->toBe($xmlString);

    // Verify the parser actually ran using the controller's dependency injection
    expect(ProcessNode::count())->toBe(1);
    expect(ProcessNode::first()->bpmn_element_id)->toBe('Start_1');
});
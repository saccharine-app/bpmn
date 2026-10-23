<?php

namespace Saccharine\Bpmn\Database\Seeders;

use Illuminate\Database\Seeder;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Services\BpmnParserService;

class BpmnCallActivityPipelineSeeder extends Seeder
{
    public function run(BpmnParserService $parser): void
    {
        $def = WorkflowDefinition::firstOrCreate(
            ['key' => 'parent-pipeline'],
            ['name' => 'Demo: Parent Pipeline with Call Activity']
        );

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                    xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                    xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                    xmlns:camunda="http://camunda.org/schema/1.0/bpmn"
                    xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                    id="Definitions_CallActivity"
                    targetNamespace="http://bpmn.io/schema/bpmn">
    <bpmn:process id="Process_Pipeline" isExecutable="true">
    <bpmn:startEvent id="Start_1" name="New Case">
        <bpmn:outgoing>Flow_To_Sub</bpmn:outgoing>
    </bpmn:startEvent>
    
    <!-- Scoped Inline Sub-Process -->
    <bpmn:subProcess id="SubProcess_Prep" name="Prepare Account">
        <bpmn:incoming>Flow_To_Sub</bpmn:incoming>
        <bpmn:outgoing>Flow_To_Call</bpmn:outgoing>
        
        <bpmn:startEvent id="SubStart_1">
        <bpmn:outgoing>Flow_Sub_1</bpmn:outgoing>
        </bpmn:startEvent>
        <bpmn:serviceTask id="Task_Sanitize" name="Sanitize Data" camunda:class="demo_sanitize_data">
        <bpmn:incoming>Flow_Sub_1</bpmn:incoming>
        <bpmn:outgoing>Flow_Sub_2</bpmn:outgoing>
        </bpmn:serviceTask>
        <bpmn:endEvent id="SubEnd_1">
        <bpmn:incoming>Flow_Sub_2</bpmn:incoming>
        </bpmn:endEvent>
        
        <bpmn:sequenceFlow id="Flow_Sub_1" sourceRef="SubStart_1" targetRef="Task_Sanitize" />
        <bpmn:sequenceFlow id="Flow_Sub_2" sourceRef="Task_Sanitize" targetRef="SubEnd_1" />
    </bpmn:subProcess>
    
    <!-- Call Activity referencing demo-order-processing -->
    <bpmn:callActivity id="Call_OrderProcess" name="Execute Order Processing" calledElement="demo-order-processing">
        <bpmn:incoming>Flow_To_Call</bpmn:incoming>
        <bpmn:outgoing>Flow_To_Archive</bpmn:outgoing>
    </bpmn:callActivity>
    
    <bpmn:serviceTask id="Task_Archive" name="Archive Context" camunda:class="demo_archive_context">
        <bpmn:incoming>Flow_To_Archive</bpmn:incoming>
        <bpmn:outgoing>Flow_To_Complete</bpmn:outgoing>
    </bpmn:serviceTask>
    
    <bpmn:endEvent id="End_Pipeline" name="Complete">
        <bpmn:incoming>Flow_To_Complete</bpmn:incoming>
    </bpmn:endEvent>
    
    <bpmn:sequenceFlow id="Flow_To_Sub" sourceRef="Start_1" targetRef="SubProcess_Prep" />
    <bpmn:sequenceFlow id="Flow_To_Call" sourceRef="SubProcess_Prep" targetRef="Call_OrderProcess" />
    <bpmn:sequenceFlow id="Flow_To_Archive" sourceRef="Call_OrderProcess" targetRef="Task_Archive" />
    <bpmn:sequenceFlow id="Flow_To_Complete" sourceRef="Task_Archive" targetRef="End_Pipeline" />
    </bpmn:process>

    <bpmndi:BPMNDiagram id="BPMNDiagram_Pipeline">
    <bpmndi:BPMNPlane id="BPMNPlane_Pipeline" bpmnElement="Process_Pipeline">
        <bpmndi:BPMNShape id="Start_1_di" bpmnElement="Start_1">
        <dc:Bounds x="130" y="162" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="SubProcess_Prep_di" bpmnElement="SubProcess_Prep" isExpanded="true">
        <dc:Bounds x="210" y="80" width="260" height="200" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="SubStart_1_di" bpmnElement="SubStart_1">
        <dc:Bounds x="235" y="162" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Task_Sanitize_di" bpmnElement="Task_Sanitize">
        <dc:Bounds x="295" y="140" width="100" height="80" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="SubEnd_1_di" bpmnElement="SubEnd_1">
        <dc:Bounds x="415" y="162" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNEdge id="Flow_Sub_1_di" bpmnElement="Flow_Sub_1">
        <di:waypoint x="271" y="180" /><di:waypoint x="295" y="180" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_Sub_2_di" bpmnElement="Flow_Sub_2">
        <di:waypoint x="395" y="180" /><di:waypoint x="415" y="180" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNShape id="Call_OrderProcess_di" bpmnElement="Call_OrderProcess">
        <dc:Bounds x="520" y="140" width="140" height="80" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Task_Archive_di" bpmnElement="Task_Archive">
        <dc:Bounds x="700" y="140" width="120" height="80" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="End_Pipeline_di" bpmnElement="End_Pipeline">
        <dc:Bounds x="860" y="162" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNEdge id="Flow_To_Sub_di" bpmnElement="Flow_To_Sub">
        <di:waypoint x="166" y="180" /><di:waypoint x="210" y="180" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_To_Call_di" bpmnElement="Flow_To_Call">
        <di:waypoint x="470" y="180" /><di:waypoint x="520" y="180" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_To_Archive_di" bpmnElement="Flow_To_Archive">
        <di:waypoint x="660" y="180" /><di:waypoint x="700" y="180" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_To_Complete_di" bpmnElement="Flow_To_Complete">
        <di:waypoint x="820" y="180" /><di:waypoint x="860" y="180" />
        </bpmndi:BPMNEdge>
    </bpmndi:BPMNPlane>
    </bpmndi:BPMNDiagram>
</bpmn:definitions>
XML;

        $version = $def->versions()->create([
            'version'   => $def->versions()->max('version') + 1,
            'bpmn_xml'  => $xml,
            'is_active' => true,
        ]);
        $parser->parseAndStore($version, $xml);
    }
}

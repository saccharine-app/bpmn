<?php

namespace Saccharine\Bpmn\Database\Seeders;

use Illuminate\Database\Seeder;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Services\BpmnParserService;

class BpmnParallelFulfillmentSeeder extends Seeder
{
    public function run(BpmnParserService $parser): void
    {
        $def = WorkflowDefinition::firstOrCreate(
            ['key' => 'parallel-fulfillment'],
            ['name' => 'Demo: Parallel Fulfillment']
        );

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                    xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                    xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                    xmlns:camunda="http://camunda.org/schema/1.0/bpmn"
                    xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                    id="Definitions_Parallel"
                    targetNamespace="http://bpmn.io/schema/bpmn">
    <bpmn:process id="Process_Parallel" isExecutable="true">
    <bpmn:startEvent id="Start_1" name="Order Approved">
        <bpmn:outgoing>Flow_To_Split</bpmn:outgoing>
    </bpmn:startEvent>
    
    <bpmn:parallelGateway id="Gateway_Split" name="Fork Workflows">
        <bpmn:incoming>Flow_To_Split</bpmn:incoming>
        <bpmn:outgoing>Flow_Branch_Payment</bpmn:outgoing>
        <bpmn:outgoing>Flow_Branch_Warehouse</bpmn:outgoing>
    </bpmn:parallelGateway>
    
    <bpmn:serviceTask id="Task_Payment" name="Capture Payment" camunda:class="demo_capture_payment">
        <bpmn:incoming>Flow_Branch_Payment</bpmn:incoming>
        <bpmn:outgoing>Flow_Payment_Done</bpmn:outgoing>
    </bpmn:serviceTask>
    
    <bpmn:serviceTask id="Task_Warehouse" name="Reserve Stock" camunda:class="demo_reserve_stock">
        <bpmn:incoming>Flow_Branch_Warehouse</bpmn:incoming>
        <bpmn:outgoing>Flow_Warehouse_Done</bpmn:outgoing>
    </bpmn:serviceTask>
    
    <bpmn:parallelGateway id="Gateway_Join" name="Join Results">
        <bpmn:incoming>Flow_Payment_Done</bpmn:incoming>
        <bpmn:incoming>Flow_Warehouse_Done</bpmn:incoming>
        <bpmn:outgoing>Flow_To_Notify</bpmn:outgoing>
    </bpmn:parallelGateway>
    
    <bpmn:serviceTask id="Task_Notify" name="Send Confirmation" camunda:class="demo_send_confirmation">
        <bpmn:incoming>Flow_To_Notify</bpmn:incoming>
        <bpmn:outgoing>Flow_To_End</bpmn:outgoing>
    </bpmn:serviceTask>
    
    <bpmn:endEvent id="End_1" name="Fulfillment Ready">
        <bpmn:incoming>Flow_To_End</bpmn:incoming>
    </bpmn:endEvent>
    
    <bpmn:sequenceFlow id="Flow_To_Split" sourceRef="Start_1" targetRef="Gateway_Split" />
    <bpmn:sequenceFlow id="Flow_Branch_Payment" sourceRef="Gateway_Split" targetRef="Task_Payment" />
    <bpmn:sequenceFlow id="Flow_Branch_Warehouse" sourceRef="Gateway_Split" targetRef="Task_Warehouse" />
    <bpmn:sequenceFlow id="Flow_Payment_Done" sourceRef="Task_Payment" targetRef="Gateway_Join" />
    <bpmn:sequenceFlow id="Flow_Warehouse_Done" sourceRef="Task_Warehouse" targetRef="Gateway_Join" />
    <bpmn:sequenceFlow id="Flow_To_Notify" sourceRef="Gateway_Join" targetRef="Task_Notify" />
    <bpmn:sequenceFlow id="Flow_To_End" sourceRef="Task_Notify" targetRef="End_1" />
    </bpmn:process>
    
    <bpmndi:BPMNDiagram id="BPMNDiagram_Parallel">
    <bpmndi:BPMNPlane id="BPMNPlane_Parallel" bpmnElement="Process_Parallel">
        <bpmndi:BPMNShape id="Start_1_di" bpmnElement="Start_1">
        <dc:Bounds x="160" y="142" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Gateway_Split_di" bpmnElement="Gateway_Split">
        <dc:Bounds x="250" y="135" width="50" height="50" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Task_Payment_di" bpmnElement="Task_Payment">
        <dc:Bounds x="360" y="60" width="120" height="80" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Task_Warehouse_di" bpmnElement="Task_Warehouse">
        <dc:Bounds x="360" y="180" width="120" height="80" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Gateway_Join_di" bpmnElement="Gateway_Join">
        <dc:Bounds x="540" y="135" width="50" height="50" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Task_Notify_di" bpmnElement="Task_Notify">
        <dc:Bounds x="650" y="120" width="130" height="80" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="End_1_di" bpmnElement="End_1">
        <dc:Bounds x="840" y="142" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNEdge id="Flow_To_Split_di" bpmnElement="Flow_To_Split">
        <di:waypoint x="196" y="160" /><di:waypoint x="250" y="160" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_Branch_Payment_di" bpmnElement="Flow_Branch_Payment">
        <di:waypoint x="275" y="135" /><di:waypoint x="275" y="100" /><di:waypoint x="360" y="100" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_Branch_Warehouse_di" bpmnElement="Flow_Branch_Warehouse">
        <di:waypoint x="275" y="185" /><di:waypoint x="275" y="220" /><di:waypoint x="360" y="220" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_Payment_Done_di" bpmnElement="Flow_Payment_Done">
        <di:waypoint x="480" y="100" /><di:waypoint x="565" y="100" /><di:waypoint x="565" y="135" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_Warehouse_Done_di" bpmnElement="Flow_Warehouse_Done">
        <di:waypoint x="480" y="220" /><di:waypoint x="565" y="220" /><di:waypoint x="565" y="185" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_To_Notify_di" bpmnElement="Flow_To_Notify">
        <di:waypoint x="590" y="160" /><di:waypoint x="650" y="160" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_To_End_di" bpmnElement="Flow_To_End">
        <di:waypoint x="780" y="160" /><di:waypoint x="840" y="160" />
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

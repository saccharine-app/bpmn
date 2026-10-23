<?php

namespace Saccharine\Bpmn\Database\Seeders;

use Illuminate\Database\Seeder;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Services\BpmnParserService;

class BpmnBoundaryEventSeeder extends Seeder
{
    public function run(BpmnParserService $parser): void
    {
        $def = WorkflowDefinition::firstOrCreate(
            ['key' => 'sla-escalation'],
            ['name' => 'Demo: SLA Escalation & Error Boundaries']
        );

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                    xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                    xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                    xmlns:camunda="http://camunda.org/schema/1.0/bpmn"
                    xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                    id="Definitions_Boundary"
                    targetNamespace="http://bpmn.io/schema/bpmn">
    <bpmn:process id="Process_Boundary" isExecutable="true">
    <bpmn:startEvent id="Start_1" name="Ticket Opened">
        <bpmn:outgoing>Flow_To_Review</bpmn:outgoing>
    </bpmn:startEvent>
    
    <bpmn:userTask id="Task_AgentReview" name="Agent Triage">
        <bpmn:incoming>Flow_To_Review</bpmn:incoming>
        <bpmn:outgoing>Flow_Review_Resolved</bpmn:outgoing>
    </bpmn:userTask>
    
    <!-- Timer Boundary attached to userTask -->
    <bpmn:boundaryEvent id="Boundary_SLA" name="24h SLA" attachedToRef="Task_AgentReview">
        <bpmn:outgoing>Flow_SLA_Breach</bpmn:outgoing>
        <bpmn:timerEventDefinition id="Timer_1">
        <bpmn:timeDuration>PT24H</bpmn:timeDuration>
        </bpmn:timerEventDefinition>
    </bpmn:boundaryEvent>
    
    <bpmn:serviceTask id="Task_Escalate" name="Dispatch PagerDuty" camunda:class="demo_dispatch_pager">
        <bpmn:incoming>Flow_SLA_Breach</bpmn:incoming>
        <bpmn:outgoing>Flow_Escalate_End</bpmn:outgoing>
    </bpmn:serviceTask>
    
    <!-- Error Boundary attached to serviceTask -->
    <bpmn:boundaryEvent id="Boundary_ApiError" name="API Crash" attachedToRef="Task_Escalate">
        <bpmn:outgoing>Flow_Fallback</bpmn:outgoing>
        <bpmn:errorEventDefinition id="Error_1" errorRef="general_error" />
    </bpmn:boundaryEvent>
    
    <bpmn:serviceTask id="Task_LogFallback" name="Log System Alert" camunda:class="demo_log_system_alert">
        <bpmn:incoming>Flow_Fallback</bpmn:incoming>
        <bpmn:outgoing>Flow_Fallback_End</bpmn:outgoing>
    </bpmn:serviceTask>
    
    <bpmn:endEvent id="End_Resolved" name="Resolved Normally">
        <bpmn:incoming>Flow_Review_Resolved</bpmn:incoming>
    </bpmn:endEvent>
    
    <bpmn:endEvent id="End_Escalated" name="Escalated">
        <bpmn:incoming>Flow_Escalate_End</bpmn:incoming>
    </bpmn:endEvent>
    
    <bpmn:endEvent id="End_Fallback" name="Fallback Handled">
        <bpmn:incoming>Flow_Fallback_End</bpmn:incoming>
    </bpmn:endEvent>
    
    <bpmn:sequenceFlow id="Flow_To_Review" sourceRef="Start_1" targetRef="Task_AgentReview" />
    <bpmn:sequenceFlow id="Flow_Review_Resolved" sourceRef="Task_AgentReview" targetRef="End_Resolved" />
    <bpmn:sequenceFlow id="Flow_SLA_Breach" sourceRef="Boundary_SLA" targetRef="Task_Escalate" />
    <bpmn:sequenceFlow id="Flow_Escalate_End" sourceRef="Task_Escalate" targetRef="End_Escalated" />
    <bpmn:sequenceFlow id="Flow_Fallback" sourceRef="Boundary_ApiError" targetRef="Task_LogFallback" />
    <bpmn:sequenceFlow id="Flow_Fallback_End" sourceRef="Task_LogFallback" targetRef="End_Fallback" />
    </bpmn:process>

    <bpmndi:BPMNDiagram id="BPMNDiagram_Boundary">
    <bpmndi:BPMNPlane id="BPMNPlane_Boundary" bpmnElement="Process_Boundary">
        <bpmndi:BPMNShape id="Start_1_di" bpmnElement="Start_1">
        <dc:Bounds x="150" y="102" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Task_AgentReview_di" bpmnElement="Task_AgentReview">
        <dc:Bounds x="240" y="80" width="110" height="80" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Boundary_SLA_di" bpmnElement="Boundary_SLA">
        <dc:Bounds x="290" y="142" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Task_Escalate_di" bpmnElement="Task_Escalate">
        <dc:Bounds x="380" y="210" width="130" height="80" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Boundary_ApiError_di" bpmnElement="Boundary_ApiError">
        <dc:Bounds x="440" y="272" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="Task_LogFallback_di" bpmnElement="Task_LogFallback">
        <dc:Bounds x="540" y="320" width="120" height="80" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="End_Resolved_di" bpmnElement="End_Resolved">
        <dc:Bounds x="430" y="102" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="End_Escalated_di" bpmnElement="End_Escalated">
        <dc:Bounds x="580" y="232" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNShape id="End_Fallback_di" bpmnElement="End_Fallback">
        <dc:Bounds x="720" y="342" width="36" height="36" />
        </bpmndi:BPMNShape>
        <bpmndi:BPMNEdge id="Flow_To_Review_di" bpmnElement="Flow_To_Review">
        <di:waypoint x="186" y="120" /><di:waypoint x="240" y="120" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_Review_Resolved_di" bpmnElement="Flow_Review_Resolved">
        <di:waypoint x="350" y="120" /><di:waypoint x="430" y="120" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_SLA_Breach_di" bpmnElement="Flow_SLA_Breach">
        <di:waypoint x="308" y="178" /><di:waypoint x="308" y="250" /><di:waypoint x="380" y="250" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_Escalate_End_di" bpmnElement="Flow_Escalate_End">
        <di:waypoint x="510" y="250" /><di:waypoint x="580" y="250" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_Fallback_di" bpmnElement="Flow_Fallback">
        <di:waypoint x="458" y="308" /><di:waypoint x="458" y="360" /><di:waypoint x="540" y="360" />
        </bpmndi:BPMNEdge>
        <bpmndi:BPMNEdge id="Flow_Fallback_End_di" bpmnElement="Flow_Fallback_End">
        <di:waypoint x="660" y="360" /><di:waypoint x="720" y="360" />
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

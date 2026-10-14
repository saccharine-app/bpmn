<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Saccharine\BpmnEngine\Models\WorkflowDefinition;
use Saccharine\BpmnEngine\Services\BpmnParserService;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the Workbench database with a small, self-contained workflow so the
     * dashboard and designer have data to render, and integration tests have a
     * definition to execute.
     */
    public function run(BpmnParserService $parser): void
    {
        $definition = WorkflowDefinition::firstOrCreate(
            ['key' => 'workbench-onboarding'],
            ['name' => 'Workbench: Customer Onboarding'],
        );

        $version = $definition->versions()->create([
            'version'  => $definition->versions()->max('version') + 1,
            'bpmn_xml' => $this->xml(),
            'is_active' => true,
        ]);

        $parser->parseAndStore($version, $this->xml());
    }

    protected function xml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                          xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                          xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                          xmlns:camunda="http://camunda.org/schema/1.0/bpmn"
                          xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                          id="Definitions_Workbench"
                          targetNamespace="http://bpmn.io/schema/bpmn">
          <bpmn:process id="Process_Workbench" isExecutable="true">
            <bpmn:startEvent id="Start_WB" name="Customer Signed Up">
              <bpmn:outgoing>Flow_WB_1</bpmn:outgoing>
            </bpmn:startEvent>
            <bpmn:serviceTask id="Task_Welcome" name="Send Welcome Email" camunda:class="bpmn_send_notification">
              <bpmn:extensionElements>
                <camunda:inputOutput>
                  <camunda:inputParameter name="notification_channel">log</camunda:inputParameter>
                  <camunda:inputParameter name="notification_subject">Welcome aboard!</camunda:inputParameter>
                  <camunda:inputParameter name="notification_body">Thanks for signing up.</camunda:inputParameter>
                </camunda:inputOutput>
              </bpmn:extensionElements>
              <bpmn:incoming>Flow_WB_1</bpmn:incoming>
              <bpmn:outgoing>Flow_WB_2</bpmn:outgoing>
            </bpmn:serviceTask>
            <bpmn:endEvent id="End_WB" name="Onboarded">
              <bpmn:incoming>Flow_WB_2</bpmn:incoming>
            </bpmn:endEvent>
            <bpmn:sequenceFlow id="Flow_WB_1" sourceRef="Start_WB" targetRef="Task_Welcome" />
            <bpmn:sequenceFlow id="Flow_WB_2" sourceRef="Task_Welcome" targetRef="End_WB" />
          </bpmn:process>
          <bpmndi:BPMNDiagram id="BPMNDiagram_WB">
            <bpmndi:BPMNPlane id="BPMNPlane_WB" bpmnElement="Process_Workbench">
              <bpmndi:BPMNShape id="Start_WB_di" bpmnElement="Start_WB">
                <dc:Bounds x="160" y="102" width="36" height="36" />
              </bpmndi:BPMNShape>
              <bpmndi:BPMNShape id="Task_Welcome_di" bpmnElement="Task_Welcome">
                <dc:Bounds x="260" y="80" width="120" height="80" />
              </bpmndi:BPMNShape>
              <bpmndi:BPMNShape id="End_WB_di" bpmnElement="End_WB">
                <dc:Bounds x="440" y="102" width="36" height="36" />
              </bpmndi:BPMNShape>
              <bpmndi:BPMNEdge id="Flow_WB_1_di" bpmnElement="Flow_WB_1">
                <dc:waypoint x="196" y="120" /><dc:waypoint x="260" y="120" />
              </bpmndi:BPMNEdge>
              <bpmndi:BPMNEdge id="Flow_WB_2_di" bpmnElement="Flow_WB_2">
                <dc:waypoint x="380" y="120" /><dc:waypoint x="440" y="120" />
              </bpmndi:BPMNEdge>
            </bpmndi:BPMNPlane>
          </bpmndi:BPMNDiagram>
        </bpmn:definitions>
        XML;
    }
}

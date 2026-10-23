<?php

namespace Saccharine\Bpmn\Database\Seeders;

use Illuminate\Database\Seeder;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Services\BpmnParserService;

class BpmnOrderProcessingSeeder extends Seeder
{
    public function run(BpmnParserService $parser): void
    {
        $def = WorkflowDefinition::firstOrCreate(
            ['key' => 'order-processing'],
            ['name' => 'Demo: Order Processing']
        );

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  xmlns:camunda="http://camunda.org/schema/1.0/bpmn"
                  xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  id="Definitions_OrderProcessing"
                  targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_OrderProcessing" isExecutable="true">
    <!-- Message Start triggered by DemoOrder creation -->
    <bpmn:startEvent id="Start_OrderPlaced" name="Order Created">
      <bpmn:outgoing>Flow_1</bpmn:outgoing>
      <bpmn:messageEventDefinition id="MsgDef_OrderPlaced" messageRef="Msg_DemoOrderCreated" />
    </bpmn:startEvent>

    <!-- Exclusive Gateway: Total >= $1000 requires review -->
    <bpmn:exclusiveGateway id="Gateway_VIP" name="Total &gt;= $1000?">
      <bpmn:incoming>Flow_1</bpmn:incoming>
      <bpmn:outgoing>Flow_HighValue</bpmn:outgoing>
      <bpmn:outgoing>Flow_Standard</bpmn:outgoing>
    </bpmn:exclusiveGateway>

    <!-- Human User Task for Manager Review -->
    <bpmn:userTask id="Task_ManualReview" name="Manager Review">
      <bpmn:incoming>Flow_HighValue</bpmn:incoming>
      <bpmn:outgoing>Flow_Reviewed</bpmn:outgoing>
    </bpmn:userTask>

    <!-- Converging Merge Gateway -->
    <bpmn:exclusiveGateway id="Gateway_Merge">
      <bpmn:incoming>Flow_Reviewed</bpmn:incoming>
      <bpmn:incoming>Flow_Standard</bpmn:incoming>
      <bpmn:outgoing>Flow_To_Notify</bpmn:outgoing>
    </bpmn:exclusiveGateway>

    <!-- Automated Notification via built-in Activity -->
    <bpmn:serviceTask id="Task_NotifyCustomer" name="Send Order Confirmation" camunda:class="bpmn_send_notification">
      <bpmn:extensionElements>
        <camunda:inputOutput>
          <camunda:inputParameter name="notification_channel">log</camunda:inputParameter>
          <camunda:inputParameter name="notification_to">{{ customer_email }}</camunda:inputParameter>
          <camunda:inputParameter name="notification_subject">Order #{{ order_number }} Confirmed</camunda:inputParameter>
          <camunda:inputParameter name="notification_body">Hello {{ customer_name }}, your order #{{ order_number }} for ${{ total }} has been processed successfully.</camunda:inputParameter>
        </camunda:inputOutput>
      </bpmn:extensionElements>
      <bpmn:incoming>Flow_To_Notify</bpmn:incoming>
      <bpmn:outgoing>Flow_To_End</bpmn:outgoing>
    </bpmn:serviceTask>

    <bpmn:endEvent id="End_1" name="Order Complete">
      <bpmn:incoming>Flow_To_End</bpmn:incoming>
    </bpmn:endEvent>

    <!-- Sequence Flows & Conditions -->
    <bpmn:sequenceFlow id="Flow_1" sourceRef="Start_OrderPlaced" targetRef="Gateway_VIP" />
    <bpmn:sequenceFlow id="Flow_HighValue" name="Yes (&gt;= $1000)" sourceRef="Gateway_VIP" targetRef="Task_ManualReview">
      <bpmn:conditionExpression xsi:type="bpmn:tFormalExpression">total &gt;= 1000</bpmn:conditionExpression>
    </bpmn:sequenceFlow>
    <bpmn:sequenceFlow id="Flow_Standard" name="No (&lt; $1000)" sourceRef="Gateway_VIP" targetRef="Gateway_Merge">
      <bpmn:conditionExpression xsi:type="bpmn:tFormalExpression">total &lt; 1000</bpmn:conditionExpression>
    </bpmn:sequenceFlow>
    <bpmn:sequenceFlow id="Flow_Reviewed" sourceRef="Task_ManualReview" targetRef="Gateway_Merge" />
    <bpmn:sequenceFlow id="Flow_To_Notify" sourceRef="Gateway_Merge" targetRef="Task_NotifyCustomer" />
    <bpmn:sequenceFlow id="Flow_To_End" sourceRef="Task_NotifyCustomer" targetRef="End_1" />
  </bpmn:process>

  <bpmn:message id="Msg_DemoOrderCreated" name="demo_order_created" />

  <bpmndi:BPMNDiagram id="BPMNDiagram_OrderProcessing">
    <bpmndi:BPMNPlane id="BPMNPlane_OrderProcessing" bpmnElement="Process_OrderProcessing">
      <bpmndi:BPMNShape id="Start_OrderPlaced_di" bpmnElement="Start_OrderPlaced">
        <dc:Bounds x="152" y="102" width="36" height="36" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Gateway_VIP_di" bpmnElement="Gateway_VIP" isMarkerVisible="true">
        <dc:Bounds x="245" y="95" width="50" height="50" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Task_ManualReview_di" bpmnElement="Task_ManualReview">
        <dc:Bounds x="350" y="80" width="110" height="80" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Gateway_Merge_di" bpmnElement="Gateway_Merge" isMarkerVisible="true">
        <dc:Bounds x="515" y="95" width="50" height="50" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Task_NotifyCustomer_di" bpmnElement="Task_NotifyCustomer">
        <dc:Bounds x="620" y="80" width="130" height="80" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="End_1_di" bpmnElement="End_1">
        <dc:Bounds x="800" y="102" width="36" height="36" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNEdge id="Flow_1_di" bpmnElement="Flow_1">
        <di:waypoint x="188" y="120" /><di:waypoint x="245" y="120" />
      </bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Flow_HighValue_di" bpmnElement="Flow_HighValue">
        <di:waypoint x="295" y="120" /><di:waypoint x="350" y="120" />
      </bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Flow_Standard_di" bpmnElement="Flow_Standard">
        <di:waypoint x="270" y="145" /><di:waypoint x="270" y="220" /><di:waypoint x="540" y="220" /><di:waypoint x="540" y="145" />
      </bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Flow_Reviewed_di" bpmnElement="Flow_Reviewed">
        <di:waypoint x="460" y="120" /><di:waypoint x="515" y="120" />
      </bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Flow_To_Notify_di" bpmnElement="Flow_To_Notify">
        <di:waypoint x="565" y="120" /><di:waypoint x="620" y="120" />
      </bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Flow_To_End_di" bpmnElement="Flow_To_End">
        <di:waypoint x="750" y="120" /><di:waypoint x="800" y="120" />
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

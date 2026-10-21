<?php

namespace Saccharine\Bpmn\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Services\BpmnParserService;

class DemoCommand extends Command
{
    protected $signature = 'bpmn:demo';
    protected $description = 'Scaffold a complete, working Order Processing demo workflow';
    
    protected function publishStub(string $stubName, string $targetPath)
    {
        $stubPath = __DIR__ . '/../../../stubs/' . $stubName;
        
        if (!File::exists(dirname($targetPath))) {
            File::makeDirectory(dirname($targetPath), 0755, true);
        }

        File::copy($stubPath, $targetPath);
        $this->line("Created: {$targetPath}");
    }

    protected function registerInConfig(string $arrayType, string $key, string $className)
    {
        $configPath = config_path('saccharine-bpmn.php');
        if (!File::exists($configPath)) return;

        $configContents = File::get($configPath);
        $newLine = "        '{$key}' => \\{$className}::class,";

        if (!str_contains($configContents, $newLine)) {
            $pattern = "/('{$arrayType}'\s*=>\s*\[)/";
            $replacement = "$1\n" . $newLine;
            $newContents = preg_replace($pattern, $replacement, $configContents, 1);
            File::put($configPath, $newContents);
        }
    }

    public function handle(BpmnParserService $parser)
    {
        $this->info('Scaffolding BPMN Demo Environment...');

        // Demo Invoice Workflow
        // Scaffold the Demo Activity
        $this->publishStub('demo-activity.stub', app_path('Workflows/Activities/DemoGenerateInvoiceActivity.php'));
        $this->registerInConfig('activities', 'demo_generate_invoice', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');

        // Scaffold the Demo Trigger
        $this->publishStub('demo-trigger.stub', app_path('Events/DemoOrderPlaced.php'));
        $this->registerInConfig('triggers', 'demo_order_placed', 'App\Events\DemoOrderPlaced');

        // Generate the Database Diagram
        $this->seedDemoWorkflow($parser);

        // Parallel Demo
        $this->registerInConfig('activities', 'demo_capture_payment', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->registerInConfig('activities', 'demo_reserve_stock', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->registerInConfig('activities', 'demo_send_confirmation', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->seedParallelFulfillmentWorkflow($parser);
        $this->line('Seeded: Demo: Parallel Fulfillment (AND Split/Join)');

        // Boundary SLA Demo
        $this->registerInConfig('activities', 'demo_dispatch_pager', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->registerInConfig('activities', 'demo_log_system_alert', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->seedBoundaryEventWorkflow($parser);
        $this->line('Seeded: Demo: SLA Escalation & Error Boundaries');

        // SubProcess & Call Activity Demo
        $this->registerInConfig('activities', 'demo_sanitize_data', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->registerInConfig('activities', 'demo_archive_context', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->seedCallActivityPipelineWorkflow($parser);
        $this->line('Seeded: Demo: Parent Pipeline with Call Activity');

        // Model-triggered order creation workflow
        $this->seedOrderNotificationWorkflow($parser);

        $this->info('All demo workflows seeded successfully!');

        $this->info('Demo successfully installed!');
        $this->line('You can view the workflow at /bpmn/workflows');
        $this->line('To run the demo, open a Tinker session and fire: \App\Events\DemoOrderPlaced::dispatch("ORD-100", 1500, "Jane Doe");');
    }

    protected function seedDemoWorkflow(BpmnParserService $parser)
    {
        $def = WorkflowDefinition::firstOrCreate(
            ['key' => 'demo-order-processing'],
            ['name' => 'Demo: Order Processing']
        );

        $xml = $this->getDemoXml();

        $version = $def->versions()->create([
            'version'   => $def->versions()->max('version') + 1,
            'bpmn_xml'  => $xml,
            'is_active' => true,
        ]);

        $parser->parseAndStore($version, $xml);
        $this->line("Seeded Workflow Diagram: Demo: Order Processing");
    }

    protected function getDemoXml(): string
    {
        // A pre-built XML string featuring a Message Start, Exclusive Gateway, User Task, Service Task, and Parallel layout.
        return '<?xml version="1.0" encoding="UTF-8"?>
        <bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" 
                          xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" 
                          xmlns:dc="http://www.omg.org/spec/DD/20100524/DC" 
                          xmlns:camunda="http://camunda.org/schema/1.0/bpmn" 
                          xmlns:di="http://www.omg.org/spec/DD/20100524/DI" 
                          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                          id="Definitions_1" 
                          targetNamespace="http://bpmn.io/schema/bpmn">
          <bpmn:process id="Process_1" isExecutable="true">
            <bpmn:startEvent id="StartEvent_1" name="Order Placed">
              <bpmn:outgoing>Flow_1</bpmn:outgoing>
              <bpmn:messageEventDefinition messageRef="Message_Demo" />
            </bpmn:startEvent>
            <bpmn:exclusiveGateway id="Gateway_VIP" name="Order &gt; $1000?">
              <bpmn:incoming>Flow_1</bpmn:incoming>
              <bpmn:outgoing>Flow_HighValue</bpmn:outgoing>
              <bpmn:outgoing>Flow_Standard</bpmn:outgoing>
            </bpmn:exclusiveGateway>
            <bpmn:userTask id="Task_ManualReview" name="Manager Review">
              <bpmn:incoming>Flow_HighValue</bpmn:incoming>
              <bpmn:outgoing>Flow_2</bpmn:outgoing>
            </bpmn:userTask>
            <bpmn:exclusiveGateway id="Gateway_Merge">
              <bpmn:incoming>Flow_2</bpmn:incoming>
              <bpmn:incoming>Flow_Standard</bpmn:incoming>
              <bpmn:outgoing>Flow_3</bpmn:outgoing>
            </bpmn:exclusiveGateway>
            <bpmn:serviceTask id="Task_Invoice" name="Generate Invoice" camunda:class="demo_generate_invoice">
              <bpmn:incoming>Flow_3</bpmn:incoming>
              <bpmn:outgoing>Flow_End</bpmn:outgoing>
            </bpmn:serviceTask>
            <bpmn:endEvent id="EndEvent_1">
              <bpmn:incoming>Flow_End</bpmn:incoming>
            </bpmn:endEvent>
            <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Gateway_VIP" />
            <bpmn:sequenceFlow id="Flow_HighValue" name="Yes" sourceRef="Gateway_VIP" targetRef="Task_ManualReview">
              <bpmn:conditionExpression xsi:type="bpmn:tFormalExpression">amount &gt;= 1000</bpmn:conditionExpression>
            </bpmn:sequenceFlow>
            <bpmn:sequenceFlow id="Flow_Standard" name="No" sourceRef="Gateway_VIP" targetRef="Gateway_Merge">
              <bpmn:conditionExpression xsi:type="bpmn:tFormalExpression">amount &lt; 1000</bpmn:conditionExpression>
            </bpmn:sequenceFlow>
            <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_ManualReview" targetRef="Gateway_Merge" />
            <bpmn:sequenceFlow id="Flow_3" sourceRef="Gateway_Merge" targetRef="Task_Invoice" />
            <bpmn:sequenceFlow id="Flow_End" sourceRef="Task_Invoice" targetRef="EndEvent_1" />
          </bpmn:process>
          <bpmn:message id="Message_Demo" name="demo_order_placed" />
          <bpmndi:BPMNDiagram id="BPMNDiagram_1">
            <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
              <bpmndi:BPMNShape id="_BPMNShape_StartEvent_2" bpmnElement="StartEvent_1">
                <dc:Bounds x="152" y="102" width="36" height="36" />
              </bpmndi:BPMNShape>
              <bpmndi:BPMNShape id="Gateway_VIP_di" bpmnElement="Gateway_VIP" isMarkerVisible="true">
                <dc:Bounds x="245" y="95" width="50" height="50" />
              </bpmndi:BPMNShape>
              <bpmndi:BPMNShape id="Task_ManualReview_di" bpmnElement="Task_ManualReview">
                <dc:Bounds x="350" y="80" width="100" height="80" />
              </bpmndi:BPMNShape>
              <bpmndi:BPMNShape id="Gateway_Merge_di" bpmnElement="Gateway_Merge" isMarkerVisible="true">
                <dc:Bounds x="505" y="95" width="50" height="50" />
              </bpmndi:BPMNShape>
              <bpmndi:BPMNShape id="Task_Invoice_di" bpmnElement="Task_Invoice">
                <dc:Bounds x="610" y="80" width="100" height="80" />
              </bpmndi:BPMNShape>
              <bpmndi:BPMNShape id="EndEvent_1_di" bpmnElement="EndEvent_1">
                <dc:Bounds x="762" y="102" width="36" height="36" />
              </bpmndi:BPMNShape>
              <bpmndi:BPMNEdge id="Flow_1_di" bpmnElement="Flow_1">
                <di:waypoint x="188" y="120" />
                <di:waypoint x="245" y="120" />
              </bpmndi:BPMNEdge>
              <bpmndi:BPMNEdge id="Flow_HighValue_di" bpmnElement="Flow_HighValue">
                <di:waypoint x="295" y="120" />
                <di:waypoint x="350" y="120" />
              </bpmndi:BPMNEdge>
              <bpmndi:BPMNEdge id="Flow_Standard_di" bpmnElement="Flow_Standard">
                <di:waypoint x="270" y="145" />
                <di:waypoint x="270" y="230" />
                <di:waypoint x="530" y="230" />
                <di:waypoint x="530" y="145" />
              </bpmndi:BPMNEdge>
              <bpmndi:BPMNEdge id="Flow_2_di" bpmnElement="Flow_2">
                <di:waypoint x="450" y="120" />
                <di:waypoint x="505" y="120" />
              </bpmndi:BPMNEdge>
              <bpmndi:BPMNEdge id="Flow_3_di" bpmnElement="Flow_3">
                <di:waypoint x="555" y="120" />
                <di:waypoint x="610" y="120" />
              </bpmndi:BPMNEdge>
              <bpmndi:BPMNEdge id="Flow_End_di" bpmnElement="Flow_End">
                <di:waypoint x="710" y="120" />
                <di:waypoint x="762" y="120" />
              </bpmndi:BPMNEdge>
            </bpmndi:BPMNPlane>
          </bpmndi:BPMNDiagram>
        </bpmn:definitions>';
    }

    protected function seedParallelFulfillmentWorkflow(BpmnParserService $parser): void
    {
      $def = WorkflowDefinition::firstOrCreate(
          ['key' => 'demo-parallel-fulfillment'],
          ['name' => 'Demo: Parallel Fulfillment (AND Split/Join)']
      );

      $xml = '<?xml version="1.0" encoding="UTF-8"?>
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
        </bpmn:definitions>';

      $version = $def->versions()->create([
          'version'   => $def->versions()->max('version') + 1,
          'bpmn_xml'  => $xml,
          'is_active' => true,
      ]);
      $parser->parseAndStore($version, $xml);
    }

    protected function seedBoundaryEventWorkflow(BpmnParserService $parser): void
    {
        $def = WorkflowDefinition::firstOrCreate(
            ['key' => 'demo-sla-escalation'],
            ['name' => 'Demo: SLA Escalation & Error Boundaries']
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
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
        </bpmn:definitions>';

        $version = $def->versions()->create([
            'version'   => $def->versions()->max('version') + 1,
            'bpmn_xml'  => $xml,
            'is_active' => true,
        ]);
        $parser->parseAndStore($version, $xml);
    }

    protected function seedCallActivityPipelineWorkflow(BpmnParserService $parser): void
    {
        $def = WorkflowDefinition::firstOrCreate(
            ['key' => 'demo-parent-pipeline'],
            ['name' => 'Demo: Parent Pipeline with Call Activity']
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
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
        </bpmn:definitions>';

        $version = $def->versions()->create([
            'version'   => $def->versions()->max('version') + 1,
            'bpmn_xml'  => $xml,
            'is_active' => true,
        ]);
        $parser->parseAndStore($version, $xml);
    }

    protected function seedOrderNotificationWorkflow(BpmnParserService $parser): void
    {
        $def = WorkflowDefinition::firstOrCreate(
            ['key' => 'demo-order-notification'],
            ['name' => 'Demo: Order Created -> Send Notification']
        );

        $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                      xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                      xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                      xmlns:camunda="http://camunda.org/schema/1.0/bpmn"
                      xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                      id="Definitions_OrderNotif"
                      targetNamespace="http://bpmn.io/schema/bpmn">
      <bpmn:process id="Process_OrderNotif" isExecutable="true">
        <!-- Message Start listening for demo_order_created -->
        <bpmn:startEvent id="Start_OrderCreated" name="Order Created">
          <bpmn:outgoing>Flow_1</bpmn:outgoing>
          <bpmn:messageEventDefinition id="MsgDef_1" messageRef="Msg_OrderCreated" />
        </bpmn:startEvent>

        <!-- Built-in notification activity with default template inputs -->
        <bpmn:serviceTask id="Task_NotifyCustomer" name="Send Order Email" camunda:class="bpmn_send_notification">
          <bpmn:extensionElements>
            <camunda:inputOutput>
              <camunda:inputParameter name="notification_channel">log</camunda:inputParameter>
              <camunda:inputParameter name="notification_to">{{ customer_email }}</camunda:inputParameter>
              <camunda:inputParameter name="notification_subject">Order {{ order_number }} Confirmation</camunda:inputParameter>
              <camunda:inputParameter name="notification_body">Thank you {{ customer_name }}! Your order of ${{ total }} has been received.</camunda:inputParameter>
            </camunda:inputOutput>
          </bpmn:extensionElements>
          <bpmn:incoming>Flow_1</bpmn:incoming>
          <bpmn:outgoing>Flow_2</bpmn:outgoing>
        </bpmn:serviceTask>

        <bpmn:endEvent id="End_1" name="Notified">
          <bpmn:incoming>Flow_2</bpmn:incoming>
        </bpmn:endEvent>

        <bpmn:sequenceFlow id="Flow_1" sourceRef="Start_OrderCreated" targetRef="Task_NotifyCustomer" />
        <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_NotifyCustomer" targetRef="End_1" />
      </bpmn:process>

      <bpmn:message id="Msg_OrderCreated" name="demo_order_created" />

      <bpmndi:BPMNDiagram id="BPMNDiagram_OrderNotif">
        <bpmndi:BPMNPlane id="BPMNPlane_OrderNotif" bpmnElement="Process_OrderNotif">
          <bpmndi:BPMNShape id="Start_OrderCreated_di" bpmnElement="Start_OrderCreated">
            <dc:Bounds x="160" y="102" width="36" height="36" />
          </bpmndi:BPMNShape>
          <bpmndi:BPMNShape id="Task_NotifyCustomer_di" bpmnElement="Task_NotifyCustomer">
            <dc:Bounds x="260" y="80" width="140" height="80" />
          </bpmndi:BPMNShape>
          <bpmndi:BPMNShape id="End_1_di" bpmnElement="End_1">
            <dc:Bounds x="460" y="102" width="36" height="36" />
          </bpmndi:BPMNShape>
          <bpmndi:BPMNEdge id="Flow_1_di" bpmnElement="Flow_1">
            <di:waypoint x="196" y="120" /><di:waypoint x="260" y="120" />
          </bpmndi:BPMNEdge>
          <bpmndi:BPMNEdge id="Flow_2_di" bpmnElement="Flow_2">
            <di:waypoint x="400" y="120" /><di:waypoint x="460" y="120" />
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
<?php

namespace Saccharine\BpmnEngine\Services;

use Saccharine\BpmnEngine\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use Exception;

class BpmnParserService
{
    public function parseAndStore(WorkflowVersion $version, string $xmlString): void
    {
        $xml = new SimpleXMLElement($xmlString);

        // STRICT NAMESPACE VALIDATION
        // Extract all namespaces used in the document
        $namespaces = $xml->getNamespaces(true);
        $bpmnUri = $namespaces['bpmn'] ?? null;

        $validBPMNURIs = [
            'http://www.omg.org/spec/BPMN/20100524/MODEL',
            'http://omg.org/spec/BPMN/20100524/MODEL'
        ];

        if (!in_array($bpmnUri, $validBPMNURIs)) {
            throw new Exception('Invalid BPMN file: Incorrect or missing BPMN namespace URI.');
        }

        // Register namespaces for XPath querying
        $xml->registerXPathNamespace('bpmn', $bpmnUri); // Safely use the exact matching valid URI
        $xml->registerXPathNamespace('camunda', 'http://camunda.org/schema/1.0/bpmn');

        // Build a dictionary of global BPMN Messages
        $messageMap = [];
        $messages = $xml->xpath('//bpmn:message');

        if ($messages !== false) {
            foreach ($messages as $message) {
                $id = (string) $message['id'];
                $name = (string) $message['name'];
                $messageMap[$id] = $name;
            }
        }

        $process = $xml->xpath('//bpmn:process')[0] ?? null;

        if (!$process) {
            throw new Exception('Invalid BPMN file: <bpmn:process> element not found.');
        }

        DB::transaction(function () use ($version, $process, $messageMap, $bpmnUri) {
            // Clear existing elements if re-parsing
            $version->nodes()->delete();
            $version->edges()->delete();

            // Extract Nodes
            $nodeTypes = [
                'startEvent', 'endEvent', 
                'serviceTask', 'userTask', 
                'exclusiveGateway', 'parallelGateway',
                'boundaryEvent',
                'callActivity',
                'subProcess'
            ];

            foreach ($nodeTypes as $type) {
                $elements = $process->xpath(".//bpmn:{$type}");

                foreach ($elements as $element) {
                    // XPath namespace registrations are per-node and are NOT inherited
                    // by child elements. Registering on the root alone means any document
                    // that doesn't itself declare xmlns:camunda blows up with
                    // "Undefined namespace prefix" as soon as we query extension elements.
                    $element->registerXPathNamespace('bpmn', $bpmnUri);
                    $element->registerXPathNamespace('camunda', 'http://camunda.org/schema/1.0/bpmn');

                    $attributes = $element->attributes();
                    $bpmnId = (string) $attributes['id'];
                    $name = (string) ($attributes['name'] ?? '');

                    $attachedToRef = null;
                    $eventDefType = null;
                    $parentElementId = null;

                    // Extract input parameters defined via Camunda extension elements
                    $inputParameters = [];
                    $camundaInputs = $element->xpath('.//bpmn:extensionElements/camunda:inputOutput/camunda:inputParameter');
                    if (!empty($camundaInputs)) {
                        foreach ($camundaInputs as $input) {
                            $paramName = (string) $input['name'];
                            $paramValue = (string) $input;
                            if ($paramName !== '') {
                                $inputParameters[$paramName] = $paramValue;
                            }
                        }
                    }

                    // Determine if this node is nested inside a subProcess
                    $parentElements = $element->xpath('parent::bpmn:subProcess');
                    if (!empty($parentElements)) {
                        $parentElementId = (string) $parentElements[0]['id'];
                    }

                    // Extract the implementation key from camunda:class
                    $camundaAttrs = $element->attributes('http://camunda.org/schema/1.0/bpmn');
                    $implementation = (string) ($camundaAttrs['class'] ?? null);

                    // Call Activity Extraction
                    if ($type === 'callActivity') {
                        $implementation = (string) $attributes['calledElement'];
                    }

                    if ($type === 'boundaryEvent') {
                        $attachedToRef = (string) $attributes['attachedToRef'];
                        
                        // Dynamically determine the sub-type by looking at child nodes
                        if (!empty($element->xpath('.//bpmn:errorEventDefinition'))) {
                            $eventDefType = 'error';
                            // In Camunda, error codes are often stored here. We can map this to $implementation for now.
                            $errorDef = $element->xpath('.//bpmn:errorEventDefinition')[0];
                            $implementation = (string) $errorDef['errorRef'] ?? 'general_error';
                        } elseif (!empty($element->xpath('.//bpmn:timerEventDefinition'))) {
                            $eventDefType = 'timer';
                            // Extract the ISO 8601 duration (e.g., 'PT2H')
                            $timerDef = $element->xpath('.//bpmn:timerEventDefinition/bpmn:timeDuration');
                            $implementation = !empty($timerDef) ? (string) $timerDef[0] : 'PT1H'; // Default to 1 hour if missing
                        } elseif (!empty($element->xpath('.//bpmn:messageEventDefinition'))) {
                            $eventDefType = 'message';
                        }
                    }

                    // Intercept Message Start Events and resolve their alias
                    else if ($type === 'startEvent') {
                        $msgDef = $element->xpath('.//bpmn:messageEventDefinition');
                        
                        if (!empty($msgDef)) {
                            // Extract the reference ID (e.g., 'Message_0x8b3a')
                            $messageRef = (string) $msgDef[0]['messageRef'];
                            
                            // Map it back to the actual string alias (e.g., 'custody_log_created')
                            $implementation = $messageMap[$messageRef] ?? $implementation;
                        }
                    }

                    $version->nodes()->create([
                        'bpmn_element_id' => $bpmnId,
                        'type'            => $type,
                        'name'            => $name ?: null,
                        'implementation'  => $implementation,
                        'input_parameters'       => !empty($inputParameters) ? $inputParameters : null,
                        'attached_to_element_id' => $attachedToRef,
                        'event_definition_type'  => $eventDefType,
                        'parent_element_id'      => $parentElementId,
                    ]);
                }
            }

            // Extract Edges (Sequence Flows)
            $flows = $process->xpath('.//bpmn:sequenceFlow');

            foreach ($flows as $flow) {
                $attributes = $flow->attributes();
                
                $conditionElement = $flow->xpath('.//bpmn:conditionExpression');
                $condition = !empty($conditionElement) ? (string) $conditionElement[0] : null;

                $version->edges()->create([
                    'bpmn_element_id'      => (string) $attributes['id'],
                    'source_node_id'       => (string) $attributes['sourceRef'],
                    'target_node_id'       => (string) $attributes['targetRef'],
                    'condition_expression' => $condition,
                ]);
            }
        });
    }
}
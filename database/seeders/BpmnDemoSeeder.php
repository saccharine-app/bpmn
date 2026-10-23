<?php

namespace Saccharine\Bpmn\Database\Seeders;

use Illuminate\Database\Seeder;

class BpmnDemoSeeder extends Seeder
{
    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        $this->call(BpmnOrderProcessingSeeder::class);
        $this->call(BpmnParallelFulfillmentSeeder::class);
        $this->call(BpmnBoundaryEventSeeder::class);
        $this->call(BpmnCallActivityPipelineSeeder::class);
    }
}

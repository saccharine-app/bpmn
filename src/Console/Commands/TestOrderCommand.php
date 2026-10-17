<?php

namespace Saccharine\Bpmn\Console\Commands;

use Illuminate\Console\Command;
use Saccharine\Bpmn\Models\DemoOrder;

class TestOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bpmn:test-order 
                            {--amount=150.00 : The order amount to evaluate against gateway conditions}
                            {--email=demo@example.com : The recipient email for notifications}
                            {--name=Demo Customer : The customer name}';

    /**
     * The console command description.
     */
    protected $description = 'Create a DemoOrder to trigger mapped BPMN model workflows';

    public function handle(): int
    {
        $amount = (float) $this->option('amount');
        $email = (string) $this->option('email');
        $name = (string) $this->option('name');
        $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -6));

        $this->info("Creating DemoOrder [{$orderNumber}]...");

        $order = DemoOrder::create([
            'order_number'   => $orderNumber,
            'customer_name'  => $name,
            'customer_email' => $email,
            'total'          => $amount,
            'status'         => 'created',
        ]);

        $this->newLine();
        $this->line("  <fg=green;options=bold>Order Created Successfully</>");
        $this->line("  ID:           <fg=yellow>{$order->id}</>");
        $this->line("  Number:       <fg=yellow>{$order->order_number}</>");
        $this->line("  Customer:     {$order->customer_name} ({$order->customer_email})");
        $this->line("  Total:        <fg=green>\${$order->total}</>");
        $this->newLine();

        $this->comment("The 'demo_order_created' event has been broadcast.");
        $this->comment("Run 'php artisan queue:work' to process the resulting workflow execution.");

        return self::SUCCESS;
    }
}
<?php

namespace Saccharine\Bpmn\Workflows\Activities;

use Workflow\Activity;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendNotificationActivity extends Activity
{
    public function execute(array $userData): array
    {
        $channel = $userData['notification_channel'] ?? 'mail'; // 'mail', 'webhook', or 'log'
        $recipient = $this->interpolate($userData['notification_to'] ?? '', $userData);
        $subject = $this->interpolate($userData['notification_subject'] ?? 'Workflow Notification', $userData);
        $message = $this->interpolate($userData['notification_body'] ?? '', $userData);

        $status = 'dispatched';
        $timestamp = now()->toIso8601String();

        try {
            switch ($channel) {
                case 'webhook':
                    $response = Http::timeout(10)->post($recipient, [
                        'subject' => $subject,
                        'message' => $message,
                        'payload' => $userData,
                    ]);
                    $status = $response->successful() ? 'delivered' : 'failed';
                    break;

                case 'mail':
                    if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                        Mail::raw($message, function ($mail) use ($recipient, $subject) {
                            $mail->to($recipient)->subject($subject);
                        });
                        $status = 'sent';
                    } else {
                        Log::warning("SendNotificationActivity: Invalid email address [{$recipient}]. Falling back to log.");
                        $status = 'invalid_recipient';
                    }
                    break;

                case 'log':
                default:
                    Log::info("BPMN Notification [{$subject}] to [{$recipient}]: {$message}");
                    $status = 'logged';
                    break;
            }
        } catch (\Throwable $e) {
            Log::error("SendNotificationActivity failed: " . $e->getMessage(), [
                'recipient' => $recipient,
                'channel'   => $channel,
            ]);
            throw $e; // Re-throw so boundary events or retry policies can intercept it
        }

        return [
            'notification_status'    => $status,
            'notification_timestamp' => $timestamp,
        ];
    }

    /**
     * Replaces simple {{ key }} placeholders with values from the userData payload.
     */
    protected function interpolate(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($matches) use ($data) {
            $key = $matches[1];
            return data_get($data, $key, $matches[0]);
        }, $template);
    }
}
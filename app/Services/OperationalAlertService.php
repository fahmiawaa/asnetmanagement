<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AsnetNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

class OperationalAlertService
{
    public function send(
        string $key,
        string $title,
        string $message,
        array $context = [],
        string $level = 'danger',
        string $link = '/operations',
    ): bool {
        if (! $this->acquireCooldown($key)) {
            return false;
        }

        Log::warning($title, ['message' => $message, ...$context]);
        $this->sendDatabaseNotification($title, $message, $level, $link);
        $this->sendEmail($title, $message, $context);
        $this->sendWebhook($key, $title, $message, $context, $level);

        return true;
    }

    private function acquireCooldown(string $key): bool
    {
        try {
            return Cache::add(
                'operations:alert:'.hash('sha256', $key),
                now()->toIso8601String(),
                now()->addMinutes(max(1, (int) config('operations.alert.cooldown_minutes')))
            );
        } catch (Throwable $exception) {
            Log::error('Penyimpanan cooldown alert tidak tersedia.', [
                'exception' => $exception->getMessage(),
            ]);

            return true;
        }
    }

    private function sendDatabaseNotification(
        string $title,
        string $message,
        string $level,
        string $link = '/operations',
    ): void {
        try {
            $users = User::query()
                ->whereIn('role', ['owner', 'admin'])
                ->where('active', true)
                ->get();

            Notification::send(
                $users,
                new AsnetNotification($title, $message, $link, $level)
            );
        } catch (Throwable $exception) {
            Log::error('Alert database gagal dikirim.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function sendEmail(string $title, string $message, array $context): void
    {
        foreach (config('operations.alert.emails', []) as $email) {
            try {
                Mail::raw(
                    $message."\n\n".json_encode($context, JSON_PRETTY_PRINT),
                    function ($mail) use ($email, $title): void {
                        $mail->to($email)->subject('[ASNet] '.$title);
                    }
                );
            } catch (Throwable $exception) {
                Log::error('Alert email gagal dikirim.', [
                    'email' => $email,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function sendWebhook(
        string $key,
        string $title,
        string $message,
        array $context,
        string $level,
    ): void {
        $url = config('operations.alert.webhook_url');

        if (! $url) {
            return;
        }

        try {
            $request = Http::timeout(10)->retry(2, 250);
            $token = config('operations.alert.webhook_token');

            if ($token) {
                $request = $request->withToken($token);
            }

            $request->post($url, [
                'source' => 'ASNet Billing',
                'key' => $key,
                'level' => $level,
                'title' => $title,
                'message' => $message,
                'context' => $context,
                'occurred_at' => now()->toIso8601String(),
            ])->throw();
        } catch (Throwable $exception) {
            Log::error('Alert webhook gagal dikirim.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

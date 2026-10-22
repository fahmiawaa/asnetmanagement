<?php

namespace App\Services;

class WhatsAppLinkService
{
    public function link(?string $number, string $message): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }
}

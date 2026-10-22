<?php

namespace App\Services;

use RuntimeException;

class BackupEncryptionService
{
    private const MAGIC = 'ASNETENC1';

    private const CHUNK_BYTES = 1024 * 1024;

    public function isEnabled(): bool
    {
        return (bool) config('backup.encryption.enabled', false);
    }

    public function extension(): string
    {
        return '.enc';
    }

    public function isEncrypted(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $magic = fread($handle, strlen(self::MAGIC));
        } finally {
            fclose($handle);
        }

        return $magic === self::MAGIC;
    }

    public function key(): string
    {
        $encoded = config('backup.encryption.key');

        if (! is_string($encoded) || trim($encoded) === '') {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY belum diatur.');
        }

        $encoded = trim($encoded);

        if (str_starts_with($encoded, 'base64:')) {
            $encoded = substr($encoded, 7);
        }

        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY harus berupa base64 dari 32 byte.');
        }

        return $key;
    }

    public function encryptFile(string $source, string $destination): void
    {
        $key = $this->key();
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);

        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');

        if ($input === false || $output === false) {
            sodium_memzero($key);

            throw new RuntimeException('File backup tidak dapat dibuka untuk enkripsi.');
        }

        try {
            if (fwrite($output, self::MAGIC.$header) === false) {
                throw new RuntimeException('Header enkripsi backup gagal ditulis.');
            }

            while (! feof($input)) {
                $chunk = fread($input, self::CHUNK_BYTES);

                if ($chunk === false) {
                    throw new RuntimeException('Pembacaan file backup gagal.');
                }

                $tag = feof($input)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);

                if (fwrite($output, $cipher) === false) {
                    throw new RuntimeException('Penulisan arsip terenkripsi gagal.');
                }
            }
        } finally {
            fclose($input);
            fclose($output);
            sodium_memzero($key);
        }
    }

    public function decryptFile(string $source, string $destination): void
    {
        $key = $this->key();

        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');

        if ($input === false || $output === false) {
            sodium_memzero($key);

            throw new RuntimeException('Arsip terenkripsi tidak dapat dibuka.');
        }

        $sawFinalChunk = false;

        try {
            $magic = fread($input, strlen(self::MAGIC));

            if ($magic !== self::MAGIC) {
                throw new RuntimeException('File bukan arsip terenkripsi ASNet.');
            }

            $header = fread($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

            if ($header === false || strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new RuntimeException('Header arsip terenkripsi tidak lengkap.');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $frameBytes = self::CHUNK_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

            while (! feof($input)) {
                $cipher = fread($input, $frameBytes);

                if ($cipher === false) {
                    throw new RuntimeException('Pembacaan arsip terenkripsi gagal.');
                }

                if ($cipher === '') {
                    break;
                }

                $frame = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);

                if ($frame === false) {
                    throw new RuntimeException('Dekripsi gagal: arsip rusak atau kunci tidak cocok.');
                }

                [$plain, $tag] = $frame;

                if (fwrite($output, $plain) === false) {
                    throw new RuntimeException('Penulisan hasil dekripsi gagal.');
                }

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $sawFinalChunk = true;

                    break;
                }
            }

            if (! $sawFinalChunk) {
                throw new RuntimeException('Dekripsi gagal: arsip terpotong.');
            }
        } finally {
            fclose($input);
            fclose($output);
            sodium_memzero($key);
        }
    }
}

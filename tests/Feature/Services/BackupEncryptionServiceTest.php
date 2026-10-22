<?php

namespace Tests\Feature\Services;

use App\Services\BackupEncryptionService;
use RuntimeException;
use Tests\TestCase;

class BackupEncryptionServiceTest extends TestCase
{
    private BackupEncryptionService $encryption;

    /** @var array<int, string> */
    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['backup.encryption.key' => base64_encode(random_bytes(32))]);

        $this->encryption = app(BackupEncryptionService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @unlink($path);
            @unlink($path.'.sha256');
        }

        parent::tearDown();
    }

    public function test_encrypts_and_decrypts_backup_across_multiple_chunks(): void
    {
        $content = random_bytes(2 * 1024 * 1024 + 12345);
        $source = $this->temporaryPath();
        file_put_contents($source, $content);

        $encrypted = $this->temporaryPath();
        $decrypted = $this->temporaryPath();

        $this->encryption->encryptFile($source, $encrypted);

        $this->assertTrue($this->encryption->isEncrypted($encrypted));
        $this->assertFalse($this->encryption->isEncrypted($source));
        $this->assertNotSame(hash('sha256', $content), hash_file('sha256', $encrypted));

        $this->encryption->decryptFile($encrypted, $decrypted);

        $this->assertSame(hash('sha256', $content), hash_file('sha256', $decrypted));
    }

    public function test_encrypts_and_decrypts_empty_backup(): void
    {
        $source = $this->temporaryPath();
        $encrypted = $this->temporaryPath();
        $decrypted = $this->temporaryPath();

        $this->encryption->encryptFile($source, $encrypted);
        $this->encryption->decryptFile($encrypted, $decrypted);

        $this->assertSame('', (string) file_get_contents($decrypted));
    }

    public function test_tampered_archive_is_rejected(): void
    {
        $source = $this->temporaryPath();
        file_put_contents($source, str_repeat('asnet-backup-payload', 1000));

        $encrypted = $this->temporaryPath();
        $decrypted = $this->temporaryPath();
        $this->encryption->encryptFile($source, $encrypted);

        $bytes = (string) file_get_contents($encrypted);
        $index = intdiv(strlen($bytes), 2);
        $bytes[$index] = chr(ord($bytes[$index]) ^ 0xFF);
        file_put_contents($encrypted, $bytes);

        $this->expectException(RuntimeException::class);
        $this->encryption->decryptFile($encrypted, $decrypted);
    }

    public function test_wrong_key_is_rejected(): void
    {
        $source = $this->temporaryPath();
        file_put_contents($source, 'backup rahasia');

        $encrypted = $this->temporaryPath();
        $decrypted = $this->temporaryPath();
        $this->encryption->encryptFile($source, $encrypted);

        config(['backup.encryption.key' => base64_encode(random_bytes(32))]);

        $this->expectException(RuntimeException::class);
        $this->encryption->decryptFile($encrypted, $decrypted);
    }

    public function test_missing_or_invalid_key_is_rejected(): void
    {
        $source = $this->temporaryPath();
        $encrypted = $this->temporaryPath();

        config(['backup.encryption.key' => null]);

        try {
            $this->encryption->encryptFile($source, $encrypted);
            $this->fail('Kunci kosong seharusnya ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('BACKUP_ENCRYPTION_KEY', $exception->getMessage());
        }

        config(['backup.encryption.key' => base64_encode('terlalu-pendek')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('32 byte');
        $this->encryption->encryptFile($source, $encrypted);
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'asnet-encryption-');

        $this->assertNotFalse($path);
        $this->temporaryPaths[] = $path;

        return $path;
    }
}

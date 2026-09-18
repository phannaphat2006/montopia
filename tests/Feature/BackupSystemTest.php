<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabase;
use App\Services\BackupBundleService;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class BackupSystemTest extends TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoot = base_path('storage/framework/testing/backup-bundles/test_'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->testRoot);
        $this->app->useStoragePath($this->testRoot);
        config(['monstopia.restore_host' => null, 'monstopia.restore_port' => null, 'monstopia.restore_username' => null]);
    }

    protected function tearDown(): void
    {
        $resolved = realpath($this->testRoot);
        $parent = realpath(base_path('storage/framework/testing/backup-bundles'));
        if ($resolved && $parent && str_starts_with($resolved, $parent.DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($resolved);
        }
        parent::tearDown();
    }

    public function test_file_restore_checks_binary_bytes_and_does_not_overwrite_original_or_previous_restore(): void
    {
        [$bundle, $manifest] = $this->bundle();
        $original = $this->testRoot.'/app/private/project-files/7/work.pdf';
        File::ensureDirectoryExists(dirname($original));
        File::put($original, 'CURRENT ORIGINAL MUST SURVIVE');
        $service = app(BackupBundleService::class);
        $first = $service->verify($bundle, true);
        $second = $service->verify($bundle, true);
        $this->assertSame(1, $first['files_verified']);
        $this->assertSame(0, $first['tables_verified']);
        $this->assertNull($first['restore_database']);
        $this->assertNotSame($first['restore_directory'], $second['restore_directory']);
        $restored = $first['restore_directory'].'/files/local/project-files/7/work.pdf';
        $this->assertSame($manifest['files'][0]['sha256'], hash_file('sha256', $restored));
        $this->assertSame('CURRENT ORIGINAL MUST SURVIVE', File::get($original));
        $this->assertFileExists($first['restore_directory'].'/verification.json');
    }

    public function test_tampered_attachment_is_rejected(): void
    {
        [$bundle] = $this->bundle();
        File::put($bundle.'/files/1.bin', 'tampered');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum');
        app(BackupBundleService::class)->verify($bundle, true);
    }

    public function test_missing_attachment_is_rejected(): void
    {
        [$bundle] = $this->bundle();
        File::delete($bundle.'/files/1.bin');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('หายไป');
        app(BackupBundleService::class)->verify($bundle, true);
    }

    public function test_tampered_database_is_rejected_even_in_files_only_mode(): void
    {
        [$bundle] = $this->bundle();
        File::put($bundle.'/database.sql.gz', 'not gzip');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum');
        app(BackupBundleService::class)->verify($bundle, true);
    }

    public function test_manifest_cannot_read_a_file_outside_its_bundle(): void
    {
        [$bundle, $manifest] = $this->bundle();
        $manifest['files'][0]['backup_path'] = '../secret.txt';
        File::put($bundle.'/manifest.json', json_encode($manifest));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('เส้นทางไฟล์ไม่ปลอดภัย');
        app(BackupBundleService::class)->verify($bundle, true);
    }

    public function test_manifest_cannot_write_outside_the_new_restore_directory(): void
    {
        [$bundle, $manifest] = $this->bundle();
        $manifest['files'][0]['stored_path'] = '../../outside.txt';
        File::put($bundle.'/manifest.json', json_encode($manifest));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('เส้นทางไฟล์ไม่ปลอดภัย');
        app(BackupBundleService::class)->verify($bundle, true);
    }

    public function test_full_restore_without_explicit_separate_mysql_configuration_is_refused(): void
    {
        [$bundle] = $this->bundle();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MONSTOPIA_RESTORE_HOST');
        app(BackupBundleService::class)->verify($bundle);
    }

    public function test_commands_report_success_and_do_not_claim_database_restoration_in_files_only_mode(): void
    {
        [$bundle] = $this->bundle();
        $this->artisan('monstopia:verify-backup', ['bundle' => $bundle, '--files-only' => true])
            ->expectsOutputToContain('files-only')->assertSuccessful();
        $this->artisan('monstopia:backup-all')->expectsOutputToContain('MySQL')->assertFailed();
    }

    public function test_sql_writer_completes_binary_data_larger_than_one_bounded_chunk(): void
    {
        $handle = fopen('php://temp', 'w+b');
        $bytes = str_repeat("\x00\xFFsql bytes\n", 120000);
        try {
            (new ReflectionMethod(BackupDatabase::class, 'writeAll'))->invoke(new BackupDatabase, $handle, $bytes);
            rewind($handle);
            $this->assertSame($bytes, stream_get_contents($handle));
        } finally {
            fclose($handle);
        }
    }

    public function test_sql_writer_rejects_zero_progress_after_a_partial_write(): void
    {
        $this->assertSqlWriteFailure('zero');
    }

    public function test_sql_writer_preserves_every_byte_when_stream_only_accepts_short_writes(): void
    {
        stream_wrapper_register('monstopiaqawrite', BackupFailureStream::class);
        BackupFailureStream::$failure = 'partial';
        BackupFailureStream::$writes = 0;
        BackupFailureStream::$data = '';
        $handle = fopen('monstopiaqawrite://test', 'wb');
        $bytes = str_repeat('SQL;', 100);
        try {
            (new ReflectionMethod(BackupDatabase::class, 'writeAll'))->invoke(new BackupDatabase, $handle, $bytes);
            $this->assertSame($bytes, BackupFailureStream::$data);
            $this->assertGreaterThan(1, BackupFailureStream::$writes);
        } finally {
            fclose($handle);
            stream_wrapper_unregister('monstopiaqawrite');
        }
    }

    public function test_sql_writer_rejects_false_write_instead_of_accepting_truncated_sql(): void
    {
        $this->assertSqlWriteFailure('false');
    }

    private function assertSqlWriteFailure(string $failure): void
    {
        stream_wrapper_register('monstopiaqawrite', BackupFailureStream::class);
        BackupFailureStream::$failure = $failure;
        BackupFailureStream::$writes = 0;
        $handle = fopen('monstopiaqawrite://test', 'wb');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('เขียนไฟล์ SQL ไม่ครบ');
            (new ReflectionMethod(BackupDatabase::class, 'writeAll'))->invoke(new BackupDatabase, $handle, str_repeat('SQL;', 100));
        } finally {
            fclose($handle);
            stream_wrapper_unregister('monstopiaqawrite');
        }
    }

    public function test_metadata_write_refuses_false_result_and_never_publishes_complete_manifest(): void
    {
        $destination = $this->testRoot.'/manifest.json';
        File::partialMock()->shouldReceive('put')->once()->andReturn(false);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('บันทึก JSON');
            (new ReflectionMethod(BackupBundleService::class, 'writeJson'))->invoke(app(BackupBundleService::class), $destination, ['status' => 'complete']);
        } finally {
            $this->assertFileDoesNotExist($destination);
        }
    }

    public function test_metadata_write_refuses_short_result_and_never_publishes_successful_verification(): void
    {
        $destination = $this->testRoot.'/verification.json';
        File::partialMock()->shouldReceive('put')->once()->andReturn(3);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('บันทึก JSON');
            (new ReflectionMethod(BackupBundleService::class, 'writeJson'))->invoke(app(BackupBundleService::class), $destination, ['files_verified' => 1]);
        } finally {
            $this->assertFileDoesNotExist($destination);
        }
    }

    public function test_metadata_writer_does_not_overwrite_an_existing_manifest(): void
    {
        $destination = $this->testRoot.'/manifest.json';
        File::put($destination, 'old backup metadata');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('ไม่อนุญาตให้เขียนทับ');
            (new ReflectionMethod(BackupBundleService::class, 'writeJson'))->invoke(app(BackupBundleService::class), $destination, ['status' => 'complete']);
        } finally {
            $this->assertSame('old backup metadata', File::get($destination));
        }
    }

    private function bundle(): array
    {
        $directory = $this->testRoot.'/fixture_'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($directory.'/files');
        File::put($directory.'/files/1.bin', "\x00\xFFMONSTOPIA real binary\x00\x7F");
        File::put($directory.'/database.sql.gz', gzencode('CREATE TABLE example (id int);'));
        $manifest = [
            'version' => 1,
            'status' => 'complete',
            'database' => [
                'backup_path' => 'database.sql.gz',
                'size_bytes' => filesize($directory.'/database.sql.gz'),
                'sha256' => hash_file('sha256', $directory.'/database.sql.gz'),
                'tables' => [],
            ],
            'files' => [[
                'attachment_id' => 1,
                'disk' => 'local',
                'stored_path' => 'project-files/7/work.pdf',
                'backup_path' => 'files/1.bin',
                'size_bytes' => filesize($directory.'/files/1.bin'),
                'sha256' => hash_file('sha256', $directory.'/files/1.bin'),
            ]],
        ];
        File::put($directory.'/manifest.json', json_encode($manifest));

        return [$directory, $manifest];
    }
}

// Test-only stream simulates disk exhaustion without filling a real disk.
class BackupFailureStream
{
    public $context;

    public static string $failure = 'zero';

    public static int $writes = 0;

    public static string $data = '';

    public function stream_open(string $path, string $mode, int $options, &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $bytes): int|false
    {
        if (self::$writes++ === 0 || self::$failure === 'partial') {
            $length = min(7, strlen($bytes));
            self::$data .= substr($bytes, 0, $length);

            return $length;
        }

        return self::$failure === 'false' ? false : 0;
    }

    public function stream_close(): void {}
}

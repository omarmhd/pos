<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class BackupCreate extends Command
{
    protected $signature   = 'backup:create {--notify : Send email notification on completion}';
    protected $description = 'Create a compressed database backup';

    public function handle(): int
    {
        $this->info('بدء النسخ الاحتياطي...');
        $startedAt = now();

        $backupDir = 'backups';
        $timestamp = $startedAt->format('Y-m-d_H-i-s');
        $sqlFile   = storage_path("app/{$backupDir}/db_{$timestamp}.sql");
        $zipFile   = storage_path("app/{$backupDir}/backup_{$timestamp}.zip");

        Storage::disk('local')->makeDirectory($backupDir);

        try {
            $this->dumpDatabase($sqlFile);
            $this->info('تم تصدير قاعدة البيانات');

            $zip = new \ZipArchive();
            $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFile($sqlFile, 'database.sql');
            $zip->addFromString('meta.json', json_encode([
                'app'       => config('app.name'),
                'date'      => $startedAt->toDateTimeString(),
                'db'        => config('database.connections.mysql.database'),
            ], JSON_PRETTY_PRINT));
            $zip->close();

            @unlink($sqlFile);

            $sizeMb = round(filesize($zipFile) / 1024 / 1024, 2);
            $this->info("تم ضغط النسخة ({$sizeMb} MB)");

            $this->cleanOld($backupDir, 14);
            $this->info('تم حذف النسخ القديمة');

            if ($this->option('notify')) {
                $this->notify(true, basename($zipFile), $sizeMb, $startedAt);
            }

            $this->info('اكتمل النسخ في ' . $startedAt->diffInSeconds(now()) . ' ثانية');
            return self::SUCCESS;

        } catch (\Throwable $e) {
            @unlink($sqlFile);
            $this->error('فشل النسخ الاحتياطي: ' . $e->getMessage());
            if ($this->option('notify')) {
                $this->notify(false, '', 0, $startedAt, $e->getMessage());
            }
            return self::FAILURE;
        }
    }

    private function dumpDatabase(string $out): void
    {
        $db      = config('database.connections.mysql');
        // يسمح بضبط مسار mysqldump الكامل على الاستضافات التي لا تضعه في PATH
        $binary  = env('MYSQLDUMP_PATH', 'mysqldump');
        $errFile = $out . '.err';

        // --no-tablespaces : يتجنّب اشتراط صلاحية PROCESS على الاستضافة المشتركة (cPanel)
        // --skip-lock-tables : لا يتطلّب صلاحية LOCK TABLES
        // --single-transaction : لقطة متّسقة لجداول InnoDB دون قفل (أداء + اتساق محاسبي)
        // stderr يُفصل في ملف منفصل حتى يظهر نص الخطأ الحقيقي بدل ابتلاعه
        $cmd = sprintf(
            '%s --no-tablespaces --single-transaction --skip-lock-tables '
            . '--default-character-set=utf8mb4 --host=%s --port=%s --user=%s %s > %s 2> %s',
            escapeshellcmd($binary),
            escapeshellarg((string) $db['host']),
            escapeshellarg((string) $db['port']),
            escapeshellarg((string) $db['username']),
            escapeshellarg((string) $db['database']),
            escapeshellarg($out),
            escapeshellarg($errFile)
        );

        // تمرير كلمة المرور عبر MYSQL_PWD: يمنع تسريبها في قائمة العمليات ويتفادى مشاكل الـ escaping
        putenv('MYSQL_PWD=' . (string) ($db['password'] ?? ''));
        exec($cmd, $ignored, $code);
        putenv('MYSQL_PWD'); // إلغاء المتغيّر بعد الانتهاء

        $err = is_file($errFile) ? trim((string) file_get_contents($errFile)) : '';
        @unlink($errFile);

        if ($code !== 0 || !file_exists($out) || filesize($out) < 100) {
            throw new \RuntimeException(
                'mysqldump failed (exit ' . $code . '): '
                . ($err !== '' ? $err : 'لا يوجد ناتج خطأ — تأكّد من توفّر mysqldump في PATH أو اضبط MYSQLDUMP_PATH')
            );
        }
    }

    private function cleanOld(string $dir, int $keep): void
    {
        $files = Storage::disk('local')->files($dir);
        sort($files);
        foreach (array_slice($files, 0, max(0, count($files) - $keep)) as $f) {
            Storage::disk('local')->delete($f);
        }
    }

    private function notify(bool $ok, string $file, float $mb, $at, string $err = ''): void
    {
        $to = Setting::get('admin_email', config('mail.from.address'));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        try {
            Mail::raw(
                $ok
                    ? "تم النسخ الاحتياطي بنجاح\nالملف: {$file}\nالحجم: {$mb} MB\nالوقت: {$at}"
                    : "فشل النسخ الاحتياطي\nالخطأ: {$err}\nالوقت: {$at}",
                fn($m) => $m->to($to)->subject(($ok ? '[نجاح]' : '[فشل]') . ' نسخة احتياطية — ' . config('app.name'))
            );
        } catch (\Throwable) {}
    }
}

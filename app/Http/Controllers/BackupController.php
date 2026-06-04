<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage');
    }

    public function index()
    {
        $files = collect(Storage::disk('local')->files('backups'))
            ->map(function ($path) {
                $name = basename($path);
                $size = Storage::disk('local')->size($path);
                $time = Storage::disk('local')->lastModified($path);
                return (object) [
                    'name'       => $name,
                    'path'       => $path,
                    'size_mb'    => round($size / 1024 / 1024, 2),
                    'modified'   => \Carbon\Carbon::createFromTimestamp($time),
                ];
            })
            ->sortByDesc('modified')
            ->values();

        $logPath   = storage_path('logs/backup.log');
        $lastLines = file_exists($logPath)
            ? array_slice(file($logPath, FILE_IGNORE_NEW_LINES), -30)
            : [];

        return view('settings.backup.index', compact('files', 'lastLines'));
    }

    public function run(Request $request)
    {
        $notify = $request->boolean('notify', false);
        $args   = $notify ? ['--notify' => true] : [];

        try {
            $exitCode = Artisan::call('backup:create', $args);
            $output   = Artisan::output();

            if ($exitCode === 0) {
                return back()->with('success', 'تم إنشاء النسخة الاحتياطية بنجاح');
            }
            return back()->with('error', 'فشل إنشاء النسخة الاحتياطية: ' . $output);

        } catch (\Throwable $e) {
            return back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function download(string $filename)
    {
        $path = 'backups/' . basename($filename); // basename prevents path traversal
        abort_unless(Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path, $filename);
    }

    public function destroy(string $filename)
    {
        $path = 'backups/' . basename($filename);
        Storage::disk('local')->delete($path);
        return back()->with('success', 'تم حذف النسخة الاحتياطية');
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallPdfFonts extends Command
{
    protected $signature   = 'pdf:install-fonts';
    protected $description = 'Download Cairo Arabic font for PDF generation';

    public function handle(): int
    {
        $dir = storage_path('fonts');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fonts = [
            'Cairo-Regular.ttf' => 'https://fonts.gstatic.com/s/cairo/v28/SLXVc1nY6HkvangtZmpMWumxSdot.ttf',
            'Cairo-Bold.ttf'    => 'https://fonts.gstatic.com/s/cairo/v28/SLXVc1nY6HkvangtZmpMWumxSdot.ttf',
        ];

        // Use Google Fonts API to resolve actual font file URLs
        $this->info('Fetching Cairo font info from Google Fonts API...');

        $ctx = stream_context_create([
            'http' => [
                'timeout'    => 15,
                'user_agent' => 'Mozilla/5.0 (compatible; PDF font installer)',
            ],
        ]);

        $css = @file_get_contents(
            'https://fonts.googleapis.com/css2?family=Cairo:wght@400;700',
            false,
            $ctx
        );

        if ($css) {
            // Extract TTF (truetype) URLs from the CSS
            preg_match_all('/src:\s*url\(([^)]+\.ttf)\)/i', $css, $matches);
            if (! empty($matches[1])) {
                $urls = array_unique($matches[1]);
                $fonts = [];
                foreach ($urls as $idx => $url) {
                    $weight = ($idx === 0) ? 'Regular' : 'Bold';
                    $fonts["Cairo-{$weight}.ttf"] = $url;
                }
            } else {
                // Fallback: try woff2 approach – re-fetch with User-Agent that returns TTF
                $this->warn('Could not extract TTF URLs from Google Fonts CSS. Trying fallback...');
            }
        }

        $allOk = true;
        foreach ($fonts as $filename => $url) {
            $dest = $dir . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($dest)) {
                $this->line("  <comment>Already exists:</comment> {$filename}");
                continue;
            }
            $this->info("  Downloading {$filename}...");
            $data = @file_get_contents($url, false, $ctx);
            if ($data === false || strlen($data) < 1000) {
                $this->error("  Failed to download {$filename}. Check internet connection.");
                $allOk = false;
                continue;
            }
            file_put_contents($dest, $data);
            $this->info("  <info>Saved:</info> {$dest}");
        }

        if ($allOk) {
            $this->info('');
            $this->info('Arabic fonts installed. PDF reports will now render Arabic correctly.');
        } else {
            $this->warn('Some fonts could not be downloaded. Arabic may not render correctly.');
            $this->warn('Manually copy Cairo-Regular.ttf and Cairo-Bold.ttf to ' . $dir);
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BenchmarkTextFileGeneration extends Command
{
    protected $signature = 'benchmark:text-file {lines=10000} {--type=all : buffered or streamed}';
    protected $description = 'Benchmark text file generation performance: Buffered vs Streamed';

    public function handle()
    {
        $lines = (int) $this->argument('lines');
        $type = $this->option('type');
        $this->info("Benchmarking with {$lines} lines...");

        // Ensure directories exist
        Storage::disk('local')->makeDirectory('benchmark');
        $localPath = storage_path('app/private/benchmark');
        if (!file_exists($localPath)) {
            mkdir($localPath, 0755, true);
        }

        if ($type === 'all' || $type === 'buffered') {
            // --- Benchmark 1: Buffered Write (Current "Network" approach) ---
            $this->info("\n--- Benchmark 1: Buffered Write (Current Implementation) ---");
            
            // Force garbage collection before starting
            gc_collect_cycles();
            $startMem = memory_get_usage(true);
            $startTime = microtime(true);
            
            // Use 1KB limit to force disk usage, simulating a large file that doesn't fit in RAM
            // This isolates the memory usage of the *reading* part (into $content)
            $stream = fopen('php://temp/maxmemory:1024', 'w+');
            for ($i = 0; $i < $lines; $i++) {
                fwrite($stream, $this->generateLine($i));
            }
            
            // This simulates the critical part: reading entire stream to memory before writing
            rewind($stream);
            $content = stream_get_contents($stream);
            
            $destFile = $localPath . '/buffered_test.txt';
            file_put_contents($destFile, $content);
            
            fclose($stream);
            
            $endTime = microtime(true);
            $endMem = memory_get_peak_usage(true);
            
            $this->reportMetrics($startTime, $endTime, $startMem, $endMem, filesize($destFile));
            
            // Cleanup
            unset($content);
            unset($stream);
            gc_collect_cycles();
        }

        if ($type === 'all' || $type === 'streamed') {
            // --- Benchmark 2: Streamed Write (Local Storage approach) ---
            $this->info("\n--- Benchmark 2: Streamed Write (Local Storage) ---");
            
            gc_collect_cycles();
            $startMem = memory_get_usage(true);
            $startTime = microtime(true);
            
            $stream = fopen('php://temp/maxmemory:1024', 'w+');
            for ($i = 0; $i < $lines; $i++) {
                fwrite($stream, $this->generateLine($i));
            }
            
            rewind($stream);
            // Using Storage facade's writeStream which uses stream_copy_to_stream internally usually
            Storage::disk('local')->put('benchmark/streamed_test.txt', $stream);
            
            fclose($stream);
            
            $endTime = microtime(true);
            $endMem = memory_get_peak_usage(true);
            
            $destFile = Storage::disk('local')->path('benchmark/streamed_test.txt');
            $this->reportMetrics($startTime, $endTime, $startMem, $endMem, filesize($destFile));
        }
    }

    private function generateLine($i)
    {
        // Simulate a line from GenerateTextFile.php (approx 200-300 bytes)
        return implode(',', [
            'SALES', 'OCASHSALES', $i, 'G/L Account', 'CUS' . str_pad($i, 5, '0', STR_PAD_LEFT),
            now()->format('d/m/Y'), 'Invoice', 'BBCI' . $i, 'Customer Name ' . Str::random(20),
            'PHP', '1000.00', '1000.00', ' ', '1000.00', '1000.00', '1', '3',
            '03.01.2.02.2', 'SALESJNL', '1000.00', '-1000.00', now()->format('d/m/Y'),
            'CASH SALES', 'Bank Account', 'BANK' . ($i % 5), '1000.00', '-1000.00'
        ]) . "\n";
    }

    private function reportMetrics($start, $end, $memStart, $memEnd, $fileSize)
    {
        $duration = $end - $start;
        $memUsage = $memEnd - $memStart;
        
        $this->line("Time Taken: " . number_format($duration, 4) . " seconds");
        $this->line("Peak Memory Usage: " . number_format($memEnd / 1024 / 1024, 2) . " MB");
        $this->line("Memory Overhead: " . number_format($memUsage / 1024 / 1024, 2) . " MB");
        $this->line("File Size: " . number_format($fileSize / 1024 / 1024, 2) . " MB");
    }
}

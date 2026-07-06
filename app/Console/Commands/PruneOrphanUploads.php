<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\Application;
use App\Models\DocAttachment;
use App\Models\Tool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneOrphanUploads extends Command
{
    protected $signature = 'storage:prune-orphans {--delete : actually delete (default is dry-run report only)}';

    protected $description = 'Find (and optionally delete) files on the public disk not referenced by any DB record.';

    public function handle(): int
    {
        $disk = Storage::disk('public');

        $referenced = $this->collectReferencedPaths($disk);

        $orphans = [];
        foreach ($disk->allFiles() as $file) {
            $normalized = ltrim($file, '/');
            if ($normalized === '.gitignore') {
                continue;
            }
            if (isset($referenced[$normalized])) {
                continue;
            }
            $orphans[] = $normalized;
        }

        if (empty($orphans)) {
            $this->info('No orphan files found on the public disk.');
            return self::SUCCESS;
        }

        $totalSize = 0;
        $this->line('Orphan files on public disk:');
        foreach ($orphans as $i => $path) {
            $size = 0;
            try {
                $size = $disk->size($path);
            } catch (Throwable $e) {
                $size = 0;
            }
            $totalSize += $size;
            $this->line(sprintf('%3d. %s (%s)', $i + 1, $path, $this->humanSize($size)));
        }

        $this->newLine();
        $this->info(sprintf('Total: %d file(s), %s', count($orphans), $this->humanSize($totalSize)));

        if (! $this->option('delete')) {
            $this->newLine();
            $this->comment('Run with --delete to remove these.');
            return self::SUCCESS;
        }

        $removed = 0;
        foreach ($orphans as $path) {
            try {
                if ($disk->delete($path)) {
                    $removed++;
                }
            } catch (Throwable $e) {
                $this->warn('Failed to delete: ' . $path . ' (' . $e->getMessage() . ')');
            }
        }

        $this->newLine();
        $this->info(sprintf('Removed %d of %d orphan file(s).', $removed, count($orphans)));

        return self::SUCCESS;
    }

    /**
     * @return array<string,bool> keyed by normalized relative path
     */
    private function collectReferencedPaths($disk): array
    {
        $referenced = [];

        $add = function (?string $path) use (&$referenced) {
            if ($path === null) {
                return;
            }
            $path = ltrim(trim($path), '/');
            if ($path === '') {
                return;
            }
            $referenced[$path] = true;
        };

        foreach (Application::all() as $app) {
            $add($app->link);
            $add($app->pdf_installation_instructions);
            $add($app->pdf_user_manual);

            // icon: full storage path (new uploads) or bare filename in images/icons.
            if (! empty($app->icon)) {
                $icon = ltrim((string) $app->icon, '/');
                foreach ([$icon, 'images/icons/' . $icon] as $candidate) {
                    if ($disk->exists($candidate)) {
                        $referenced[$candidate] = true;
                    }
                }
            }
        }

        foreach (Tool::all() as $tool) {
            // icon: full storage path (new uploads) or bare filename in tool_icons.
            if (! empty($tool->icon)) {
                $icon = ltrim((string) $tool->icon, '/');
                foreach ([$icon, 'images/icons/tool_icons/' . $icon] as $candidate) {
                    if ($disk->exists($candidate)) {
                        $referenced[$candidate] = true;
                    }
                }
            }
        }

        foreach (DocAttachment::all() as $attachment) {
            $add($attachment->file_path);
        }

        return $referenced;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        return ($i === 0)
            ? sprintf('%d %s', (int) $value, $units[$i])
            : sprintf('%.2f %s', $value, $units[$i]);
    }
}

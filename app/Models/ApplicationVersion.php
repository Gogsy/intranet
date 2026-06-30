<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ApplicationVersion extends Model
{
    use \App\Concerns\LogsModelActivity;

    protected $fillable = [
        'application_id', 'version_number', 'file_path', 'source',
        'is_active', 'size', 'original_url', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'size'      => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** The stored APK's full filename (shown as the version's name). */
    public function getFileNameAttribute(): ?string
    {
        return $this->file_path ? basename($this->file_path) : null;
    }

    /** Public URL to the stored APK. */
    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('public')->exists($this->file_path)
            ? Storage::url($this->file_path)
            : asset('storage/' . ltrim($this->file_path, '/'));
    }

    /** Human-readable size, e.g. "42.1 MB". */
    public function getSizeForHumansAttribute(): string
    {
        $bytes = (float) $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return $i === 0 ? sprintf('%d %s', $bytes, $units[$i]) : sprintf('%.1f %s', $bytes, $units[$i]);
    }

    /** Make this the single active version for its application. */
    public function activate(): void
    {
        static::where('application_id', $this->application_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->forceFill(['is_active' => true])->save();
    }
}

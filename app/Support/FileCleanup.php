<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class FileCleanup
{
    /**
     * Delete a public-disk file only if it is non-empty, exists on the public disk,
     * and is not referenced by any OTHER row of the given model/column.
     *
     * @param string $value     The stored file path/value.
     * @param string $model     Fully-qualified Eloquent model class.
     * @param string $column    The column referencing the file.
     * @param mixed  $currentId The id of the record being deleted (excluded from the ref check).
     */
    public static function deleteIfUnreferenced(?string $value, string $model, string $column, $currentId): void
    {
        if (empty($value)) {
            return;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($value)) {
            return;
        }

        // If any OTHER row references the same value, keep the file.
        $referenced = $model::where($column, $value)
            ->where('id', '!=', $currentId)
            ->exists();

        if ($referenced) {
            return;
        }

        $disk->delete($value);
    }

    /**
     * Delete an UPLOADED icon stored as a bare filename in the DB but living in a
     * sub-directory on the public disk. Skips committed/predefined icons and any
     * icon still referenced by another row.
     *
     * @param string        $filename     Bare filename stored in the column (e.g. "logo.png").
     * @param string        $model        Fully-qualified Eloquent model class.
     * @param string        $subdir       Public-disk sub-directory the upload lives in (e.g. "images/icons").
     * @param array<string> $committedDirs public_path-relative dirs holding committed icons to protect.
     * @param mixed         $currentId    Id of the record being deleted.
     */
    public static function deleteUploadedIconIfUnreferenced(?string $filename, string $model, string $subdir, array $committedDirs, $currentId): void
    {
        if (empty($filename)) {
            return;
        }

        // Never delete predefined/committed icons.
        if (self::isCommittedAsset($filename, $committedDirs)) {
            return;
        }

        $path = trim($subdir, '/') . '/' . $filename;
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return;
        }

        // Keep if another row references the same filename.
        $referenced = $model::where('icon', $filename)
            ->where('id', '!=', $currentId)
            ->exists();

        if ($referenced) {
            return;
        }

        $disk->delete($path);
    }

    /**
     * True if a committed asset exists at any of the given public_path-relative locations.
     * Used to guard against deleting predefined/committed icons.
     *
     * @param string         $value          The bare value/filename.
     * @param array<string>  $relativeDirs   public_path-relative directories to check (e.g. 'images/icons').
     */
    public static function isCommittedAsset(?string $value, array $relativeDirs): bool
    {
        if (empty($value)) {
            return false;
        }

        foreach ($relativeDirs as $dir) {
            $path = public_path(trim($dir, '/') . '/' . $value);
            if (is_file($path)) {
                return true;
            }
        }

        return false;
    }
}

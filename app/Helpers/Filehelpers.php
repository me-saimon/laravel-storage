<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileHelper
{
    /**
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $folder
     * @param string|null $customFileName
     * @param string|null $disk
     * @param array $options
     * @return array ['path' => ..., 'filename' => ...]
     */
    public static function upload($file, $folder = 'uploads', $customFileName = null, $disk = null, array $options = [])
    {
        if (! $file) {
            return null;
        }

        $folder = self::normalizePath($folder);
        $filename = self::makeFileName($file, $customFileName, $options);
        $storage = self::storage($disk);

        if (! empty($options['old_file'])) {
            self::delete($options['old_file'], $disk);
        }

        if (empty($options['overwrite'])) {
            $filename = self::availableFileName($folder, $filename, $disk);
        }

        $putOptions = [];
        if (! empty($options['visibility'])) {
            $putOptions['visibility'] = $options['visibility'];
        }

        $path = $storage->putFileAs($folder, $file, $filename, $putOptions);

        return [
            'path' => $path,
            'filename' => $filename,
            'extension' => strtolower($file->getClientOriginalExtension()),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'disk' => $disk,
            'url' => self::url($path, $disk),
        ];
    }

    /**
     * Upload using a custom file name.
     */
    public static function uploadAs($file, $folder, $fileName, $disk = null, array $options = [])
    {
        return self::upload($file, $folder, $fileName, $disk, $options);
    }

    /**
     * Delete the old file first, then upload the new one.
     */
    public static function replace($file, $oldFilePath = null, $folder = 'uploads', $customFileName = null, $disk = null, array $options = [])
    {
        $options['old_file'] = $oldFilePath;

        return self::upload($file, $folder, $customFileName, $disk, $options);
    }

    /**
     * Delete a file from Laravel storage.
     */
    public static function delete($path, $disk = null)
    {
        if (! $path) {
            return false;
        }

        $path = self::normalizePath($path);
        $storage = self::storage($disk);

        return $storage->exists($path) ? $storage->delete($path) : false;
    }

    /**
     * Check if a file exists in Laravel storage.
     */
    public static function exists($path, $disk = null)
    {
        if (! $path) {
            return false;
        }

        return self::storage($disk)->exists(self::normalizePath($path));
    }

    /**
     * Get a public URL for a storage file when the disk supports it.
     */
    public static function url($path, $disk = null)
    {
        if (! $path) {
            return null;
        }

        try {
            return self::storage($disk)->url(self::normalizePath($path));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Move an uploaded file directly into the public directory.
     */
    public static function uploadToPublic($file, $folder = 'uploads', $customFileName = null, $oldFilePath = null, array $options = [])
    {
        if (! $file) {
            return null;
        }

        if ($oldFilePath) {
            self::deletePublic($oldFilePath);
        }

        $folder = self::normalizePath($folder);
        $filename = self::makeFileName($file, $customFileName, $options);
        $targetDirectory = public_path($folder);

        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        if (empty($options['overwrite'])) {
            $filename = self::availablePublicFileName($folder, $filename);
        }

        $file->move($targetDirectory, $filename);
        $path = self::normalizePath($folder . '/' . $filename);

        return [
            'path' => $path,
            'filename' => $filename,
            'extension' => strtolower($file->getClientOriginalExtension()),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'url' => asset($path),
        ];
    }

    /**
     * Delete a file from the public directory.
     */
    public static function deletePublic($path)
    {
        if (! $path) {
            return false;
        }

        $fullPath = public_path(self::normalizePath($path));

        if (is_file($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Check if a file exists in the public directory.
     */
    public static function existsPublic($path)
    {
        if (! $path) {
            return false;
        }

        return is_file(public_path(self::normalizePath($path)));
    }

    /**
     * Build a clean file name with the uploaded file extension.
     */
    public static function makeFileName($file, $customFileName = null, array $options = [])
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $name = $customFileName ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $givenExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($givenExtension) {
            $name = pathinfo($name, PATHINFO_FILENAME);
            $extension = $givenExtension;
        }

        $name = Str::slug($name);
        $name = $name ?: 'file';

        if (array_key_exists('unique', $options) && ! $options['unique']) {
            return $name . '.' . $extension;
        }

        return $name . '-' . time() . '-' . Str::random(8) . '.' . $extension;
    }

    protected static function availableFileName($folder, $filename, $disk = null)
    {
        $storage = self::storage($disk);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $candidate = $filename;
        $counter = 1;

        while ($storage->exists(self::normalizePath($folder . '/' . $candidate))) {
            $candidate = $name . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $candidate;
    }

    protected static function availablePublicFileName($folder, $filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $candidate = $filename;
        $counter = 1;

        while (is_file(public_path(self::normalizePath($folder . '/' . $candidate)))) {
            $candidate = $name . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $candidate;
    }

    protected static function storage($disk = null)
    {
        return $disk ? Storage::disk($disk) : Storage::getFacadeRoot();
    }

    protected static function normalizePath($path)
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = str_replace('\\', '/', $path);
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}

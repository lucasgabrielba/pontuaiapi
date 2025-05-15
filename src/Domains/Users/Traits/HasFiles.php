<?php

namespace Domains\Users\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasFiles
{
    protected string $disk = 's3';

    public function storeFile(UploadedFile $file, string $folder): string
    {
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::ulid() . '.' . $extension;
        $path = $file->storeAs("users/{$this->id}/{$folder}", $fileName, $this->disk);
        
        return $path;
    }

    public function deleteFile(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    public function getFileUrl(string $path): ?string
    {
        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->url($path);
        }
        
        return null;
    }

    public function fileExists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }
}
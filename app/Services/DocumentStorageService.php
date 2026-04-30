<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentStorageService
{
    public function exists(string $path): bool
    {
        return Storage::exists($path);
    }

    public function delete(string $path): void
    {
        Storage::delete($path);
    }

    public function download(string $path): BinaryFileResponse
    {
        return Storage::download($path);
    }

    public function path(string $path): string
    {
        return Storage::path($path);
    }
}

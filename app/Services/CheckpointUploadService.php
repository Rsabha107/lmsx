<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CheckpointUploadService
{
    public function storePhoto(UploadedFile $file, string|int $checkpointId, string|int $jobId): string
    {
        $extension = $file->extension() ?: 'jpg';

        $timestamp = now()->format('YmdHis');
        $filename = "checkpoint_{$checkpointId}_photo_{$timestamp}.{$extension}";

        // Store in job-specific directory
        $directory = "jobs/{$jobId}/photos";
        Storage::disk('local')->putFileAs($directory, $file, $filename);

        return $directory . '/' . $filename;
    }

    public function storeSignature(?string $signatureData, string|int $checkpointId, string|int $jobId): ?string
    {
        if (empty($signatureData)) {
            return null;
        }

        if (preg_match('/^data:image\/(\w+);base64,/', $signatureData, $type)) {
            $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
            $extension = strtolower($type[1]);
        } else {
            $extension = 'png';
        }

        $decoded = base64_decode($signatureData);

        if ($decoded === false || empty($decoded)) {
            return null;
        }

        $timestamp = now()->format('YmdHis');
        $filename = "checkpoint_{$checkpointId}_signature_{$timestamp}.{$extension}";

        // Store in job-specific directory
        $directory = "jobs/{$jobId}/signatures";
        $path = $directory . '/' . $filename;

        Storage::disk('local')->put($path, $decoded);

        return $path;
    }
}

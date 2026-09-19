<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CheckpointUploadService
{
    /** Roughly 4MB of decoded image; base64 inflates by ~33%. */
    private const MAX_BASE64_BYTES = 6 * 1024 * 1024;

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function storePhoto(UploadedFile $file, string|int $checkpointId, string|int $jobId): string
    {
        $extension = strtolower($file->extension() ?: 'jpg');

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Unsupported image type.');
        }

        $timestamp = now()->format('YmdHis');
        $filename = "checkpoint_{$checkpointId}_photo_{$timestamp}.{$extension}";

        // Store in job-specific directory
        $directory = "jobs/{$jobId}/photos";
        Storage::disk('local')->putFileAs($directory, $file, $filename);

        return $directory . '/' . $filename;
    }

    public function storeSignature(?string $signatureData, string|int $checkpointId, string|int $jobId): ?string
    {
        return $this->storeBase64($signatureData, 'signature', 'signatures', $checkpointId, $jobId);
    }

    /**
     * The legacy web endpoint posts evidence as data URLs rather than multipart
     * uploads, so both photos and signatures can arrive base64 encoded.
     */
    public function storeBase64(
        ?string $data,
        string $type,
        string $directorySegment,
        string|int $checkpointId,
        string|int $jobId
    ): ?string {
        if (empty($data)) {
            return null;
        }

        if (strlen($data) > self::MAX_BASE64_BYTES) {
            throw new RuntimeException('Evidence image is too large.');
        }

        if (preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
            $data = substr($data, strpos($data, ',') + 1);
        }

        $decoded = base64_decode($data, true);

        if ($decoded === false || $decoded === '') {
            return null;
        }

        // The declared data-URL type is attacker-controlled, so the extension is
        // taken from the decoded bytes instead. This also rejects non-images.
        $extension = $this->extensionFromContents($decoded);

        if ($extension === null) {
            throw new RuntimeException('Evidence must be a valid image.');
        }

        $timestamp = now()->format('YmdHis');
        $filename = "checkpoint_{$checkpointId}_{$type}_{$timestamp}.{$extension}";

        // Store in job-specific directory
        $directory = "jobs/{$jobId}/{$directorySegment}";
        $path = $directory . '/' . $filename;

        Storage::disk('local')->put($path, $decoded);

        return $path;
    }

    private function extensionFromContents(string $contents): ?string
    {
        $info = @getimagesizefromstring($contents);

        if ($info === false) {
            return null;
        }

        return match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF => 'gif',
            default => null,
        };
    }
}

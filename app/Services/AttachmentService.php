<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AttachmentService
{
    public function __construct(
        private AttachmentCollectionRegistry $registry,
        private AttachmentPathGenerator $pathGenerator,
    ) {}

    /**
     * Guarda el archivo en storage y crea el registro de metadatos.
     * Si falla el guardado físico no se crea el registro.
     * Si falla el registro se elimina el archivo guardado.
     */
    public function store(
        Model $attachable,
        UploadedFile $file,
        string $collection,
        ?User $user,
        array $data = []
    ): Attachment {
        $morphKey = $this->morphKey($attachable);
        $this->registry->assertValid($morphKey, $collection);

        $detectedMime = $this->detectMime($file);
        $this->assertMimeAllowed($morphKey, $collection, $detectedMime);
        $this->assertSizeAllowed($morphKey, $collection, $file->getSize());
        $this->assertCountAllowed($attachable, $morphKey, $collection);

        $extension = $this->extensionFromMime($detectedMime);
        $path = $this->pathGenerator->generate($attachable, $collection, $extension);
        $disk = config('attachments.disk', 'attachments');

        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        try {
            $attachment = DB::connection('tenant')->transaction(function () use (
                $attachable, $collection, $disk, $path, $file, $detectedMime,
                $extension, $user, $data
            ) {
                return $attachable->attachments()->create([
                    'collection' => $collection,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name' => $this->pathGenerator->storedName($path),
                    'mime_type' => $detectedMime,
                    'extension' => $extension,
                    'size' => $file->getSize(),
                    'checksum' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by_user_id' => $user?->id,
                    'notes' => $data['notes'] ?? null,
                    'metadata' => $data['metadata'] ?? null,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        return $attachment;
    }

    public function list(
        Model $attachable,
        ?string $collection = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = $attachable->attachments()->with('uploadedBy')->latest();

        if ($collection !== null) {
            $query->where('collection', $collection);
        }

        return $query->paginate($perPage);
    }

    /** Actualiza solo notes y metadata. El archivo físico nunca se modifica aquí. */
    public function update(Attachment $attachment, array $data): Attachment
    {
        $attachment->update(array_filter([
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $attachment->notes,
            'metadata' => array_key_exists('metadata', $data) ? $data['metadata'] : $attachment->metadata,
        ], fn ($v) => true));

        return $attachment->fresh();
    }

    /** Devuelve un stream del archivo para descarga autorizada. */
    public function download(Attachment $attachment): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404, 'El archivo no existe en storage.');
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }

    /**
     * Elimina el archivo físico y hace soft delete del registro.
     * Si el archivo no existe en disco se loguea y se continúa con el soft delete.
     * Si la eliminación física falla por otro motivo, no se hace soft delete.
     */
    public function delete(Attachment $attachment): void
    {
        $disk = Storage::disk($attachment->disk);

        try {
            $disk->delete($attachment->path);
        } catch (\League\Flysystem\UnableToDeleteFile $e) {
            Log::warning('AttachmentService: archivo ya inexistente al borrar.', [
                'attachment_id' => $attachment->id,
                'path' => $attachment->path,
            ]);
        }

        $attachment->delete();
    }

    // ─── privados ────────────────────────────────────────────────────────────

    private function detectMime(UploadedFile $file): string
    {
        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file->getRealPath());

        if ($mime === false) {
            throw new RuntimeException('No se pudo detectar el tipo MIME del archivo.');
        }

        return $mime;
    }

    private function assertMimeAllowed(string $morphKey, string $collection, string $mime): void
    {
        $allowed = $this->registry->allowedMimes($morphKey, $collection);

        if (! in_array($mime, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Tipo de archivo '{$mime}' no permitido en la colección '{$collection}'."
            );
        }
    }

    private function assertSizeAllowed(string $morphKey, string $collection, int $size): void
    {
        $max = $this->registry->maxSize($morphKey, $collection);

        if ($size > $max) {
            $maxMb = round($max / 1024 / 1024, 1);
            throw new \InvalidArgumentException("El archivo supera el tamaño máximo de {$maxMb} MB.");
        }
    }

    private function assertCountAllowed(Model $attachable, string $morphKey, string $collection): void
    {
        $max = $this->registry->maxCount($morphKey, $collection);
        $current = $attachable->attachments()->where('collection', $collection)->count();

        if ($current >= $max) {
            throw new \InvalidArgumentException(
                "Se ha alcanzado el límite de {$max} imágenes para esta colección."
            );
        }
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    private function morphKey(Model $model): string
    {
        $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        $class = get_class($model);

        return array_search($class, $morphMap, true) ?: class_basename($class);
    }
}

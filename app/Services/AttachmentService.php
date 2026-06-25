<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

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
    public function download(Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404, 'El archivo no existe en storage.');
        }

        $response = $disk->download($attachment->path, $attachment->original_name);
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    /**
     * Devuelve un thumbnail JPEG inline del adjunto.
     * Soporta imágenes (GD), PDFs e Office (Imagick + GhostScript/LibreOffice).
     * El resultado se genera una sola vez y se cachea en disco bajo thumbs/.
     */
    public function thumbnail(Attachment $attachment, int $maxDim = 300): StreamedResponse
    {
        if (! $this->mimeSupportsThumb($attachment->mime_type)) {
            abort(415, 'El adjunto no tiene vista previa disponible.');
        }

        $disk = Storage::disk($attachment->disk);
        $thumbPath = 'thumbs/' . $attachment->path;

        if (! $disk->exists($thumbPath)) {
            if (str_starts_with($attachment->mime_type, 'image/')) {
                $this->generateThumbnail($disk, $attachment->path, $thumbPath, $maxDim);
            } else {
                $this->generateDocumentThumbnail($disk, $attachment, $thumbPath, $maxDim);
            }
        }

        return response()->stream(function () use ($disk, $thumbPath) {
            echo $disk->get($thumbPath);
        }, 200, [
            'Content-Type'        => 'image/jpeg',
            'Cache-Control'       => 'private, max-age=86400',
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * Elimina el archivo físico (y su thumbnail si existe) y hace soft delete del registro.
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

        $thumbPath = 'thumbs/' . $attachment->path;
        if ($disk->exists($thumbPath)) {
            $disk->delete($thumbPath);
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
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => 'bin',
        };
    }

    private function generateThumbnail(Filesystem $disk, string $srcPath, string $thumbPath, int $maxDim): void
    {
        $content = $disk->get($srcPath);
        $src = @imagecreatefromstring($content);

        if ($src === false) {
            abort(422, 'No se pudo procesar la imagen para generar el thumbnail.');
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        $ratio = min($maxDim / $origW, $maxDim / $origH);

        if ($ratio >= 1.0) {
            // La imagen original ya es más pequeña que el thumbnail deseado
            imagedestroy($src);
            $disk->put($thumbPath, $content);

            return;
        }

        $thumbW = (int) round($origW * $ratio);
        $thumbH = (int) round($origH * $ratio);

        $thumb = imagecreatetruecolor($thumbW, $thumbH);

        // Fondo blanco para imágenes con transparencia (PNG)
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefilledrectangle($thumb, 0, 0, $thumbW - 1, $thumbH - 1, $white);

        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);

        ob_start();
        imagejpeg($thumb, null, 82);
        $data = ob_get_clean();

        imagedestroy($src);
        imagedestroy($thumb);

        $disk->put($thumbPath, $data);
    }

    private function mimeSupportsThumb(string $mime): bool
    {
        return str_starts_with($mime, 'image/')
            || in_array($mime, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ], true);
    }

    /**
     * Genera un thumbnail JPEG para un PDF o documento Office.
     * PDF → primera página via Imagick.
     * Office → LibreOffice headless → PDF → primera página via Imagick.
     * Requiere la extensión Imagick y GhostScript instalados en el servidor.
     */
    private function generateDocumentThumbnail(
        Filesystem $disk,
        Attachment $attachment,
        string $thumbPath,
        int $maxDim
    ): void {
        if (! extension_loaded('imagick')) {
            abort(415, 'El servidor no tiene soporte para vistas previas de documentos (requiere Imagick).');
        }

        // Usar la extensión derivada del MIME, no la almacenada en BD (puede ser 'bin' en archivos antiguos)
        $ext = $this->extensionFromMime($attachment->mime_type);
        $tmpOriginal = sys_get_temp_dir() . '/att_' . uniqid('', true) . '.' . $ext;
        file_put_contents($tmpOriginal, $disk->get($attachment->path));

        $tmpPdf = null;

        try {
            if ($attachment->mime_type === 'application/pdf') {
                $pdfPath = $tmpOriginal;
            } else {
                $pdfPath = $tmpPdf = $this->convertOfficeToPdf($tmpOriginal);
            }

            $jpegData = $this->pdfFirstPageToJpeg($pdfPath, $maxDim);
            $disk->put($thumbPath, $jpegData);
        } finally {
            @unlink($tmpOriginal);
            if ($tmpPdf !== null && file_exists($tmpPdf)) {
                @unlink($tmpPdf);
            }
        }
    }

    private function pdfFirstPageToJpeg(string $pdfPath, int $maxDim): string
    {
        $imagick = new \Imagick();
        $imagick->setResolution(150, 150);
        $imagick->readImage($pdfPath . '[0]');
        $imagick->setImageBackgroundColor(new \ImagickPixel('white'));
        $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $imagick->setImageFormat('jpeg');
        $imagick->thumbnailImage($maxDim, $maxDim, true);

        $jpeg = $imagick->getImageBlob();
        $imagick->clear();
        $imagick->destroy();

        return $jpeg;
    }

    private function convertOfficeToPdf(string $filePath): string
    {
        $bin = $this->findLibreOfficeBin();

        if ($bin === null) {
            abort(415, 'El servidor no tiene LibreOffice para convertir documentos Office a PDF.');
        }

        $outDir = sys_get_temp_dir();
        $process = new Process([
            $bin,
            '--headless',
            '--norestore',
            '--nofirststartwizard',
            '--convert-to', 'pdf',
            '--outdir', $outDir,
            $filePath,
        ]);
        $process->setTimeout(60);
        // HOME=/tmp garantiza que LibreOffice pueda escribir su perfil de usuario.
        // Sin esto falla en Docker porque www-data no tiene un HOME escribible.
        $process->setEnv(['HOME' => '/tmp', 'DCONF_PROFILE' => '/dev/null']);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::error('AttachmentService: LibreOffice conversion failed.', [
                'file' => $filePath,
                'exit_code' => $process->getExitCode(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ]);
            abort(422, 'No se pudo convertir el documento Office a PDF.');
        }

        $pdfPath = $outDir . DIRECTORY_SEPARATOR . pathinfo($filePath, PATHINFO_FILENAME) . '.pdf';

        if (! file_exists($pdfPath)) {
            Log::error('AttachmentService: LibreOffice produced no output PDF.', [
                'expected_path' => $pdfPath,
                'stdout' => $process->getOutput(),
            ]);
            abort(422, 'La conversión a PDF no generó el archivo esperado.');
        }

        return $pdfPath;
    }

    private function findLibreOfficeBin(): ?string
    {
        $candidates = [
            '/usr/bin/libreoffice',
            '/usr/local/bin/libreoffice',
            '/usr/bin/soffice',
            '/usr/local/bin/soffice',
        ];

        foreach ($candidates as $bin) {
            if (is_executable($bin)) {
                return $bin;
            }
        }

        foreach (['libreoffice', 'soffice'] as $name) {
            try {
                $process = new Process(['which', $name]);
                $process->run();
                if ($process->isSuccessful()) {
                    $path = trim($process->getOutput());
                    if ($path !== '' && is_executable($path)) {
                        return $path;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function morphKey(Model $model): string
    {
        $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        $class = get_class($model);

        return array_search($class, $morphMap, true) ?: class_basename($class);
    }
}

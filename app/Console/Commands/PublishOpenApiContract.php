<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Genera el contrato OpenAPI destinado al frontend (config/scribe_public.php, que excluye
 * superadmin/impersonación/observabilidad interna) y lo publica en public/openapi/frontend.yaml,
 * la URL estática que consume el frontend Next.js. Ver docs/api-contract.md.
 */
class PublishOpenApiContract extends Command
{
    protected $signature = 'contract:publish';

    protected $description = 'Genera el contrato OpenAPI frontend y lo publica en public/openapi/frontend.yaml';

    public function handle(): int
    {
        if (! class_exists(\Knuckles\Scribe\Config\Defaults::class)) {
            $this->error('Scribe no está instalado (require-dev). Ejecuta `composer install` con las dependencias de desarrollo.');

            return self::FAILURE;
        }

        $this->info('Generando spec Scribe (config/scribe_public.php)...');

        // Se ejecuta scribe:generate como proceso separado (no Artisan::call en el mismo
        // proceso): Scribe hace exit(2) directamente cuando alguna ruta individual falla su
        // ResponseCall, lo que mataría este comando entero si se invocara in-process.
        $process = new Process(
            [PHP_BINARY, 'artisan', 'scribe:generate', '--config=scribe_public', '--force'],
            base_path(),
            null,
            null,
            300
        );
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        // exit code 2 = Scribe generó el spec pero alguna ruta individual falló su
        // ResponseCall (ver vendor/knuckleswtf/scribe GenerateDocumentation::handle()).
        // El archivo igualmente se escribe; lo tratamos como advertencia, no como fallo duro.
        if (! in_array($process->getExitCode(), [0, 2], true)) {
            $this->error('scribe:generate falló (código '.$process->getExitCode().').');

            return self::FAILURE;
        }

        if ($process->getExitCode() === 2) {
            $this->warn('scribe:generate reportó errores en alguna(s) ruta(s) individual(es); revisa el log anterior.');
        }

        $generated = base_path('storage/app/scribe-public/openapi.yaml');

        if (! File::exists($generated)) {
            $this->error("No se encontró el spec generado en {$generated}.");

            return self::FAILURE;
        }

        $publicDir = public_path('openapi');
        File::ensureDirectoryExists($publicDir);

        $target = $publicDir.'/frontend.yaml';
        File::copy($generated, $target);

        $meta = $this->buildMeta($target);
        File::put($publicDir.'/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info("Contrato publicado en {$target}");
        $this->line('Metadatos: '.$publicDir.'/meta.json');
        $this->table(array_keys($meta), [array_values($meta)]);

        return self::SUCCESS;
    }

    private function buildMeta(string $file): array
    {
        return [
            'generated_at' => now('UTC')->toIso8601String(),
            'git_commit' => $this->gitCommit(),
            'contract_sha256' => hash('sha256', File::get($file)),
            'source' => 'config/scribe_public.php',
        ];
    }

    private function gitCommit(): ?string
    {
        if (! class_exists(Process::class)) {
            return null;
        }

        $process = new Process(['git', 'rev-parse', '--short', 'HEAD'], base_path());
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\OpenApi\OpenApiContractDiffer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Regenera el contrato OpenAPI frontend en una ruta temporal y lo compara estructuralmente
 * contra el que está commiteado en public/openapi/frontend.yaml (OpenApiContractDiffer).
 *
 * Dos usos:
 *  - Local: recordar al desarrollador que el contrato commiteado no refleja el código actual.
 *  - CI: bloquear el merge si hay cambios "breaking" no reconocidos explícitamente
 *    (--allow-breaking) o si el contrato commiteado quedó desactualizado (--fail-on-any).
 */
class CheckOpenApiContract extends Command
{
    protected $signature = 'contract:check
        {--allow-breaking : No fallar aunque se detecten cambios breaking (para migraciones deliberadas)}
        {--fail-on-any : Fallar también si hay cambios compatibles/informativos sin commitear (usar en CI)}';

    protected $description = 'Compara el contrato OpenAPI commiteado contra el generado a partir del código actual';

    public function handle(OpenApiContractDiffer $differ): int
    {
        $committed = public_path('openapi/frontend.yaml');

        if (! File::exists($committed)) {
            $this->error("No existe {$committed}. Ejecuta primero: php artisan contract:publish");

            return self::FAILURE;
        }

        $this->info('Regenerando contrato frontend para comparar...');

        // Proceso separado: ver comentario equivalente en PublishOpenApiContract (Scribe hace
        // exit(2) directamente cuando falla una ruta individual, matando el proceso actual
        // si se invocara con Artisan::call in-process).
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

        if (! in_array($process->getExitCode(), [0, 2], true)) {
            $this->error('scribe:generate falló al regenerar para la comparación (código '.$process->getExitCode().').');

            return self::FAILURE;
        }

        $fresh = base_path('storage/app/scribe-public/openapi.yaml');

        if (! File::exists($fresh)) {
            $this->error("No se encontró el spec recién generado en {$fresh}.");

            return self::FAILURE;
        }

        $old = Yaml::parseFile($committed);
        $new = Yaml::parseFile($fresh);

        $changes = $differ->diff($old, $new);

        if (empty($changes)) {
            $this->info('Sin cambios de contrato. public/openapi/frontend.yaml está actualizado.');

            return self::SUCCESS;
        }

        $breaking = array_filter($changes, fn ($c) => $c['severity'] === OpenApiContractDiffer::BREAKING);
        $compatible = array_filter($changes, fn ($c) => $c['severity'] === OpenApiContractDiffer::COMPATIBLE);
        $info = array_filter($changes, fn ($c) => $c['severity'] === OpenApiContractDiffer::INFO);

        $this->reportGroup('BREAKING', $breaking);
        $this->reportGroup('COMPATIBLE', $compatible);
        $this->reportGroup('INFO', $info);

        $this->newLine();
        $this->line('El contrato ha cambiado. Ejecuta `php artisan contract:publish` (o `composer contract:update`) y commitea public/openapi/frontend.yaml y public/openapi/meta.json.');

        if (! empty($breaking) && ! $this->option('allow-breaking')) {
            $this->error(sprintf('%d cambio(s) BREAKING sin reconocer. Usa --allow-breaking si es una migración deliberada (documéntala en el evolution log / handoff).', count($breaking)));

            return self::FAILURE;
        }

        if ($this->option('fail-on-any')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function reportGroup(string $label, array $changes): void
    {
        if (empty($changes)) {
            return;
        }

        $this->newLine();
        $this->line("<comment>{$label}</comment> (".count($changes).')');

        foreach ($changes as $change) {
            $this->line(sprintf('  [%s %s] %s', $change['method'], $change['path'], $change['detail']));
        }
    }
}

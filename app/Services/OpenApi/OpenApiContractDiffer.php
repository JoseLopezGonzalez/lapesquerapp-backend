<?php

namespace App\Services\OpenApi;

/**
 * Comparación estructural (no textual) de dos specs OpenAPI: compara la forma del contrato
 * (endpoints, parámetros requeridos, nombres/tipos de propiedades top-level de request y
 * response) sin depender de valores de ejemplo, IDs o timestamps generados en cada ejecución.
 *
 * Deliberadamente NO es un diff semántico completo de JSON Schema (no baja a más de un nivel
 * de anidación en objetos, no compara enums ni formatos). El objetivo es detectar con bajo
 * ruido los cambios que realmente rompen a un cliente generado, no perseguir cada detalle.
 */
class OpenApiContractDiffer
{
    public const BREAKING = 'breaking';

    public const COMPATIBLE = 'compatible';

    public const INFO = 'info';

    /**
     * @return array<int, array{severity: string, type: string, path: string, method: string, detail: string}>
     */
    public function diff(array $old, array $new): array
    {
        $changes = [];

        $oldEndpoints = $this->extractEndpoints($old);
        $newEndpoints = $this->extractEndpoints($new);

        foreach ($oldEndpoints as $key => $oldEndpoint) {
            [$method, $path] = explode(' ', $key, 2);

            if (! isset($newEndpoints[$key])) {
                $changes[] = $this->change(self::BREAKING, 'endpoint_removed', $path, $method, 'El endpoint ya no existe en el contrato nuevo.');

                continue;
            }

            $newEndpoint = $newEndpoints[$key];

            $changes = array_merge(
                $changes,
                $this->diffRequestFields($path, $method, $oldEndpoint['requestFields'], $newEndpoint['requestFields']),
                $this->diffResponseFields($path, $method, $oldEndpoint['responseFields'], $newEndpoint['responseFields'])
            );
        }

        foreach ($newEndpoints as $key => $newEndpoint) {
            if (! isset($oldEndpoints[$key])) {
                [$method, $path] = explode(' ', $key, 2);
                $changes[] = $this->change(self::INFO, 'endpoint_added', $path, $method, 'Endpoint nuevo, no presente en el contrato anterior.');
            }
        }

        return $changes;
    }

    private function diffRequestFields(string $path, string $method, array $old, array $new): array
    {
        $changes = [];

        foreach ($old as $field => $meta) {
            if (! array_key_exists($field, $new)) {
                $changes[] = $this->change(self::COMPATIBLE, 'request_field_removed', $path, $method, "Campo de request `{$field}` ya no existe (relajación, no rompe clientes existentes).");

                continue;
            }

            if ($meta['required'] && ! $new[$field]['required']) {
                $changes[] = $this->change(self::COMPATIBLE, 'request_field_now_optional', $path, $method, "Campo `{$field}` deja de ser obligatorio.");
            }

            if (! $meta['required'] && $new[$field]['required']) {
                $changes[] = $this->change(self::BREAKING, 'request_field_now_required', $path, $method, "Campo `{$field}` pasa a ser obligatorio: clientes existentes que no lo envíen fallarán.");
            }

            if ($meta['type'] !== null && $new[$field]['type'] !== null && $meta['type'] !== $new[$field]['type']) {
                $changes[] = $this->change(self::BREAKING, 'request_field_type_changed', $path, $method, "Campo `{$field}` cambia de tipo: {$meta['type']} → {$new[$field]['type']}.");
            }
        }

        foreach ($new as $field => $meta) {
            if (! array_key_exists($field, $old)) {
                $severity = $meta['required'] ? self::BREAKING : self::COMPATIBLE;
                $type = $meta['required'] ? 'request_field_added_required' : 'request_field_added_optional';
                $changes[] = $this->change($severity, $type, $path, $method, "Campo de request nuevo `{$field}`".($meta['required'] ? ' (obligatorio)' : ' (opcional)').'.');
            }
        }

        return $changes;
    }

    private function diffResponseFields(string $path, string $method, array $old, array $new): array
    {
        $changes = [];

        foreach ($old as $field => $meta) {
            if (! array_key_exists($field, $new)) {
                $changes[] = $this->change(self::BREAKING, 'response_field_removed', $path, $method, "Campo de respuesta `{$field}` ya no existe: un cliente que lo lea recibirá `undefined`.");

                continue;
            }

            if ($meta['type'] !== null && $new[$field]['type'] !== null && $meta['type'] !== $new[$field]['type']) {
                $changes[] = $this->change(self::BREAKING, 'response_field_type_changed', $path, $method, "Campo de respuesta `{$field}` cambia de tipo: {$meta['type']} → {$new[$field]['type']}.");
            }
        }

        foreach ($new as $field => $meta) {
            if (! array_key_exists($field, $old)) {
                $changes[] = $this->change(self::COMPATIBLE, 'response_field_added', $path, $method, "Campo de respuesta nuevo `{$field}` (los clientes existentes lo ignorarán).");
            }
        }

        return $changes;
    }

    private function change(string $severity, string $type, string $path, string $method, string $detail): array
    {
        return compact('severity', 'type', 'path', 'method', 'detail');
    }

    /**
     * @return array<string, array{requestFields: array, responseFields: array}>
     */
    private function extractEndpoints(array $spec): array
    {
        $endpoints = [];

        foreach ($spec['paths'] ?? [] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! is_array($operation) || ! in_array(strtolower($method), ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $key = strtoupper($method).' '.$path;

                $endpoints[$key] = [
                    'requestFields' => $this->extractSchemaFields($operation['requestBody']['content']['application/json']['schema'] ?? null),
                    'responseFields' => $this->extractResponseFields($operation['responses'] ?? []),
                ];
            }
        }

        return $endpoints;
    }

    private function extractResponseFields(array $responses): array
    {
        foreach ($responses as $status => $response) {
            if ((int) $status >= 200 && (int) $status < 300) {
                $schema = $response['content']['application/json']['schema'] ?? null;

                // Colecciones paginadas / envueltas en `data`: comparamos la forma del item, no el sobre.
                if (isset($schema['properties']['data']['items'])) {
                    return $this->extractSchemaFields($schema['properties']['data']['items']);
                }

                if (isset($schema['properties']['data']) && is_array($schema['properties']['data'])) {
                    return $this->extractSchemaFields($schema['properties']['data']);
                }

                return $this->extractSchemaFields($schema);
            }
        }

        return [];
    }

    /**
     * @return array<string, array{type: ?string, required: bool}>
     */
    private function extractSchemaFields(?array $schema): array
    {
        if (! $schema || ! isset($schema['properties']) || ! is_array($schema['properties'])) {
            return [];
        }

        $required = $schema['required'] ?? [];
        $fields = [];

        foreach ($schema['properties'] as $name => $definition) {
            $fields[$name] = [
                'type' => is_array($definition) ? ($definition['type'] ?? null) : null,
                'required' => in_array($name, $required, true),
            ];
        }

        return $fields;
    }
}

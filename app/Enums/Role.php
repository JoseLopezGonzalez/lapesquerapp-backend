<?php

namespace App\Enums;

enum Role: string
{
    case Tecnico = 'tecnico';
    case Administrador = 'administrador';
    case Direccion = 'direccion';
    case Administracion = 'administracion';
    case Comercial = 'comercial';
    case Operario = 'operario';

    /**
     * Valores string para validación y almacenamiento.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Etiquetas para UI (selects, listados).
     */
    public function label(): string
    {
        return match ($this) {
            self::Tecnico => 'Técnico',
            self::Administrador => 'Administrador',
            self::Direccion => 'Dirección',
            self::Administracion => 'Administración',
            self::Comercial => 'Comercial',
            self::Operario => 'Operario',
        };
    }

    /**
     * Mapeo de nombres de roles antiguos (BD) al enum actual.
     * Usado en migración de datos.
     */
    public static function fromLegacyName(string $legacyName): ?self
    {
        return match (strtolower($legacyName)) {
            'superuser' => self::Tecnico,
            'manager' => self::Administrador,
            'admin' => self::Administracion,
            'store_operator' => self::Operario,
            default => null,
        };
    }

    /**
     * Opciones para endpoint options (id + name para compatibilidad con selects).
     */
    public static function optionsForApi(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[] = [
                'id' => $case->value,
                'name' => $case->label(),
            ];
        }
        return $options;
    }
}

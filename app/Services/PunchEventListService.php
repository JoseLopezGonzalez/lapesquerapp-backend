<?php

namespace App\Services;

use App\Models\PunchEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Servicio de listado y filtros para eventos de fichaje.
 */
class PunchEventListService
{
    /**
     * Aplicar filtros a la consulta de fichajes.
     * Soporta estructura anidada de filtros (filters.filters) como otros controladores genéricos.
     *
     * @param Builder<PunchEvent> $query
     * @param array<string, mixed> $filters
     * @return Builder<PunchEvent>
     */
    public function applyFiltersToQuery(Builder $query, array $filters): Builder
    {
        if (isset($filters['filters'])) {
            $filters = $filters['filters'];
        }

        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        if (isset($filters['ids']) && is_array($filters['ids']) && !empty($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['employee_ids']) && is_array($filters['employee_ids']) && !empty($filters['employee_ids'])) {
            $query->whereIn('employee_id', $filters['employee_ids']);
        } elseif (isset($filters['employees']) && is_array($filters['employees']) && !empty($filters['employees'])) {
            $query->whereIn('employee_id', $filters['employees']);
        }

        if (isset($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (isset($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }

        if (isset($filters['devices']) && is_array($filters['devices']) && !empty($filters['devices'])) {
            $query->whereIn('device_id', $filters['devices']);
        }

        if (isset($filters['dates'])) {
            $dates = $filters['dates'];
            if (isset($dates['start'])) {
                try {
                    $query->where('timestamp', '>=', Carbon::parse($dates['start'])->startOfDay());
                } catch (\Exception $e) {
                }
            }
            if (isset($dates['end'])) {
                try {
                    $query->where('timestamp', '<=', Carbon::parse($dates['end'])->endOfDay());
                } catch (\Exception $e) {
                }
            }
        }

        if (isset($filters['date_start'])) {
            try {
                $query->where('timestamp', '>=', Carbon::parse($filters['date_start'])->startOfDay());
            } catch (\Exception $e) {
            }
        }

        if (isset($filters['date_end'])) {
            try {
                $query->where('timestamp', '<=', Carbon::parse($filters['date_end'])->endOfDay());
            } catch (\Exception $e) {
            }
        }

        if (isset($filters['timestamp_start'])) {
            try {
                $query->where('timestamp', '>=', Carbon::parse($filters['timestamp_start']));
            } catch (\Exception $e) {
            }
        }

        if (isset($filters['timestamp_end'])) {
            try {
                $query->where('timestamp', '<=', Carbon::parse($filters['timestamp_end']));
            } catch (\Exception $e) {
            }
        }

        if (isset($filters['date'])) {
            try {
                $date = Carbon::parse($filters['date']);
                $query->whereDate('timestamp', $date->toDateString());
            } catch (\Exception $e) {
            }
        }

        return $query;
    }
}

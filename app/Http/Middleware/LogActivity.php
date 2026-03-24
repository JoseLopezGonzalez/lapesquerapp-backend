<?php

namespace App\Http\Middleware;

use App\Jobs\StoreActivityLogJob;
use App\Models\ExternalUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;
use Stevebauman\Location\Facades\Location;

class LogActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            // Verificar si el usuario está autenticado antes de registrar la actividad
            if (! auth()->check()) {
                return $response;
            }

            $ip = $request->ip();

            // GeoIP con cache de 24h por IP: evita la llamada HTTP externa en requests repetidos
            $location = Cache::remember("geoip:{$ip}", 86400, function () use ($ip) {
                try {
                    $loc = Location::get($ip);
                    return is_object($loc) ? $loc : null;
                } catch (\Exception $e) {
                    Log::error('Error obteniendo la ubicación: '.$e->getMessage());
                    return null;
                }
            });

            // Analizar el User-Agent
            $agent = new Agent;
            $userAgentHeader = $request->header('User-Agent');
            if ($userAgentHeader) {
                $agent->setUserAgent($userAgentHeader);
            }

            $actor = auth()->user();

            $data = [
                'user_id'    => $actor instanceof ExternalUser ? null : auth()->id(),
                'action'     => $request->method().' '.$request->path(),
                'ip_address' => $ip,
                'country'    => $location?->countryName ?? 'Desconocido',
                'city'       => $location?->cityName ?? 'Desconocido',
                'region'     => $location?->regionName ?? 'Desconocido',
                'platform'   => $agent->platform() ?? 'Desconocido',
                'browser'    => $agent->browser() ?? 'Desconocido',
                'device'     => $actor instanceof ExternalUser
                    ? trim(($agent->device() ?? 'Desconocido').' [external_user]')
                    : ($agent->device() ?? 'Desconocido'),
                'path'       => $request->path(),
                'method'     => $request->method(),
                'location'   => "{$location?->countryName}, {$location?->cityName}",
            ];

            // La escritura a la BD se hace de forma asíncrona (o síncrona si QUEUE_CONNECTION=sync)
            $tenantDatabase = config('database.connections.tenant.database', '');
            if ($tenantDatabase) {
                dispatch(new StoreActivityLogJob($tenantDatabase, $data));
            }
        } catch (\Exception $e) {
            Log::error('Error en el middleware LogActivity: '.$e->getMessage());
        }

        return $response;
    }
}

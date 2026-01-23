<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function index()
    {
        $settings = DB::connection('tenant')->table('settings')->pluck('value', 'key');
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $data = $request->all();

        // Manejo especial para company.mail.password:
        // Si no viene en el request y hay configuración previa de email,
        // mantener la contraseña actual (no actualizarla)
        if (!isset($data['company.mail.password'])) {
            // Verificar si se están actualizando otros campos de email
            $isUpdatingEmailSettings = false;
            foreach (array_keys($data) as $key) {
                if (str_starts_with($key, 'company.mail.')) {
                    $isUpdatingEmailSettings = true;
                    break;
                }
            }

            // Si se están actualizando campos de email, verificar si hay configuración previa
            if ($isUpdatingEmailSettings) {
                $hasExistingEmailConfig = DB::connection('tenant')
                    ->table('settings')
                    ->whereIn('key', [
                        'company.mail.host',
                        'company.mail.username',
                        'company.mail.from_address'
                    ])
                    ->whereNotNull('value')
                    ->where('value', '!=', '')
                    ->exists();

                // Si hay configuración previa, no actualizar el password (mantener el actual)
                // Esto permite al frontend omitir el campo cuando no se quiere cambiar la contraseña
                if ($hasExistingEmailConfig) {
                    // El campo password no se incluirá en la actualización,
                    // por lo que se mantendrá el valor actual en la base de datos
                }
            }
        }

        // Actualizar solo los campos que vienen en el request
        // Si company.mail.password no viene, no se actualizará (se mantendrá el valor actual)
        foreach ($data as $key => $value) {
            DB::connection('tenant')->table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Settings updated']);
    }
}

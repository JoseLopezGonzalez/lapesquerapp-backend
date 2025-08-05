<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Debugging Excel::download() - Investigación profunda...\n";

try {
    // Simular el request como lo haría el frontend
    $request = new \Illuminate\Http\Request();
    $request->headers->set('X-Tenant', 'brisamar');
    
    // Simular el middleware de tenant
    $tenant = \App\Models\Tenant::where('subdomain', 'brisamar')->where('active', true)->first();
    
    if (!$tenant) {
        echo "Error: Tenant no encontrado\n";
        exit(1);
    }
    
    echo "Tenant encontrado: {$tenant->subdomain} -> {$tenant->database}\n";
    
    // Configurar la conexión del tenant
    config(['database.connections.tenant.database' => $tenant->database]);
    \Illuminate\Support\Facades\DB::purge('tenant');
    \Illuminate\Support\Facades\DB::reconnect('tenant');
    
    echo "Conexión configurada\n";
    
    // Crear el export
    $export = new \App\Exports\v2\BoxesReportExport($request, 3);
    
    echo "Export creado exitosamente\n";
    
    $collection = $export->collection();
    echo "Cajas en collection: " . $collection->count() . "\n";
    
    if ($collection->count() > 0) {
        $firstBox = $collection->first();
        echo "Primera caja ID: " . $firstBox->id . "\n";
        
        // Probar Excel::download paso a paso
        echo "\nProbando Excel::download() con investigación profunda...\n";
        
        // Verificar archivos temporales antes
        echo "Archivos temporales antes:\n";
        $tempFiles = glob('/tmp/*.xlsx');
        foreach ($tempFiles as $file) {
            echo "- " . basename($file) . " (" . filesize($file) . " bytes)\n";
        }
        
        // Intentar Excel::download con try-catch detallado
        try {
            echo "\nEjecutando Excel::download()...\n";
            $response = \Maatwebsite\Excel\Facades\Excel::download($export, 'debug_test.xlsx');
            
            echo "Response creada: " . get_class($response) . "\n";
            
            // Si es BinaryFileResponse, investigar el archivo
            if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
                $file = $response->getFile();
                echo "Archivo de respuesta: " . $file->getPathname() . "\n";
                echo "Archivo existe: " . ($file->isFile() ? 'Sí' : 'No') . "\n";
                echo "Archivo es legible: " . ($file->isReadable() ? 'Sí' : 'No') . "\n";
                echo "Tamaño del archivo: " . $file->getSize() . " bytes\n";
                
                // Verificar archivos temporales después
                echo "\nArchivos temporales después:\n";
                $tempFiles = glob('/tmp/*.xlsx');
                foreach ($tempFiles as $file) {
                    echo "- " . basename($file) . " (" . filesize($file) . " bytes)\n";
                }
                
                // Intentar leer el archivo directamente
                if ($file->isFile() && $file->isReadable()) {
                    $content = file_get_contents($file->getPathname());
                    echo "Contenido leído directamente: " . strlen($content) . " bytes\n";
                    
                    if (strlen($content) > 0) {
                        file_put_contents('debug_direct.xlsx', $content);
                        echo "Contenido guardado en debug_direct.xlsx\n";
                    }
                }
            }
            
            // Intentar obtener el contenido de la respuesta
            echo "\nObteniendo contenido de la respuesta...\n";
            $content = $response->getContent();
            echo "Tamaño del contenido: " . strlen($content) . " bytes\n";
            
            if (strlen($content) > 0) {
                file_put_contents('debug_response.xlsx', $content);
                echo "Contenido guardado en debug_response.xlsx\n";
            } else {
                echo "ERROR: El contenido de la respuesta está vacío\n";
            }
            
        } catch (Exception $e) {
            echo "Error en Excel::download(): " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
        
    } else {
        echo "No hay datos en la collection\n";
    }
    
} catch (Exception $e) {
    echo "Error general: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 
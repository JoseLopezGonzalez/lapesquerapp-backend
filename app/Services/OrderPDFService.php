<?php

namespace App\Services;

use App\Models\Order;
use Beganovich\Snappdf\Snappdf;


/**
 * Service especializado en generación de PDFs relacionados con Orders.
 * 
 * NOTA: Si se requiere para otras entidades, considerar crear un PDFService general.
 */
class OrderPDFService
{
    /**
     * Generar un documento PDF y devolver su ruta.
     *
     * @param Order $order
     * @param string $docType
     * @param string $viewName
     * @return string
     */
    public function generateDocument(Order $order, string $docType, string $viewPath): string
    {
        $formattedId = str_replace('#', '', $order->formattedId);
        $pdfPath = storage_path("app/public/{$docType}-{$formattedId}.pdf");

        /* // Si ya existe no lo regeneramos (opcional) */
        // ⏱️ Si existe, verificar si ha sido generado hace menos de 30 segundos
        if (file_exists($pdfPath)) {
            $lastModified = filemtime($pdfPath);
            $now = time();
            $ageInSeconds = $now - $lastModified;

            if ($ageInSeconds < 30) {
                return $pdfPath; // ✅ Reutilizar si es reciente
            }

            // 🗑️ Eliminar si está obsoleto
            unlink($pdfPath);
        }


        // ⚠️ Pasar la variable como 'entity', no como 'order'
        $html = view($viewPath, ['entity' => $order])->render();

        // Crear PDF con Snappdf
        $snappdf = new Snappdf();
        
        // Configure Chromium using centralized configuration
        $this->configureChromium($snappdf);

        // Generar contenido
        $pdfContent = $snappdf->setHtml($html)->generate();

        // Guardar el archivo
        file_put_contents($pdfPath, $pdfContent);

        return $pdfPath;
    }

    /**
     * Configure Chromium/Chrome path and arguments for PDF generation.
     *
     * @param Snappdf $snappdf
     * @param array $additionalArguments Additional arguments to add (optional)
     * @return void
     */
    private function configureChromium(Snappdf $snappdf, array $additionalArguments = []): void
    {
        // Set Chromium path from configuration
        $chromiumPath = config('pdf.chromium.path', '/usr/bin/google-chrome');
        $snappdf->setChromiumPath($chromiumPath);

        // Apply margins from configuration
        $margins = config('pdf.chromium.margins', []);
        if (isset($margins['top'])) {
            $snappdf->addChromiumArguments('--margin-top=' . $margins['top']);
        }
        if (isset($margins['right'])) {
            $snappdf->addChromiumArguments('--margin-right=' . $margins['right']);
        }
        if (isset($margins['bottom'])) {
            $snappdf->addChromiumArguments('--margin-bottom=' . $margins['bottom']);
        }
        if (isset($margins['left'])) {
            $snappdf->addChromiumArguments('--margin-left=' . $margins['left']);
        }

        // Apply default arguments from configuration
        $defaultArguments = config('pdf.chromium.arguments', []);
        foreach ($defaultArguments as $argument) {
            $snappdf->addChromiumArguments($argument);
        }

        // Apply additional arguments if provided
        foreach ($additionalArguments as $argument) {
            $snappdf->addChromiumArguments($argument);
        }
    }
}

<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\ExtractPdfRequest;
use App\Services\PdfExtractionService;
use Illuminate\Http\JsonResponse;
use Throwable;

class PdfExtractionController extends Controller
{
    public function __construct(
        private PdfExtractionService $extractionService
    ) {}

    /**
     * Extrae texto de un PDF y lo devuelve estructurado (Compras, buyer, company, purchases, etc.).
     */
    public function extract(ExtractPdfRequest $request): JsonResponse
    {
        try {
            $data = $this->extractionService->extractAndProcess($request->file('pdf'));
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No se pudo procesar el archivo PDF.',
                'userMessage' => 'El archivo PDF no pudo ser leído. Verifique que sea un PDF válido.',
            ], 422);
        }

        return response()->json([
            'message' => 'PDF procesado correctamente.',
            'data' => $data,
        ]);
    }
}

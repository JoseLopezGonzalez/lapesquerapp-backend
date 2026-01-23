<?php

namespace App\Services;

use App\Models\Order;
use App\Services\TenantMailConfigService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderMailerService
{

    protected $pdfService;
    protected $mailConfigService;

    public function __construct(OrderPDFService $pdfService, TenantMailConfigService $mailConfigService)
    {
        $this->pdfService = $pdfService;
        $this->mailConfigService = $mailConfigService;
    }

    /**
     * Envío personalizado de documentos.
     */
    public function sendDocuments(Order $order, array $documents): void
    {
        foreach ($documents as $document) {
            $this->sendDocument($order, $document['type'], $document['recipients']);
        }
    }

    /**
     * Envío estándar de documentos (según config).
     */
    public function sendStandardDocuments(Order $order): void
    {
        $recipientsConfig = config('order_documents.standard_recipients');

        foreach ($recipientsConfig as $recipientKey => $docTypes) {

            // ✅ Obtenemos email desde las entidades dinámicamente
            [$mainEmails, $ccEmails] = $this->getEmailsFromEntities($order, $recipientKey);
            if (empty($mainEmails))
                continue; // Saltamos si no hay email

            $subject = "Documentación del Pedido #{$order->id}";

            $documentsToAttach = [];
            foreach ($docTypes as $docType) {
                // Generar el PDF
                $viewPath = config("order_documents.documents.{$docType}.view_path");
                $pdfPath = $this->pdfService->generateDocument($order, $docType, $viewPath);

                // Verificar existencia
                if (!file_exists($pdfPath)) {
                    Log::error("No se encuentra el documento: {$pdfPath}");
                    continue;
                }

                $documentName = ucfirst($docType) . "-pedido_#{$order->id}.pdf";

                $documentsToAttach[] = [
                    'path' => $pdfPath,
                    'name' => $documentName
                ];
            }




            // Saltamos si no hay documentos
            if (empty($documentsToAttach))
                continue;

            // Definir Markdown según destinatario
            $markdownTemplates = [
                'customer' => 'emails.orders.shipped',
                'transport' => 'emails.orders.transport_details',
                'salesperson' => 'emails.orders.commercial',
            ];
            $markdownTemplate = $markdownTemplates[$recipientKey] ?? 'emails.orders.shipped';

            // Configurar mailer del tenant antes de enviar
            $this->mailConfigService->configureTenantMailer();

            // Crear y enviar
            $mailable = new \App\Mail\StandardOrderDocuments(
                $order,
                $subject,
                $markdownTemplate,
                $documentsToAttach
            );

            Mail::to((array) $mainEmails)
                ->cc((array) $ccEmails)
                ->bcc(tenantSetting('company.bcc_email'))
                ->send($mailable);
        }
    }

    /**
     * Lógica interna para envío personalizado.
     */
    private function sendDocument(Order $order, string $docType, array $recipients): void
    {
        $config = config('order_documents');

        $documentConfig = $config['documents'][$docType] ?? null;
        if (!$documentConfig) {
            Log::error("No se encuentra configuración para el documento: {$docType}");
            return;
        }

        $subject = str_replace('{order_id}', $order->id, $documentConfig['subject_template']);
        $bodyTemplate = $documentConfig['body_template'];

        $documentName = $documentConfig['document_name'];
        //$documentName = ucfirst(str_replace('_', ' ', $docType));

        $viewPath = $documentConfig['view_path'];

        // ✅ Generar el PDF
        $pdfPath = $this->pdfService->generateDocument($order, $docType, $viewPath);

        // ✅ Comprobar existencia
        if (!file_exists($pdfPath)) {
            Log::error("No se encuentra el documento generado: {$pdfPath}");
            return;
        }

        foreach ($recipients as $recipientKey) {
            [$mainEmails, $ccEmails] = $this->getEmailsFromEntities($order, $recipientKey);
            if (empty($mainEmails)) {
                Log::warning("No se encontraron emails para el destinatario: {$recipientKey}");
                continue;
            }

            // Configurar mailer del tenant antes de enviar
            $this->mailConfigService->configureTenantMailer();

            // ✅ Crear mailable con adjunto
            $mailable = new \App\Mail\GenericOrderDocument(
                $order,
                $bodyTemplate,
                $subject,
                $documentName,
                $pdfPath // ✅ Adjuntamos el PDF
            );

            // ✅ Enviar email
            Mail::to((array) $mainEmails)
                ->cc((array) $ccEmails)
                ->bcc(tenantSetting('company.bcc_email'))
                ->send($mailable);
        }
    }



    /**
     * Obtener emails dinámicos desde las entidades.
     */
    private function getEmailsFromEntities(Order $order, string $recipientKey): array
    {
        $mainEmails = [];
        $ccEmails = [];

        switch ($recipientKey) {
            case 'customer':
                $mainEmails = $order->emailsArray ?? [];
                $ccEmails = $order->ccEmailsArray ?? [];
                break;

            case 'transport':
                $mainEmails = $order->transport->emailsArray ?? [];
                $ccEmails = $order->transport->ccEmailsArray ?? [];
                break;

            case 'salesperson':
                if ($order->salesperson) {
                    $mainEmails = $order->salesperson->emailsArray ?? [];
                    $ccEmails = $order->salesperson->ccEmailsArray ?? [];
                } elseif ($order->comercial) {
                    $mainEmails = $order->comercial->emailsArray ?? [];
                    $ccEmails = $order->comercial->ccEmailsArray ?? [];
                }
                break;
        }

        return [$mainEmails, $ccEmails];
    }
}

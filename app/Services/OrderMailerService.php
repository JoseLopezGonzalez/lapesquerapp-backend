<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderMailerService
{
    protected $pdfService;

    protected $mailConfigService;

    protected $trackingLinkService;

    public function __construct(
        OrderPDFService $pdfService,
        TenantMailConfigService $mailConfigService,
        CarrierTrackingLinkService $trackingLinkService
    ) {
        $this->pdfService = $pdfService;
        $this->mailConfigService = $mailConfigService;
        $this->trackingLinkService = $trackingLinkService;
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
        $order->loadMissing([
            'auxiliaryLines.auxiliaryProduct',
            'auxiliaryLines.tax',
        ]);

        $recipientsConfig = config('order_documents.standard_recipients');

        foreach ($recipientsConfig as $recipientKey => $docTypes) {

            // ✅ Obtenemos email desde las entidades dinámicamente
            [$mainEmails, $ccEmails] = $this->getEmailsFromEntities($order, $recipientKey);
            if (empty($mainEmails)) {
                continue;
            } // Saltamos si no hay email

            $subject = "Documentación del Pedido #{$order->id}";

            $documentsToAttach = [];
            foreach ($docTypes as $docType) {
                // Generar el PDF
                $viewPath = config("order_documents.documents.{$docType}.view_path");
                $pdfPath = $this->pdfService->generateDocument($order, $docType, $viewPath);

                // Verificar existencia
                if (! file_exists($pdfPath)) {
                    Log::error("No se encuentra el documento: {$pdfPath}");

                    continue;
                }

                $documentName = ucfirst($docType)."-pedido_#{$order->id}.pdf";

                $documentsToAttach[] = [
                    'path' => $pdfPath,
                    'name' => $documentName,
                ];
            }

            // Saltamos si no hay documentos
            if (empty($documentsToAttach)) {
                continue;
            }

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
        $order->loadMissing([
            'auxiliaryLines.auxiliaryProduct',
            'auxiliaryLines.tax',
        ]);

        $config = config('order_documents');

        $documentConfig = $config['documents'][$docType] ?? null;
        if (! $documentConfig) {
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
        if (! file_exists($pdfPath)) {
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

            case 'external_processor':
                if ($order->externalProcessor) {
                    $mainEmails = $order->externalProcessor->emailsArray ?? [];
                    $ccEmails = $order->externalProcessor->ccEmailsArray ?? [];
                }
                break;
        }

        return [$mainEmails, $ccEmails];
    }

    /**
     * Envío de documentación de exportación marítima al cliente: mensaje fijo (con datos del
     * envío) + notas opcionales del usuario + Export Packing List de todos los contenedores
     * del pedido + los adjuntos del pedido (BL, documentación sanitaria, facturas...) que el
     * usuario elija. `subject`/`body` son opcionales: si no se indican, se usa un asunto por
     * defecto y no se añade ninguna nota adicional al cuerpo fijo del email.
     *
     * @param  array<int>  $attachmentIds
     */
    public function sendMaritimeExportDocuments(Order $order, ?string $subject, ?string $body, array $attachmentIds = []): void
    {
        $subject = $subject ?: "Pedido {$order->formattedId} expedido - Documentación adjunta"
            .($order->buyer_reference ? " - {$order->buyer_reference}" : '');

        $order->loadMissing([
            'customer.country',
            'maritimeShippingDetail.customsBroker',
            'incoterm',
            'auxiliaryLines.auxiliaryProduct',
            'auxiliaryLines.tax',
        ]);

        [$mainEmails, $ccEmails] = $this->getEmailsFromEntities($order, 'customer');

        if (empty($mainEmails)) {
            Log::warning("sendMaritimeExportDocuments: el pedido #{$order->id} no tiene emails de cliente configurados.");

            return;
        }

        $containers = $order->maritimeContainers()
            ->with([
                'pallets.boxes.box.product.species.fishingGear',
                'pallets.boxes.box.product.captureZone',
            ])
            ->get();

        $shippingLine = $order->maritimeShippingDetail?->shipping_line;

        $documentsToAttach = [];
        $trackingLinks = [];

        $packingListPath = $this->pdfService->generateDocument(
            $order,
            'packing-list',
            config('order_documents.documents.packing-list.view_path')
        );

        if (file_exists($packingListPath)) {
            $documentsToAttach[] = [
                'path' => $packingListPath,
                'name' => "Packing_List_{$order->formattedId}.pdf",
            ];
        } else {
            Log::error("sendMaritimeExportDocuments: no se pudo generar el Packing List del pedido #{$order->id}.");
        }

        foreach ($containers as $container) {
            $pdfPath = $this->pdfService->generateExportPackingList($order, $container);

            if (! file_exists($pdfPath)) {
                Log::error("sendMaritimeExportDocuments: no se pudo generar el Export Packing List del contenedor {$container->id} (pedido #{$order->id}).");

                continue;
            }

            $documentsToAttach[] = [
                'path' => $pdfPath,
                'name' => "Export_Packing_List_{$order->formattedId}_{$container->container_number}.pdf",
            ];

            $trackingUrl = $this->trackingLinkService->urlFor($shippingLine, $container->container_number);
            if ($trackingUrl) {
                $trackingLinks[] = [
                    'containerNumber' => $container->container_number,
                    'url' => $trackingUrl,
                ];
            }
        }

        if (! empty($attachmentIds)) {
            $attachments = $order->attachments()->whereIn('id', $attachmentIds)->get();

            foreach ($attachments as $attachment) {
                $documentsToAttach[] = [
                    'path' => $attachment->path,
                    'name' => $attachment->original_name,
                    'disk' => $attachment->disk,
                    'mime' => $attachment->mime_type,
                ];
            }
        }

        $this->mailConfigService->configureTenantMailer();

        $mailable = new \App\Mail\OrderMaritimeExportDocuments(
            $order,
            $subject,
            $body,
            $documentsToAttach,
            $trackingLinks,
            $this->trackingLinkService->label($shippingLine)
        );

        Mail::to((array) $mainEmails)
            ->cc((array) $ccEmails)
            ->bcc(tenantSetting('company.bcc_email'))
            ->send($mailable);
    }

    /**
     * Envío de documentación al maquilador (CMR + letreros con datos anonimizados).
     */
    public function sendMaquiladorDocuments(Order $order): void
    {
        $externalProcessor = $order->externalProcessor;

        if (! $externalProcessor) {
            Log::warning("sendMaquiladorDocuments: pedido #{$order->id} no tiene maquilador asignado.");

            return;
        }

        $mainEmails = $externalProcessor->emailsArray;
        $ccEmails = $externalProcessor->ccEmailsArray;

        if (empty($mainEmails)) {
            Log::warning("sendMaquiladorDocuments: el maquilador {$externalProcessor->id} no tiene emails configurados.");

            return;
        }

        $docTypes = ['maquilador-cmr', 'maquilador-signs'];
        $documentsToAttach = [];

        foreach ($docTypes as $docType) {
            $viewPath = config("order_documents.documents.{$docType}.view_path");
            $documentName = config("order_documents.documents.{$docType}.document_name");
            $pdfPath = $this->pdfService->generateDocument($order, $docType, $viewPath);

            if (! file_exists($pdfPath)) {
                Log::error("sendMaquiladorDocuments: no se pudo generar el documento {$docType} para pedido #{$order->id}");

                continue;
            }

            $documentsToAttach[] = [
                'path' => $pdfPath,
                'name' => $documentName.'_pedido_'.$order->id.'.pdf',
            ];
        }

        if (empty($documentsToAttach)) {
            return;
        }

        $this->mailConfigService->configureTenantMailer();

        $mailable = new \App\Mail\StandardOrderDocuments(
            $order,
            "Documentación de Expedición - Pedido #{$order->id}",
            'emails.orders.maquilador',
            $documentsToAttach
        );

        Mail::to($mainEmails)
            ->cc($ccEmails)
            ->bcc(tenantSetting('company.bcc_email'))
            ->send($mailable);
    }
}

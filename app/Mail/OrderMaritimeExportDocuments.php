<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\TenantMailConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderMaritimeExportDocuments extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{path: string, name: string, disk?: ?string, mime?: ?string}>  $documents
     */
    public function __construct(
        public Order $order,
        public string $subjectText,
        public string $body,
        public array $documents,
    ) {}

    public function build(): self
    {
        $mailConfigService = app(TenantMailConfigService::class);

        $email = $this->subject($this->subjectText)
            ->from($mailConfigService->getFromAddress(), $mailConfigService->getFromName())
            ->markdown('emails.orders.maritime_export', [
                'order' => $this->order,
                'body' => $this->body,
            ]);

        foreach ($this->documents as $document) {
            if (! empty($document['disk'])) {
                $email->attachFromStorageDisk($document['disk'], $document['path'], $document['name'], [
                    'mime' => $document['mime'] ?? null,
                ]);

                continue;
            }

            $email->attach($document['path'], [
                'as' => $document['name'],
                'mime' => $document['mime'] ?? 'application/pdf',
            ]);
        }

        return $email;
    }
}

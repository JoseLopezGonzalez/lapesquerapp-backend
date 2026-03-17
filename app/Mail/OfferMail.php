<?php

namespace App\Mail;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Offer $offer,
        public string $subjectLine,
        public string $pdfContent
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.offers.offer',
            with: [
                'offer' => $this->offer,
            ]
        );
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $this->pdfContent,
                'oferta-'.$this->offer->id.'.pdf'
            )->withMime('application/pdf'),
        ];
    }
}

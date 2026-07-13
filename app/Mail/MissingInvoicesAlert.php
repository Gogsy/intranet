<?php

namespace App\Mail;

use App\Support\InvoiceTracker\Months;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MissingInvoicesAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $suppliers,
        public int $year,
        public int $month,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Missing invoice entries for %s %d: %d supplier(s)',
                Months::name($this->month),
                $this->year,
                $this->suppliers->count(),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoices.missing-alert',
        );
    }
}

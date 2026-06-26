<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\CustomerProspect;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProspectOutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CustomerProspect $prospect,
        public Company $company,
        public string $bodyText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Anfrage von '.$this->company->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.prospect-outreach',
            with: [
                'company' => $this->company,
                'prospect' => $this->prospect,
                'bodyText' => $this->bodyText,
                'optOutUrl' => route('public.prospect-opt-out.show', $this->prospect->opt_out_token),
            ],
        );
    }
}

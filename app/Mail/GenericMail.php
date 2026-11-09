<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class GenericMail extends Mailable
{
    /** @param array{label:string,url:string}|null $action bouton affiché sous le message */
    public function __construct(public string $mailSubject, public array $lines, public ?array $file = null, public ?array $action = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.generic');
    }

    public function attachments(): array
    {
        if (! $this->file) {
            return [];
        }

        return [Attachment::fromData(fn () => $this->file['data'], $this->file['name'])->withMime($this->file['mime'])];
    }
}

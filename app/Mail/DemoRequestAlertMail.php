<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the Botzo team that someone asked for a demo, with a WhatsApp link to reach them.
 */
class DemoRequestAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoRequest $demoRequest, public ?string $whatsappUrl, public string $adminUrl)
    {
    }

    public function build()
    {
        return $this->subject('طلب عرض توضيحي جديد: '.$this->demoRequest->full_name)
            ->view('emails.demo_request_alert');
    }
}

<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Tenant $tenant,
        public User $owner,
        public Subscription $subscription,
        public int $daysRemaining
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $planName = $this->subscription->plan?->name ?? 'Subscription';
        return new Envelope(
            subject: "Action Required: {$this->tenant->name} — {$planName} renews in {$this->daysRemaining} day(s)",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-renewal',
        );
    }
}

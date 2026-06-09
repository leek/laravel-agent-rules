# Mail

**Purpose:** Mailable classes — one class per email the application sends.

## Naming

- **MUST** be event-like, **no suffix** — e.g. `InvoicePaid`, `OrderShipped`, `WelcomeNewUser`.

## Rules

- **MUST** use the modern Mailable API: `envelope(): Envelope`, `content(): Content`, `attachments(): array` — not the legacy `build()` method.
- **MUST** pass data via constructor-promoted public properties — public properties are automatically available to the view.
- **MUST** implement `ShouldQueue` for mail sent during a web request — SMTP calls are slow and block the response. If the mail references rows written in an open transaction, also see the `afterCommit` rules in `app/Jobs/CLAUDE.md`.
- **PREFER** markdown mailables (`Content(markdown: ...)`) for transactional mail — consistent styling, free plain-text version.
- **SHOULD** set subjects explicitly in the `Envelope`; don't rely on the class-name-derived default.

```php
final class InvoicePaid extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Invoice #{$this->invoice->number} paid");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice-paid');
    }
}
```

## Sending

```php
Mail::to($user)->send(new InvoicePaid($invoice));
```

- **PREFER** a Notification with a `mail` channel when the email is "tell this user something happened" — see `app/Notifications/CLAUDE.md`. Use a bare Mailable for non-user recipients (external parties, fixed addresses) or heavily bespoke emails.

## Testing

- Use `Mail::fake()` + `Mail::assertSent()` / `assertQueued()` in feature tests (see the fakes section in `tests/CLAUDE.md`).
- **SHOULD** test mailable content directly without sending: `(new InvoicePaid($invoice))->assertSeeInHtml(...)`.

## Create

```bash
php artisan make:mail InvoicePaid --markdown=mail.invoice-paid
```

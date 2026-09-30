# Mail

**Purpose:** Mailable classes — one class per email the application sends.

## Naming

- **MUST** be `{Subject}Mail` (e.g. `InvoicePaidMail`, `OrderShippedMail`, `WelcomeNewUserMail`). The `Mail` suffix keeps mailables distinct from Notifications, which stay event-like with no suffix — see `app/Notifications/CLAUDE.md`.

## Rules

- **MUST** use the modern Mailable API: `envelope(): Envelope`, `content(): Content`, `attachments(): array` — not the legacy `build()` method.
- **MUST** pass data via constructor-promoted public properties — public properties are automatically available to the view.
- **MUST** implement `ShouldQueue` when the mailable is sent during a web or Livewire request — SMTP calls are slow and block the response. Mail already dispatched from a queued job/listener does not need another queue hop. If the mail references rows written in an open transaction, also see the `afterCommit` rules in `app/Jobs/CLAUDE.md`.
- **PREFER** markdown mailables (`Content(markdown: ...)`) for transactional mail — consistent styling, free plain-text version.
- **MUST** set subjects via `__()` (or a translated string built in `envelope()`); don't rely on the class-name-derived default — see i18n in `app/CLAUDE.md`.

```php
final class InvoicePaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.invoice_paid.subject', ['number' => $this->invoice->number]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice-paid');
    }
}
```

## Sending

```php
Mail::to($user)->send(new InvoicePaidMail($invoice));
```

- **PREFER** a Notification with a `mail` channel when the email is "tell this user something happened" — see `app/Notifications/CLAUDE.md`. Use a Mailable for non-user recipients (external parties, fixed addresses) or heavily bespoke emails that are not multi-channel notifications.

## Testing

- Use `Mail::fake()` + `Mail::assertSent()` / `assertQueued()` in feature tests (see the fakes section in `tests/CLAUDE.md`).
- **SHOULD** test required business content directly without sending: `(new InvoicePaidMail($invoice))->assertSeeInHtml($invoice->number)` or assertions for the invoice amount/action link. Avoid pinning incidental translated prose or layout; see `tests/Feature/CLAUDE.md` for the business-contract exception.

## Create

```bash
php artisan make:mail InvoicePaidMail --markdown=mail.invoice-paid
```

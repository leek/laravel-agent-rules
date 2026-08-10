# Localization (`lang/`)

**Purpose:** store user-facing copy. PHP and Blade read strings via `__()` / `trans_choice()` — never inline user-facing literals (see `app/CLAUDE.md`).

## Layout

- **PHP files:** `lang/{locale}/*.php` returning arrays — e.g. `lang/en/orders.php` with keys like `'status' => ['pending' => 'Pending']`.
- **JSON files:** `lang/{locale}.json` for short UI strings keyed by the source English phrase, when the project prefers that style.
- **MUST** name PHP files and array keys `snake_case` — `orders.php`, `article_added`, not `Orders.php` / `articleAdded`.
- **SHOULD** group by domain or feature (`orders`, `auth`, `validation`) rather than one giant `messages.php`.

## Keys

- **MUST** keep keys stable once shipped — changing a key is a breaking rename for every call site.
- **SHOULD** nest by concept: `__('orders.status.pending')` rather than flat `__('orders_status_pending')`.
- **MUST** pass interpolations as the second argument: `__('mail.invoice_paid.subject', ['number' => $invoice->number])`.

## Rules

- **MUST NOT** put HTML structure in translation strings when a Blade component can own the markup — translate phrases, not full layout.
- **AVOID** concatenating translated fragments into sentences (word order differs by locale); use one string with placeholders instead.

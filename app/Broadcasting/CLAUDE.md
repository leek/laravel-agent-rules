# Broadcasting Channels

**Purpose:** authorize access to private / presence WebSocket channels. The class is an authorization gate — the mirror of a Policy for real-time channels, not a place for business logic.

## Naming

- **MUST** be `{Subject}Channel` (e.g. `OrderChannel`, `ChatRoomChannel`). Lives in `app/Broadcasting/`.

## Create

```bash
php artisan make:channel OrderChannel
```

## Registration

- **MUST** map each channel name pattern to its class in `routes/channels.php`:

```php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders.{order}', OrderChannel::class);
```

- Channel-name placeholders (`{order}`) are **route-model-bound exactly like routes** — type-hint the model in the auth method and Laravel resolves it. A name that doesn't match the parameter resolves to `null`.

## Authorization

- The auth method is `join($user, ...$bindings): bool|array`. The authenticated user is always the first argument; remaining args are the bound placeholders.
- **Private channel** — return `bool`.
- **Presence channel** — return an **array** of the data to share with other subscribers (e.g. `['id' => $user->id, 'name' => $user->name]`). Return `false`/`null`/empty to deny.
- **MUST** return a real ownership/membership check scoped to `$user`. Never `return true` on a channel that carries another user's data.
- **AVOID** side effects or business logic here — resolve, authorize, return.

```php
final class OrderChannel
{
    public function join(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
}
```

Presence channel returning member data:

```php
final class ChatRoomChannel
{
    public function join(User $user, ChatRoom $room): array|bool
    {
        if (! $room->members()->whereKey($user->id)->exists()) {
            return false;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }
}
```

## The event side lives in `app/Events/`

- The class that fires data onto a channel is an **Event** implementing `ShouldBroadcast` (queued) or `ShouldBroadcastNow` (synchronous) — see `app/Events/CLAUDE.md`. The `Broadcasting` directory only holds the auth gate.
- **MUST** return a `PrivateChannel` / `PresenceChannel` / `Channel` from `broadcastOn()` whose name matches a pattern registered in `routes/channels.php`.
- **SHOULD** set a stable wire name with `broadcastAs()` so the client subscribes to a contract, not a class name.
- **MUST** keep the `broadcastWith()` payload to JSON-serializable scalars/arrays — same rule as a Notification's `toArray()`. Do not ship model instances; send ids + the fields the client needs.
- **PREFER** `broadcast(new OrderShipped($order))->toOthers()` when the originating user already updated their own UI optimistically.

```php
final class OrderShipped implements ShouldBroadcast
{
    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("orders.{$this->order->id}");
    }

    public function broadcastAs(): string
    {
        return 'order.shipped';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->order->id, 'status' => $this->order->status->value];
    }
}
```

> Channel route registration and the `channels.php` file are covered in `routes/CLAUDE.md`.

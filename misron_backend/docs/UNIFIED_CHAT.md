# Unified Chat

Omnichannel inbox (Facebook Messenger, Instagram, WhatsApp Cloud API, TikTok) inside the SoftCommerce admin. Conversations live only in this installation's database.

Admin → **Unified Chat**. Access is controlled by the existing **Staff → Roles** page (permission group *Unified Chat Management*), plus a per-connected-account staff list.

## Access model
A staff member sees a conversation only if **all** hold: role has `view_unified_chat`; they were granted that connected account (Super Admin: all); and role has `view_all_chats`, or `view_assigned_chats` and the chat is assigned to them. Enforced in `App\Services\Chat\ChatAccess` for every API route, attachment, export, analytics query, polling call and broadcast channel. Unauthorized ids return 404. No role is granted anything by the migration — add the permissions to roles in the Roles page.

| Permission | Effect |
|---|---|
| view_unified_chat | open the inbox |
| view_all_chats / view_assigned_chats | scope inside authorized accounts |
| reply_unified_chat | send, change status/priority/tags, link customers |
| assign_unified_chat | assign (assignee must have that account) |
| manage_chat_accounts | connect / disconnect / staff access / diagnostics |
| manage_chat_settings, manage_chat_automation | saved replies, greeting/away/auto-close |
| view_chat_analytics, export_chat_data | reports, CSV |
| create_order_from_chat | order modal, product search |
| manage_chat_notes | internal notes |

## Install
```bash
php artisan migrate --path=database/migrations/unified_chat --force   # additive: chat_* tables + 12 permissions
```
No shell? Import `sqlupdates/unified_chat_v1.sql` once in phpMyAdmin instead (rollback: `unified_chat_v1_rollback.sql`). The SQL was generated from the migration but has not been executed against a real MySQL server. Then **Clear Cache** in the admin so the new permissions appear in Staff → Roles.

### .env
```
CHAT_MODE=gateway                 # gateway (default, recommended) | direct
CHAT_GATEWAY_URL=https://integration.example.com
CHAT_INSTALLATION_ID=inst_…       # from `php bin/gateway installation:create`
CHAT_INSTALLATION_SECRET=…
CHAT_ATTACHMENT_DISK=local        # private disk; files are served only through the authorized route
CHAT_RETENTION_DAYS=0             # 0 = keep; N = delete messages older than N days (chat:maintain)
CHAT_MAX_DISCOUNT_PERCENT=10      # chat-order discount cap for non-Super-Admin
# direct mode only (your own Meta app):
META_APP_ID= META_APP_SECRET= META_WEBHOOK_VERIFY_TOKEN= META_GRAPH_VERSION=v21.0
TIKTOK_MESSAGING_ENABLED=false    # keep false until TikTok approves Business Messaging for your app
```
APP_URL must be the public https URL. Webhook endpoint (direct mode): `https://<domain>/api/chat/webhook/{facebook|instagram|whatsapp}`. Gateway delivery endpoint: `https://<domain>/api/chat/gateway/ingest`. OAuth return: `https://<domain>/admin/unified-chat/connect/callback`.

### Background work
The app already runs `queue:work --once` every minute from the scheduler, so a single cron (`* * * * * php artisan schedule:run`) is enough for chat. A persistent worker (`php artisan queue:work`) is faster and recommended on VPS. `chat:maintain` (every 5 min) re-queues stuck events, auto-resolves idle chats, applies retention.

### Real-time
Default: authorized polling every 5 s (ids only, paused while the tab is hidden) — works on any hosting. For WebSockets set `BROADCAST_DRIVER` (pusher-compatible/Reverb/Redis) and load Laravel Echo on the page; the UI auto-subscribes to `private-chat.account.{id}` (authorized by `ChatAccess` at subscription; events carry ids only, the browser then re-reads through the authorized API).

## Gateway
`/softtech_gateway` is a separate deployable (see its README). Use it when many installations share one set of provider apps.

## Chat → order
`ChatOrderService` writes the same `combined_orders / orders / order_details` rows as the website and funnel checkouts, takes stock and FIFO cost through `StockLedger`, prices via `cart_product_price`, delivery via the store shipping rule (`DeliveryCharge`). Placed as cash-on-delivery/unpaid; idempotent per form (`idempotency_key`). Carrier-wise shipping is refused (needs a customer-chosen carrier). It does not post the POS-style accounting entries (POS posts at sale time; website/funnel orders do not).

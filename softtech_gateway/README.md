# Softtech Messaging Integration Gateway

A small, separately deployed service that lets many **independent** SoftCommerce installations (each with its own domain, hosting and database) share one set of official provider developer apps.

It holds **routing and delivery state only**: which connected account belongs to which installation, and a short-lived queue of events being delivered. It never stores conversations or customer profiles. Message history lives only in each installation's own database.

## What it does
- Receives Meta (Messenger, Instagram, WhatsApp) webhooks, verifies `X-Hub-Signature-256`.
- Splits each webhook per connected account and forwards only that account's entries to the owning installation, HMAC-signed (`X-Softtech-Installation/Timestamp/Signature`).
- Stores before forwarding; retries with exponential backoff (30s → 6h cap); dead-letters after 10 attempts. Installations de-duplicate on `event_id`, so redelivery is safe.
- Runs the shared-app OAuth for Facebook Pages (+ linked Instagram): the app secret and code exchange stay here; the installation receives tokens only through a single-use, 5-minute, installation-bound grant.
- First installation to claim an account owns it. No other installation can claim it until it is unlinked or the owner is revoked.

## Not implemented / needs external approval
- WhatsApp Embedded Signup page (needs Meta Tech Provider status and a front-end using Meta's JS SDK). Until then WhatsApp numbers are registered with `POST /v1/accounts/register` (installation proves control with its own token).
- TikTok: no routes; TikTok Business Messaging access must be granted first.

## Deploy
```bash
cd softtech_gateway
export GATEWAY_KEY=$(php -r 'echo base64_encode(random_bytes(32));')   # keep it; encrypts installation secrets at rest
export GATEWAY_DSN='mysql:host=localhost;dbname=gateway;charset=utf8mb4' GATEWAY_DB_USER=… GATEWAY_DB_PASS=…   # default: sqlite file in storage/
export META_APP_ID=… META_APP_SECRET=… META_WEBHOOK_VERIFY_TOKEN=… GATEWAY_PUBLIC_URL=https://integration.example.com
php bin/gateway migrate
php bin/gateway installation:create "Brand A" brand-a.com https://brand-a.com/api/chat/gateway/ingest
```
Serve `public/` over HTTPS only. Cron every minute: `php bin/gateway deliver`.
Meta app settings: webhook callback `https://<gateway>/webhook/facebook` (and `/instagram`, `/whatsapp`), the verify token above, OAuth redirect `https://<gateway>/oauth/callback`.

## Operations
`installation:rotate <id>` (old secret valid 1 hour) · `installation:revoke <id>` (removes routes, dead-letters pending) · `dead` · `requeue <id>`.
Tests: `php tests/run.php` (no dependencies).

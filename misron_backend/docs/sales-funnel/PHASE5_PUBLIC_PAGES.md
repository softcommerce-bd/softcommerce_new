# Phase 5 — Public pages, templates, publishing tools

Status: implemented and tested on the test databases. Nothing here is applied to the main `misron`
database; `sqlupdates/sales_funnel_v4_publishing.sql` is prepared for the final integration.

What an admin can now do, end to end, without code:

1. Open **Sales Funnels → Create Funnel**, choose one of eight designs in Bengali or English, search and
   pick the product from the store, name the page.
2. Land in the editor with the page already built around that product (a *Setup* tab lists anything
   still missing), drag sections, edit text, choose products and offers, change the order form.
3. **Preview** on desktop, tablet and phone widths; **share a secret draft link** with someone who has no account.
4. **Publish** now or **schedule** it (start and, optionally, end), with the checks shown first.
5. Fix search and sharing details (title, description, share picture, hide from search) at any time.
6. See every version, look at an old one, restore it into the draft (and optionally publish it).
7. Customers order from `/funnel/<address>`; each order is an ordinary order of the store, marked
   *Funnel* in **Orders**, with a filter link from the funnel list.

---

## 1. The public page `/funnel/[slug]`

| Part | Behaviour |
|---|---|
| Source of truth | The backend: `GET /api/v2/funnels/{slug}`. It answers only for a page that is live *right now* (published, or scheduled and its time has passed, and not past its end time). Drafts, unpublished, archived and deleted pages answer "not available". |
| Rendering | The same renderer as the editor canvas (`funnel/render`), so the page looks as it did in the editor. The route imports no editor code. |
| Cache | The page HTML is cached by Next.js for 60 seconds (`revalidate = 60`). Offers, prices, stock and the order form are **not** cached: the browser asks the API live. |
| Cache refresh | When a page is published, unpublished, scheduled time is reached, or its address / search details change, the backend calls `POST /api/funnel/revalidate` on the storefront (bearer secret) and the cached copy is dropped at once. If that call fails, the page is at most 60 seconds stale. It never makes a publish fail. |
| Ending | A page with an end time closes itself in the visitor's browser at that moment (the cached HTML may be a minute old) and the backend refuses new orders from it regardless. |
| Not available | HTTP 404 with the store's own not-found screen, marked `noindex` (Phase 6). |
| Chrome | `/funnel/*` and `/builder/*` render without the store header, footer, cart or sign-up prompts (`LayoutDetector`). |
| Analytics | The Meta Pixel and GTM are not loaded in the editor. Funnel analytics events arrive in Phase 6. |

**Status code (fixed in Phase 6).** A missing or closed page now returns a real HTTP **404** (still with the
not-found screen and `noindex`). The store's loading screens moved from `app/loading.jsx` into the store's own
segments, so nothing streams above `/funnel/*` and the page decides its status before sending anything. No
`proxy.js` is needed. See PHASE6.md.

## 2. Secret draft links

* Created in the editor (**Share** button) by anyone who can edit the funnel: lifetime 1 hour up to 30 days
  (`config/funnel.php → preview`), at most 20 active links per funnel, an optional label ("Client review").
* The link is `/funnel/<slug>/preview/<40-character secret>`. Only the **SHA-256 hash** of the secret is stored
  (`funnel_preview_links.token_hash`); the secret is shown once, at creation. A database dump cannot open a preview.
* A link is tied to its funnel and slug, expires, can be switched off, and stops working when the funnel is
  archived or deleted. Every failure (unknown, expired, revoked, wrong funnel, malformed) gives the same answer.
* The preview shows the **current draft** (edits appear without a new link), is never cached
  (`Cache-Control: no-store, private`), is `noindex, nofollow` (header and meta), sends no referrer
  (`Referrer-Policy: no-referrer`, `<meta name="referrer">`) so the secret does not leak to other sites, and has
  a banner saying what it is.
* **It cannot place orders.** The order form runs in preview mode (the same "pretend order" as the editor's
  Preview), and there is no ordering route under a preview link (tested).
* Throttled with its own limiter (`funnel-preview`, 180/minute per address).

## 3. Scheduling

* **Schedule** (`POST …/schedule`, permission `publish_funnel`): validates the draft exactly like a publish, freezes
  it as a version *now*, sets the status to `scheduled` with `scheduled_at` and an optional `unpublish_at`.
  What goes live is what was checked, even if the draft changes afterwards.
* A scheduled funnel can be cancelled (back to draft, or to unpublished if it had been live before).
  A live funnel cannot be scheduled again (publish updates it; unpublish first to schedule a new start).
* **End time** can be set or cleared on a live or scheduled funnel. Publishing again keeps a future end time and
  drops a past one; unpublishing and archiving clear it.
* **The clock decides, not cron.** `Funnel::isLive()` compares `scheduled_at` / `unpublish_at` with the time on every
  public read, so a page appears and disappears on time even if the scheduler is down. The scheduler only
  tidies the stored status and refreshes the storefront cache:
  `php artisan funnel:reconcile` runs every minute from `app/Console/Kernel.php`; the host's cron job must call
  `php artisan schedule:run` (it already must for the existing queue worker entry).
  A page that is scheduled and due may keep showing the "not available" screen for up to a minute (cache) until
  the scheduler's next run or the cache expires.
* If the scheduler was down for a whole window, the funnel never goes live and becomes `unpublished`.
* Times are exchanged as ISO 8601 with an offset (`2026-12-31T18:00:00+06:00`); the editor uses the admin's own zone.

## 4. Version history and restore

* Every publish creates an immutable version (`funnel_versions`, kind `published`); a restore creates a
  *restore point* of the draft it replaces (the last 30 are kept).
* The **History** dialog lists versions (who, when, label, which one is live), shows any version rendered as
  customers saw it (desktop or phone width, read-only), and offers **Restore as draft** and, with publish rights,
  **Restore and publish**. The live page changes only if the admin chooses the second.
* Restoring never deletes anything.

## 5. Search and social sharing

Kept on the funnel record (not in the versioned page), so they can be corrected without publishing again:
page title, search title and description, share title and description, share picture (from the media library),
"hide from search engines" (noindex), canonical address (https only). The editor shows live previews of the
search result and the shared-link card, with the usual length hints.

The page `<head>` gets: title, description, canonical, `robots`, Open Graph (`og:title/description/image/url/type/locale`),
Twitter card, and schema.org **Product** JSON-LD (first one-piece offer, with the live price and availability).
`/funnel/sitemap.xml` lists live, indexable funnels (`app/funnel/sitemap.js`, fed by `GET /api/v2/funnels`);
`/robots.txt` (new, `app/robots.js`) keeps `/builder`, `/funnel/*/preview/` and `/dashboard` out and points at the sitemap.
The store had no robots.txt before; everything else stays crawlable.

## 6. Templates

Eight designs, each in Bengali and English (16 rows in `funnel_templates`, installed by `php artisan funnel:templates`,
safe to repeat). Definitions are code (`app/Services/Funnel/Templates/Definitions`) and tested: every one is a valid
draft, holds **no product**, and publishes once its products are chosen.

| Design | For | Slots (products it asks for) |
|---|---|---|
| Classic single product | the all-rounder: hero with photos, benefits, 1/2/3-piece packages, FAQ | 1 product → 3 offers |
| Bundle and save | selling more pieces per order, price per piece | 1 product → 1/2/4-piece bundles |
| Flash sale | time-boxed sale: countdown, discount badge, one price | 1 product, offer + countdown end a week from creation |
| Video story | a video leads, then the offer | 1 product (needs a video link) |
| Customer proof | trust icons, review cards, offer | 1 product (needs real reviews) |
| Quick order (mobile ads) | photos, price, form on one short screen | 1 product |
| Premium dark | dark, serif, gold accents for style products | 1 product |
| Product showcase | four products as cards, one order | 4 products |

Rules that keep templates honest:

* A template never stores a product. Its **slots** name the offers that need one; choosing a product at creation
  (or later in the editor's *Setup* tab) fills every offer of the slot and keeps each offer's piece count.
* Wording the merchant must write themselves is wrapped in `[[double brackets]]` (the review cards, the video link).
  **A page containing such text, or a video block without a link, cannot be published.**
* Sample wording is general; each template lists "check before you publish" reminders (delivery, payment, discounts).
* The flash sale's countdown and offer end at a real date a week after creation, not an "evergreen" timer.

## 7. Languages and mobile

* A page is Bengali or English (`page.settings.language`); the page root carries `lang`, the editor offers the
  switch, templates exist in both languages, the order form, confirmation and messages have both dictionaries
  (`funnel/commerce/i18n.js`). The font stack handles both scripts.
* Mobile: every template is built and opened by the browser suite at 390 px wide (touch, 2× pixel ratio) and 1280 px,
  asserting: HTTP 200, no sideways overflow, no console errors, the live offers drawn with the real product's
  name, text at least 12 px, an order button at least 44 px tall. Screenshots are in
  `misron_frontend/test-results/funnel-editor-screens/templates/`.

## 8. Admin integration

* **Create** page: template gallery with language switch, product search (the same read-only catalogue the
  editor uses), thumbnails when `public/assets/funnel-templates/*.jpg` exist.
* **List**: shows go-live and go-offline times, *View live page*, *View orders* (opens the Orders list filtered
  to this funnel), and keeps working before the new SQL is installed (the page checks for the new columns).
* **Orders**: funnel orders carry a *Funnel* badge; `?funnel_id=` filters. Both use `orders.funnel_id`
  (`sales_funnel_v2_orders.sql`) and are inert while it is absent.

## 9. API additions

Editor (token + permission): `GET funnels/{id}/versions/{versionId}`, `POST funnels/{id}/schedule`,
`POST funnels/{id}/schedule/cancel`, `PUT funnels/{id}/end-time`, `GET|POST funnels/{id}/preview-links`,
`DELETE funnels/{id}/preview-links/{linkId}`, `POST funnels/{id}/template-slots`, `GET templates`;
`POST funnels` accepts `template_id` and `slots`; `PUT funnels/{id}` accepts `noindex`, `og_title`,
`og_description`, `canonical_url`, `og_image_upload_id`.
Public: `GET funnels` (live list), `GET funnels/{slug}` (now with `seo`), `GET funnels/{slug}/preview/{token}`
(+ `/offers`, `/quote`).

## 10. Deployment

Backend, in this order, after a backup:

1. `sqlupdates/sales_funnel_v4_publishing.sql` (idempotent; or `php artisan migrate --path=database/migrations/sales_funnel --force`).
2. Deploy the code. The admin pages tolerate the code arriving first.
3. `php artisan funnel:templates` (installs the eight designs).
4. Cron: `* * * * * php artisan schedule:run` (already needed).
5. `.env`: `FUNNEL_PUBLIC_URL` (the storefront origin), `FUNNEL_REVALIDATE_URL=https://<storefront>/api/funnel/revalidate`,
   `FUNNEL_REVALIDATE_SECRET=<random string>`.

Storefront: set `FUNNEL_REVALIDATE_SECRET` (same value) and deploy. Without it the cache refresh endpoint refuses
everything and pages refresh within 60 seconds.

Optional: capture the gallery thumbnails with `FUNNEL_THUMBS=1 npx playwright test templates.spec.js`, then
`php artisan funnel:templates` again.

Rollback: `sqlupdates/sales_funnel_v4_publishing_rollback.sql` (drops preview links and the new columns; pages,
versions and orders are untouched).

## 11. Not done (by design or later)

* Saved sections UI (the table exists), analytics events and dashboard, Pixel/GTM/CAPI wiring (Phase 6).
* Unpublished online-payment orders are not expired automatically (as on the website today).

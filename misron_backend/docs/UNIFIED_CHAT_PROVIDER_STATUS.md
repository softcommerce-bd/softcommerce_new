# Provider readiness (accurate as of implementation; nothing here has been tested live)

Legend: **Implemented** = code exists · **Mock-tested** = automated tests with faked HTTP · **Sandbox/Live** = verified against the real provider (none yet) · **Pending/Blocked** = needs external approval.

| Capability | Facebook Messenger | Instagram | WhatsApp Cloud | TikTok |
|---|---|---|---|---|
| Inbound text/media, status, dedupe, ordering | Implemented, mock-tested | Implemented, mock-tested | Implemented, mock-tested | Pipeline only (neutral envelope) |
| Send text | Implemented, mock-tested | Implemented, mock-tested | Implemented, mock-tested | **Blocked** – refuses, no endpoint invented |
| Send image/file | Implemented (signed URL) | image only | Implemented | No |
| Template messages outside 24 h | n/a (HUMAN_AGENT tag not used) | n/a | Adapter supports `template` payload; **no template UI** | n/a |
| OAuth / onboarding | Direct + gateway: implemented, **untested live** | via linked Page (facebook_login flow); Instagram Login flow not wired to onboarding | **Embedded Signup not implemented**; manual phone-number-id + token | Not available |
| Coexistence (WhatsApp Business app) | – | – | **Not implemented**; echo events from the phone app are stored if Meta sends them | – |
| Live-tested | No | No | No | No |

## External requirements (Meta)
Meta developer app (Business type) · products Messenger, Instagram, WhatsApp · permissions `pages_messaging`, `pages_show_list`, `pages_manage_metadata`, `instagram_basic`, `instagram_manage_messages`, `business_management`, `whatsapp_business_messaging`, `whatsapp_business_management` · **Advanced Access via App Review** (and Business Verification) before customer-owned Pages/numbers work — in development mode only app roles can message · OAuth redirect and webhook URLs above · for the gateway model, Meta Tech Provider status for WhatsApp Embedded Signup · WhatsApp conversation charges are billed by Meta to the customer’s account; template approval needed for messages after 24 h. Verify the permission list and Graph version (`META_GRAPH_VERSION`, default v21.0) against current Meta docs before submitting review — this was not re-verified online during implementation.

## TikTok
Requires TikTok for Business developer registration, Business Messaging API access granted to the app, and security/privacy review. Not available to all accounts or regions. Leave `TIKTOK_MESSAGING_ENABLED=false` until approved; the UI shows the connect button disabled and sends are refused.

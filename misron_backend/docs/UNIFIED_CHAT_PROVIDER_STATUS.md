# Provider readiness

Nothing here has been tested against a live provider: no Meta/TikTok credentials or approvals exist in this project.
Legend: **Mock-tested** = automated tests with faked HTTP · **Live-tested** = real provider (none yet) · **Doc-checked** = confirmed against Meta's official documentation as returned by search on 2026-10-30 (the documentation pages themselves could not be fetched from the build sandbox, so only search excerpts were read).

## What was checked against official documentation
| Item | Result |
|---|---|
| Latest Graph API version | **v26.0** (released 2026-07-29). v25.0 (2026-02-18, supported to 2028-07-29) is the default here (`META_GRAPH_VERSION`). **v21.0 — the previous default — is removed 2027-01-21**; v20 on 2026-09-24. Pin deliberately and review on each Meta release. |
| Messenger / Instagram (Facebook Login path) permissions | `pages_show_list`, `pages_manage_metadata`, `pages_messaging`, `pages_read_engagement`, `business_management`; Instagram adds `instagram_basic`, `instagram_manage_messages`. `business_management` is a dependency of `pages_messaging`, `pages_show_list`, `instagram_manage_messages`. Matches the scopes requested. |
| Instagram API with Instagram Login (alternative path) | Uses a **different** permission set (`instagram_business_basic`, `instagram_business_manage_messages`). Onboarding for this path is **not implemented**; only the Facebook-Login path (Page-linked Instagram) is. The two sets are never mixed. |
| Advanced Access | Needed for accounts without a role on your app; requires App Review and Business Verification; Ongoing Review afterwards. Not needed only if you message your own Page. |
| Instagram webhooks | Echo arrives as `is_echo` inside `messages` (handled). `message_echoes` was **not** found as an Instagram field; it is requested only for Pages. |
| WhatsApp Embedded Signup | `FB.login` with `config_id`, `response_type:'code'`, `override_default_response_type:true`, `extras.setup`, `sessionInfoVersion:'3'`; browser `message` event of type `WA_EMBEDDED_SIGNUP`; code from `response.authResponse.code`. Needs a Facebook Login for Business configuration (Embedded Signup variation, Cloud API product), allowed domains and OAuth redirect set to the signup page's domain. Implemented as described. **Embedded Signup v2 is deprecated (2026-10-15 per Meta); the page uses `sessionInfoVersion` 3 — review v4 before launch.** |
| WhatsApp coexistence | Enabled by `extras.featureType = 'whatsapp_business_app_onboarding'`; success event `FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING`; requires WhatsApp Business app ≥ 2.24.17; registration is skipped; history sync must be started within 24 h; throughput fixed at 20 mps; groups, disappearing messages and broadcast lists unsupported. Webhook fields to subscribe: `account_update`, `history`, `smb_app_state_sync`, `smb_message_echoes`. |
| WhatsApp business-scoped user ids | Payloads may omit the phone number (`from`/`wa_id`) and carry `user_id`/`username`. Parser tolerates it; **replying to username-only contacts is refused** (no verified send contract). |

## NOT verified (read the current Meta reference before relying on these)
Exact request shapes for token exchange in Embedded Signup, `POST /{waba}/subscribed_apps`, `POST /{phone}/register` (pin), and the `smb_message_echoes` item layout (`value.message_echoes[]` is my assumption, mock-tested only). The coexistence **history / contacts sync request** (SMB App Data API) is **not implemented**. Permission names for WhatsApp (`whatsapp_business_management`, `whatsapp_business_messaging`) are standard but not re-checked in this pass.

## Capability matrix
| | Messenger | Instagram | WhatsApp Cloud | TikTok |
|---|---|---|---|---|
| Inbound text/media, status, dedupe, ordering | Mock-tested | Mock-tested | Mock-tested | pipeline only |
| Send text / image | Mock-tested | Mock-tested (image only) | Mock-tested | **Disabled** |
| Webhook signature (direct mode) | Mock-tested | Mock-tested | Mock-tested | refused |
| Live health check (token, subscription) | Mock-tested | Mock-tested | Mock-tested | always "not available" |
| Onboarding | Direct OAuth + gateway OAuth: mock-tested | via linked Page (Facebook Login) | **Embedded Signup page + completion + coexistence flag: mock-tested end to end in the gateway**; manual id+token fallback | none |
| Approved template messages | n/a | n/a | list/validate/send, text BODY parameters only, marketing hidden by default: mock-tested | n/a |
| Live-tested | No | No | No | No |

## Billing and policy guards in code
Free-form replies only inside the 24 h window; outside it only an **approved, non-marketing** template to a customer who has already written (never opens a conversation, no bulk). UI states that Meta may charge for template messages. Marketing templates are hidden unless `CHAT_WA_ALLOW_MARKETING_TEMPLATES=true`. Header-media and button-variable templates are shown as unsupported.

## External requirements (cannot be done from code)
Meta developer app (Business) with Messenger, Instagram and WhatsApp products · App Review + Business Verification for Advanced Access · **Tech Provider** status and a Facebook Login for Business configuration for Embedded Signup (`WA_ES_CONFIG_ID`) · allowed domain/redirect for the gateway · a verified webhook URL per product and subscription of the app to Page / WABA events · WhatsApp message-template approval and a payment method on the customer's WABA · **TikTok**: developer registration, Business Messaging API access and review — `TIKTOK_MESSAGING_ENABLED` stays `false`.

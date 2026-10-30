# Unified Chat – progress (resume from here)
Branch claude/peaceful-planck-elqcki. See misron_backend/docs/UNIFIED_CHAT.md (deploy) and UNIFIED_CHAT_PROVIDER_STATUS.md (what is verified).
Done: core inbox, gateway, MySQL-verified chat orders, WhatsApp Embedded Signup (+coexistence flag), templates, health verify, queue/scheduler smoke, two-installation isolation test, polling race fix.
Open: live provider tests, Meta approvals, Embedded Signup v4, coexistence history sync, WebSockets, Instagram Login onboarding, TikTok.
Local verification DB: MariaDB 10.11 throwaway (misron_test) built from empty_misron.sql with data tables truncated; env in /tmp (not committed).

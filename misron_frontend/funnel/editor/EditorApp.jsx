"use client";

import { useEffect, useMemo, useState } from "react";
import { Lock } from "lucide-react";
import { PageSpinner } from "@/app/components/ui";
import { createApi } from "./api.js";
import Editor from "./Editor.jsx";
import { clearSession, currentToken, readSession, takeHandoffCode, writeSession } from "./session.js";

/**
 * Entry point of the funnel editor route. Signs in with the one-time code
 * from the admin panel (or the tab's existing session), loads the funnel and
 * the block registry, then hands over to the editor.
 */
export default function EditorApp({ funnelId }) {
  const [phase, setPhase] = useState({ name: "loading" });
  const api = useMemo(() => createApi({ getToken: currentToken }), []);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      const code = takeHandoffCode();
      let session = null;

      if (code) {
        try {
          const result = await api.exchange(code);
          session = { token: result.token, expiresAt: result.expires_at, returnUrl: result.user?.return_url ?? null };
          writeSession(session);
        } catch (error) {
          clearSession();
          const reason = error.code === "invalid_handoff" ? "expired-link" : error.status === 429 ? "too-many" : unreachable(error) ? "unreachable" : "error";
          if (!cancelled) setPhase({ name: "denied", reason, message: error.message });
          return;
        }
      } else {
        session = readSession();
      }

      if (!session) {
        if (!cancelled) setPhase({ name: "denied", reason: "no-session" });
        return;
      }

      try {
        const [me, registry, funnel] = await Promise.all([api.me(), api.registry(), api.funnel(funnelId)]);
        if (cancelled) return;
        const returnUrl = me.user?.return_url ?? session.returnUrl ?? null;
        writeSession({ ...session, returnUrl });
        setPhase({ name: "ready", funnel: funnel.data, registry: registry.registry, user: me.user, returnUrl });
      } catch (error) {
        if (cancelled) return;
        if (error.status === 401) {
          clearSession();
          setPhase({ name: "denied", reason: "expired", returnUrl: session.returnUrl });
        } else if (error.status === 403) {
          setPhase({ name: "denied", reason: "forbidden", returnUrl: session.returnUrl });
        } else if (error.status === 404) {
          setPhase({ name: "denied", reason: "not-found", returnUrl: session.returnUrl });
        } else if (unreachable(error)) {
          setPhase({ name: "denied", reason: "unreachable", returnUrl: session.returnUrl });
        } else {
          setPhase({ name: "denied", reason: "error", message: error.message, returnUrl: session.returnUrl });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [api, funnelId]);

  if (phase.name === "loading") {
    return (
      <div className="flex h-dvh items-center justify-center bg-canvas">
        <PageSpinner label="Opening the editor" />
      </div>
    );
  }

  if (phase.name === "denied") return <AccessScreen {...phase} />;

  return <Editor funnel={phase.funnel} registry={phase.registry} user={phase.user} api={api} returnUrl={phase.returnUrl} />;
}

// The /api/v2 proxy answers with a bare 5xx (no JSON from Laravel) when the
// backend is down or BACKEND_API_URL points at the wrong address.
function unreachable(error) {
  return error.status === 0 || (error.status >= 500 && !error.body?.message);
}

const MESSAGES = {
  "no-session": {
    title: "Open the editor from the admin panel",
    body: "For security, the funnel editor opens only from Sales Funnels in the admin panel. Go there and click Open Visual Builder on the funnel.",
  },
  "expired-link": {
    title: "This editor link has expired",
    body: "Editor links work once and only for a minute. Go back to Sales Funnels in the admin panel and click Open Visual Builder again.",
  },
  expired: {
    title: "Your editor session has ended",
    body: "Open the funnel again from Sales Funnels in the admin panel to continue.",
  },
  forbidden: {
    title: "You don't have access to this funnel",
    body: "Your staff role does not allow viewing sales funnels. Ask an administrator for access.",
  },
  "not-found": {
    title: "Funnel not found",
    body: "It may have been deleted. Go back to Sales Funnels to see the current list.",
  },
  "too-many": {
    title: "Too many sign-ins just now",
    body: "The editor was opened many times in a short while. Wait a minute, then open the funnel again from Sales Funnels in the admin panel.",
  },
  unreachable: {
    title: "The editor cannot reach the admin server",
    body: "The storefront could not connect to the Misron admin server. Make sure it is running, then open the funnel again from Sales Funnels. (Technical: the storefront's BACKEND_API_URL must point at the admin server.)",
  },
  error: {
    title: "The editor could not load",
    body: "Check your connection and reload the page.",
  },
};

function AccessScreen({ reason, message, returnUrl }) {
  const text = MESSAGES[reason] ?? MESSAGES.error;
  return (
    <div className="flex h-dvh items-center justify-center bg-canvas px-4" data-testid="editor-access" data-reason={reason}>
      <div className="flex max-w-[440px] flex-col items-center gap-3 rounded-lg bg-surface p-8 text-center shadow-e2">
        <span className="inline-flex size-12 items-center justify-center rounded-full bg-canvas-sunken text-ink-secondary">
          <Lock size={22} aria-hidden="true" />
        </span>
        <h1 className="m-0 text-[20px] font-semibold">{text.title}</h1>
        <p className="m-0 text-[14px] text-ink-secondary">{text.body}</p>
        {reason === "error" && message ? <p className="m-0 text-[12px] text-ink-tertiary">{message}</p> : null}
        {returnUrl ? (
          <a className="btn btn-md btn-primary mt-2" href={returnUrl}>
            Go to Sales Funnels
          </a>
        ) : null}
      </div>
    </div>
  );
}

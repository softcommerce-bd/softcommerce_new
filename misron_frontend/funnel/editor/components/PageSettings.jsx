"use client";

import { useState } from "react";
import toast from "react-hot-toast";
import { cx } from "../../core/values.js";
import { useEditor } from "../useEditorState.js";
import { ColorField, ImageField, NumberField, SelectField, TextareaField, TextField, ToggleField } from "./fields.jsx";

/** Search engines cut titles and descriptions near these lengths. */
const TITLE_HINT = 60;
const DESCRIPTION_HINT = 160;

/**
 * Shown when no block is selected: the funnel's details and its search and
 * sharing settings (saved straight to the funnel record, effective at once),
 * and page-wide design settings (saved with the draft).
 */
export default function PageSettings() {
  const { state } = useEditor();
  return <PageSettingsBody key={settingsKey(state.details)} />;
}

/** "shop.example" from a full address; a stand-in while the address is unknown. */
function hostOf(url) {
  try {
    return new URL(url).host;
  } catch {
    return "yourshop.com";
  }
}

/** The forms below start from the saved values; a new key re-reads them after a save or reload. */
function settingsKey(details) {
  return [details.title, details.slug, details.metaTitle, details.metaDescription, details.ogImageUploadId, details.seo.noindex, details.seo.ogTitle, details.seo.ogDescription, details.seo.canonicalUrl].join("\u0001");
}

function PageSettingsBody() {
  const { state, actions } = useEditor();
  const settings = state.doc.page.settings ?? {};
  const saved = state.details;
  const [details, setDetails] = useState({ title: saved.title, slug: saved.slug });
  const [seo, setSeo] = useState({
    metaTitle: saved.metaTitle,
    metaDescription: saved.metaDescription,
    ogTitle: saved.seo.ogTitle,
    ogDescription: saved.seo.ogDescription,
    noindex: saved.seo.noindex,
    canonicalUrl: saved.seo.canonicalUrl,
  });
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState(null);

  const detailsDirty = details.title !== saved.title || details.slug !== saved.slug;
  const seoDirty =
    seo.metaTitle !== saved.metaTitle ||
    seo.metaDescription !== saved.metaDescription ||
    seo.ogTitle !== saved.seo.ogTitle ||
    seo.ogDescription !== saved.seo.ogDescription ||
    seo.noindex !== saved.seo.noindex ||
    seo.canonicalUrl !== saved.seo.canonicalUrl;

  const save = async (which, patch, message) => {
    setBusy(which);
    setError(null);
    try {
      await actions.saveDetails(patch);
      toast.success(message);
    } catch (e) {
      setError(e.body?.errors?.[0]?.message ?? e.message);
    } finally {
      setBusy(null);
    }
  };

  const previewTitle = seo.metaTitle || details.title || "Page title";
  const previewDescription = seo.metaDescription || "Add a short description so people know what they will find on this page.";
  const shareTitle = seo.ogTitle || previewTitle;
  const shareDescription = seo.ogDescription || seo.metaDescription || "";
  const host = hostOf(state.details.publicUrl);
  const address = `${host} › funnel › ${details.slug}`;

  return (
    <div className="flex h-full flex-col overflow-y-auto" data-testid="page-settings">
      <div className="border-b border-hairline px-4 py-3">
        <h2 className="m-0 text-[15px] font-semibold">Page settings</h2>
        <p className="m-0 mt-0.5 text-[12px] text-ink-tertiary">Select a block on the page to edit it.</p>
      </div>

      <fieldset disabled={!state.canEdit} className="m-0 flex flex-col gap-4 border-0 border-b border-hairline p-4">
        <legend className="sr-only">Funnel details</legend>
        <TextField label="Page title" value={details.title} maxLength={200} onChange={(title) => setDetails({ ...details, title })} testId="page-title" />
        <TextField
          label="Page address"
          value={details.slug}
          maxLength={80}
          help={state.details.status === "published" ? "Changing the address of a published page breaks links already shared in ads." : "/funnel/ followed by this name."}
          onChange={(slug) => setDetails({ ...details, slug: slug.toLowerCase().replace(/[^a-z0-9-]/g, "-") })}
          testId="page-slug"
        />
        <button
          type="button"
          className="btn btn-sm btn-secondary self-start"
          onClick={() => save("details", { title: details.title, slug: details.slug }, "Page details saved")}
          disabled={!detailsDirty || busy !== null || !details.title.trim()}
          data-testid="save-details"
        >
          {busy === "details" ? "Saving…" : "Save details"}
        </button>
      </fieldset>

      <fieldset disabled={!state.canEdit} className="m-0 flex flex-col gap-4 border-0 border-b border-hairline p-4" data-testid="seo-settings">
        <legend className="mb-1 text-[12px] font-semibold uppercase tracking-wide text-ink-secondary">Search and sharing</legend>
        <p className="m-0 text-[12px] text-ink-tertiary">These apply to the live page as soon as you save; no need to publish again.</p>

        <div className="rounded-[8px] border border-hairline bg-white p-3" aria-label="How the page may look in search results" data-testid="seo-snippet">
          <div className="truncate text-[12px] text-[#4d5156]">{address}</div>
          <div className="truncate text-[17px] leading-snug text-[#1a0dab]">{previewTitle}</div>
          <div className="line-clamp-2 text-[13px] text-[#4d5156]">{previewDescription}</div>
        </div>

        <TextField label="Title for search results" value={seo.metaTitle} maxLength={255} placeholder="Uses the page title when empty" onChange={(metaTitle) => setSeo({ ...seo, metaTitle })} testId="seo-title" />
        <Counter value={seo.metaTitle || details.title} hint={TITLE_HINT} />
        <TextareaField label="Description for search results" value={seo.metaDescription} maxLength={500} onChange={(metaDescription) => setSeo({ ...seo, metaDescription })} testId="seo-description" />
        <Counter value={seo.metaDescription} hint={DESCRIPTION_HINT} />

        <ImageField
          label="Picture shown when the link is shared"
          help="Facebook, WhatsApp and Messenger show this. A wide picture, about 1200 × 630, works best."
          value={saved.ogImageUrl ? { uploadId: saved.ogImageUploadId, url: saved.ogImageUrl } : undefined}
          onChange={(image) => {
            // The field also reports edits to the picture's description, which a share picture does not have.
            if ((image?.uploadId ?? null) === (saved.ogImageUploadId ?? null)) return;
            save("image", { og_image_upload_id: image?.uploadId ?? null }, image ? "Share picture saved" : "Share picture removed");
          }}
          testId="seo-image"
        />

        <div className="rounded-[8px] border border-hairline bg-white" aria-label="How a shared link may look" data-testid="seo-social">
          {saved.ogImageUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={saved.ogImageUrl} alt="" className="aspect-[1.91/1] w-full rounded-t-[8px] object-cover" />
          ) : (
            <div className="flex aspect-[1.91/1] w-full items-center justify-center rounded-t-[8px] bg-canvas-sunken text-[12px] text-ink-tertiary">No share picture</div>
          )}
          <div className="border-t border-hairline px-3 py-2">
            <div className="truncate text-[11px] uppercase text-ink-tertiary">{host}</div>
            <div className="truncate text-[14px] font-semibold">{shareTitle}</div>
            {shareDescription ? <div className="line-clamp-2 text-[12px] text-ink-secondary">{shareDescription}</div> : null}
          </div>
        </div>

        <TextField label="Share title (optional)" value={seo.ogTitle} maxLength={255} placeholder="Uses the search title when empty" onChange={(ogTitle) => setSeo({ ...seo, ogTitle })} />
        <TextareaField label="Share description (optional)" value={seo.ogDescription} maxLength={500} onChange={(ogDescription) => setSeo({ ...seo, ogDescription })} />
        <ToggleField label="Hide this page from search engines" help="Use it for pages that only ad visitors should find." value={seo.noindex} onChange={(noindex) => setSeo({ ...seo, noindex })} testId="seo-noindex" />
        <TextField label="Canonical address (advanced)" value={seo.canonicalUrl} maxLength={500} placeholder="Leave empty unless you copy this page elsewhere" help="A full https:// address that search engines should treat as the main one." onChange={(canonicalUrl) => setSeo({ ...seo, canonicalUrl })} testId="seo-canonical" />

        {error ? <p className="m-0 text-[12px] text-danger" role="alert" data-testid="settings-error">{error}</p> : null}
        <button
          type="button"
          className="btn btn-sm btn-secondary self-start"
          onClick={() =>
            save(
              "seo",
              {
                meta_title: seo.metaTitle || null,
                meta_description: seo.metaDescription || null,
                og_title: seo.ogTitle || null,
                og_description: seo.ogDescription || null,
                noindex: seo.noindex,
                canonical_url: seo.canonicalUrl || null,
              },
              "Search and sharing settings saved",
            )
          }
          disabled={!seoDirty || busy !== null}
          data-testid="seo-save"
        >
          {busy === "seo" ? "Saving…" : "Save search and sharing"}
        </button>
      </fieldset>

      <fieldset disabled={!state.canEdit} className="m-0 flex flex-col gap-4 border-0 p-4">
        <legend className="mb-1 text-[12px] font-semibold uppercase tracking-wide text-ink-secondary">Design</legend>
        <ColorField label="Accent colour (buttons, badges)" value={settings.accentColor} placeholder="Store colour" onChange={(v) => actions.setPageSetting("accentColor", v)} testId="page-accent" />
        <ColorField label="Page background" value={settings.backgroundColor} placeholder="#ffffff" onChange={(v) => actions.setPageSetting("backgroundColor", v)} />
        <ColorField label="Text colour" value={settings.textColor} placeholder="Default" onChange={(v) => actions.setPageSetting("textColor", v)} />
        <SelectField
          label="Font"
          value={settings.fontFamily ?? "hind_siliguri"}
          options={[["hind_siliguri", "Hind Siliguri (Bengali + English)"], ["system", "Phone default"], ["serif", "Serif"]]}
          onChange={(v) => actions.setPageSetting("fontFamily", v === "hind_siliguri" ? undefined : v)}
        />
        <NumberField label="Content width" value={settings.maxWidth} min={320} max={1600} step={10} unit="px" placeholder="1140" onChange={(v) => actions.setPageSetting("maxWidth", v)} />
        <SelectField
          label="Page language"
          value={settings.language ?? "bn"}
          options={[["bn", "বাংলা (Bengali)"], ["en", "English"]]}
          onChange={(v) => actions.setPageSetting("language", v === "bn" ? undefined : v)}
        />
      </fieldset>
    </div>
  );
}

/** Length against the point where search engines start cutting the text. */
function Counter({ value, hint }) {
  const length = (value ?? "").length;
  return (
    <p className={cx("m-0 -mt-2 text-[11px]", length > hint ? "text-warning" : "text-ink-tertiary")} data-testid="seo-counter">
      {length} / about {hint} characters{length > hint ? " — search engines may cut the rest" : ""}
    </p>
  );
}

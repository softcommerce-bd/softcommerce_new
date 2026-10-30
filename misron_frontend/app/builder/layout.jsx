// The funnel editor is an admin tool on the storefront's domain: keep it out
// of search engines, and send no Referer from it, so the one-time sign-in code
// in its first URL never reaches another site.
export const metadata = {
  title: "Funnel editor",
  robots: { index: false, follow: false },
  referrer: "no-referrer",
};

export default function BuilderLayout({ children }) {
  return children;
}

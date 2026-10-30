import { notFound } from "next/navigation";
import EditorLoader from "./EditorLoader";

export default async function FunnelEditorPage({ params }) {
  const { id } = await params;
  if (!/^\d{1,18}$/.test(id)) notFound();
  return <EditorLoader funnelId={Number(id)} />;
}

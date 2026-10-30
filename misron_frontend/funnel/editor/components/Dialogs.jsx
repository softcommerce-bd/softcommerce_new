"use client";

import { CircleAlert } from "lucide-react";
import Modal from "@/app/components/ui/Modal";
import { locatePath } from "../../core/paths.js";
import { findNode } from "../../core/tree.js";
import { nodeTitle } from "../registry.js";
import { useEditor } from "../useEditorState.js";

/**
 * Problems the server found, each linked to the block it is about.
 */
export function ProblemsDialog({ open, onClose, title, intro, problems }) {
  const { state, actions } = useEditor();
  return (
    <Modal open={open} onClose={onClose} title={title} size="lg">
      <div className="flex flex-col gap-3" data-testid="problems-dialog">
        {intro ? <p className="m-0 text-[14px] text-ink-secondary">{intro}</p> : null}
        <ul className="m-0 flex max-h-[50vh] list-none flex-col gap-2 overflow-y-auto p-0">
          {problems.map((problem, index) => {
            const { nodeId } = locatePath(state.doc, problem.path);
            const node = nodeId ? findNode(state.doc, nodeId)?.node : null;
            return (
              <li key={index} className="flex items-start gap-2 rounded-[6px] border border-hairline px-3 py-2">
                <CircleAlert size={16} className="mt-0.5 shrink-0 text-danger" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                  <p className="m-0 text-[14px] text-ink">{problem.message}</p>
                  {node ? (
                    <button
                      type="button"
                      className="mt-0.5 text-[12px] text-[#1f6feb] hover:underline"
                      onClick={() => {
                        actions.select(node.id);
                        onClose();
                      }}
                    >
                      Go to: {nodeTitle(node)}
                    </button>
                  ) : null}
                </div>
              </li>
            );
          })}
        </ul>
        <div className="flex justify-end">
          <button type="button" className="btn btn-md btn-secondary" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </Modal>
  );
}

export function ConflictDialog({ open }) {
  const { actions } = useEditor();
  return (
    <Modal open={open} onClose={() => {}} title="This funnel was changed in another window" size="md" showClose={false} closeOnBackdrop={false}>
      <div className="flex flex-col gap-4" data-testid="conflict-dialog">
        <p className="m-0 text-[14px] text-ink-secondary">
          Someone (or another tab) saved a newer version while you were editing. Choose which version to keep.
        </p>
        <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
          <button type="button" className="btn btn-md btn-secondary" onClick={() => actions.reload()} data-testid="conflict-reload">
            Load their version
          </button>
          <button type="button" className="btn btn-md btn-primary" onClick={() => actions.keepMine()} data-testid="conflict-keep-mine">
            Keep my version
          </button>
        </div>
        <p className="m-0 text-[12px] text-ink-tertiary">Keeping your version replaces theirs. Loading theirs discards your unsaved changes.</p>
      </div>
    </Modal>
  );
}

export function SessionEndedDialog({ open, returnUrl }) {
  return (
    <Modal open={open} onClose={() => {}} title="Your editor session has ended" size="md" showClose={false} closeOnBackdrop={false}>
      <div className="flex flex-col gap-4" data-testid="session-ended-dialog">
        <p className="m-0 text-[14px] text-ink-secondary">
          For security, editor sessions end after a few hours. Changes saved before now are kept. Open the funnel again from the admin panel to continue.
        </p>
        {returnUrl ? (
          <a className="btn btn-md btn-primary self-end" href={returnUrl}>
            Go to Sales Funnels
          </a>
        ) : null}
      </div>
    </Modal>
  );
}

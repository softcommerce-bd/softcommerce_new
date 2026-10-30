"use client";

import { createContext, useContext } from "react";

/**
 * What is being dragged and where it would land. Kept apart from the editor
 * state because it changes on every pointer move and must not cause the
 * document, history or autosave logic to re-run.
 */
export const DragStateContext = createContext({ activeDrag: null, dropTarget: null });

export function useDragState() {
  return useContext(DragStateContext);
}

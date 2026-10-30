"use client";

import { useLayoutEffect, useRef } from "react";
import { cx } from "../../core/values.js";

/**
 * Text that can be typed into directly on the canvas.
 *
 * The element is uncontrolled while focused: React never rewrites its text
 * during typing (which would move the caret). Its content is synced from the
 * document only when the value changes from elsewhere, such as undo.
 *
 * It becomes editable once its block is selected, so the first click selects
 * the block and the next places the caret.
 */
export default function InlineText({
  as = "span",
  className,
  value,
  multiline = false,
  placeholder,
  optional = false,
  selected,
  editable,
  maxLength,
  onChange,
}) {
  const ref = useRef(null);

  useLayoutEffect(() => {
    const el = ref.current;
    if (el && document.activeElement !== el && el.textContent !== value) {
      el.textContent = value;
    }
  });

  if (optional && !value && !selected) return null;

  const Tag = as;

  const handleInput = (event) => {
    const el = event.currentTarget;
    let text = el.textContent ?? "";
    if (!multiline && text.includes("\n")) text = text.replace(/\n+/g, " ");
    if (maxLength && text.length > maxLength) {
      text = text.slice(0, maxLength);
      el.textContent = text;
      placeCaretAtEnd(el);
    }
    onChange(text);
  };

  const handleKeyDown = (event) => {
    if (event.key === "Enter" && !multiline) {
      event.preventDefault();
      event.currentTarget.blur();
    } else if (event.key === "Escape") {
      event.preventDefault();
      event.currentTarget.blur();
    }
    // Typing must not trigger editor shortcuts (Delete removes text, not the block).
    event.stopPropagation();
  };

  const handlePaste = (event) => {
    event.preventDefault();
    const text = event.clipboardData.getData("text/plain");
    document.execCommand("insertText", false, multiline ? text : text.replace(/\s*\n\s*/g, " "));
  };

  return (
    <Tag
      ref={ref}
      className={cx(
        className,
        multiline && "whitespace-pre-line",
        "fn-inline-text cursor-text rounded-[2px] outline-none",
        editable && selected && "focus:bg-[rgba(31,111,235,.06)] focus:shadow-[0_0_0_1px_rgba(31,111,235,.45)]",
        "empty:before:pointer-events-none empty:before:opacity-40 empty:before:content-[attr(data-placeholder)]",
      )}
      data-placeholder={placeholder}
      contentEditable={editable && selected ? "plaintext-only" : false}
      suppressContentEditableWarning
      spellCheck={false}
      onInput={handleInput}
      onKeyDown={handleKeyDown}
      onPaste={handlePaste}
    />
  );
}

function placeCaretAtEnd(el) {
  const range = document.createRange();
  range.selectNodeContents(el);
  range.collapse(false);
  const selection = window.getSelection();
  selection?.removeAllRanges();
  selection?.addRange(range);
}

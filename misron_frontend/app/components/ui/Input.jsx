"use client";

import { forwardRef, useId, useState } from "react";
import { Eye, EyeOff } from "lucide-react";

/**
 * Text field with an optional leading icon, label, hint and error.
 *
 * The label is wired to the input with a generated id, and the error message
 * is announced through aria-describedby + role="alert" — so a failed submit is
 * audible, not only visible.
 *
 * Password fields get a reveal toggle for free: pass type="password".
 */
const Input = forwardRef(function Input(
  {
    label,
    hint,
    error,
    icon: Icon,
    trailing,
    type = "text",
    className = "",
    containerClassName = "",
    id: providedId,
    ...props
  },
  ref,
) {
  const generatedId = useId();
  const id = providedId ?? generatedId;
  const [revealed, setRevealed] = useState(false);

  const isPassword = type === "password";
  const inputType = isPassword && revealed ? "text" : type;

  const describedBy = [error ? `${id}-error` : null, hint ? `${id}-hint` : null]
    .filter(Boolean)
    .join(" ");

  // Icons sit inside the field, so the text needs padding to clear them.
  const paddingLeft = Icon ? "pl-11" : "";
  const paddingRight = isPassword || trailing ? "pr-11" : "";

  return (
    <div className={containerClassName}>
      {label && (
        <label htmlFor={id} className="field-label">
          {label}
        </label>
      )}

      <div className="relative">
        {Icon && (
          <Icon
            size={17}
            className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-tertiary"
            aria-hidden="true"
          />
        )}

        <input
          ref={ref}
          id={id}
          type={inputType}
          className={[
            "field",
            paddingLeft,
            paddingRight,
            error ? "field-error" : "",
            className,
          ]
            .filter(Boolean)
            .join(" ")}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy || undefined}
          {...props}
        />

        {isPassword && (
          <button
            type="button"
            onClick={() => setRevealed((v) => !v)}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-ink-tertiary transition-colors hover:text-ink"
            aria-label={revealed ? "Hide password" : "Show password"}
          >
            {revealed ? <EyeOff size={17} /> : <Eye size={17} />}
          </button>
        )}

        {!isPassword && trailing && (
          <div className="absolute right-3 top-1/2 -translate-y-1/2">{trailing}</div>
        )}
      </div>

      {error ? (
        <p id={`${id}-error`} role="alert" className="field-error-text">
          {error}
        </p>
      ) : (
        hint && (
          <p id={`${id}-hint`} className="field-hint">
            {hint}
          </p>
        )
      )}
    </div>
  );
});

export default Input;

"use client";

import { forwardRef, useId } from "react";
import { ChevronDown } from "lucide-react";

/**
 * Dropdown built on a native <select>.
 *
 * Native keeps the platform picker on mobile — a wheel on iOS, a sheet on
 * Android — which no custom listbox matches for one-handed use. The browser's
 * own arrow is removed and a lucide chevron drawn in its place so the control
 * matches the rest of the form set.
 */
const Select = forwardRef(function Select(
  {
    label,
    hint,
    error,
    options = [],
    children,
    className = "",
    containerClassName = "",
    id: providedId,
    ...props
  },
  ref,
) {
  const generatedId = useId();
  const id = providedId ?? generatedId;

  return (
    <div className={containerClassName}>
      {label && (
        <label htmlFor={id} className="field-label">
          {label}
        </label>
      )}

      <div className="relative">
        <select
          ref={ref}
          id={id}
          className={[
            "field cursor-pointer appearance-none pr-10",
            error ? "field-error" : "",
            className,
          ]
            .filter(Boolean)
            .join(" ")}
          aria-invalid={error ? true : undefined}
          {...props}
        >
          {children ??
            options.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
        </select>

        <ChevronDown
          size={16}
          className="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-ink-tertiary"
          aria-hidden="true"
        />
      </div>

      {error ? (
        <p role="alert" className="field-error-text">
          {error}
        </p>
      ) : (
        hint && <p className="field-hint">{hint}</p>
      )}
    </div>
  );
});

export default Select;

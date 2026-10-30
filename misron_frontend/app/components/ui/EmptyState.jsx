import Button from "./Button";

/**
 * The screen shown when a list has nothing in it.
 *
 * An empty state should say what is missing, why, and what to do next. The
 * old pages either showed a bare "No products found" string or nothing at all.
 * The icon sits in a soft tinted disc rather than floating alone.
 */
export default function EmptyState({
  icon: Icon,
  title,
  description,
  action,
  secondaryAction,
  className = "",
}) {
  return (
    <div
      className={[
        "flex flex-col items-center justify-center px-6 py-16 text-center",
        className,
      ].join(" ")}
    >
      {Icon && (
        <div
          className="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-canvas-sunken"
          aria-hidden="true"
        >
          <Icon size={26} className="text-ink-tertiary" strokeWidth={1.5} />
        </div>
      )}

      <h3 className="type-title-3 text-ink">{title}</h3>

      {description && (
        <p className="type-callout mt-2 max-w-sm text-ink-tertiary">{description}</p>
      )}

      {(action || secondaryAction) && (
        <div className="mt-7 flex flex-wrap items-center justify-center gap-3">
          {action && (
            <Button
              href={action.href}
              onClick={action.onClick}
              iconLeft={action.icon}
              variant="primary"
            >
              {action.label}
            </Button>
          )}
          {secondaryAction && (
            <Button
              href={secondaryAction.href}
              onClick={secondaryAction.onClick}
              iconLeft={secondaryAction.icon}
              variant="secondary"
            >
              {secondaryAction.label}
            </Button>
          )}
        </div>
      )}
    </div>
  );
}

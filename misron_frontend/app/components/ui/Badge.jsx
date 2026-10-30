/**
 * Small status / label pill.
 *
 * `tone` names the meaning, never the colour, so a status can be recoloured
 * across the app in one place.
 */

const TONES = {
  neutral: "badge-neutral",
  sale: "badge-sale",
  success: "badge-success",
  warning: "badge-warning",
  danger: "badge-danger",
  info: "badge-info",
  solid: "badge-solid",
};

export default function Badge({
  children,
  tone = "neutral",
  icon: Icon,
  className = "",
  ...props
}) {
  return (
    <span
      className={["badge", TONES[tone] ?? TONES.neutral, className].join(" ")}
      {...props}
    >
      {Icon && <Icon size={11} aria-hidden="true" />}
      {children}
    </span>
  );
}

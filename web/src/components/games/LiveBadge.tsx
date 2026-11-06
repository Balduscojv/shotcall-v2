/** Etiqueta "AO VIVO" com o ponto pulsando, como no canvas. */
export function LiveBadge({ size = "md" }: { size?: "sm" | "md" }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-lg border-2 border-ink bg-pink font-display text-ink shadow-hard-sm ${
        size === "sm" ? "px-1.5 py-0.5 text-[10px]" : "px-2.5 py-1 text-xs"
      }`}
    >
      <span className="size-2 animate-pulse-dot rounded-full bg-ink" aria-hidden="true" />
      AO VIVO
    </span>
  );
}

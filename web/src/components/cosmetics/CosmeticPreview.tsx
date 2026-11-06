import { bannerLook, frameLook, titleLook } from "@/lib/cosmetics";
import type { Cosmetic, CosmeticLook, CosmeticSlot } from "@/lib/types";

/** Título-prêmio ao lado do @. */
export function TitleBadge({ title, className = "" }: { title: CosmeticLook & { name: string }; className?: string }) {
  const look = titleLook(title);
  return (
    <span
      className={`inline-flex items-center rounded-md border-2 border-ink px-2 py-0.5 font-display text-[11px] leading-none tracking-wide shadow-hard-sm ${look.className} ${className}`}
      style={look.style}
    >
      {title.name.toUpperCase()}
    </span>
  );
}

type Previewable = CosmeticLook & { name: string; slot: CosmeticSlot };

/** Miniatura do prêmio para os cards da coleção e da moderação. */
export function CosmeticPreview({ cosmetic, initials = "SC" }: { cosmetic: Previewable | Cosmetic; initials?: string }) {
  if (cosmetic.slot === "BANNER") {
    const look = bannerLook(cosmetic);
    return (
      <div className={`aspect-[1200/280] w-full rounded-xl border-2 border-ink ${look?.className ?? ""}`} style={look?.style} aria-hidden="true" />
    );
  }

  if (cosmetic.slot === "FRAME") {
    const look = frameLook(cosmetic);
    return (
      <div className="flex aspect-[1200/280] w-full items-center justify-center rounded-xl border-2 border-ink bg-ground" aria-hidden="true">
        <span
          className={`inline-flex size-12 items-center justify-center rounded-full border-4 bg-surface font-display text-base text-chalk ${look.className}`}
          style={look.style}
        >
          {initials}
        </span>
      </div>
    );
  }

  return (
    <div className="flex aspect-[1200/280] w-full items-center justify-center gap-2 rounded-xl border-2 border-ink bg-ground" aria-hidden="true">
      <span className="text-sm font-extrabold text-cyan">@voce</span>
      <TitleBadge title={cosmetic} />
    </div>
  );
}

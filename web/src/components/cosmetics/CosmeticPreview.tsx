import { bannerClass, frameClass, titleClass } from "@/lib/cosmetics";
import type { Cosmetic, EquippedItem } from "@/lib/types";

/** Título-prêmio ao lado do @. */
export function TitleBadge({ title, className = "" }: { title: EquippedItem | Pick<Cosmetic, "name" | "asset_key" | "rarity">; className?: string }) {
  return (
    <span
      className={`inline-flex items-center rounded-md border-2 border-ink px-2 py-0.5 font-display text-[11px] leading-none tracking-wide shadow-hard-sm ${titleClass(title)} ${className}`}
    >
      {title.name.toUpperCase()}
    </span>
  );
}

/** Miniatura do prêmio para os cards da coleção e da moderação. */
export function CosmeticPreview({ cosmetic, initials = "SC" }: { cosmetic: Cosmetic; initials?: string }) {
  if (cosmetic.slot === "BANNER") {
    return <div className={`aspect-[1200/280] w-full rounded-xl border-2 border-ink ${bannerClass(cosmetic)}`} aria-hidden="true" />;
  }

  if (cosmetic.slot === "FRAME") {
    return (
      <div className="flex aspect-[1200/280] w-full items-center justify-center rounded-xl border-2 border-ink bg-ground" aria-hidden="true">
        <span
          className={`inline-flex size-12 items-center justify-center rounded-full border-4 bg-surface font-display text-base text-chalk ${frameClass(cosmetic)}`}
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

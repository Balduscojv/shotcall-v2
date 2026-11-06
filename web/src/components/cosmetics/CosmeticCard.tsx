import type { ReactNode } from "react";
import { formatDay, rarityLabel, rarityStyle, slotLabel } from "@/lib/labels";
import type { Cosmetic } from "@/lib/types";
import { CosmeticPreview } from "./CosmeticPreview";

/** Card de prêmio: prévia, raridade, nome, de onde veio e uma área de ação embaixo. */
export function CosmeticCard({
  cosmetic,
  initials,
  note,
  children,
}: {
  cosmetic: Cosmetic;
  initials?: string;
  /** Texto no rodapé; por padrão, o motivo e a data do desbloqueio. */
  note?: ReactNode;
  children?: ReactNode;
}) {
  const footer =
    note ?? (cosmetic.reason && cosmetic.unlocked_at ? `${cosmetic.reason} · ${formatDay(cosmetic.unlocked_at)}` : null);

  return (
    <li
      className={`flex flex-col gap-3 rounded-2xl border-[3px] bg-panel p-4 ${cosmetic.equipped ? "border-lime shadow-hard" : "border-ink"}`}
    >
      <CosmeticPreview cosmetic={cosmetic} initials={initials} />
      <div className="flex items-center justify-between gap-2">
        <span className={`rounded-md border-2 border-ink px-2 py-0.5 font-display text-[11px] ${rarityStyle[cosmetic.rarity]}`}>
          {rarityLabel[cosmetic.rarity].toUpperCase()}
        </span>
        {cosmetic.equipped && <span className="text-xs font-extrabold text-lime">EM USO</span>}
      </div>
      <div className="flex flex-col gap-0.5">
        <span className="text-sm font-bold text-subtle">{slotLabel[cosmetic.slot]}</span>
        <span className="text-lg leading-snug font-extrabold">{cosmetic.name}</span>
        {cosmetic.description && <span className="text-sm text-muted">{cosmetic.description}</span>}
      </div>
      {footer && <p className="mt-auto text-sm text-subtle">{footer}</p>}
      {children && <div className={footer ? "" : "mt-auto"}>{children}</div>}
    </li>
  );
}

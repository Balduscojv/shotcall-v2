import { rarityLabel, rarityStyle, slotLabel } from "@/lib/labels";
import type { LockedMission } from "@/lib/types";

/** Missão secreta que ainda não foi liberada: "???" com a dica vaga, sem mostrar o prêmio. */
export function LockedMissionCard({ mission }: { mission: LockedMission }) {
  return (
    <li className="flex flex-col gap-3 rounded-2xl border-[3px] border-dashed border-surface bg-panel/60 p-4">
      <div
        className="fence flex aspect-[1200/280] w-full items-center justify-center rounded-xl border-2 border-ink"
        aria-hidden="true"
      >
        <span className="font-display text-3xl tracking-[0.3em] text-subtle">???</span>
      </div>
      <div className="flex items-center justify-between gap-2">
        <span className={`rounded-md border-2 border-ink px-2 py-0.5 font-display text-[11px] ${rarityStyle[mission.rarity]}`}>
          {rarityLabel[mission.rarity].toUpperCase()}
        </span>
        <span className="text-xs font-extrabold text-subtle">MISSÃO SECRETA</span>
      </div>
      <div className="flex flex-col gap-0.5">
        <span className="text-sm font-bold text-subtle">{slotLabel[mission.slot]}</span>
        <span className="text-lg leading-snug font-extrabold text-muted">???</span>
        {mission.hint && <span className="font-marker text-base text-muted">“{mission.hint}”</span>}
      </div>
    </li>
  );
}

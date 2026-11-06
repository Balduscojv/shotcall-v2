"use client";

import { useState } from "react";
import { periodLabel, pct } from "@/lib/games";
import type { Shot, Team } from "@/lib/types";

// Quadra da NBA: 94 × 50 pés → 940 × 500 no SVG. Arremessos vêm em % do comprimento e da largura.
const W = 940;
const H = 500;

function HalfCourt({ mirror }: { mirror?: boolean }) {
  return (
    <g transform={mirror ? `translate(${W} 0) scale(-1 1)` : undefined}>
      {/* garrafão, lance livre, arco de 3 com as retas do canto, tabela e aro */}
      <rect x="0" y="170" width="190" height="160" />
      <circle cx="190" cy="250" r="60" />
      <path d="M0 30 H140 A237.5 237.5 0 0 1 140 470 H0" />
      <line x1="40" y1="220" x2="40" y2="280" />
      <circle cx="52.5" cy="250" r="7.5" stroke="var(--color-orange)" strokeOpacity="1" strokeWidth="4" />
    </g>
  );
}

type Filter = "all" | number;

/** Gráfico de lançamentos: cestas em verde-limão, erros em anel rosa. Filtra por período e por time. */
export function ShotChart({ shots, home, away, periods }: { shots: Shot[]; home: Team; away: Team; periods: number[] }) {
  const [period, setPeriod] = useState<Filter>("all");
  const [team, setTeam] = useState<"all" | number>("all");

  const visible = shots.filter((s) => (period === "all" || s.period === period) && (team === "all" || s.team_id === team));
  const made = visible.filter((s) => s.made).length;

  const chip = (active: boolean) =>
    `h-9 rounded-full px-3.5 text-[13px] ${active ? "border-2 border-ink bg-orange font-extrabold text-ink" : "bg-surface font-bold text-chalk hover:bg-panel"}`;

  return (
    <section aria-labelledby="h-lancamentos" className="flex min-w-0 flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id="h-lancamentos" className="font-display text-lg">LANÇAMENTOS</h2>
        <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrar por período">
          <button type="button" className={chip(period === "all")} aria-pressed={period === "all"} onClick={() => setPeriod("all")}>
            Todos
          </button>
          {periods.map((p) => (
            <button key={p} type="button" className={chip(period === p)} aria-pressed={period === p} onClick={() => setPeriod(p)}>
              {periodLabel(p)}
            </button>
          ))}
        </div>
      </div>

      <div className="relative overflow-hidden rounded-2xl border-[3px] border-ink bg-[#1F3A5F] shadow-hard">
        <svg viewBox={`0 0 ${W} ${H}`} width="100%" role="img" aria-label={`Quadra com ${visible.length} arremessos de quadra: ${made} cestas (pontos verdes) e ${visible.length - made} erros (anéis rosa).`} className="block">
          <g fill="none" stroke="var(--color-chalk)" strokeOpacity=".5" strokeWidth="3">
            <rect x="1.5" y="1.5" width={W - 3} height={H - 3} />
            <line x1={W / 2} y1="0" x2={W / 2} y2={H} />
            <circle cx={W / 2} cy={H / 2} r="60" />
            <HalfCourt />
            <HalfCourt mirror />
          </g>
          {visible.map((s) => {
            const cx = (s.x / 100) * W;
            const cy = (s.y / 100) * H;
            return s.made ? (
              <circle key={s.n} cx={cx} cy={cy} r="9" fill="var(--color-lime)" stroke="var(--color-ink)" strokeWidth="2.5" />
            ) : (
              <circle key={s.n} cx={cx} cy={cy} r="8" fill="none" stroke="var(--color-pink)" strokeWidth="3.5" />
            );
          })}
        </svg>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrar por time">
          {(["all", away, home] as const).map((t) => {
            const id = t === "all" ? "all" : t.id;
            return (
              <button key={String(id)} type="button" className={chip(team === id)} aria-pressed={team === id} onClick={() => setTeam(id)}>
                {t === "all" ? "Os dois" : t.tricode}
              </button>
            );
          })}
        </div>
        <span className="text-sm font-bold text-muted tabular-nums">
          {made}/{visible.length} · {pct(made, visible.length)}
        </span>
      </div>
    </section>
  );
}

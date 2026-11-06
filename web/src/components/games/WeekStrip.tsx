import Link from "next/link";
import { dayOfMonth, weekdayShort } from "@/lib/games";
import type { GamesDay } from "@/lib/types";

const href = (day: string) => `/jogos?data=${day}`;

const arrow = (dir: "prev" | "next") => (
  <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
    <path d={dir === "prev" ? "M11 3 5 9l6 6" : "M7 3l6 6-6 6"} fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
  </svg>
);

/** Faixa da semana (segunda a domingo) com quantos jogos tem em cada dia, e setas para o dia anterior/seguinte com jogo. */
export function WeekStrip({ meta }: { meta: GamesDay["meta"] }) {
  const step = (dir: "prev" | "next") => {
    const target = dir === "prev" ? meta.prev_date : meta.next_date;
    const label = dir === "prev" ? "Dia anterior com jogo" : "Próximo dia com jogo";
    const cls = "inline-flex size-11 shrink-0 items-center justify-center rounded-full border-2";
    return target ? (
      <Link href={href(target)} aria-label={label} className={`${cls} border-ink bg-chalk text-ink no-underline hover:bg-lime`}>
        {arrow(dir)}
      </Link>
    ) : (
      <span aria-hidden="true" className={`${cls} border-surface text-surface`}>
        {arrow(dir)}
      </span>
    );
  };

  return (
    <nav aria-label="Dias da semana" className="flex items-center gap-2">
      {step("prev")}
      <ol className="grid min-w-0 flex-1 grid-cols-7 gap-1.5">
        {meta.week.map((d) => {
          const active = d.date === meta.date;
          const today = d.date === meta.today;
          return (
            <li key={d.date} className="min-w-0">
              <Link
                href={href(d.date)}
                aria-current={active ? "date" : undefined}
                aria-label={`${today ? "Hoje, " : ""}${weekdayShort(d.date)} ${dayOfMonth(d.date)}: ${d.games} jogo(s)${d.live ? `, ${d.live} ao vivo` : ""}`}
                className={`flex h-[68px] flex-col items-center justify-center gap-0.5 rounded-xl border-2 no-underline transition-colors ${
                  active
                    ? "border-ink bg-orange text-ink shadow-hard-sm"
                    : d.games
                      ? "border-surface bg-panel text-chalk hover:border-chalk"
                      : "border-surface bg-transparent text-subtle hover:border-chalk"
                }`}
              >
                <span className="text-[9px] font-extrabold sm:text-[11px] sm:tracking-wide">{today ? "HOJE" : weekdayShort(d.date)}</span>
                <span className="font-score text-2xl leading-none tabular-nums">{dayOfMonth(d.date)}</span>
                <span className={`flex h-2 items-center gap-0.5 ${active ? "text-ink" : d.live ? "text-pink" : "text-muted"}`} aria-hidden="true">
                  {d.live ? (
                    <span className="size-1.5 animate-pulse-dot rounded-full bg-current" />
                  ) : (
                    Array.from({ length: Math.min(d.games, 5) }, (_, i) => <span key={i} className="size-1 rounded-full bg-current" />)
                  )}
                </span>
              </Link>
            </li>
          );
        })}
      </ol>
      {step("next")}
    </nav>
  );
}

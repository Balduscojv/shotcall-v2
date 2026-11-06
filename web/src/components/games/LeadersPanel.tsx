import { teamAccent } from "@/lib/games";
import type { GameDetail, Leader, Team, TeamSheet } from "@/lib/types";

const categories: { key: keyof TeamSheet["leaders"]; title: string; unit: string }[] = [
  { key: "pts", title: "Pontos", unit: "PTS" },
  { key: "reb", title: "Rebotes", unit: "REB" },
  { key: "ast", title: "Assistências", unit: "AST" },
];

function Line({ team, leader, best }: { team: Team; leader: Leader; best: boolean }) {
  return (
    <div className="flex items-center gap-3">
      <span className="h-9 w-1.5 shrink-0 rounded-full" style={{ background: teamAccent(team) }} aria-hidden="true" />
      <span className="flex min-w-0 flex-1 flex-col">
        <span className={`truncate text-[15px] leading-tight ${best ? "font-extrabold text-chalk" : "font-bold text-muted"}`}>
          {leader?.name ?? "—"}
        </span>
        <span className="font-display text-[11px] text-subtle">{team.tricode}</span>
      </span>
      <span className={`font-score text-3xl leading-none tabular-nums ${best ? "text-yellow" : "text-muted"}`}>{leader?.value ?? 0}</span>
    </div>
  );
}

/** Líderes por categoria: os dois times frente a frente, o maior número em destaque. */
export function LeadersPanel({ game }: { game: GameDetail }) {
  return (
    <section aria-labelledby="h-lideres" className="flex flex-col gap-3">
      <h2 id="h-lideres" className="font-display text-base">LÍDERES</h2>
      <ul className="flex flex-col gap-2.5">
        {categories.map(({ key, title, unit }) => {
          const a = game.teams.away.leaders[key];
          const h = game.teams.home.leaders[key];
          const [av, hv] = [a?.value ?? 0, h?.value ?? 0];
          return (
            <li key={key} className="flex flex-col gap-2.5 rounded-2xl border-2 border-surface bg-panel px-4 py-3">
              <span className="flex items-baseline justify-between text-xs font-extrabold tracking-wider text-subtle">
                <span>{title.toUpperCase()}</span>
                <span>{unit}</span>
              </span>
              <Line team={game.away} leader={a} best={av > hv} />
              <Line team={game.home} leader={h} best={hv > av} />
            </li>
          );
        })}
      </ul>
    </section>
  );
}

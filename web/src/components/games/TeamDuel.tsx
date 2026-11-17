import { teamAccent } from "@/lib/games";
import type { GameDetail, Team, TeamSheet } from "@/lib/types";
import { TeamLogo } from "@/components/nba/TeamLogo";

type Totals = TeamSheet["totals"];

type Row = {
  label: string;
  /** Número que decide quem foi melhor e o tamanho da barra. */
  value: (t: Totals) => number;
  /** Como mostrar: o principal e, se houver, um detalhe menor ("37/81"). */
  show: (t: Totals) => [string, string?];
  /** Erros: menos é melhor. */
  lowerIsBetter?: boolean;
};

const ratio = (m: number, a: number) => (a > 0 ? m / a : 0);
const pctText = (m: number, a: number) => (a > 0 ? `${Math.round((m / a) * 100)}%` : "—");

const rows: Row[] = [
  { label: "Arremessos", value: (t) => ratio(t.fgm, t.fga), show: (t) => [pctText(t.fgm, t.fga), `${t.fgm}/${t.fga}`] },
  { label: "Bolas de 3", value: (t) => t.fg3m, show: (t) => [String(t.fg3m)] },
  { label: "Lances livres", value: (t) => ratio(t.ftm, t.fta), show: (t) => [pctText(t.ftm, t.fta), `${t.ftm}/${t.fta}`] },
  { label: "Rebotes", value: (t) => t.reb, show: (t) => [String(t.reb)] },
  { label: "Assistências", value: (t) => t.ast, show: (t) => [String(t.ast)] },
  { label: "Roubos", value: (t) => t.stl, show: (t) => [String(t.stl)] },
  { label: "Tocos", value: (t) => t.blk, show: (t) => [String(t.blk)] },
  { label: "Erros", value: (t) => t.tov, show: (t) => [String(t.tov)], lowerIsBetter: true },
];

function TeamTag({ team, align }: { team: Team; align: "left" | "right" }) {
  return (
    <span className={`flex items-center gap-2 ${align === "right" ? "flex-row-reverse" : ""}`}>
      <TeamLogo team={team} size={22} />
      <span className="font-display text-sm">{team.tricode}</span>
    </span>
  );
}

/**
 * Duelo dos times: uma linha por número, visitante à esquerda e mandante à direita, com a barra
 * dividida nas cores dos dois. O melhor de cada linha fica em destaque.
 */
export function TeamDuel({ game }: { game: GameDetail }) {
  const away = game.teams.away.totals;
  const home = game.teams.home.totals;
  const [awayColor, homeColor] = [teamAccent(game.away), teamAccent(game.home)];

  return (
    <section aria-labelledby="h-duelo" className="flex flex-col gap-4 rounded-2xl border-[3px] border-ink bg-panel p-4 shadow-hard">
      <div className="flex items-center justify-between">
        <TeamTag team={game.away} align="left" />
        <h2 id="h-duelo" className="font-display text-sm text-subtle">DUELO</h2>
        <TeamTag team={game.home} align="right" />
      </div>

      <ul className="flex flex-col gap-3.5">
        {rows.map((row) => {
          const [a, h] = [row.value(away), row.value(home)];
          const better = a === h ? null : (a > h) !== !!row.lowerIsBetter ? "away" : "home";
          // Barra: fatia de cada time no total (em "menos é melhor", inverte para o melhor ocupar mais).
          const [wa, wh] = row.lowerIsBetter ? [h, a] : [a, h];
          const share = wa + wh > 0 ? (wa / (wa + wh)) * 100 : 50;
          const [aMain, aSub] = row.show(away);
          const [hMain, hSub] = row.show(home);

          return (
            <li key={row.label} className="flex flex-col gap-1.5">
              <div className="grid grid-cols-[1fr_auto_1fr] items-baseline gap-2 tabular-nums">
                <span className="flex items-baseline gap-1.5">
                  <span className={`text-lg leading-none ${better === "away" ? "font-extrabold text-chalk" : "font-bold text-muted"}`}>{aMain}</span>
                  {aSub && <span className="text-xs text-subtle">{aSub}</span>}
                </span>
                <span className="text-center text-xs font-bold tracking-wide text-subtle uppercase">{row.label}</span>
                <span className="flex flex-row-reverse items-baseline gap-1.5">
                  <span className={`text-lg leading-none ${better === "home" ? "font-extrabold text-chalk" : "font-bold text-muted"}`}>{hMain}</span>
                  {hSub && <span className="text-xs text-subtle">{hSub}</span>}
                </span>
              </div>
              <div className="flex h-2 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
                <span
                  className="h-full rounded-l-full"
                  style={{ width: `${share}%`, background: awayColor, opacity: better === "home" ? 0.4 : 1 }}
                />
                <span
                  className="h-full flex-1 rounded-r-full"
                  style={{ background: homeColor, opacity: better === "away" ? 0.4 : 1 }}
                />
              </div>
            </li>
          );
        })}
      </ul>
    </section>
  );
}

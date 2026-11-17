import Link from "next/link";
import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { hasScore, leader, longDay, matchupGradient, periodLabel, statusLine, teamAccent, tipoff } from "@/lib/games";
import type { BoxLine, GameDetail, Team, TeamSheet } from "@/lib/types";
import { AutoRefresh } from "@/components/games/AutoRefresh";
import { LiveBadge } from "@/components/games/LiveBadge";
import { LeadersPanel } from "@/components/games/LeadersPanel";
import { ShotChart } from "@/components/games/ShotChart";
import { ShotCallPanel } from "@/components/shotcalls/ShotCallPanel";
import { TeamDuel } from "@/components/games/TeamDuel";
import { TeamLogo } from "@/components/nba/TeamLogo";
import { TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Jogo" };

export default function GamePage({ params }: PageProps<"/jogos/[id]">) {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <Suspense fallback={<TabSkeleton />}>
        <GameView params={params} />
      </Suspense>
    </div>
  );
}

/** Dia da NBA (Nova York) do jogo, para o link de volta ao calendário. */
const nbaDay = (iso: string) => new Intl.DateTimeFormat("en-CA", { timeZone: "America/New_York" }).format(new Date(iso));

async function GameView({ params }: Pick<PageProps<"/jogos/[id]">, "params">) {
  const { id } = await params;
  const { data: game } = await serverApi<{ data: GameDetail }>(`/games/${encodeURIComponent(id)}`);
  const day = nbaDay(game.start_time);
  const live = game.status === "LIVE";
  const scored = hasScore(game);
  const ahead = leader(game);
  const periods = game.periods.map((p) => p.period);
  const where = [game.arena, game.national_tv].filter(Boolean).join(" · ");

  const score = (side: "home" | "away") => (
    <span
      className={`font-score text-6xl leading-none tabular-nums drop-shadow-[3px_3px_0_var(--color-ink)] sm:text-8xl ${
        ahead === side ? "text-yellow" : "text-chalk"
      }`}
    >
      {side === "home" ? game.home_score : game.away_score}
    </span>
  );

  // No celular o logo vai em cima da sigla (cabe na coluna); no computador, ao lado. Espelhado:
  // visitante com o logo à esquerda, mandante à direita.
  const teamName = (team: Team, side: "home" | "away") => (
    <span
      className={`flex min-w-0 flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-2.5 ${
        side === "home" ? "items-end text-right sm:flex-row-reverse" : "items-start"
      }`}
    >
      <TeamLogo team={team} size={44} />
      <span className={`flex min-w-0 flex-col ${side === "home" ? "items-end" : ""}`}>
        <span className="font-display text-xl leading-none drop-shadow-[2px_2px_0_var(--color-ink)] sm:text-2xl">{team.tricode}</span>
        <span className="text-xs font-bold break-words text-chalk/85 sm:text-sm">
          {team.city} {team.name}
        </span>
      </span>
    </span>
  );

  return (
    <>
      <AutoRefresh enabled={live} seconds={10} />
      <Link href={`/jogos?data=${day}`} className="self-start text-sm font-bold text-cyan no-underline hover:text-lime">
        ← Jogos de {longDay(day)}
      </Link>

      <section
        aria-label="Placar"
        className="relative overflow-hidden rounded-3xl border-[3px] border-ink px-5 py-6 shadow-hard-lg sm:px-8"
        style={{ background: matchupGradient(game) }}
      >
        <span className="absolute inset-0 bg-ink/25" aria-hidden="true" />
        <div className="relative grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3">
          <div className="flex min-w-0 flex-col items-start gap-2 sm:flex-row sm:items-end sm:gap-5">
            {teamName(game.away, "away")}
            {scored && score("away")}
          </div>
          <div className="flex max-w-[8.5rem] flex-col items-center gap-2 text-center sm:max-w-none">
            {live ? (
              <LiveBadge />
            ) : (
              <span className="rounded-lg border-2 border-ink bg-chalk px-2.5 py-1 font-display text-xs text-ink shadow-hard-sm">
                {statusLine(game, day)}
              </span>
            )}
            {live && <span className="font-score text-2xl tabular-nums sm:text-3xl">{statusLine(game, day)}</span>}
            {where && <span className="rounded-full bg-ink/55 px-2.5 py-0.5 text-xs font-bold">{where}</span>}
          </div>
          <div className="flex min-w-0 flex-col-reverse items-end gap-2 text-right sm:flex-row sm:items-end sm:gap-5">
            {scored && score("home")}
            {teamName(game.home, "home")}
          </div>
        </div>
      </section>

      {!scored ? (
        <div className="mx-auto flex w-full max-w-xl flex-col gap-4">
          <p className="text-center text-sm text-muted">
            A bola sobe às {tipoff(game, day)} (Brasília). Placar, arremessos e estatísticas aparecem aqui quando o jogo começar.
          </p>
          <ShotCallPanel game={game} />
        </div>
      ) : (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
          <div className="flex min-w-0 flex-col gap-6">
            <ShotChart shots={game.shots} home={game.home} away={game.away} periods={periods} />
            <Quarters game={game} />
          </div>
          <aside className="flex min-w-0 flex-col gap-4" aria-label="Destaques">
            <ShotCallPanel game={game} />
            <TeamDuel game={game} />
            <LeadersPanel game={game} />
          </aside>
          <div className="flex min-w-0 flex-col gap-6 lg:col-span-2">
            <BoxScore team={game.away} sheet={game.teams.away} />
            <BoxScore team={game.home} sheet={game.teams.home} />
          </div>
        </div>
      )}
    </>
  );
}

function Quarters({ game }: { game: GameDetail }) {
  const best = (p: GameDetail["periods"][number]) =>
    p.home_points === p.away_points ? null : p.home_points > p.away_points ? "home" : "away";

  return (
    <section aria-labelledby="h-quartos" className="flex flex-col gap-3">
      <h2 id="h-quartos" className="font-display text-lg">PONTOS POR QUARTO</h2>
      <div className="overflow-x-auto rounded-2xl border-2 border-surface bg-panel">
        <table className="w-full min-w-[320px] text-center tabular-nums">
          <thead>
            <tr className="text-sm text-subtle">
              <th scope="col" className="px-3 py-2 text-left">
                <span className="sr-only">Time</span>
              </th>
              {game.periods.map((p) => (
                <th key={p.period} scope="col" className="px-3 py-2 font-bold">
                  {p.period <= 4 ? p.period : periodLabel(p.period)}
                </th>
              ))}
              <th scope="col" className="px-3 py-2 font-bold">T</th>
            </tr>
          </thead>
          <tbody>
            {(["away", "home"] as const).map((side) => (
              <tr key={side} className="border-t-2 border-surface">
                <th scope="row" className="px-3 py-2 text-left font-display text-sm">{game[side].tricode}</th>
                {game.periods.map((p) => (
                  <td key={p.period} className={`px-3 py-2 ${best(p) === side ? "font-extrabold text-lime" : ""}`}>
                    {side === "home" ? p.home_points : p.away_points}
                  </td>
                ))}
                <td className={`px-3 py-2 font-extrabold ${leader(game) === side ? "text-yellow" : ""}`}>
                  {side === "home" ? game.home_score : game.away_score}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

function BoxScore({ team, sheet }: { team: Team; sheet: TeamSheet }) {
  const cols: [keyof BoxLine, string, string][] = [
    ["minutes", "MIN", "Minutos"],
    ["pts", "PTS", "Pontos"],
    ["reb", "REB", "Rebotes"],
    ["ast", "AST", "Assistências"],
    ["stl", "ROB", "Roubos"],
    ["blk", "TOC", "Tocos"],
    ["tov", "ERR", "Erros"],
  ];
  const played = sheet.players.filter((p) => p.minutes && p.minutes !== "00:00");
  const out = sheet.players.length - played.length;

  return (
    <section aria-labelledby={`h-box-${team.id}`} className="flex flex-col gap-3">
      <h2 id={`h-box-${team.id}`} className="font-display text-lg">
        <span className="mr-2 inline-block size-3 rounded-sm border-2 border-ink align-middle" style={{ background: teamAccent(team) }} aria-hidden="true" />
        {team.city.toUpperCase()} {team.name.toUpperCase()}
      </h2>
      {played.length === 0 ? (
        <p className="text-muted">Sem estatísticas ainda.</p>
      ) : (
        <div className="overflow-x-auto rounded-2xl border-2 border-surface bg-panel">
          <table className="w-full min-w-[640px] text-right text-sm tabular-nums">
            <thead>
              <tr className="text-xs text-subtle">
                <th scope="col" className="px-3 py-2 text-left font-bold">Jogador</th>
                {cols.map(([, short, long]) => (
                  <th key={short} scope="col" className="px-2 py-2 font-bold">
                    <abbr title={long} className="no-underline">{short}</abbr>
                  </th>
                ))}
                <th scope="col" className="px-2 py-2 font-bold"><abbr title="Arremessos de quadra" className="no-underline">FG</abbr></th>
                <th scope="col" className="px-2 py-2 font-bold"><abbr title="Bolas de 3 convertidas" className="no-underline">3P</abbr></th>
                <th scope="col" className="px-2 py-2 font-bold"><abbr title="Lances livres" className="no-underline">LL</abbr></th>
                <th scope="col" className="px-3 py-2 font-bold">+/-</th>
              </tr>
            </thead>
            <tbody>
              {played.map((p) => (
                <tr key={p.player_id} className="border-t-2 border-surface">
                  <th scope="row" className="max-w-[200px] truncate px-3 py-2 text-left font-bold">
                    {p.name ?? "—"}
                    {p.position && <span className="ml-1.5 text-xs text-subtle">{p.position}</span>}
                  </th>
                  {cols.map(([key, short]) => (
                    <td key={short} className={`px-2 py-2 ${key === "pts" ? "font-extrabold text-chalk" : "text-muted"}`}>
                      {p[key] ?? "—"}
                    </td>
                  ))}
                  <td className="px-2 py-2 text-muted">{p.fgm}-{p.fga}</td>
                  <td className="px-2 py-2 text-muted">{p.fg3m}</td>
                  <td className="px-2 py-2 text-muted">{p.ftm}-{p.fta}</td>
                  <td className={`px-3 py-2 ${p.plus_minus > 0 ? "text-lime" : p.plus_minus < 0 ? "text-pink" : "text-muted"}`}>
                    {p.plus_minus > 0 ? `+${p.plus_minus}` : p.plus_minus}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {out > 0 && <p className="text-sm text-subtle">{out} {out === 1 ? "jogador não entrou" : "jogadores não entraram"}.</p>}
    </section>
  );
}

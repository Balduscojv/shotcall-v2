import Link from "next/link";
import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { fmoney, fpoints, fvariation, roleLabel } from "@/lib/fantasy";
import { onTeamAccent, teamAccent } from "@/lib/games";
import type { PlayerDetail } from "@/lib/types";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";
import { PlayerPhoto } from "@/components/nba/PlayerPhoto";
import { TeamLogo } from "@/components/nba/TeamLogo";

export const metadata = { title: "Jogador" };

export default function PlayerPage({ params }: PageProps<"/jogadores/[id]">) {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <Suspense fallback={<TabSkeleton />}>
        <PlayerView params={params} />
      </Suspense>
    </div>
  );
}

/** Rótulos do detalhamento da pontuação (FantasyScorer). */
const breakdownLabel: Record<string, string> = {
  pts: "Pontos",
  reb: "Rebotes",
  ast: "Assistências",
  stl: "Roubos",
  blk: "Tocos",
  fg3m_bonus: "Bônus de 3",
  fg_missed: "Arremessos errados",
  ft_missed: "Lances livres errados",
  tov: "Turnovers",
  pf: "Faltas",
  tech: "Técnicas",
  flagrant: "Flagrantes",
  multi: "Duplo/triplo-duplo",
  impact: "Impacto (+/−)",
};

const dateFmt = new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "2-digit", timeZone: "America/New_York" });
const nextFmt = new Intl.DateTimeFormat("pt-BR", { weekday: "short", day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" });

async function PlayerView({ params }: Pick<PageProps<"/jogadores/[id]">, "params">) {
  const { id } = await params;
  const { data: p } = await serverApi<{ data: PlayerDetail }>(`/players/${encodeURIComponent(id)}`);
  const accent = p.team ? teamAccent(p.team) : "var(--color-surface)";
  const maxPrice = Math.max(1, ...p.price_history.map((h) => h.price));

  const stat = (label: string, value: string, tone = "text-chalk") => (
    <div className="flex flex-col gap-1 rounded-2xl border-2 border-surface bg-panel px-4 py-3">
      <span className="text-xs font-bold text-subtle">{label}</span>
      <span className={`font-score text-2xl leading-none tabular-nums ${tone}`}>{value}</span>
    </div>
  );

  return (
    <>
      <Link href="/time" className="self-start text-sm font-bold text-cyan no-underline hover:text-lime">
        ← Meu time
      </Link>

      <header
        className="relative overflow-hidden rounded-3xl border-[3px] border-ink px-5 py-6 shadow-hard-lg sm:px-8"
        style={{ background: accent, color: p.team ? onTeamAccent(p.team) : undefined }}
      >
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div className="flex items-end gap-4">
            <span className="relative shrink-0">
              <PlayerPhoto id={p.id} name={p.name} team={p.team} size={96} className="border-[3px] shadow-hard-sm" />
              {p.team && (
                <span className="absolute -right-2 -bottom-1 flex size-9 items-center justify-center rounded-full border-2 border-ink bg-chalk">
                  <TeamLogo team={p.team} size={26} />
                </span>
              )}
            </span>
            <div className="flex flex-col gap-1">
              <span className="text-sm font-extrabold opacity-85">
                {p.team ? `${p.team.city} ${p.team.name}` : "Sem time"}
                {p.jersey && ` · #${p.jersey}`}
              </span>
              <h1 className="font-display text-3xl leading-none sm:text-4xl">{p.name}</h1>
              <span className="text-sm font-bold opacity-85">{p.role ? `${roleLabel[p.role]} (${p.role})` : "Posição indefinida"}</span>
            </div>
          </div>
          {!p.active && (
            <span className="rotate-3 rounded-lg border-2 border-ink bg-pink px-2.5 py-1 text-xs font-extrabold text-ink shadow-hard-sm">INATIVO</span>
          )}
        </div>
      </header>

      <section aria-label="Resumo" className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {stat(p.round ? `Preço · rodada ${p.round.number}` : "Preço", p.price === null ? "—" : fmoney(p.price))}
        {stat(
          "Variação",
          p.variation ? fvariation(p.variation) : "—",
          p.variation && p.variation > 0 ? "text-lime" : p.variation && p.variation < 0 ? "text-pink" : "text-chalk",
        )}
        {stat("Mínimo p/ valorizar", p.min_to_gain === null ? "—" : `${fpoints(p.min_to_gain)} / jogo`)}
        {stat("Média na temporada", p.season.average === null ? "—" : fpoints(p.season.average))}
      </section>
      <p className="-mt-3 text-sm text-subtle">
        {p.season.games} {p.season.games === 1 ? "jogo pontuado" : "jogos pontuados"} na temporada
        {p.season.best !== null && ` · melhor jogo ${fpoints(p.season.best)}`} · {p.games_in_round}{" "}
        {p.games_in_round === 1 ? "jogo" : "jogos"} na rodada
      </p>

      {p.price_history.length > 0 && (
        <section aria-labelledby="h-preco" className="flex flex-col gap-3">
          <h2 id="h-preco" className="font-display text-lg">PREÇO POR RODADA</h2>
          <ol className="flex items-end gap-2 overflow-x-auto rounded-2xl border-2 border-surface bg-panel px-4 pb-3 pt-6">
            {p.price_history.map((h) => (
              <li key={h.round} className="flex min-w-12 flex-col items-center gap-1">
                <span className="text-[11px] font-bold tabular-nums">{fmoney(h.price).replace("F$ ", "")}</span>
                <span
                  className={`w-7 rounded-t-md ${h.variation > 0 ? "bg-lime" : h.variation < 0 ? "bg-pink" : "bg-cyan"}`}
                  style={{ height: `${Math.max(8, (h.price / maxPrice) * 96)}px` }}
                  aria-label={`Rodada ${h.round}: ${fmoney(h.price)}${h.variation ? `, ${fvariation(h.variation)}` : ""}`}
                />
                <span className="text-[11px] text-subtle">R{h.round}</span>
              </li>
            ))}
          </ol>
        </section>
      )}

      {p.upcoming.length > 0 && (
        <section aria-labelledby="h-proximos" className="flex flex-col gap-3">
          <h2 id="h-proximos" className="font-display text-lg">PRÓXIMOS JOGOS</h2>
          <ul className="flex flex-wrap gap-2">
            {p.upcoming.map((g) => (
              <li key={g.id}>
                <Link
                  href={`/jogos/${g.id}`}
                  className="flex flex-col rounded-xl border-2 border-surface bg-panel px-3 py-2 text-sm no-underline hover:border-cyan"
                >
                  <span className="font-bold text-chalk">
                    {g.home ? "vs" : "@"} {g.opponent.tricode}
                  </span>
                  <span className="text-xs text-subtle">{nextFmt.format(new Date(g.start_time))}</span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      )}

      <section aria-labelledby="h-jogos" className="flex flex-col gap-3">
        <h2 id="h-jogos" className="font-display text-lg">JOGOS NA TEMPORADA</h2>
        {p.games.length === 0 ? (
          <EmptyState title="NENHUM JOGO PONTUADO AINDA">
            Os pontos aparecem aqui quando ele entrar em quadra. Calouros e jogadores sem jogos começam com o preço de novato.
          </EmptyState>
        ) : (
          <ul className="flex flex-col overflow-hidden rounded-2xl border-[3px] border-ink bg-panel shadow-hard">
            {p.games.map((g) => {
              const won = g.status === "FINAL" && g.team_score > g.opponent_score;
              const parts = Object.entries(g.breakdown).filter(([, v]) => v !== 0);
              return (
                <li key={g.id} className="border-b-2 border-surface last:border-0">
                  <details className="group">
                    <summary className="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                      <span className="w-12 shrink-0 text-xs font-bold text-subtle">{dateFmt.format(new Date(g.start_time))}</span>
                      <span className="flex min-w-0 flex-1 flex-col">
                        <span className="font-bold">
                          {g.home ? "vs" : "@"} {g.opponent.tricode}{" "}
                          {g.status === "FINAL" && (
                            <span className={`text-xs font-extrabold ${won ? "text-lime" : "text-pink"}`}>
                              {won ? "V" : "D"} {g.team_score}–{g.opponent_score}
                            </span>
                          )}
                        </span>
                        {g.line && (
                          <span className="text-xs text-subtle">
                            {g.line.minutes ?? "0"} min · {g.line.pts} pts · {g.line.reb} reb · {g.line.ast} ast
                            {g.line.stl + g.line.blk > 0 && ` · ${g.line.stl} rb · ${g.line.blk} tc`}
                          </span>
                        )}
                      </span>
                      <span className="font-score text-2xl leading-none tabular-nums">{fpoints(g.points)}</span>
                      <span className="text-subtle transition-transform group-open:rotate-180" aria-hidden="true">
                        ▾
                      </span>
                    </summary>
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-1 bg-surface/40 px-4 py-3 text-sm sm:grid-cols-3">
                      {parts.length === 0 && <dd className="text-subtle">Sem pontuação neste jogo.</dd>}
                      {parts.map(([k, v]) => (
                        <div key={k} className="flex justify-between gap-2">
                          <dt className="text-subtle">{breakdownLabel[k] ?? k}</dt>
                          <dd className={`font-bold tabular-nums ${v > 0 ? "text-lime" : "text-pink"}`}>
                            {v > 0 ? "+" : ""}
                            {fpoints(v)}
                          </dd>
                        </div>
                      ))}
                    </dl>
                  </details>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    </>
  );
}

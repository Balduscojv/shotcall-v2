import { fmoney, fpoints, roleLabel, roleOf } from "@/lib/fantasy";
import { teamAccent } from "@/lib/games";
import type { FantasyRound, FantasyTeam } from "@/lib/types";
import { LiveBadge } from "@/components/games/LiveBadge";
import { BoostCard } from "./BoostCard";
import { Flame } from "./Flame";

/** Mercado fechado: as parciais do time na rodada, jogador por jogador. */
export function TeamLive({ team, round, multiplier }: { team: FantasyTeam; round: FantasyRound; multiplier: number }) {
  const settled = round.status === "SETTLED";
  const anyLive = team.players.some((p) => p.live);

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
      <section aria-labelledby="h-parciais" className="flex flex-col gap-3">
        <div className="flex items-center justify-between gap-2">
          <h2 id="h-parciais" className="font-display text-lg">{settled ? "PONTOS DA RODADA" : "PARCIAIS"}</h2>
          {anyLive && <LiveBadge size="sm" />}
        </div>
        <ul className="flex flex-col overflow-hidden rounded-2xl border-[3px] border-ink bg-panel shadow-hard">
          {team.players.map((p) => (
            <li key={p.id} className={`flex items-center gap-3 border-b-2 border-surface px-4 py-3 last:border-0 ${p.cestinha ? "bg-yellow/10" : ""}`}>
              <span className="h-11 w-1.5 shrink-0 rounded-full" style={{ background: p.team ? teamAccent(p.team) : undefined }} aria-hidden="true" />
              <span className="flex min-w-0 flex-1 flex-col">
                <span className="flex items-center gap-2 truncate font-bold">
                  {p.name}
                  {p.cestinha && (
                    <span className="flex items-center gap-1 rounded-md border-2 border-ink bg-yellow px-1 font-display text-[10px] text-ink" title={`Cestinha: pontua ${multiplier}x`}>
                      <Flame size={9} />
                      {multiplier}X
                    </span>
                  )}
                  {p.live && <span className="size-2 animate-pulse-dot rounded-full bg-pink" aria-label="jogando agora" />}
                </span>
                <span className="text-xs text-subtle">
                  {p.team?.tricode} · {roleOf(p)} · {roleLabel[roleOf(p)]} · {p.games_played}/{p.games_in_round} jogos
                </span>
              </span>
              <span className={`font-score text-3xl leading-none tabular-nums ${p.cestinha ? "text-yellow" : "text-chalk"}`}>{fpoints(p.points)}</span>
            </li>
          ))}
        </ul>
      </section>

      <aside className="flex flex-col gap-3" aria-label="Resumo">
        <div className="flex flex-col items-center gap-1 rounded-2xl border-[3px] border-ink bg-hype px-4 py-6 text-ink shadow-hard">
          <span className="font-display text-sm">{settled ? "TOTAL DA RODADA" : "PARCIAL DA RODADA"}</span>
          <span className="font-score text-6xl leading-none tabular-nums">{fpoints(team.points)}</span>
          <span className="text-sm font-bold">
            pontos{team.boost.value > 0 && ` · ${fpoints(team.base_points)} dos jogadores + ${Math.round(team.boost.value * 100)}% de boost`}
          </span>
        </div>
        <BoostCard boost={team.boost} basePoints={team.base_points} />
        <div className="grid grid-cols-2 gap-2.5">
          <div className="flex flex-col rounded-2xl border-2 border-surface bg-panel px-3 py-2">
            <span className="text-[11px] font-extrabold tracking-wider text-subtle">PATRIMÔNIO</span>
            <span className="font-score text-2xl">{fmoney(team.patrimony)}</span>
          </div>
          <div className="flex flex-col rounded-2xl border-2 border-surface bg-panel px-3 py-2">
            <span className="text-[11px] font-extrabold tracking-wider text-subtle">SALDO</span>
            <span className="font-score text-2xl">{fmoney(team.balance)}</span>
          </div>
        </div>
        <p className="text-sm text-muted">
          {settled
            ? "Rodada apurada. Os preços já mudaram para a próxima."
            : "O mercado reabre quando a rodada for apurada, depois do último jogo. Seu time segue para a próxima rodada; dá para mexer quando abrir."}
        </p>
      </aside>
    </div>
  );
}

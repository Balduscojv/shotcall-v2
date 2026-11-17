import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { roundSpan } from "@/lib/fantasy";
import type { FantasyTeamResponse, MarketResponse } from "@/lib/types";
import { AutoRefresh } from "@/components/games/AutoRefresh";
import { TeamBuilder } from "@/components/fantasy/TeamBuilder";
import { TeamLive } from "@/components/fantasy/TeamLive";
import { LockCountdown } from "@/components/shotcalls/LockCountdown";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Escale seu time" };

// Fantasy: com o mercado aberto, montar o time; fechado, as parciais da rodada.
export default function TeamPage() {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <Suspense fallback={<TabSkeleton />}>
        <MyTeam />
      </Suspense>
    </div>
  );
}

const statusBadge: Record<string, [string, string]> = {
  OPEN: ["MERCADO ABERTO", "bg-lime"],
  LOCKED: ["MERCADO FECHADO", "bg-chalk"],
  SETTLING: ["APURANDO", "bg-yellow"],
  SETTLED: ["RODADA APURADA", "bg-cyan"],
  UPCOMING: ["EM BREVE", "bg-surface text-chalk"],
};

async function MyTeam() {
  const { data } = await serverApi<FantasyTeamResponse>("/fantasy/team");
  const { round, team, previous } = data;
  const open = round.status === "OPEN";
  const market = open ? await serverApi<MarketResponse>("/fantasy/market") : null;
  const [badge, badgeColor] = statusBadge[round.status] ?? statusBadge.UPCOMING;
  const live = (!open && !!team?.players.some((p) => p.live)) || !!previous?.team?.players.some((p) => p.live);

  return (
    <>
      <AutoRefresh enabled={live} seconds={30} />
      {previous && (
        <section className="flex flex-col gap-4">
          <header className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex flex-col gap-1">
              <h2 className="font-display text-2xl">RODADA {previous.round.number} EM ANDAMENTO</h2>
              <p className="font-semibold text-muted">{roundSpan(previous.round.starts_at, previous.round.ends_at)}</p>
            </div>
            <span className="rotate-3 rounded-lg border-2 border-ink bg-chalk px-2.5 py-1 text-xs font-extrabold text-ink shadow-hard-sm">PARCIAIS</span>
          </header>
          {previous.team ? (
            <TeamLive team={previous.team} round={previous.round} multiplier={data.cestinha_multiplier} />
          ) : (
            <p className="font-semibold text-muted">Você não escalou time na rodada {previous.round.number}.</p>
          )}
        </section>
      )}
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="font-display text-3xl">MEU TIME</h1>
          <p className="font-semibold text-muted">
            Rodada {round.number} · {roundSpan(round.starts_at, round.ends_at)}
          </p>
        </div>
        <div className="flex items-center gap-4">
          {open && round.lock_at && <LockCountdown lockAt={round.lock_at} label="MERCADO FECHA EM" />}
          <span className={`rotate-3 rounded-lg border-2 border-ink px-2.5 py-1 text-xs font-extrabold text-ink shadow-hard-sm ${badgeColor}`}>{badge}</span>
        </div>
      </header>

      {open && round.provisional_prices && (
        <p className="rounded-2xl border-[3px] border-ink bg-yellow px-4 py-3 font-semibold text-ink shadow-hard-sm">
          Preços provisórios: a rodada {previous?.round.number ?? round.number - 1} ainda está rolando. Quando ela for apurada, os preços e o
          seu patrimônio são atualizados. Se o time passar do orçamento, ele continua salvo e você recebe um aviso para ajustar.
        </p>
      )}

      {open && market ? (
        <TeamBuilder team={team} market={market.data} teams={market.meta.teams} startingBalance={data.patrimony} />
      ) : team ? (
        <TeamLive team={team} round={round} multiplier={data.cestinha_multiplier} />
      ) : (
        <EmptyState title="VOCÊ NÃO ESCALOU TIME NESTA RODADA">
          O mercado da próxima rodada abre quando esta fechar. Volte para montar seu time: 2 armadores, 2 alas, 1 pivô e o cestinha, que
          pontua em dobro.
        </EmptyState>
      )}
    </>
  );
}

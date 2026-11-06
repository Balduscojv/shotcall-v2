import { matchupGradient } from "@/lib/games";
import { bandLabel, marketLabel, marketPoints, nextTier } from "@/lib/shotcalls";
import { tierLabel } from "@/lib/labels";
import type { GameDetail, ShotCallMarket } from "@/lib/types";
import { LockCountdown } from "./LockCountdown";
import { ShotCallForm } from "./ShotCallForm";

/** Taxa do modo jogo: quanto falta para contar ou para o próximo nível. */
function Accuracy({ accuracy }: { accuracy: GameDetail["shot_call"]["accuracy"] }) {
  const { rate, counted, tier } = accuracy;
  const next = rate === null ? null : nextTier(rate);
  const text =
    rate === null
      ? counted < 10
        ? `Faltam ${10 - counted} ${10 - counted === 1 ? "jogo apurado" : "jogos apurados"} para sua taxa contar`
        : "Sem taxa ainda"
      : `${Math.round(rate * 100)}%${tier ? ` · ${tierLabel[tier]}` : ""}${next ? ` · faltam ${next.gap} p.p. para ${tierLabel[next.tier]}` : ""}`;
  const width = rate === null ? (Math.min(counted, 10) / 10) * 100 : rate * 100;

  return (
    <div className="flex flex-col gap-2 rounded-2xl border-2 border-surface bg-panel px-4 py-3">
      <div className="flex flex-wrap justify-between gap-x-3 gap-y-1 text-sm">
        <span className="text-muted">Sua taxa (últimos 20)</span>
        <strong>{text}</strong>
      </div>
      <div className="h-2.5 overflow-hidden rounded-full bg-surface" aria-hidden="true">
        <div className="h-full rounded-full bg-gradient-to-r from-muted to-yellow" style={{ width: `${width}%` }} />
      </div>
    </div>
  );
}

/** Depois que a bola sobe: o que eu cravei em cada mercado e se está dando certo (ou deu). */
function Locked({ game }: { game: GameDetail }) {
  const { mine, margin, mvp_leader } = game.shot_call;
  const final = game.status === "FINAL";

  if (!mine) {
    return <p className="text-muted">{final ? "Você não palpitou neste jogo." : "Você não palpitou neste jogo, e ele já começou."}</p>;
  }

  const winner = mine.winner_team_id === game.home.id ? game.home : game.away;
  const rows: { market: ShotCallMarket; pick: string; now: string | null }[] = [
    {
      market: "winner",
      pick: winner.tricode,
      now: null,
    },
    { market: "margin", pick: `por ${bandLabel[mine.margin_band]}`, now: margin ? `${final ? "final" : "agora"}: ${bandLabel[margin]}` : null },
    { market: "mvp", pick: mine.mvp?.name ?? "—", now: mvp_leader ? `${final ? "MVP" : "lidera"}: ${mvp_leader.name}` : null },
  ];

  return (
    <div className="flex flex-col gap-3">
      <ul className="flex flex-col gap-2">
        {rows.map(({ market, pick, now }) => {
          const hit = mine.markets?.[market];
          return (
            <li key={market} className="flex items-center justify-between gap-3 text-sm">
              <span className="flex min-w-0 flex-col">
                <span className="text-muted">
                  {marketLabel[market]} <span className="text-xs text-subtle">+{marketPoints[market]}</span>
                </span>
                {now && <span className="truncate text-xs text-subtle">{now}</span>}
              </span>
              <strong className={`flex shrink-0 items-center gap-1.5 ${hit === undefined ? "" : hit ? "text-lime" : "text-pink"}`}>
                {pick}
                {hit !== undefined && <span aria-label={hit ? (final ? "acertou" : "dando certo") : final ? "errou" : "dando errado"}>{hit ? "▲" : "▼"}</span>}
              </strong>
            </li>
          );
        })}
      </ul>
      {mine.correct !== null && (
        <>
          <div className="h-2.5 overflow-hidden rounded-full bg-surface" aria-hidden="true">
            <div className="h-full rounded-full bg-gradient-to-r from-lime to-cyan" style={{ width: `${(mine.correct / 3) * 100}%` }} />
          </div>
          <p className="text-sm text-muted">
            {mine.provisional ? `${mine.correct} de 3 certos até agora` : `${mine.correct} de 3 certos`} ·{" "}
            <strong className="text-chalk">
              {mine.points} {mine.points === 1 ? "ponto" : "pontos"}
            </strong>
            {mine.provisional && final && " · apurando"}
          </p>
        </>
      )}
    </div>
  );
}

/** Painel "Seu palpite" da página do jogo, em qualquer momento do jogo. */
export function ShotCallPanel({ game }: { game: GameDetail }) {
  const sc = game.shot_call;
  const final = game.status === "FINAL";
  const badge = !sc.locked ? (sc.mine ? "CRAVADO" : "ABERTO") : final && sc.mine && !sc.mine.provisional ? "APURADO" : "TRAVADO";

  return (
    <section aria-labelledby="h-palpite" className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-2xl border-[3px] border-ink bg-panel p-4 shadow-hard">
        <div className="flex items-center justify-between gap-2">
          <h2 id="h-palpite" className="font-display text-base">SEU PALPITE</h2>
          <span
            className={`rotate-3 rounded-md border-2 border-ink px-2 py-0.5 text-xs font-extrabold ${
              badge === "ABERTO" ? "bg-lime text-ink" : badge === "CRAVADO" ? "bg-cyan text-ink" : "bg-chalk text-ink"
            }`}
          >
            {badge}
          </span>
        </div>

        {!sc.locked ? (
          <>
            <div
              className="relative flex items-center justify-between overflow-hidden rounded-2xl border-2 border-ink px-4 py-3"
              style={{ background: matchupGradient(game) }}
            >
              <span className="absolute inset-0 bg-ink/25" aria-hidden="true" />
              <span className="relative flex flex-col">
                <span className="font-display text-xl drop-shadow-[2px_2px_0_var(--color-ink)]">{game.away.tricode}</span>
                <span className="text-xs font-bold">fora</span>
              </span>
              <span className="relative">
                {/* Buzzer beater: o arremesso que sai antes da buzina e cai com o relógio zerado. */}
                <LockCountdown lockAt={sc.lock_at} label="BUZZER BEATER" />
              </span>
              <span className="relative flex flex-col items-end">
                <span className="font-display text-xl drop-shadow-[2px_2px_0_var(--color-ink)]">{game.home.tricode}</span>
                <span className="text-xs font-bold">casa</span>
              </span>
            </div>
            <ShotCallForm gameId={game.id} home={game.home} away={game.away} shotCall={sc} />
          </>
        ) : (
          <Locked game={game} />
        )}
      </div>
      <Accuracy accuracy={sc.accuracy} />
    </section>
  );
}


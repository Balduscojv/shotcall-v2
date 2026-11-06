import Link from "next/link";
import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { formatDay, formatRate, marginLabel, modeLabel, tierColor, tierLabel } from "@/lib/labels";
import { getProfile } from "@/lib/profile";
import type { AccuracyMode, Paginated, ShotCallHistory } from "@/lib/types";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Perfil" };

// Aba "Palpites": taxa de acerto por modo e o histórico de shot calls de jogo.
export default function ShotCallsTab({ params, searchParams }: PageProps<"/u/[handle]">) {
  return (
    <Suspense fallback={<TabSkeleton />}>
      <ShotCalls params={params} searchParams={searchParams} />
    </Suspense>
  );
}

async function ShotCalls({ params, searchParams }: Pick<PageProps<"/u/[handle]">, "params" | "searchParams">) {
  const { handle } = await params;
  const page = Math.max(1, Number((await searchParams).pagina) || 1);
  const profile = await getProfile(handle);
  const calls = await serverApi<Paginated<ShotCallHistory>>(
    `/users/${encodeURIComponent(handle)}/shot-calls?page=${page}`,
  );
  const own = profile.friendship === "SELF";

  return (
    <div className="flex flex-col gap-8">
      <section aria-labelledby="h-acerto" className="flex flex-col gap-4">
        <h2 id="h-acerto" className="font-display text-2xl">TAXA DE ACERTO</h2>
        <ul className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {(Object.keys(modeLabel) as AccuracyMode[]).map((mode) => {
            const acc = profile.accuracy[mode];
            return (
              <li key={mode} className="flex flex-col gap-1 rounded-2xl border-[3px] border-ink bg-panel p-4">
                <span className="text-sm font-bold text-muted">{modeLabel[mode]}</span>
                <span className="font-score text-4xl leading-none">
                  {acc?.rate != null ? formatRate(acc.rate) : "—"}
                </span>
                {acc?.tier ? (
                  <span className={`mt-1 self-start rounded-md border-2 border-ink px-2 py-0.5 font-display text-[11px] text-ink ${tierColor[acc.tier]}`}>
                    {tierLabel[acc.tier].toUpperCase()}
                  </span>
                ) : (
                  <span className="text-xs text-subtle">{acc ? `${acc.counted} contados` : "Sem palpites apurados"}</span>
                )}
              </li>
            );
          })}
        </ul>
      </section>

      <section aria-labelledby="h-historico" className="flex flex-col gap-4">
        <h2 id="h-historico" className="font-display text-2xl">HISTÓRICO</h2>
        {calls.data.length === 0 ? (
          <EmptyState title={own ? "NENHUM PALPITE AINDA" : "NADA PRA VER AQUI"}>
            {own
              ? "Seus shot calls de jogo aparecem aqui: vencedor, margem e MVP, com o resultado depois do apito final."
              : "Os palpites aparecem aqui quando os jogos começam."}
          </EmptyState>
        ) : (
          <ul className="flex flex-col gap-3">
            {calls.data.map((call) => (
              <ShotCallRow key={call.id} call={call} />
            ))}
          </ul>
        )}
        {calls.meta.last_page > 1 && (
          <nav aria-label="Páginas do histórico" className="flex items-center justify-between gap-3">
            {page > 1 ? (
              <Link href={`?pagina=${page - 1}`} className="font-bold text-cyan">← Mais recentes</Link>
            ) : <span />}
            <span className="text-sm text-subtle">Página {page} de {calls.meta.last_page}</span>
            {page < calls.meta.last_page ? (
              <Link href={`?pagina=${page + 1}`} className="font-bold text-cyan">Mais antigos →</Link>
            ) : <span />}
          </nav>
        )}
      </section>
    </div>
  );
}

function ShotCallRow({ call }: { call: ShotCallHistory }) {
  const { game, pick } = call;
  const started = game.status !== "SCHEDULED";

  const result =
    call.status === "SETTLED" ? (
      <span className="rounded-full bg-lime px-3 py-1 font-extrabold text-ink">
        {call.correct} de 3 · +{call.points} pts
      </span>
    ) : call.status === "VOID" ? (
      <span className="rounded-full bg-surface px-3 py-1 font-bold text-muted">Anulado</span>
    ) : started ? (
      <span className="rounded-full bg-pink px-3 py-1 font-extrabold text-ink">Em jogo</span>
    ) : (
      <span className="rounded-full border-2 border-surface px-3 py-1 font-bold text-muted">Aberto</span>
    );

  return (
    <li className="flex flex-wrap items-center gap-x-6 gap-y-3 rounded-2xl border-[3px] border-ink bg-panel px-5 py-4">
      <div className="flex min-w-[180px] items-center gap-3">
        <span className="w-14 text-sm font-bold text-subtle">{formatDay(game.start_time)}</span>
        <span className="font-display text-lg">
          {game.away.tricode}
          {started && <span className="font-score text-xl"> {game.away.score}</span>}
          <span className="px-1.5 text-subtle">@</span>
          {game.home.tricode}
          {started && <span className="font-score text-xl"> {game.home.score}</span>}
        </span>
      </div>
      <div className="flex flex-1 flex-wrap gap-2 text-[13px]">
        <span className="rounded-full bg-surface px-3 py-1 font-bold">{pick.winner} vence</span>
        <span className="rounded-full bg-surface px-3 py-1 font-bold">{marginLabel[pick.margin_band] ?? pick.margin_band}</span>
        {pick.mvp && <span className="rounded-full bg-surface px-3 py-1 font-bold">MVP: {pick.mvp}</span>}
      </div>
      <div className="text-[13px]">{result}</div>
    </li>
  );
}

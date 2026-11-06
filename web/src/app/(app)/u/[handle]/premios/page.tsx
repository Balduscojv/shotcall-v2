import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { formatDay, rarityLabel, rarityStyle, slotLabel } from "@/lib/labels";
import { getProfile } from "@/lib/profile";
import type { Award } from "@/lib/types";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Prêmios" };

// Aba "Prêmios": cosméticos conquistados com acerto nos palpites e títulos de liga.
export default function AwardsTab({ params }: PageProps<"/u/[handle]/premios">) {
  return (
    <Suspense fallback={<TabSkeleton />}>
      <Awards params={params} />
    </Suspense>
  );
}

async function Awards({ params }: Pick<PageProps<"/u/[handle]/premios">, "params">) {
  const { handle } = await params;
  const profile = await getProfile(handle);
  const awards = await serverApi<{ data: Award[]; meta: { collection_total: number } }>(
    `/users/${encodeURIComponent(handle)}/awards`,
  );
  const own = profile.friendship === "SELF";

  return (
    <section aria-labelledby="h-premios" className="flex flex-col gap-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="h-premios" className="font-display text-2xl">PRÊMIOS</h2>
        <span className="font-bold text-muted">
          {awards.data.length} de {awards.meta.collection_total} da coleção 2026-27
        </span>
      </div>

      {awards.data.length === 0 ? (
        <EmptyState title={own ? "A VITRINE ESTÁ VAZIA" : "NENHUM PRÊMIO AINDA"}>
          {own
            ? "Acerte 70% ou mais nos shot calls de jogo, da semana ou da temporada para desbloquear molduras, banners e títulos. Campeão de liga também leva troféu."
            : "Os prêmios conquistados aparecem aqui."}
        </EmptyState>
      ) : (
        <ul className="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-4">
          {awards.data.map((award) => (
            <li
              key={award.key}
              className="flex flex-col gap-3 rounded-2xl border-[3px] border-ink bg-panel p-4 shadow-hard"
            >
              <div className="flex items-center justify-between gap-2">
                <span className={`rounded-md border-2 border-ink px-2 py-0.5 font-display text-[11px] ${rarityStyle[award.rarity]}`}>
                  {rarityLabel[award.rarity].toUpperCase()}
                </span>
                {award.equipped && <span className="text-xs font-extrabold text-lime">EM USO</span>}
              </div>
              <div className="flex flex-col gap-0.5">
                <span className="text-sm font-bold text-subtle">{slotLabel[award.slot] ?? award.slot}</span>
                <span className="text-lg leading-snug font-extrabold">{award.name}</span>
              </div>
              <p className="mt-auto text-sm text-muted">
                {award.reason} · {formatDay(award.unlocked_at)}
              </p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

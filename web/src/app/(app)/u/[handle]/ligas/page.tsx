import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { getProfile } from "@/lib/profile";
import type { ProfileLeague } from "@/lib/types";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Ligas" };

const roleLabel = { OWNER: "Dono", ADMIN: "Admin", MEMBER: "Membro" } as const;
const competitionLabel = { FANTASY: "Fantasy", SHOTCALL: "Shot calls" } as const;

// Aba "Ligas": ligas de que a pessoa participa, com posição na última rodada apurada.
export default function LeaguesTab({ params }: PageProps<"/u/[handle]/ligas">) {
  return (
    <Suspense fallback={<TabSkeleton />}>
      <Leagues params={params} />
    </Suspense>
  );
}

async function Leagues({ params }: Pick<PageProps<"/u/[handle]/ligas">, "params">) {
  const { handle } = await params;
  const profile = await getProfile(handle);
  const { data: leagues } = await serverApi<{ data: ProfileLeague[] }>(`/users/${encodeURIComponent(handle)}/leagues`);
  const own = profile.friendship === "SELF";

  return (
    <section aria-labelledby="h-ligas" className="flex flex-col gap-4">
      <h2 id="h-ligas" className="font-display text-2xl">LIGAS</h2>
      {leagues.length === 0 ? (
        <EmptyState title="NENHUMA LIGA VISÍVEL">
          {own ? "Você ainda não entrou em nenhuma liga." : "As ligas privadas só aparecem para quem também participa."}
        </EmptyState>
      ) : (
        <ul className="grid grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-4">
          {leagues.map((league) => (
            <li key={league.slug} className="flex overflow-hidden rounded-2xl border-[3px] border-ink bg-panel shadow-hard">
              <span className="w-3 shrink-0" style={{ background: league.color }} aria-hidden="true" />
              <div className="flex flex-1 flex-col gap-3 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex min-w-0 flex-col">
                    <span className="truncate text-lg font-extrabold">{league.name}</span>
                    <span className="text-sm text-muted">
                      {league.is_global
                        ? `${league.members_count.toLocaleString("pt-BR")} jogadores`
                        : `${league.members_count} de ${league.max_members} membros`}
                      {league.role && !league.is_global && ` · ${roleLabel[league.role]}`}
                    </span>
                  </div>
                  <div className="flex flex-col items-end">
                    <span className="font-score text-3xl leading-none">{league.rank ? `${league.rank}º` : "—"}</span>
                    <span className="text-xs text-subtle">posição</span>
                  </div>
                </div>
                <div className="flex flex-wrap gap-2 text-xs">
                  {league.competitions.map((c) => (
                    <span key={c} className="rounded-full bg-surface px-2.5 py-1 font-bold">
                      {competitionLabel[c]}
                    </span>
                  ))}
                  {league.privacy === "PRIVATE" && (
                    <span className="rounded-full border-2 border-surface px-2.5 py-0.5 font-bold text-muted">Privada</span>
                  )}
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

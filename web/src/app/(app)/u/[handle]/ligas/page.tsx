import Link from "next/link";
import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { getProfile } from "@/lib/profile";
import type { ProfileLeague } from "@/lib/types";
import { LeagueCard } from "@/components/leagues/LeagueCard";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Ligas" };

// Aba "Ligas": ligas de que a pessoa participa, com posição na última rodada apurada.
// Só aparecem as públicas e as privadas em que quem vê também está, então todas abrem.
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
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="h-ligas" className="font-display text-2xl">LIGAS</h2>
        {own && <Link href="/ligas" className="font-bold">Gerenciar ligas</Link>}
      </div>
      {leagues.length === 0 ? (
        <EmptyState title="NENHUMA LIGA VISÍVEL">
          {own ? "Você ainda não entrou em nenhuma liga." : "As ligas privadas só aparecem para quem também participa."}
        </EmptyState>
      ) : (
        <ul className="grid grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-4">
          {leagues.map((league) => (
            <LeagueCard key={league.slug} league={league} />
          ))}
        </ul>
      )}
    </section>
  );
}

import Link from "next/link";
import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { competitionLabel } from "@/lib/leagues";
import type { LeagueInvite } from "@/lib/types";
import { JoinInviteButton } from "@/components/leagues/JoinInviteButton";
import { LeagueCrest } from "@/components/leagues/LeagueCrest";
import { TabSkeleton } from "@/components/profile/TabStates";
import { buttonClass } from "@/components/ui/button";

export const metadata = { title: "Convite para liga" };

// Link de convite (/ligas/convite/K7Q2XP): mostra a liga e entra com um clique.
// Sem sessão, o serverApi manda para o login.
export default function InvitePage({ params }: PageProps<"/ligas/convite/[code]">) {
  return (
    <div className="flex justify-center px-4 pt-10 sm:px-0">
      <Suspense fallback={<TabSkeleton />}>
        <Invite params={params} />
      </Suspense>
    </div>
  );
}

async function Invite({ params }: { params: Promise<{ code: string }> }) {
  const { code } = await params;
  const { data: league } = await serverApi<{ data: LeagueInvite }>(`/leagues/invite/${encodeURIComponent(code)}`);
  const full = league.members_count >= league.max_members;

  return (
    <section className="flex w-full max-w-md flex-col items-center gap-4 rounded-3xl border-[3px] border-ink bg-panel p-8 text-center shadow-hard-lg">
      <p className="font-bold text-muted">
        <Link href={`/u/${league.owner.handle}`}>@{league.owner.handle}</Link> te chamou para
      </p>
      <LeagueCrest name={league.name} color={league.color} size="lg" />
      <h1 className="font-display text-2xl break-words">{league.name.toUpperCase()}</h1>
      <p className="text-sm text-muted">
        {league.members_count} de {league.max_members} membros · {league.competitions.map((c) => competitionLabel[c]).join(" + ")}
      </p>
      {league.description && <p className="text-muted">{league.description}</p>}

      {league.is_member ? (
        <Link href={`/ligas/${league.slug}`} className={buttonClass("primary", "lg")}>
          Você já está nela · abrir
        </Link>
      ) : full ? (
        <p className="font-bold text-danger">Essa liga está cheia.</p>
      ) : (
        <JoinInviteButton code={code} />
      )}
    </section>
  );
}

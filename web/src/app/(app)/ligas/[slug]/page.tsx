import Link from "next/link";
import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import { competitionLabel, roleLabel } from "@/lib/leagues";
import type { League, LeagueMember, Standings as StandingsData } from "@/lib/types";
import { InvitePanel, LeagueActionButton, MemberControls } from "@/components/leagues/LeagueForms";
import { LeagueCrest } from "@/components/leagues/LeagueCrest";
import { Standings } from "@/components/leagues/Standings";
import { Avatar } from "@/components/profile/Avatar";
import { TabSkeleton } from "@/components/profile/TabStates";
import { buttonClass } from "@/components/ui/button";

export const metadata = { title: "Liga" };

type Search = { board?: string; period?: string; rodada?: string };

// Página da liga (plano: tela 16; canvas App-Liga): rankings, membros e convite.
export default function LeaguePage({ params, searchParams }: PageProps<"/ligas/[slug]">) {
  return (
    <div className="px-4 pt-6 sm:px-0">
      <Suspense fallback={<TabSkeleton />}>
        <LeagueView params={params} searchParams={searchParams as Promise<Search>} />
      </Suspense>
    </div>
  );
}

async function LeagueView({ params, searchParams }: { params: Promise<{ slug: string }>; searchParams: Promise<Search> }) {
  const { slug } = await params;
  const search = await searchParams;
  const query = new URLSearchParams();
  if (search.board) query.set("board", search.board);
  if (search.period) query.set("period", search.period);
  if (search.rodada && /^\d+$/.test(search.rodada)) query.set("round", search.rodada);

  const path = `/leagues/${encodeURIComponent(slug)}`;
  // A liga primeiro: privada de quem não participa dá 404 aqui, sem disparar as outras.
  const { data: league } = await serverApi<{ data: League }>(path);
  const [me, standings, members] = await Promise.all([
    requireUser(),
    serverApi<StandingsData>(`${path}/standings?${query}`),
    league.is_global ? Promise.resolve(null) : serverApi<{ data: LeagueMember[] }>(`${path}/members`),
  ]);

  return (
    <div className="flex flex-col gap-8">
      <header className="flex flex-wrap items-center gap-4">
        <LeagueCrest name={league.name} color={league.color} size="lg" />
        <div className="flex min-w-0 flex-1 basis-60 flex-col gap-1">
          <Link href="/ligas" className="text-sm font-bold">
            ← Ligas
          </Link>
          <h1 className="font-display text-3xl break-words">{league.name.toUpperCase()}</h1>
          <p className="text-sm font-semibold text-muted">
            {league.is_global
              ? `Pública · ${league.members_count.toLocaleString("pt-BR")} jogadores`
              : `${league.privacy === "PRIVATE" ? "Privada" : "Pública"} · ${league.members_count} de ${league.max_members} membros`}
            {" · "}
            {league.competitions.map((c) => competitionLabel[c]).join(" + ")}
            {league.role && !league.is_global && ` · você é ${roleLabel[league.role].toLowerCase()}`}
          </p>
          {league.description && <p className="max-w-2xl text-muted">{league.description}</p>}
        </div>
        <div className="flex flex-wrap gap-2">
          {league.can.join && (
            <LeagueActionButton slug={league.slug} action="join" label="Entrar na liga" />
          )}
          {league.can.update && (
            <Link href={`/ligas/${league.slug}/editar`} className={buttonClass("outline")}>
              Editar
            </Link>
          )}
        </div>
      </header>

      <div className="grid grid-cols-1 items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
        <Standings slug={league.slug} standings={standings} />

        <aside className="flex flex-col gap-6">
          {league.invite_code && (
            <section aria-labelledby="h-convite" className="flex flex-col gap-3 rounded-3xl border-[3px] border-ink bg-panel p-5 shadow-hard">
              <h2 id="h-convite" className="font-display text-xl">CONVIDAR</h2>
              <p className="text-sm text-muted">Mande o link ou o código para quem você quer na liga.</p>
              <InvitePanel slug={league.slug} code={league.invite_code} canRegenerate={league.can.moderate} />
            </section>
          )}

          {members && (
            <section aria-labelledby="h-membros" className="flex flex-col gap-3">
              <h2 id="h-membros" className="font-display text-xl">
                MEMBROS <span className="text-subtle">{members.data.length}</span>
              </h2>
              <ul className="flex flex-col gap-2">
                {members.data.map((member) => {
                  const isOwner = member.league_role === "OWNER";
                  const isMe = member.id === me.id;
                  const canRemove =
                    league.can.moderate && !isOwner && !isMe && (league.role === "OWNER" || member.league_role === "MEMBER");
                  return (
                    <li key={member.id} className="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-panel p-3">
                      <Link href={`/u/${member.handle}`} className="flex min-w-0 items-center gap-3 text-chalk no-underline">
                        <Avatar user={member} size="xs" />
                        <span className="flex min-w-0 flex-col">
                          <span className="truncate text-sm font-extrabold">
                            {member.name}
                            {isMe && <span className="text-lime"> (você)</span>}
                          </span>
                          <span className="truncate text-xs text-muted">{roleLabel[member.league_role]}</span>
                        </span>
                      </Link>
                      {!isOwner && !isMe && (league.can.change_roles || canRemove) && (
                        <MemberControls slug={league.slug} member={member} canChangeRole={league.can.change_roles} canRemove={canRemove} />
                      )}
                    </li>
                  );
                })}
              </ul>
            </section>
          )}

          {league.can.leave && (
            <LeagueActionButton
              slug={league.slug}
              action="leave"
              label="Sair da liga"
              variant="ghost"
              confirmText={`Sair da ${league.name}? Para voltar, vai precisar do código.`}
            />
          )}
        </aside>
      </div>
    </div>
  );
}

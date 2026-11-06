import Link from "next/link";
import { competitionLabel, roleLabel } from "@/lib/leagues";
import type { ProfileLeague } from "@/lib/types";
import { LeagueCrest } from "./LeagueCrest";

/**
 * Card de liga com escudo, lotação, papel e posição. Em "Minhas ligas" e no perfil.
 * Com `linked`, leva para a página da liga (no perfil de outra pessoa, a privada não abre).
 */
export function LeagueCard({ league, linked = true }: { league: ProfileLeague; linked?: boolean }) {
  const body = (
    <>
      <LeagueCrest name={league.name} color={league.color} />
      <div className="flex min-w-0 flex-1 flex-col gap-2">
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
    </>
  );

  const box = "flex items-center gap-4 rounded-2xl border-[3px] border-ink bg-panel p-4 text-chalk shadow-hard";
  return (
    <li className="flex">
      {linked ? (
        <Link href={`/ligas/${league.slug}`} className={`${box} flex-1 no-underline transition-transform hover:-translate-y-0.5`}>
          {body}
        </Link>
      ) : (
        <div className={`${box} flex-1`}>{body}</div>
      )}
    </li>
  );
}

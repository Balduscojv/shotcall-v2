import Link from "next/link";
import { competitionLabel } from "@/lib/leagues";
import type { LeagueTrophy } from "@/lib/types";
import { LeagueCrest } from "./LeagueCrest";

/** Até quantos troféus aparecem antes do "e mais N". */
const SHOWN = 6;

const medal = {
  1: { label: "Campeão", ring: "#FFC83D", cup: "bg-[linear-gradient(160deg,var(--color-yellow),var(--color-orange))]" },
  2: { label: "2º lugar", ring: "#C9D1DC", cup: "bg-[#C9D1DC]" },
  3: { label: "3º lugar", ring: "#D9822B", cup: "bg-[#D9822B]" },
} as const;

/** Vitrine de troféus do perfil (plano: tela 17): pódios de liga, campeões primeiro. */
export function TrophyShelf({ trophies }: { trophies: LeagueTrophy[] }) {
  const shown = trophies.slice(0, SHOWN);
  const titles = trophies.filter((t) => t.rank === 1).length;

  return (
    <section aria-labelledby="h-trofeus" className="flex flex-col gap-3 px-4 sm:px-0">
      <h2 id="h-trofeus" className="font-display text-lg">
        TROFÉUS{" "}
        <span className="text-subtle">
          {titles > 0 && `${titles} ${titles === 1 ? "título" : "títulos"} · `}
          {trophies.length} {trophies.length === 1 ? "pódio" : "pódios"}
        </span>
      </h2>
      <ul className="flex gap-3 overflow-x-auto pb-2">
        {shown.map((t, i) => {
          const m = medal[t.rank as 1 | 2 | 3];
          const body = (
            <>
              <span className="relative">
                <LeagueCrest name={t.league_name} color={t.league_color} size="sm" />
                <span
                  className={`absolute -right-2 -bottom-1 flex size-6 items-center justify-center rounded-full border-2 border-ink font-score text-xs text-ink ${m.cup}`}
                  aria-hidden="true"
                >
                  {t.rank}
                </span>
              </span>
              <span className="flex min-w-0 flex-col">
                <span className="text-xs font-extrabold tracking-wide uppercase" style={{ color: m.ring }}>
                  {m.label}
                </span>
                <span className="truncate font-extrabold">{t.league_name}</span>
                <span className="truncate text-xs text-muted">
                  {competitionLabel[t.competition]} · {t.season} · {t.members_count} membros
                </span>
              </span>
            </>
          );
          const box = "flex w-64 items-center gap-3 rounded-2xl border-2 bg-panel p-3 text-chalk";
          return (
            <li key={`${t.league_name}-${t.competition}-${t.season}-${i}`} className="shrink-0">
              {t.league_slug ? (
                <Link href={`/ligas/${t.league_slug}`} className={`${box} no-underline hover:-translate-y-0.5`} style={{ borderColor: m.ring }}>
                  {body}
                </Link>
              ) : (
                <div className={box} style={{ borderColor: m.ring }}>
                  {body}
                </div>
              )}
            </li>
          );
        })}
        {trophies.length > SHOWN && (
          <li className="flex shrink-0 items-center px-3 text-sm font-bold text-muted">e mais {trophies.length - SHOWN}</li>
        )}
      </ul>
    </section>
  );
}

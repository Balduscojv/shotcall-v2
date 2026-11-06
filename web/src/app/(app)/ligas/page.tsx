import Link from "next/link";
import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import type { ProfileLeague } from "@/lib/types";
import { LeagueCard } from "@/components/leagues/LeagueCard";
import { JoinByCodeForm, PublicLeagueSearch } from "@/components/leagues/LeagueForms";
import { TabSkeleton } from "@/components/profile/TabStates";
import { buttonClass } from "@/components/ui/button";

export const metadata = { title: "Ligas" };

// Minhas ligas (com a posição), criar, entrar por código e buscar públicas (plano: tela 15).
export default function LeaguesPage() {
  return (
    <div className="flex flex-col gap-10 px-4 pt-6 sm:px-0">
      <Suspense fallback={<TabSkeleton />}>
        <MyLeagues />
      </Suspense>

      <div className="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="h-codigo" className="flex flex-col gap-3 rounded-3xl border-[3px] border-ink bg-panel p-5 shadow-hard">
          <h2 id="h-codigo" className="font-display text-xl">ENTRAR COM CÓDIGO</h2>
          <p className="text-muted">Ligas privadas só aceitam quem tem o código de 6 letras ou o link de convite.</p>
          <JoinByCodeForm />
        </section>
        <section aria-labelledby="h-publicas" className="flex flex-col gap-3 rounded-3xl border-[3px] border-ink bg-panel p-5 shadow-hard">
          <h2 id="h-publicas" className="font-display text-xl">LIGAS PÚBLICAS</h2>
          <PublicLeagueSearch />
        </section>
      </div>
    </div>
  );
}

async function MyLeagues() {
  const { data: leagues, meta } = await serverApi<{ data: ProfileLeague[]; meta: { count: number; max: number } }>("/leagues");
  const atLimit = meta.count >= meta.max;

  return (
    <section aria-labelledby="h-minhas" className="flex flex-col gap-4">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h1 id="h-minhas" className="font-display text-3xl">MINHAS LIGAS</h1>
          <p className="text-muted">
            {meta.count} de {meta.max} ligas, fora a Liga Global.
          </p>
        </div>
        {atLimit ? (
          <p className="max-w-xs text-sm text-subtle">Você chegou ao máximo de {meta.max} ligas. Saia de uma para criar outra.</p>
        ) : (
          <Link href="/ligas/nova" className={buttonClass("hype", "lg")}>
            Criar liga
          </Link>
        )}
      </header>
      <ul className="grid grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-4">
        {leagues.map((league) => (
          <LeagueCard key={league.slug} league={league} />
        ))}
      </ul>
      {meta.count === 0 && (
        <p className="text-muted">Crie uma liga e chame os amigos, ou entre numa com o código que te mandaram.</p>
      )}
    </section>
  );
}

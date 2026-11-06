import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { longDay } from "@/lib/games";
import type { GamesDay } from "@/lib/types";
import { AutoRefresh } from "@/components/games/AutoRefresh";
import { GameCard } from "@/components/games/GameCard";
import { WeekStrip } from "@/components/games/WeekStrip";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Jogos" };

type Search = { data?: string };

// Calendário da NBA. O dia é o da noite americana (Nova York); os horários aparecem em Brasília.
export default function GamesPage({ searchParams }: PageProps<"/jogos">) {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <Suspense fallback={<TabSkeleton />}>
        <GamesOfDay searchParams={searchParams as Promise<Search>} />
      </Suspense>
    </div>
  );
}

async function GamesOfDay({ searchParams }: { searchParams: Promise<Search> }) {
  const { data } = await searchParams;
  const query = data && /^\d{4}-\d{2}-\d{2}$/.test(data) ? `?date=${data}` : "";
  const { data: games, meta } = await serverApi<GamesDay>(`/games${query}`);

  const live = games.filter((g) => g.status === "LIVE");
  const isToday = meta.date === meta.today;

  return (
    <>
      <AutoRefresh enabled={live.length > 0} />
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h1 className="font-display text-3xl">{isToday ? "JOGOS DE HOJE" : "JOGOS"}</h1>
          <p className="text-muted first-letter:uppercase">
            {longDay(meta.date)}
            {games.length > 0 && ` · ${games.length} ${games.length === 1 ? "jogo" : "jogos"}`}
            {live.length > 0 && ` · ${live.length} ao vivo`}
          </p>
        </div>
        <p className="text-sm text-subtle">Horários de Brasília</p>
      </header>

      <WeekStrip meta={meta} />

      {games.length === 0 ? (
        <EmptyState title="SEM JOGOS NESTE DIA">
          {meta.next_date ? "Use as setas para ir ao próximo dia com jogo." : "O calendário ainda não tem jogos depois deste dia."}
        </EmptyState>
      ) : (
        <ul className="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-5">
          {/* Ao vivo primeiro, depois na ordem do calendário. */}
          {[...live, ...games.filter((g) => g.status !== "LIVE")].map((game) => (
            <GameCard key={game.id} game={game} nbaDay={meta.date} />
          ))}
        </ul>
      )}
    </>
  );
}

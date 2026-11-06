"use client";

import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { teamAccent } from "@/lib/games";
import { bandLabel, bands, formatAverage } from "@/lib/shotcalls";
import type { GameShotCall, MarginBand, MvpCandidate, Team } from "@/lib/types";
import { buttonClass } from "@/components/ui/button";

const check = (
  <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
    <path d="M3 8.5 6.5 12 13 4" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
  </svg>
);

function Legend({ n, title, points }: { n: number; title: string; points: number }) {
  return (
    <legend className="mb-2 font-display text-sm">
      {n} · {title} <span className="font-sans text-xs font-bold text-subtle">+{points}</span>
    </legend>
  );
}

/**
 * Formulário do shot call: vencedor (+1), margem (+2) e MVP do jogo (+3). Dá para mudar
 * quantas vezes quiser até a bola subir; o servidor confere a trava.
 */
export function ShotCallForm({ gameId, home, away, shotCall }: { gameId: string; home: Team; away: Team; shotCall: GameShotCall }) {
  const router = useRouter();
  const mine = shotCall.mine;
  const [winner, setWinner] = useState<number | null>(mine?.winner_team_id ?? null);
  const [band, setBand] = useState<MarginBand | null>(mine?.margin_band ?? null);
  const [mvp, setMvp] = useState<number | null>(mine?.mvp?.id ?? null);
  const [picking, setPicking] = useState(!mine?.mvp);
  const [query, setQuery] = useState("");
  const [teamFilter, setTeamFilter] = useState<number | "all">("all");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const teams = [away, home];
  const teamOf = (id: number) => (id === home.id ? home : away);
  const chosen = shotCall.candidates.find((c) => c.id === mvp) ?? null;
  const dirty = winner !== (mine?.winner_team_id ?? null) || band !== (mine?.margin_band ?? null) || mvp !== (mine?.mvp?.id ?? null);

  const list = useMemo(() => {
    const q = query.trim().toLocaleLowerCase("pt-BR");
    return shotCall.candidates.filter(
      (c) => (teamFilter === "all" || c.team_id === teamFilter) && (!q || c.name.toLocaleLowerCase("pt-BR").includes(q)),
    );
  }, [shotCall.candidates, query, teamFilter]);

  async function submit() {
    if (!winner || !band || !mvp) {
      setError("Escolha o vencedor, a margem e o MVP.");
      return;
    }
    setPending(true);
    setError(null);
    setSaved(false);
    try {
      await api(`/games/${gameId}/shot-call`, { method: "PUT", body: { winner_team_id: winner, margin_band: band, mvp_player_id: mvp } });
      setSaved(true);
      router.refresh();
    } catch (e) {
      setError(
        e instanceof ApiError
          ? (e.field("game") ?? e.field("winner_team_id") ?? e.field("margin_band") ?? e.field("mvp_player_id") ?? e.message)
          : "Não deu certo.",
      );
    } finally {
      setPending(false);
    }
  }

  const candidateRow = (c: MvpCandidate) => {
    const team = teamOf(c.team_id);
    const active = c.id === mvp;
    return (
      <li key={c.id}>
        <button
          type="button"
          aria-pressed={active}
          onClick={() => {
            setMvp(c.id);
            setPicking(false);
            setSaved(false);
          }}
          className={`flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left ${active ? "bg-purple/25" : "hover:bg-surface"}`}
        >
          <span className="h-8 w-1.5 shrink-0 rounded-full" style={{ background: teamAccent(team) }} aria-hidden="true" />
          <span className="flex min-w-0 flex-1 flex-col">
            <span className="truncate font-bold">{c.name}</span>
            <span className="text-xs text-subtle">
              {team.tricode}
              {c.position && ` · ${c.position}`} · {formatAverage(c.average)}
            </span>
          </span>
          {active && <span className="text-purple">{check}</span>}
        </button>
      </li>
    );
  };

  return (
    <div className="flex flex-col gap-4">
      <p className="rounded-xl bg-purple/15 px-3 py-2 text-xs font-bold text-[#C4B5FD]">
        Cada acerto vira boost no seu time do fantasy nesta rodada: vencedor +1%, margem +2%, MVP +3%.
      </p>
      <fieldset className="m-0 border-0 p-0">
        <Legend n={1} title="VENCEDOR" points={1} />
        <div className="grid grid-cols-2 gap-2.5">
          {teams.map((t) => {
            const active = winner === t.id;
            return (
              <button
                key={t.id}
                type="button"
                aria-pressed={active}
                onClick={() => {
                  setWinner(t.id);
                  setSaved(false);
                }}
                className={`flex h-[52px] items-center justify-center gap-2 rounded-2xl border-2 font-display text-base ${
                  active ? "border-ink bg-cyan text-ink shadow-hard-sm" : "border-surface bg-panel text-chalk hover:border-chalk"
                }`}
              >
                {active && check}
                {t.tricode}
                <span className={`font-sans text-[11px] font-bold ${active ? "text-ink/70" : "text-subtle"}`}>{t.id === home.id ? "casa" : "fora"}</span>
              </button>
            );
          })}
        </div>
      </fieldset>

      <fieldset className="m-0 border-0 p-0">
        <Legend n={2} title="MARGEM" points={2} />
        <div className="grid grid-cols-4 gap-2">
          {bands.map((b) => {
            const active = band === b;
            return (
              <button
                key={b}
                type="button"
                aria-pressed={active}
                aria-label={`Vence por ${bandLabel[b]} pontos`}
                onClick={() => {
                  setBand(b);
                  setSaved(false);
                }}
                className={`h-[46px] rounded-xl border-2 text-sm font-extrabold ${
                  active ? "border-ink bg-lime text-ink shadow-hard-sm" : "border-surface bg-panel text-chalk hover:border-chalk"
                }`}
              >
                {bandLabel[b]}
              </button>
            );
          })}
        </div>
      </fieldset>

      <fieldset className="m-0 border-0 p-0">
        <Legend n={3} title="MVP DO JOGO" points={3} />
        <p className="-mt-1 mb-2 text-xs text-subtle">Quem fizer mais pontos fantasy na partida.</p>
        {chosen && !picking ? (
          <div className="flex items-center gap-3 rounded-2xl border-2 border-purple bg-[#1C1730] px-3 py-2.5">
            <span className="h-10 w-1.5 shrink-0 rounded-full" style={{ background: teamAccent(teamOf(chosen.team_id)) }} aria-hidden="true" />
            <span className="flex min-w-0 flex-1 flex-col">
              <strong className="truncate">{chosen.name}</strong>
              <span className="text-xs text-muted">
                {teamOf(chosen.team_id).tricode} · {formatAverage(chosen.average)}
              </span>
            </span>
            <button type="button" onClick={() => setPicking(true)} className="h-11 px-2 text-sm font-extrabold text-[#C4B5FD] hover:text-chalk">
              Trocar
            </button>
          </div>
        ) : (
          <div className="flex flex-col gap-2 rounded-2xl border-2 border-surface bg-panel p-2">
            <div className="flex flex-wrap gap-2 px-1 pt-1">
              <label htmlFor="mvp-busca" className="sr-only">
                Buscar jogador
              </label>
              <input
                id="mvp-busca"
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Buscar jogador"
                className="h-10 min-w-0 flex-1 rounded-xl border-2 border-surface bg-ground px-3 text-sm text-chalk placeholder:text-subtle focus:border-cyan focus:outline-none"
              />
              <div className="flex gap-1" role="group" aria-label="Filtrar por time">
                {(["all", away, home] as const).map((t) => {
                  const id = t === "all" ? "all" : t.id;
                  const active = teamFilter === id;
                  return (
                    <button
                      key={String(id)}
                      type="button"
                      aria-pressed={active}
                      onClick={() => setTeamFilter(id)}
                      className={`h-10 rounded-xl px-3 text-xs font-extrabold ${active ? "bg-chalk text-ink" : "bg-surface text-chalk"}`}
                    >
                      {t === "all" ? "Todos" : t.tricode}
                    </button>
                  );
                })}
              </div>
            </div>
            <ul className="flex max-h-72 flex-col overflow-y-auto" aria-label="Jogadores">
              {list.length ? list.map(candidateRow) : <li className="px-3 py-4 text-sm text-subtle">Ninguém com esse nome.</li>}
            </ul>
          </div>
        )}
      </fieldset>

      <div className="flex flex-col gap-2">
        <button type="button" onClick={submit} disabled={pending || (!dirty && !!mine)} className={buttonClass("hype", "lg", "w-full")}>
          {pending ? "CRAVANDO…" : mine ? "ATUALIZAR PALPITE" : "CRAVAR PALPITE"}
        </button>
        <p className="text-center text-sm" role="status">
          {error ? (
            <span className="font-bold text-danger">{error}</span>
          ) : saved || (mine && !dirty) ? (
            <span className="font-bold text-lime">Palpite cravado. Dá para mudar até a bola subir.</span>
          ) : (
            <span className="text-subtle">Você pode mudar até a bola subir.</span>
          )}
        </p>
      </div>
    </div>
  );
}

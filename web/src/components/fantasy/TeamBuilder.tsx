"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { fmoney, fpoints, fvariation, roleLabel, roleOf, slots } from "@/lib/fantasy";
import { onTeamAccent, teamAccent } from "@/lib/games";
import type { FantasySlot, FantasyTeam, MarketPlayer, Team } from "@/lib/types";
import { buttonClass } from "@/components/ui/button";
import { BoostCard } from "./BoostCard";
import { Flame, OnFire } from "./Flame";

type Draft = Record<FantasySlot, MarketPlayer | null>;
type Sort = "price" | "average" | "games";

/** Onde cada vaga fica na meia quadra (em %): pivô perto da cesta, alas nos lados, armadores atrás. */
const spots: Record<FantasySlot, { left: string; top: string }> = {
  C: { left: "50%", top: "17%" },
  PF: { left: "20%", top: "44%" },
  SF: { left: "80%", top: "44%" },
  PG: { left: "29%", top: "76%" },
  SG: { left: "71%", top: "76%" },
};

function initialDraft(team: FantasyTeam | null, market: MarketPlayer[]): Draft {
  const byId = new Map(market.map((p) => [p.id, p]));
  const draft = { PG: null, SG: null, SF: null, PF: null, C: null } as Draft;
  team?.players.forEach((p) => {
    // Time de antes da regra de uma vaga por posição: encaixa pela posição do jogador.
    const s = slots.includes(p.slot) ? p.slot : roleOf(p);
    if (!draft[s]) draft[s] = byId.get(p.id) ?? null;
  });
  return draft;
}

/**
 * Montar o time com o mercado aberto: a quadra com as 5 vagas e o mercado ao lado. Clicar numa
 * vaga filtra o mercado pela posição; o foguinho escolhe o cestinha. Nada é salvo até "Salvar time".
 */
export function TeamBuilder({
  team,
  market,
  teams,
  startingBalance,
}: {
  team: FantasyTeam | null;
  market: MarketPlayer[];
  teams: Team[];
  startingBalance: number;
}) {
  const router = useRouter();
  const teamById = useMemo(() => new Map(teams.map((t) => [t.id, t])), [teams]);
  const [draft, setDraft] = useState<Draft>(() => initialDraft(team, market));
  const [cestinha, setCestinha] = useState<number | null>(team?.cestinha_id ?? null);
  const [slot, setSlot] = useState<FantasySlot>(() => slots.find((s) => !initialDraft(team, market)[s]) ?? "PG");
  const [query, setQuery] = useState("");
  const [teamFilter, setTeamFilter] = useState<number | "all">("all");
  const [sort, setSort] = useState<Sort>("price");
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);
  const [saved, setSaved] = useState(false);

  const patrimony = team?.patrimony ?? startingBalance;
  const picked = slots.map((s) => draft[s]).filter((p): p is MarketPlayer => !!p);
  const cost = picked.reduce((sum, p) => sum + p.price, 0);
  const balance = Math.round((patrimony - cost) * 100) / 100;
  const complete = picked.length === 5 && cestinha !== null && picked.some((p) => p.id === cestinha);
  const original = team ? [...team.players.map((p) => p.id)].sort().join() + `|${team.cestinha_id}` : "";
  const dirty = [...picked.map((p) => p.id)].sort().join() + `|${cestinha}` !== original;

  const list = useMemo(() => {
    const q = query.trim().toLocaleLowerCase("pt-BR");
    const rows = market.filter(
      (p) => roleOf(p) === slot && (teamFilter === "all" || p.team_id === teamFilter) && (!q || p.name.toLocaleLowerCase("pt-BR").includes(q)),
    );
    const key: Record<Sort, (p: MarketPlayer) => number> = {
      price: (p) => p.price,
      average: (p) => p.average ?? -1,
      games: (p) => p.games_in_round * 100 + (p.average ?? 0),
    };
    return rows.sort((a, b) => key[sort](b) - key[sort](a)).slice(0, 60);
  }, [market, slot, teamFilter, query, sort]);

  function choose(p: MarketPlayer) {
    const next = { ...draft, [slot]: p };
    setDraft(next);
    setSaved(false);
    setErrors([]);
    // Trocou o cestinha por outro jogador: fica sem cestinha até escolher de novo.
    if (draft[slot]?.id === cestinha) setCestinha(null);
    // Próxima vaga vazia, para escalar em sequência.
    const empty = slots.find((s) => !next[s]);
    if (empty) setSlot(empty);
  }

  function remove(s: FantasySlot) {
    if (draft[s]?.id === cestinha) setCestinha(null);
    setDraft({ ...draft, [s]: null });
    setSlot(s);
    setSaved(false);
  }

  async function save() {
    setPending(true);
    setErrors([]);
    try {
      await api("/fantasy/team", { method: "PUT", body: { players: slots.map((s) => draft[s]?.id), cestinha_id: cestinha } });
      setSaved(true);
      router.refresh();
    } catch (e) {
      setErrors(e instanceof ApiError ? (Object.values(e.errors).flat().length ? Object.values(e.errors).flat() : [e.message]) : ["Não deu certo."]);
    } finally {
      setPending(false);
    }
  }

  const owned = new Set(picked.map((p) => p.id));

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
      <div className="flex min-w-0 flex-col gap-4">
        <section aria-label="Saldo e patrimônio" className="grid grid-cols-3 gap-2.5">
          {[
            ["SALDO", fmoney(balance), balance < 0 ? "text-danger" : "text-chalk"],
            ["CUSTO", fmoney(cost), "text-chalk"],
            ["PATRIMÔNIO", fmoney(patrimony), "text-chalk"],
          ].map(([label, value, color]) => (
            <div key={label} className="flex min-w-0 flex-col rounded-2xl border-2 border-surface bg-panel px-3 py-2">
              <span className="text-[11px] font-extrabold tracking-wider text-subtle">{label}</span>
              <span className={`truncate font-score text-xl sm:text-2xl ${color}`}>{value}</span>
            </div>
          ))}
        </section>

        <section aria-label="Escalação" className="relative aspect-[354/400] overflow-hidden rounded-3xl border-[3px] border-ink bg-[#1F3A5F] shadow-hard">
          <svg viewBox="0 0 354 400" className="absolute inset-0 size-full" aria-hidden="true">
            <g fill="none" stroke="var(--color-chalk)" strokeOpacity=".45" strokeWidth="2.5">
              <rect x="117" y="0" width="120" height="150" />
              <circle cx="177" cy="150" r="46" />
              <path d="M22 0v40a155 155 0 0 0 310 0V0" />
              <path d="M120 400a57 57 0 0 1 114 0" />
            </g>
            <circle cx="177" cy="32" r="12" fill="none" stroke="var(--color-orange)" strokeWidth="4" />
            <rect x="152" y="14" width="50" height="4" fill="var(--color-chalk)" />
          </svg>

          {slots.map((s) => {
            const p = draft[s];
            const t = p ? teamById.get(p.team_id) : null;
            const isC = !!p && p.id === cestinha;
            const active = slot === s;
            return (
              <div key={s} className="absolute flex w-[30%] -translate-x-1/2 -translate-y-1/2 flex-col items-center gap-1" style={spots[s]}>
                <button
                  type="button"
                  onClick={() => setSlot(s)}
                  aria-pressed={active}
                  aria-label={p ? `${roleLabel[s]}: ${p.name}. Trocar` : `Escalar ${roleLabel[s].toLowerCase()} (${s})`}
                  className={`relative flex size-14 items-center justify-center rounded-full border-[3px] font-display text-xs sm:size-16 ${
                    active ? "outline-[3px] outline-offset-2 outline-lime" : ""
                  } ${p ? "border-ink text-chalk" : "border-dashed border-chalk/60 bg-ink/40 text-chalk/80"} ${isC ? "animate-on-fire" : ""}`}
                  style={p && t ? { background: teamAccent(t), color: onTeamAccent(t) } : undefined}
                >
                  {p ? t?.tricode : "+"}
                  {isC && <OnFire />}
                </button>
                <span className={`max-w-full truncate rounded-md px-1.5 py-0.5 text-[11px] font-extrabold sm:text-xs ${isC ? "bg-yellow text-ink" : "bg-ink text-chalk"}`}>
                  {p ? `${p.name.split(" ").slice(-1)[0]} · ${s}` : `${roleLabel[s]} · ${s}`}
                </span>
                {p && (
                  <span className="flex items-center gap-1">
                    <span className="text-[11px] font-bold whitespace-nowrap text-muted">{fmoney(p.price)}</span>
                    <button
                      type="button"
                      onClick={() => setCestinha(p.id)}
                      aria-pressed={isC}
                      aria-label={`Fazer ${p.name} cestinha (pontuação em dobro)`}
                      className={`flex h-6 w-7 items-center justify-center rounded-md ${isC ? "bg-yellow" : "bg-ink/70 hover:bg-ink"}`}
                    >
                      <Flame lit={isC} size={11} />
                    </button>
                    <button
                      type="button"
                      onClick={() => remove(s)}
                      aria-label={`Tirar ${p.name} do time`}
                      className="h-6 w-6 rounded-md bg-ink/70 text-xs font-extrabold text-pink hover:bg-ink"
                    >
                      ×
                    </button>
                  </span>
                )}
              </div>
            );
          })}
        </section>

        <div className="flex flex-col gap-2">
          <button type="button" onClick={save} disabled={pending || !complete || !dirty} className={buttonClass("hype", "lg", "w-full")}>
            {pending ? "SALVANDO…" : team ? "SALVAR ALTERAÇÕES" : "SALVAR TIME"}
          </button>
          <div role="status" className="text-center text-sm">
            {errors.length ? (
              errors.map((e) => (
                <p key={e} className="font-bold text-danger">
                  {e}
                </p>
              ))
            ) : saved ? (
              <p className="font-bold text-lime">Time salvo. Dá para mexer até o mercado fechar.</p>
            ) : !complete ? (
              <p className="text-subtle">
                {picked.length < 5 ? `Faltam ${5 - picked.length} jogador(es).` : "Escolha o cestinha: toque no foguinho de um dos jogadores."}
              </p>
            ) : dirty ? (
              <p className="text-subtle">Alterações não salvas.</p>
            ) : (
              <p className="text-subtle">Time salvo. O cestinha pontua em dobro.</p>
            )}
          </div>
        </div>
        <BoostCard boost={team?.boost ?? null} />
      </div>

      <section aria-labelledby="h-mercado" className="flex min-w-0 flex-col gap-3">
        <h2 id="h-mercado" className="font-display text-lg">MERCADO</h2>
        {/* Uma vaga por posição: escolher a posição é escolher a vaga. */}
        <div className="grid grid-cols-5 gap-1.5" role="group" aria-label="Posição">
          {slots.map((r) => {
            const active = slot === r;
            const filled = !!draft[r];
            return (
              <button
                key={r}
                type="button"
                aria-pressed={active}
                aria-label={`${roleLabel[r]} (${r})${filled ? `: ${draft[r]!.name}` : ": vaga vazia"}`}
                onClick={() => setSlot(r)}
                className={`relative flex min-w-0 flex-col items-center justify-center gap-0.5 rounded-xl border-2 px-1 py-2 transition-colors ${
                  active ? "border-ink bg-chalk text-ink shadow-hard-sm" : "border-surface bg-panel text-chalk hover:border-chalk/60"
                }`}
              >
                <span className="font-display text-base leading-none">{r}</span>
                <span className={`max-w-full text-center text-[9px] leading-tight font-bold break-words sm:text-[11px] ${active ? "text-ink/70" : "text-muted"}`}>{roleLabel[r]}</span>
                {filled && (
                  <span
                    className={`absolute -top-1.5 -right-1.5 flex size-4 items-center justify-center rounded-full border-2 border-ink text-[8px] font-black ${active ? "bg-ink text-chalk" : "bg-lime text-ink"}`}
                    aria-hidden="true"
                  >
                    ✓
                  </span>
                )}
              </button>
            );
          })}
        </div>

        <div className="flex flex-wrap gap-2">
          <label htmlFor="mercado-busca" className="sr-only">
            Buscar jogador
          </label>
          <input
            id="mercado-busca"
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Buscar jogador"
            className="h-10 min-w-0 flex-1 basis-40 rounded-xl border-2 border-surface bg-ground px-3 text-sm text-chalk placeholder:text-subtle focus:border-cyan focus:outline-none"
          />
          <label htmlFor="mercado-time" className="sr-only">
            Time
          </label>
          <select
            id="mercado-time"
            value={String(teamFilter)}
            onChange={(e) => setTeamFilter(e.target.value === "all" ? "all" : Number(e.target.value))}
            className="h-10 rounded-xl border-2 border-surface bg-ground px-2 text-sm text-chalk"
          >
            <option value="all">Todos os times</option>
            {teams.map((t) => (
              <option key={t.id} value={t.id}>
                {t.tricode} · {t.name}
              </option>
            ))}
          </select>
          <label htmlFor="mercado-ordem" className="sr-only">
            Ordenar
          </label>
          <select
            id="mercado-ordem"
            value={sort}
            onChange={(e) => setSort(e.target.value as Sort)}
            className="h-10 rounded-xl border-2 border-surface bg-ground px-2 text-sm text-chalk"
          >
            <option value="price">Mais caros</option>
            <option value="average">Maior média</option>
            <option value="games">Mais jogos na rodada</option>
          </select>
        </div>

        <p className="text-sm text-subtle">
          Escalando: <strong className="text-chalk">{roleLabel[slot]} ({slot})</strong>
          {draft[slot] && ` (no lugar de ${draft[slot]!.name})`}
        </p>

        <ul className="flex max-h-[640px] flex-col overflow-y-auto rounded-2xl border-2 border-surface bg-panel" aria-label="Jogadores do mercado">
          {list.length === 0 && <li className="px-4 py-6 text-sm text-subtle">Ninguém encontrado.</li>}
          {list.map((p) => {
            const t = teamById.get(p.team_id);
            const mine = owned.has(p.id);
            const afford = p.price - (draft[slot]?.price ?? 0) <= balance;
            return (
              <li key={p.id} className="flex items-center gap-3 border-b-2 border-surface px-3 py-2.5 last:border-0">
                <span className="h-10 w-1.5 shrink-0 rounded-full" style={{ background: t ? teamAccent(t) : undefined }} aria-hidden="true" />
                <span className="flex min-w-0 flex-1 flex-col">
                  <Link href={`/jogadores/${p.id}`} className="truncate font-bold text-chalk no-underline hover:text-cyan">
                    {p.name}
                  </Link>
                  <span className="text-xs text-subtle">
                    {t?.tricode} · {roleOf(p)} · média {p.average === null ? "—" : fpoints(p.average)} · {p.games_in_round} {p.games_in_round === 1 ? "jogo" : "jogos"} na rodada
                  </span>
                </span>
                <span className="flex shrink-0 flex-col items-end">
                  <span className="font-score text-lg leading-none">{fmoney(p.price)}</span>
                  {p.variation !== 0 && (
                    <span className={`text-[11px] font-extrabold ${p.variation > 0 ? "text-lime" : "text-pink"}`}>{fvariation(p.variation)}</span>
                  )}
                </span>
                <button
                  type="button"
                  disabled={mine}
                  onClick={() => choose(p)}
                  aria-label={mine ? `${p.name} já está no time` : `Escalar ${p.name} por ${fmoney(p.price)}`}
                  className={`h-10 w-[84px] shrink-0 rounded-xl text-xs font-extrabold ${
                    mine ? "bg-surface text-subtle" : afford ? "border-2 border-ink bg-lime text-ink" : "border-2 border-surface text-subtle hover:text-chalk"
                  }`}
                >
                  {mine ? "No time" : afford ? "Escalar" : "Sem saldo"}
                </button>
              </li>
            );
          })}
        </ul>
      </section>
    </div>
  );
}

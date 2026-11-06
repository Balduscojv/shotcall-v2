"use client";

import { useEffect, useRef, useState } from "react";
import { api } from "@/lib/api";
import type { MissionLeague } from "@/lib/types";
import { LeagueCrest } from "@/components/leagues/LeagueCrest";

const SEARCH_DELAY_MS = 250;

/**
 * Parâmetro "liga" de uma missão: qualquer liga (0) ou uma específica, achada pelo nome.
 * O valor continua sendo o id da liga (inteiro), como os outros parâmetros.
 */
export function LeagueParamPicker({
  id,
  label,
  help,
  value,
  onChange,
  error,
}: {
  id: string;
  label: string;
  help?: string;
  value: number;
  onChange: (value: number) => void;
  error?: string;
}) {
  const [selected, setSelected] = useState<MissionLeague | null>(null);
  const [term, setTerm] = useState("");
  const [results, setResults] = useState<MissionLeague[]>([]);
  const latest = useRef(0);

  // Editando uma missão já vinculada: busca o nome da liga pelo id.
  useEffect(() => {
    if (value === 0 || selected?.id === value) return;
    api<{ data: MissionLeague[] }>(`/admin/missions/leagues?ids=${value}`)
      .then(({ data }) => setSelected(data[0] ?? null))
      .catch(() => setSelected(null));
  }, [value, selected?.id]);

  useEffect(() => {
    if (term.trim().length < 2) return;
    const reqId = ++latest.current;
    const timer = setTimeout(async () => {
      try {
        const { data } = await api<{ data: MissionLeague[] }>(`/admin/missions/leagues?q=${encodeURIComponent(term.trim())}`);
        if (reqId === latest.current) setResults(data);
      } catch {
        if (reqId === latest.current) setResults([]);
      }
    }, SEARCH_DELAY_MS);
    return () => clearTimeout(timer);
  }, [term]);

  const shown = term.trim().length < 2 ? [] : results;
  const describedBy = error ? `${id}-erro` : `${id}-dica`;

  return (
    <div className="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-3">
      <span id={`${id}-rotulo`} className="text-sm font-bold text-muted">
        {label}
      </span>
      <div className="flex flex-wrap gap-2" aria-describedby={describedBy}>
        <button
          type="button"
          aria-pressed={value === 0}
          onClick={() => {
            onChange(0);
            setSelected(null);
          }}
          className={`h-11 rounded-full border-2 px-4 text-sm font-extrabold ${value === 0 ? "border-ink bg-lime text-ink" : "border-surface text-muted hover:text-chalk"}`}
        >
          Qualquer liga
        </button>
        {value !== 0 && (
          <span role="status" className="flex h-11 items-center gap-2 rounded-full border-2 border-ink bg-lime pr-4 pl-1 text-sm font-extrabold text-ink">
            {selected ? <LeagueCrest name={selected.name} color={selected.color} size="sm" /> : null}
            <span className="sr-only">Vinculada a </span>
            {selected ? selected.name : `Liga #${value}`}
          </span>
        )}
      </div>

      <label htmlFor={`${id}-busca`} className="sr-only">
        Buscar liga pelo nome
      </label>
      <input
        id={`${id}-busca`}
        type="search"
        value={term}
        onChange={(e) => setTerm(e.target.value)}
        placeholder="Buscar uma liga específica pelo nome"
        autoComplete="off"
        className="h-11 rounded-full border-2 border-ink bg-surface px-4 font-semibold text-chalk outline-none placeholder:text-subtle/70 focus:border-cyan"
      />
      <div aria-live="polite">
        {term.trim().length >= 2 && shown.length === 0 && <p className="text-sm text-subtle">Nenhuma liga com “{term.trim()}”.</p>}
        {shown.length > 0 && (
          <ul className="flex flex-col gap-1.5" aria-label="Ligas encontradas">
            {shown.map((league) => (
              <li key={league.id}>
                <button
                  type="button"
                  onClick={() => {
                    onChange(league.id);
                    setSelected(league);
                    setTerm("");
                  }}
                  className="flex w-full items-center gap-3 rounded-2xl bg-panel p-2 text-left hover:bg-surface"
                >
                  <LeagueCrest name={league.name} color={league.color} size="sm" />
                  <span className="flex min-w-0 flex-col">
                    <span className="truncate font-extrabold">{league.name}</span>
                    <span className="text-xs text-muted">
                      {league.members_count} membros{league.finished ? " · encerrada" : ""}
                    </span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
      {error ? (
        <p id={`${id}-erro`} className="text-sm font-bold text-danger">
          {error}
        </p>
      ) : (
        help && (
          <p id={`${id}-dica`} className="text-sm text-subtle">
            {help}
          </p>
        )
      )}
    </div>
  );
}

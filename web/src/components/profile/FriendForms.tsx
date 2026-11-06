"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";
import { Avatar } from "@/components/profile/Avatar";
import type { Friendship, FriendSearchResult } from "@/lib/types";

const SEARCH_DELAY_MS = 250;

/**
 * Adicionar amigo pelo @: enquanto digita, sugere quem tem o @ começando assim, já com a ação
 * certa para cada um (adicionar, cancelar, aceitar). Enter manda o pedido para o @ exato.
 */
export function AddFriendForm() {
  const router = useRouter();
  const [term, setTerm] = useState("");
  const [results, setResults] = useState<FriendSearchResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const latest = useRef(0);

  const query = term.trim().replace(/^@+/, "");

  useEffect(() => {
    if (query.length < 2) return;
    const id = ++latest.current;
    const timer = setTimeout(async () => {
      setSearching(true);
      try {
        const { data } = await api<{ data: FriendSearchResult[] }>(`/friends/search?q=${encodeURIComponent(query)}`);
        if (id === latest.current) setResults(data);
      } catch {
        if (id === latest.current) setResults([]);
      } finally {
        if (id === latest.current) setSearching(false);
      }
    }, SEARCH_DELAY_MS);
    return () => clearTimeout(timer);
  }, [query]);

  const shown = query.length < 2 ? [] : results;

  function setStatus(handle: string, friendship: Friendship) {
    setResults((list) => list.map((u) => (u.handle === handle ? { ...u, friendship } : u)));
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!query) return;

    setPending(true);
    setMessage(null);
    try {
      const { data } = await api<{ data: { status: Friendship } }>("/friends", { body: { handle: query } });
      setMessage({ ok: true, text: data.status === "FRIENDS" ? "Agora vocês são amigos!" : "Pedido enviado." });
      setStatus(query.toLowerCase(), data.status);
      router.refresh();
    } catch (e) {
      setMessage({ ok: false, text: e instanceof ApiError ? (e.field("handle") ?? e.message) : "Não deu certo." });
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col gap-3">
      <form onSubmit={onSubmit} className="flex flex-col gap-2" noValidate role="search">
        <label htmlFor="add-friend" className="text-sm font-bold text-muted">
          Adicionar amigo pelo @
        </label>
        <div className="flex flex-wrap gap-2">
          <div className="flex h-11 min-w-0 flex-1 basis-56 items-center rounded-full border-2 border-ink bg-surface focus-within:border-cyan">
            <span className="pl-4 font-bold text-subtle">@</span>
            <input
              id="add-friend"
              name="handle"
              value={term}
              onChange={(e) => {
                setTerm(e.target.value);
                setMessage(null);
              }}
              autoComplete="off"
              autoCapitalize="none"
              spellCheck={false}
              placeholder="crava_tudo"
              aria-describedby="add-friend-results"
              className="h-full w-full min-w-0 bg-transparent pr-4 pl-1 font-semibold text-chalk outline-none placeholder:text-subtle/70"
            />
          </div>
          <button type="submit" disabled={pending || !query} className={buttonClass("primary")}>
            {pending ? "Enviando…" : "Adicionar"}
          </button>
        </div>
        {message && (
          <p role="status" className={`text-sm font-bold ${message.ok ? "text-lime" : "text-danger"}`}>
            {message.text}
          </p>
        )}
      </form>

      <div id="add-friend-results" aria-live="polite">
        {query.length >= 2 && !searching && shown.length === 0 && (
          <p className="text-sm text-subtle">Ninguém com @ começando por “{query}”.</p>
        )}
        {shown.length > 0 && (
          <ul className="flex flex-col gap-2" aria-label="Resultados da busca">
            {shown.map((user) => (
              <li key={user.id} className="flex flex-wrap items-center justify-between gap-3">
                <Link href={`/u/${user.handle}`} className="flex min-w-0 items-center gap-3 text-chalk no-underline">
                  <Avatar user={user} size="sm" />
                  <span className="flex min-w-0 flex-col">
                    <span className="truncate font-extrabold">{user.name}</span>
                    <span className="truncate text-sm text-cyan">@{user.handle}</span>
                  </span>
                </Link>
                <ResultAction user={user} onChange={(status) => setStatus(user.handle!, status)} />
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

/** Ação de cada resultado da busca, conforme a relação atual. */
function ResultAction({ user, onChange }: { user: FriendSearchResult; onChange: (status: Friendship) => void }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const handle = user.handle!;

  async function run(action: () => Promise<Friendship>) {
    setPending(true);
    setError(null);
    try {
      onChange(await action());
      router.refresh();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Não deu certo.");
    } finally {
      setPending(false);
    }
  }

  const add = () =>
    run(async () => (await api<{ data: { status: Friendship } }>("/friends", { body: { handle } })).data.status);
  const accept = () =>
    run(async () => {
      await api(`/friends/${handle}/accept`, { method: "POST" });
      return "FRIENDS";
    });
  const cancel = () =>
    run(async () => {
      await api(`/friends/${handle}`, { method: "DELETE" });
      return "NONE";
    });

  let action = null;
  switch (user.friendship) {
    case "NONE":
      action = (
        <button type="button" disabled={pending} onClick={add} className={buttonClass("primary", "md", "px-4")}>
          Adicionar
        </button>
      );
      break;
    case "SENT":
      action = (
        <button type="button" disabled={pending} onClick={cancel} className={buttonClass("ghost", "md", "px-3")}>
          Enviado · cancelar
        </button>
      );
      break;
    case "RECEIVED":
      action = (
        <button type="button" disabled={pending} onClick={accept} className={buttonClass("primary", "md", "px-4")}>
          Aceitar pedido
        </button>
      );
      break;
    case "FRIENDS":
      action = <span className="px-3 text-sm font-extrabold text-lime">Amigos</span>;
      break;
  }

  return (
    <div className="flex flex-col items-end gap-1">
      {action}
      {error && (
        <p role="alert" className="text-xs font-bold text-danger">
          {error}
        </p>
      )}
    </div>
  );
}

/** Aceitar/recusar um pedido recebido, ou cancelar um enviado. */
export function RequestActions({ handle, kind }: { handle: string; kind: "received" | "sent" }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  async function run(path: string, method: string) {
    setPending(true);
    try {
      await api(path, { method });
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  if (kind === "sent") {
    return (
      <button type="button" disabled={pending} onClick={() => run(`/friends/${handle}`, "DELETE")} className={buttonClass("ghost", "md", "px-3")}>
        Cancelar
      </button>
    );
  }

  return (
    <div className="flex gap-2">
      <button type="button" disabled={pending} onClick={() => run(`/friends/${handle}/accept`, "POST")} className={buttonClass("primary", "md", "px-4")}>
        Aceitar
      </button>
      <button type="button" disabled={pending} onClick={() => run(`/friends/${handle}`, "DELETE")} className={buttonClass("ghost", "md", "px-3")}>
        Recusar
      </button>
    </div>
  );
}

/** Desfazer amizade direto da lista de amigos (só no próprio perfil). */
export function RemoveFriendButton({ handle, name }: { handle: string; name: string }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  async function remove() {
    if (!confirm(`Desfazer a amizade com ${name}?`)) return;
    setPending(true);
    try {
      await api(`/friends/${handle}`, { method: "DELETE" });
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <button
      type="button"
      onClick={remove}
      disabled={pending}
      aria-label={`Remover ${name} dos amigos`}
      className={buttonClass("ghost", "md", "px-3 text-sm")}
    >
      Remover
    </button>
  );
}

"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { api, ApiError, type FieldErrors } from "@/lib/api";
import { competitionLabel, crestColors } from "@/lib/leagues";
import type { Competition, League, LeagueMember, LeaguePrivacy, LeagueRole, PublicLeague } from "@/lib/types";
import { buttonClass } from "@/components/ui/button";
import { Field, FormAlert } from "@/components/ui/Field";
import { LeagueCrest } from "./LeagueCrest";

const SEARCH_DELAY_MS = 250;

const errorText = (e: unknown, field?: string) =>
  e instanceof ApiError ? ((field && e.field(field)) ?? e.field("league") ?? e.field("code") ?? e.message) : "Não deu certo.";

/** Entrar numa liga com o código de 6 caracteres. */
export function JoinByCodeForm() {
  const router = useRouter();
  const [code, setCode] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setError(null);
    try {
      const { data } = await api<{ data: { slug: string } }>("/leagues/join", { body: { code } });
      router.push(`/ligas/${data.slug}`);
    } catch (e) {
      setError(errorText(e, "code"));
      setPending(false);
    }
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-2">
      <label htmlFor="league-code" className="text-sm font-bold text-muted">
        Recebeu um código?
      </label>
      <div className="flex flex-wrap gap-2">
        <input
          id="league-code"
          name="code"
          value={code}
          onChange={(e) => {
            setCode(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 6));
            setError(null);
          }}
          autoComplete="off"
          autoCapitalize="characters"
          spellCheck={false}
          placeholder="K7Q2XP"
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? "league-code-erro" : undefined}
          className="h-11 w-36 rounded-full border-2 border-ink bg-surface px-4 text-center font-score text-xl tracking-[0.2em] text-chalk outline-none placeholder:text-subtle/50 focus:border-cyan"
        />
        <button type="submit" disabled={pending || code.length !== 6} className={buttonClass("primary")}>
          {pending ? "Entrando…" : "Entrar"}
        </button>
      </div>
      {error && (
        <p id="league-code-erro" role="alert" className="text-sm font-bold text-danger">
          {error}
        </p>
      )}
    </form>
  );
}

/** Busca das ligas públicas pelo nome; sem termo, mostra as mais cheias. */
export function PublicLeagueSearch() {
  const [term, setTerm] = useState("");
  const [results, setResults] = useState<PublicLeague[] | null>(null);
  const latest = useRef(0);

  useEffect(() => {
    const id = ++latest.current;
    const timer = setTimeout(async () => {
      try {
        const { data } = await api<{ data: PublicLeague[] }>(`/leagues/search?q=${encodeURIComponent(term.trim())}`);
        if (id === latest.current) setResults(data);
      } catch {
        if (id === latest.current) setResults([]);
      }
    }, term ? SEARCH_DELAY_MS : 0);
    return () => clearTimeout(timer);
  }, [term]);

  return (
    <div className="flex flex-col gap-3">
      <div role="search" className="flex flex-col gap-2">
        <label htmlFor="league-search" className="text-sm font-bold text-muted">
          Buscar pelo nome
        </label>
        <input
          id="league-search"
          type="search"
          value={term}
          onChange={(e) => setTerm(e.target.value)}
          placeholder="Liga do bairro"
          className="h-11 rounded-full border-2 border-ink bg-surface px-4 font-semibold text-chalk outline-none placeholder:text-subtle/70 focus:border-cyan"
        />
      </div>
      <div aria-live="polite">
        {results === null ? (
          <p className="text-sm text-subtle">Carregando…</p>
        ) : results.length === 0 ? (
          <p className="text-sm text-subtle">{term.trim() ? `Nenhuma liga pública com “${term.trim()}”.` : "Ainda não há ligas públicas."}</p>
        ) : (
          <ul className="flex flex-col gap-2" aria-label="Ligas públicas">
            {results.map((league) => (
              <PublicLeagueRow key={league.slug} league={league} />
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

function PublicLeagueRow({ league }: { league: PublicLeague }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const full = league.members_count >= league.max_members;

  async function join() {
    setPending(true);
    setError(null);
    try {
      await api(`/leagues/${league.slug}/join`, { method: "POST" });
      router.push(`/ligas/${league.slug}`);
    } catch (e) {
      setError(errorText(e));
      setPending(false);
    }
  }

  return (
    <li className="flex flex-wrap items-center gap-3 rounded-2xl bg-panel p-3">
      <Link href={`/ligas/${league.slug}`} className="flex min-w-0 flex-1 basis-48 items-center gap-3 text-chalk no-underline">
        <LeagueCrest name={league.name} color={league.color} size="sm" />
        <span className="flex min-w-0 flex-col">
          <span className="truncate font-extrabold">{league.name}</span>
          <span className="truncate text-sm text-muted">
            {league.members_count} de {league.max_members} · {league.competitions.map((c) => competitionLabel[c]).join(" + ")}
          </span>
        </span>
      </Link>
      <div className="flex flex-col items-end gap-1">
        {league.is_member ? (
          <span className="px-3 text-sm font-extrabold text-lime">Você está nela</span>
        ) : full ? (
          <span className="px-3 text-sm font-bold text-subtle">Cheia</span>
        ) : (
          <button type="button" onClick={join} disabled={pending} className={buttonClass("primary", "md", "px-4")}>
            {pending ? "Entrando…" : "Entrar"}
          </button>
        )}
        {error && (
          <p role="alert" className="text-xs font-bold text-danger">
            {error}
          </p>
        )}
      </div>
    </li>
  );
}

type LeagueFormValues = Pick<League, "name" | "color" | "description" | "privacy" | "competitions">;

/** Criar ou editar liga, com o escudo atualizando ao lado. */
export function LeagueForm({ league }: { league?: LeagueFormValues & { slug: string } }) {
  const router = useRouter();
  const editing = Boolean(league);
  const [name, setName] = useState(league?.name ?? "");
  const [description, setDescription] = useState(league?.description ?? "");
  const [color, setColor] = useState(league?.color ?? crestColors[2]);
  const [privacy, setPrivacy] = useState<LeaguePrivacy>(league?.privacy ?? "PRIVATE");
  const [competitions, setCompetitions] = useState<Competition[]>(league?.competitions ?? ["FANTASY", "SHOTCALL"]);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  function toggle(c: Competition) {
    setCompetitions((list) => (list.includes(c) ? list.filter((x) => x !== c) : [...list, c]));
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    setMessage(null);
    const body = { name, description, color, privacy, competitions };
    try {
      const { data } = await api<{ data: League }>(editing ? `/leagues/${league!.slug}` : "/leagues", {
        method: editing ? "PATCH" : "POST",
        body,
      });
      router.push(`/ligas/${data.slug}`);
      router.refresh();
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage(e.field("league") ?? (Object.keys(e.errors).length ? null : e.message));
      } else {
        setMessage("Não deu certo.");
      }
      setPending(false);
    }
  }

  const fieldError = (name: string) => errors[name]?.[0] ?? Object.entries(errors).find(([k]) => k.startsWith(`${name}.`))?.[1][0];

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-8 lg:grid-cols-[1fr_300px]">
      <div className="flex flex-col gap-6">
        {message && <FormAlert>{message}</FormAlert>}
        <Field
          label="Nome da liga"
          name="name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          maxLength={40}
          required
          placeholder="Liga do Rachão"
          error={fieldError("name")}
        />

        <div className="flex flex-col gap-1.5">
          <label htmlFor="f-description" className="text-sm font-bold text-muted">
            Descrição <span className="font-normal text-subtle">(opcional)</span>
          </label>
          <textarea
            id="f-description"
            name="description"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            maxLength={200}
            rows={3}
            placeholder="Quem perder paga o churrasco."
            aria-describedby="f-description-dica"
            className="rounded-xl border-2 border-ink bg-surface px-4 py-3 font-semibold text-chalk outline-none placeholder:text-subtle/70 focus:border-cyan"
          />
          <p id="f-description-dica" className="text-sm text-subtle">
            {fieldError("description") ?? `${description.length} de 200`}
          </p>
        </div>

        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1.5 text-sm font-bold text-muted">Cor do escudo</legend>
          <div className="flex flex-wrap gap-2">
            {crestColors.map((c) => (
              <label key={c} className="cursor-pointer">
                <input type="radio" name="color" value={c} checked={color === c} onChange={() => setColor(c)} className="peer sr-only" />
                <span
                  className="block size-11 rounded-full border-[3px] border-ink outline-offset-2 peer-checked:outline-3 peer-checked:outline-chalk peer-focus-visible:outline-3 peer-focus-visible:outline-cyan"
                  style={{ background: c }}
                />
                <span className="sr-only">{c}</span>
              </label>
            ))}
          </div>
        </fieldset>

        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1.5 text-sm font-bold text-muted">Quem pode entrar</legend>
          <div className="grid gap-2 sm:grid-cols-2">
            {(
              [
                ["PRIVATE", "Privada", "Só com o código ou o link de convite."],
                ["PUBLIC", "Pública", "Aparece na busca; qualquer um entra."],
              ] as const
            ).map(([value, label, hint]) => (
              <label
                key={value}
                className={`flex cursor-pointer flex-col gap-0.5 rounded-2xl border-2 p-4 has-focus-visible:border-cyan ${
                  privacy === value ? "border-lime bg-panel" : "border-surface"
                }`}
              >
                <input type="radio" name="privacy" value={value} checked={privacy === value} onChange={() => setPrivacy(value)} className="sr-only" />
                <span className="font-extrabold">{label}</span>
                <span className="text-sm text-muted">{hint}</span>
              </label>
            ))}
          </div>
        </fieldset>

        <fieldset className="flex flex-col gap-2" aria-describedby={fieldError("competitions") ? "f-competitions-erro" : undefined}>
          <legend className="mb-1.5 text-sm font-bold text-muted">O que a liga disputa</legend>
          <div className="grid gap-2 sm:grid-cols-2">
            {(
              [
                ["FANTASY", "Ranking do time e do patrimônio."],
                ["SHOTCALL", "Ranking dos pontos de palpites."],
              ] as const
            ).map(([value, hint]) => (
              <label
                key={value}
                className={`flex cursor-pointer flex-col gap-0.5 rounded-2xl border-2 p-4 has-focus-visible:border-cyan ${
                  competitions.includes(value) ? "border-lime bg-panel" : "border-surface"
                }`}
              >
                <input type="checkbox" checked={competitions.includes(value)} onChange={() => toggle(value)} className="sr-only" />
                <span className="font-extrabold">{competitionLabel[value]}</span>
                <span className="text-sm text-muted">{hint}</span>
              </label>
            ))}
          </div>
          {fieldError("competitions") && (
            <p id="f-competitions-erro" className="text-sm font-bold text-danger">
              {fieldError("competitions")}
            </p>
          )}
        </fieldset>

        <div className="flex flex-wrap gap-3">
          <button type="submit" disabled={pending} className={buttonClass("primary", "lg")}>
            {pending ? "Salvando…" : editing ? "Salvar" : "Criar liga"}
          </button>
          <Link href={editing ? `/ligas/${league!.slug}` : "/ligas"} className={buttonClass("ghost", "lg")}>
            Cancelar
          </Link>
        </div>
      </div>

      <aside aria-label="Prévia" className="flex flex-col items-center gap-3 self-start rounded-3xl border-[3px] border-ink bg-panel p-6 shadow-hard">
        <LeagueCrest name={name || "Sua liga"} color={color} size="lg" />
        <p className="text-center font-display text-lg break-words">{name || "SUA LIGA"}</p>
        <p className="text-sm text-muted">
          {privacy === "PRIVATE" ? "Privada" : "Pública"} · até 20 membros
        </p>
      </aside>
    </form>
  );
}

/** Código e link de convite, com copiar e (para dono e admins) gerar código novo. */
export function InvitePanel({ slug, code: initial, canRegenerate }: { slug: string; code: string; canRegenerate: boolean }) {
  const [code, setCode] = useState(initial);
  const [copied, setCopied] = useState<"code" | "link" | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function copy(kind: "code" | "link") {
    const text = kind === "code" ? code : `${window.location.origin}/ligas/convite/${code}`;
    try {
      await navigator.clipboard.writeText(text);
      setCopied(kind);
      setTimeout(() => setCopied(null), 2000);
    } catch {
      setError("Não deu para copiar. Selecione e copie à mão.");
    }
  }

  async function regenerate() {
    if (!confirm("Gerar um código novo? O atual e o link antigo param de funcionar.")) return;
    setPending(true);
    setError(null);
    try {
      const { data } = await api<{ data: { invite_code: string } }>(`/leagues/${slug}/invite-code`, { method: "POST" });
      setCode(data.invite_code);
    } catch (e) {
      setError(errorText(e));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-3">
        <span className="rounded-xl border-2 border-ink bg-ink px-4 py-2 font-score text-3xl tracking-[0.25em] text-lime select-all">{code}</span>
        <button type="button" onClick={() => copy("code")} className={buttonClass("outline", "md", "px-4")}>
          {copied === "code" ? "Copiado!" : "Copiar código"}
        </button>
        <button type="button" onClick={() => copy("link")} className={buttonClass("primary", "md", "px-4")}>
          {copied === "link" ? "Link copiado!" : "Copiar link"}
        </button>
      </div>
      <p className="sr-only" role="status">
        {copied ? "Copiado para a área de transferência." : ""}
      </p>
      {canRegenerate && (
        <button type="button" onClick={regenerate} disabled={pending} className={buttonClass("ghost", "md", "self-start px-0 text-sm")}>
          {pending ? "Gerando…" : "Gerar código novo"}
        </button>
      )}
      {error && (
        <p role="alert" className="text-sm font-bold text-danger">
          {error}
        </p>
      )}
    </div>
  );
}

/** Botão de uma ação da liga (entrar, sair, excluir), com confirmação e erro embaixo. */
export function LeagueActionButton({
  slug,
  action,
  label,
  confirmText,
  variant = "primary",
}: {
  slug: string;
  action: "join" | "leave" | "delete";
  label: string;
  confirmText?: string;
  variant?: "primary" | "outline" | "ghost";
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function run() {
    if (confirmText && !confirm(confirmText)) return;
    setPending(true);
    setError(null);
    try {
      if (action === "delete") {
        await api(`/leagues/${slug}`, { method: "DELETE" });
      } else {
        await api(`/leagues/${slug}/${action}`, { method: "POST" });
      }
      if (action === "join") {
        router.refresh();
        setPending(false);
      } else {
        router.push("/ligas");
        router.refresh();
      }
    } catch (e) {
      setError(errorText(e));
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col gap-1">
      <button
        type="button"
        onClick={run}
        disabled={pending}
        className={buttonClass(variant, "md", action === "delete" ? "text-danger hover:text-danger" : "")}
      >
        {pending ? "Aguarde…" : label}
      </button>
      {error && (
        <p role="alert" className="text-sm font-bold text-danger">
          {error}
        </p>
      )}
    </div>
  );
}

/** Trocar o papel de um membro (só o dono) e remover (dono e admins). */
export function MemberControls({
  slug,
  member,
  canChangeRole,
  canRemove,
}: {
  slug: string;
  member: LeagueMember;
  canChangeRole: boolean;
  canRemove: boolean;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const handle = member.handle!;

  async function run(call: () => Promise<unknown>) {
    setPending(true);
    setError(null);
    try {
      await call();
      router.refresh();
    } catch (e) {
      setError(errorText(e, "role"));
    } finally {
      setPending(false);
    }
  }

  function changeRole(role: LeagueRole) {
    if (role === "OWNER" && !confirm(`Passar a liga para ${member.name}? Você vira admin.`)) return;
    run(() => api(`/leagues/${slug}/members/${handle}`, { method: "PATCH", body: { role } }));
  }

  function remove() {
    if (!confirm(`Tirar ${member.name} da liga?`)) return;
    run(() => api(`/leagues/${slug}/members/${handle}`, { method: "DELETE" }));
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <div className="flex items-center gap-2">
        {canChangeRole && (
          <select
            aria-label={`Papel de ${member.name}`}
            value={member.league_role}
            disabled={pending}
            onChange={(e) => changeRole(e.target.value as LeagueRole)}
            className="h-10 rounded-full border-2 border-surface bg-panel px-3 text-sm font-bold text-chalk focus:border-cyan"
          >
            <option value="MEMBER">Membro</option>
            <option value="ADMIN">Admin</option>
            <option value="OWNER">Passar a liga</option>
          </select>
        )}
        {canRemove && (
          <button type="button" onClick={remove} disabled={pending} aria-label={`Tirar ${member.name} da liga`} className={buttonClass("ghost", "md", "px-3 text-sm")}>
            Remover
          </button>
        )}
      </div>
      {error && (
        <p role="alert" className="text-xs font-bold text-danger">
          {error}
        </p>
      )}
    </div>
  );
}

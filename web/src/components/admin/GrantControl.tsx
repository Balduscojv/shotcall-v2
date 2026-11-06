"use client";

import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";

/**
 * Liberar ou remover um prêmio de um usuário. A API valida tudo (só admin, prêmio
 * existente, sem repetir); a mensagem dela aparece embaixo.
 */
export function GrantControl({
  handle,
  cosmeticKey,
  cosmeticName,
  owned,
}: {
  handle: string;
  cosmeticKey: string;
  cosmeticName: string;
  owned: boolean;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const base = `/admin/users/${encodeURIComponent(handle)}/cosmetics`;

  async function run(action: () => Promise<unknown>) {
    setPending(true);
    setError(null);
    try {
      await action();
      router.refresh();
    } catch (e) {
      setError(e instanceof ApiError ? (e.field("key") ?? e.field("reason") ?? e.message) : "Não deu certo.");
    } finally {
      setPending(false);
    }
  }

  function grant(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const reason = String(new FormData(event.currentTarget).get("reason") ?? "").trim();
    run(() => api(base, { body: { key: cosmeticKey, reason: reason || null } }));
  }

  function revoke() {
    if (!window.confirm(`Remover ${cosmeticName} de @${handle}? Se estiver em uso, sai do perfil.`)) return;
    run(() => api(`${base}/${encodeURIComponent(cosmeticKey)}`, { method: "DELETE" }));
  }

  const reasonId = `reason-${cosmeticKey}`;

  return (
    <div className="flex flex-col gap-2">
      {owned ? (
        <button type="button" onClick={revoke} disabled={pending} className={buttonClass("outline", "md", "w-full")}>
          {pending ? "Removendo…" : "Remover"}
        </button>
      ) : (
        <form onSubmit={grant} className="flex flex-col gap-2" noValidate>
          <label htmlFor={reasonId} className="sr-only">
            Motivo para liberar {cosmeticName}
          </label>
          <input
            id={reasonId}
            name="reason"
            maxLength={120}
            autoComplete="off"
            placeholder="Motivo (opcional)"
            className="h-10 rounded-full border-2 border-ink bg-surface px-4 text-sm font-semibold text-chalk outline-none placeholder:text-subtle/70 focus:border-cyan"
          />
          <button type="submit" disabled={pending} className={buttonClass("primary", "md", "w-full")}>
            {pending ? "Liberando…" : "Liberar"}
          </button>
        </form>
      )}
      {error && (
        <p role="alert" className="text-sm font-bold text-danger">
          {error}
        </p>
      )}
    </div>
  );
}

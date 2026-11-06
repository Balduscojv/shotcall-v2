"use client";

import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";

/** Adicionar amigo pelo @. */
export function AddFriendForm() {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const handle = String(new FormData(form).get("handle") ?? "").trim();
    if (!handle) return;

    setPending(true);
    setMessage(null);
    try {
      const { data } = await api<{ data: { status: string } }>("/friends", { body: { handle } });
      setMessage({ ok: true, text: data.status === "FRIENDS" ? "Agora vocês são amigos!" : "Pedido enviado." });
      form.reset();
      router.refresh();
    } catch (e) {
      setMessage({ ok: false, text: e instanceof ApiError ? (e.field("handle") ?? e.message) : "Não deu certo." });
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-2" noValidate>
      <label htmlFor="add-friend" className="text-sm font-bold text-muted">
        Adicionar amigo pelo @
      </label>
      <div className="flex flex-wrap gap-2">
        <div className="flex h-11 min-w-0 flex-1 basis-56 items-center rounded-full border-2 border-ink bg-surface focus-within:border-cyan">
          <span className="pl-4 font-bold text-subtle">@</span>
          <input
            id="add-friend"
            name="handle"
            autoComplete="off"
            autoCapitalize="none"
            spellCheck={false}
            placeholder="crava_tudo"
            className="h-full w-full min-w-0 bg-transparent pr-4 pl-1 font-semibold text-chalk outline-none placeholder:text-subtle/70"
          />
        </div>
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Enviando…" : "Adicionar"}
        </button>
      </div>
      {message && (
        <p role="status" className={`text-sm font-bold ${message.ok ? "text-lime" : "text-danger"}`}>
          {message.text}
        </p>
      )}
    </form>
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

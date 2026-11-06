"use client";

import { useRouter } from "next/navigation";
import { useRef, useState, type ChangeEvent } from "react";
import { api, ApiError } from "@/lib/api";

const MAX_BYTES = 8 * 1024 * 1024;
const TYPES = ["image/jpeg", "image/png", "image/webp"];

type Props = {
  kind: "avatar" | "banner";
  hasImage: boolean;
  /** "chip": botão escuro sobre o banner; "plain": botão de contorno na linha de ações. */
  look?: "chip" | "plain";
};

/** Enviar ou remover a foto de perfil ou o banner. Recarrega o perfil ao terminar. */
export function ImageControl({ kind, hasImage, look = "plain" }: Props) {
  const router = useRouter();
  const input = useRef<HTMLInputElement>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const label = kind === "avatar" ? "foto" : "banner";

  async function run(action: () => Promise<unknown>) {
    setPending(true);
    setError(null);
    try {
      await action();
      router.refresh();
    } catch (e) {
      setError(e instanceof ApiError ? (e.field("image") ?? e.message) : "Não deu certo. Tente de novo.");
    } finally {
      setPending(false);
    }
  }

  function onPick(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    if (!TYPES.includes(file.type)) return setError("Use uma imagem JPG, PNG ou WebP.");
    if (file.size > MAX_BYTES) return setError("A imagem pode ter no máximo 8 MB.");

    const body = new FormData();
    body.append("image", file);
    run(() => api(`/me/${kind}`, { body }));
  }

  const button =
    look === "chip"
      ? "inline-flex h-10 items-center gap-2 rounded-full border-2 border-ink bg-ink/80 px-4 text-sm font-extrabold text-chalk backdrop-blur hover:bg-ink"
      : "inline-flex h-11 items-center gap-2 rounded-full border-2 border-chalk px-4 text-sm font-extrabold text-chalk hover:bg-chalk hover:text-ink";

  return (
    <div className="flex flex-col items-end gap-1.5">
      <div className="flex flex-wrap items-center gap-2">
        <input ref={input} type="file" accept={TYPES.join(",")} className="sr-only" tabIndex={-1} onChange={onPick} />
        <button type="button" className={button} disabled={pending} onClick={() => input.current?.click()}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden="true">
            <path d="M4 8h3l2-3h6l2 3h3v11H4z" strokeLinejoin="round" />
            <circle cx="12" cy="13" r="3.5" />
          </svg>
          {pending ? "Enviando…" : `Trocar ${label}`}
        </button>
        {hasImage && (
          <button
            type="button"
            className={look === "chip" ? button : "h-11 px-2 text-sm font-bold text-muted underline-offset-4 hover:text-chalk hover:underline"}
            disabled={pending}
            onClick={() => run(() => api(`/me/${kind}`, { method: "DELETE" }))}
          >
            Remover
          </button>
        )}
      </div>
      {error && (
        <p role="alert" className="rounded-lg border-2 border-ink bg-pink px-3 py-1.5 text-sm font-bold text-ink">
          {error}
        </p>
      )}
    </div>
  );
}

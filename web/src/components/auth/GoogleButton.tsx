"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { FormAlert } from "@/components/ui/Field";
import { safeReturnPath } from "./returnPath";

/**
 * "Entrar com Google". Só aparece se o backend tiver o Google configurado. É um link comum (não
 * fetch): o navegador vai ao Google e volta pelo /api/auth/google/callback, já logado.
 */
export function GoogleButton({ label = "Entrar com Google" }: { label?: string }) {
  const [href, setHref] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    api<{ data: { google: boolean } }>("/auth/providers")
      .then(({ data }) => {
        if (!data.google) return;
        setHref(`/api/auth/google/redirect?${new URLSearchParams({ voltar: safeReturnPath() })}`);
        setFailed(new URLSearchParams(window.location.search).get("erro") === "google");
      })
      .catch(() => {});
  }, []);

  if (!href) return null;

  return (
    <div className="flex flex-col gap-4">
      {failed && <FormAlert>Não deu para entrar com o Google. Tente de novo.</FormAlert>}
      <a
        href={href}
        className="inline-flex h-14 w-full items-center justify-center gap-3 rounded-full border-2 border-ink bg-chalk text-base font-extrabold text-ink no-underline shadow-hard-sm transition-transform hover:-translate-y-0.5"
      >
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 48 48">
          <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z" />
          <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" />
          <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z" />
          <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z" />
        </svg>
        {label}
      </a>
      <p className="flex items-center gap-3 text-xs font-bold text-subtle" aria-hidden="true">
        <span className="h-px flex-1 bg-surface" />
        ou com e-mail
        <span className="h-px flex-1 bg-surface" />
      </p>
    </div>
  );
}

"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api, ApiError } from "@/lib/api";

/** Chave liga/desliga de um prêmio ou missão, direto da lista. */
export function ActiveToggle({
  active,
  path,
  method,
  label,
}: {
  active: boolean;
  /** Rota da API que recebe { active }. */
  path: string;
  method: "POST" | "PATCH";
  /** Nome do item, para o leitor de tela. */
  label: string;
}) {
  const router = useRouter();
  const [on, setOn] = useState(active);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function toggle() {
    const next = !on;
    setPending(true);
    setError(null);
    setOn(next);
    try {
      await api(path, { method, body: { active: next } });
      router.refresh();
    } catch (e) {
      setOn(!next);
      setError(e instanceof ApiError ? e.message : "Não deu certo.");
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col items-start gap-1">
      <button
        type="button"
        role="switch"
        aria-checked={on}
        aria-label={`${label}: ${on ? "ativo" : "desativado"}`}
        onClick={toggle}
        disabled={pending}
        className="inline-flex items-center gap-2 text-sm font-extrabold disabled:opacity-60"
      >
        <span
          aria-hidden="true"
          className={`relative inline-flex h-6 w-11 items-center rounded-full border-2 border-ink transition-colors ${on ? "bg-lime" : "bg-surface"}`}
        >
          <span className={`absolute size-4 rounded-full border-2 border-ink bg-chalk transition-transform ${on ? "translate-x-5" : "translate-x-0.5"}`} />
        </span>
        <span className={on ? "text-lime" : "text-subtle"}>{on ? "Ativo" : "Desativado"}</span>
      </button>
      {error && (
        <p role="alert" className="text-xs font-bold text-danger">
          {error}
        </p>
      )}
    </div>
  );
}

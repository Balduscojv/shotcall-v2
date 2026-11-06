"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";

/** "Entrar na liga" da página do link de convite. */
export function JoinInviteButton({ code }: { code: string }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function join() {
    setPending(true);
    setError(null);
    try {
      const { data } = await api<{ data: { slug: string } }>("/leagues/join", { body: { code } });
      router.push(`/ligas/${data.slug}`);
    } catch (e) {
      setError(e instanceof ApiError ? (e.field("code") ?? e.field("league") ?? e.message) : "Não deu certo.");
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col items-center gap-2">
      <button type="button" onClick={join} disabled={pending} className={buttonClass("hype", "lg")}>
        {pending ? "Entrando…" : "Entrar na liga"}
      </button>
      {error && (
        <p role="alert" className="text-sm font-bold text-danger">
          {error}
        </p>
      )}
    </div>
  );
}

"use client";

import Link from "next/link";
import { useEffect, useSyncExternalStore } from "react";
import { Ball } from "@/components/ui/Logo";
import { buttonClass } from "@/components/ui/button";

const subscribe = (cb: () => void) => {
  window.addEventListener("online", cb);
  window.addEventListener("offline", cb);
  return () => {
    window.removeEventListener("online", cb);
    window.removeEventListener("offline", cb);
  };
};

/** Tela de erro das áreas do app: sem conexão ou falha no servidor, com "tentar de novo". */
export function ErrorScreen({ error, retry }: { error: unknown; retry: () => void }) {
  const online = useSyncExternalStore(subscribe, () => navigator.onLine, () => true);

  useEffect(() => {
    console.error(error);
  }, [error]);

  // Voltou a conexão: tenta sozinho.
  useEffect(() => {
    if (online) return;
    const again = () => retry();
    window.addEventListener("online", again, { once: true });
    return () => window.removeEventListener("online", again);
  }, [online, retry]);

  const digest = error instanceof Error && "digest" in error ? String((error as Error & { digest?: string }).digest ?? "") : "";

  return (
    <div role="alert" className="mx-auto flex max-w-xl flex-col items-start gap-5 px-4 py-16">
      <Ball size={56} stroke={4} />
      <h1 className="font-display text-[clamp(32px,6vw,56px)] leading-none">
        {online ? "BOLA NO ARO" : "SEM SINAL"}
        <span className="text-orange">.</span>
      </h1>
      <p className="text-lg text-muted">
        {online
          ? "Algo deu errado do nosso lado ao carregar esta tela. Tente de novo em instantes."
          : "Você está sem internet. Assim que a conexão voltar, a página tenta de novo sozinha."}
      </p>
      <div className="flex flex-wrap items-center gap-3">
        <button type="button" onClick={() => retry()} className={buttonClass("primary")}>
          Tentar de novo
        </button>
        <Link href="/perfil" className={buttonClass("ghost")}>
          Ir para o meu perfil
        </Link>
      </div>
      {digest && <p className="text-xs text-subtle">Código do erro: {digest}</p>}
    </div>
  );
}

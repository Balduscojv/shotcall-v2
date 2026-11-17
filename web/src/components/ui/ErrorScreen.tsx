"use client";

import Link from "next/link";
import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { Ball } from "@/components/ui/Logo";
import { HoopLoader } from "@/components/ui/HoopLoader";
import { buttonClass } from "@/components/ui/button";

// Quanto tempo a tela "acordando" espera a API antes de admitir o erro.
const WAKE_WAIT_MS = 5 * 60_000;
const WAKE_POLL_MS = 5_000;

type ApiState = "checking" | "waking" | "up" | "gave-up";

async function apiIsUp(): Promise<boolean> {
  try {
    const res = await fetch("/acordar", { cache: "no-store", signal: AbortSignal.timeout(15_000) });
    return res.ok;
  } catch {
    return false;
  }
}

/**
 * O erro pode ser só a API dormindo (Render Free). Pergunta ao Next se ela está de pé; se não,
 * fica conferindo até ela responder e então tenta de novo sozinha.
 */
function useApiWake(enabled: boolean, retry: () => void): ApiState {
  const [state, setState] = useState<ApiState>("checking");
  const retryRef = useRef(retry);
  useEffect(() => {
    retryRef.current = retry;
  }, [retry]);

  useEffect(() => {
    if (!enabled) return;
    let cancelled = false;
    const deadline = Date.now() + WAKE_WAIT_MS;

    (async () => {
      if (await apiIsUp()) {
        if (!cancelled) setState("up");
        return;
      }
      if (cancelled) return;
      setState("waking");
      while (!cancelled && Date.now() < deadline) {
        await new Promise((resolve) => setTimeout(resolve, WAKE_POLL_MS));
        if (cancelled) return;
        if (await apiIsUp()) {
          if (!cancelled) retryRef.current();
          return;
        }
      }
      if (!cancelled) setState("gave-up");
    })();

    return () => {
      cancelled = true;
    };
  }, [enabled]);

  return state;
}

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

  const apiState = useApiWake(online, retry);

  const digest = error instanceof Error && "digest" in error ? String((error as Error & { digest?: string }).digest ?? "") : "";

  if (online && apiState === "checking") return <HoopLoader />;

  if (online && apiState === "waking") {
    return (
      <div role="status" className="mx-auto flex max-w-xl flex-col items-center gap-2 px-4 py-10 text-center">
        <HoopLoader label="ACORDANDO O SERVIDOR" />
        <p className="text-lg text-muted">
          O servidor estava descansando e leva até 3 minutos para voltar. Assim que ele responder, a página abre sozinha.
        </p>
      </div>
    );
  }

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

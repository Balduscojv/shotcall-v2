"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

/**
 * Recarrega os dados da página de tempos em tempos enquanto tem jogo ao vivo. Não faz nada
 * com a aba escondida, e volta a atualizar assim que ela aparece de novo.
 */
export function AutoRefresh({ enabled, seconds = 15 }: { enabled: boolean; seconds?: number }) {
  const router = useRouter();

  useEffect(() => {
    if (!enabled) return;

    const tick = () => {
      if (document.visibilityState === "visible") router.refresh();
    };
    const id = window.setInterval(tick, seconds * 1000);
    document.addEventListener("visibilitychange", tick);

    return () => {
      window.clearInterval(id);
      document.removeEventListener("visibilitychange", tick);
    };
  }, [enabled, seconds, router]);

  return null;
}

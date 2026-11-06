"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { NOTIFICATIONS_CHANGED } from "@/lib/notifications";

const POLL_MS = 60_000;

/** Quantas não lidas; null se não deu para saber (sem conexão, sessão expirada). */
async function unreadCount(): Promise<number | null> {
  try {
    const { data } = await api<{ data: { count: number } }>("/notifications/unread-count");
    return data.count;
  } catch {
    return null;
  }
}

/**
 * Sininho do cabeçalho com a contagem de não lidas. O layout não re-renderiza ao navegar,
 * então a contagem vem do navegador: ao abrir, a cada troca de página, ao voltar para a aba,
 * a cada minuto e quando a lista marca como lidas.
 */
export function NotificationBell() {
  const pathname = usePathname();
  const [count, setCount] = useState(0);

  useEffect(() => {
    let alive = true;
    const refresh = () =>
      unreadCount().then((n) => {
        if (alive && n !== null) setCount(n);
      });

    void refresh();
    const timer = setInterval(() => document.visibilityState === "visible" && void refresh(), POLL_MS);
    const onVisible = () => document.visibilityState === "visible" && void refresh();
    const onChanged = () => void refresh();
    document.addEventListener("visibilitychange", onVisible);
    window.addEventListener(NOTIFICATIONS_CHANGED, onChanged);
    return () => {
      alive = false;
      clearInterval(timer);
      document.removeEventListener("visibilitychange", onVisible);
      window.removeEventListener(NOTIFICATIONS_CHANGED, onChanged);
    };
  }, [pathname]);

  return <BellLink count={count} active={pathname === "/notificacoes"} />;
}

/** Sininho sem contagem: fallback do <Suspense> enquanto a URL e a contagem não chegam. */
export function NotificationBellStatic() {
  return <BellLink count={0} active={false} />;
}

function BellLink({ count, active }: { count: number; active: boolean }) {
  const label = count === 0 ? "Notificações" : `Notificações: ${count} não ${count === 1 ? "lida" : "lidas"}`;

  return (
    <Link
      href="/notificacoes"
      aria-label={label}
      title={label}
      aria-current={active ? "page" : undefined}
      className={`relative inline-flex size-11 items-center justify-center rounded-full no-underline hover:text-lime ${active ? "text-lime" : "text-chalk"}`}
    >
      <svg viewBox="0 0 24 24" className="size-6" fill="none" stroke="currentColor" strokeWidth={2.2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M6 8a6 6 0 1 1 12 0c0 7 3 8.5 3 8.5H3S6 15 6 8" />
        <path d="M10.3 20a1.94 1.94 0 0 0 3.4 0" />
      </svg>
      {count > 0 && (
        <span
          aria-hidden="true"
          className="absolute top-0.5 right-0 inline-flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-ink bg-pink px-1 text-xs font-extrabold text-ink"
        >
          {count > 9 ? "9+" : count}
        </span>
      )}
    </Link>
  );
}

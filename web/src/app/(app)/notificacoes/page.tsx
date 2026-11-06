import Link from "next/link";
import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { timeAgo } from "@/lib/notifications";
import type { AppNotification } from "@/lib/types";
import { Avatar } from "@/components/profile/Avatar";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";
import { MarkAllRead } from "@/components/notifications/MarkAllRead";

export const metadata = { title: "Notificações" };

export default function NotificationsPage() {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <h1 className="font-display text-3xl">NOTIFICAÇÕES</h1>
      <Suspense fallback={<TabSkeleton />}>
        <NotificationList />
      </Suspense>
    </div>
  );
}

async function NotificationList() {
  const { data, meta } = await serverApi<{ data: AppNotification[]; meta: { unread: number } }>("/notifications");

  if (data.length === 0) {
    return (
      <EmptyState title="NADA POR AQUI AINDA">
        Pedidos de amizade, prêmios liberados e a apuração das rodadas aparecem aqui.
      </EmptyState>
    );
  }

  return (
    <>
      <MarkAllRead enabled={meta.unread > 0} />
      <ul className="flex max-w-3xl flex-col gap-2">
        {data.map((n) => (
          <li key={n.id}>
            <NotificationRow notification={n} />
          </li>
        ))}
      </ul>
    </>
  );
}

function NotificationRow({ notification: n }: { notification: AppNotification }) {
  const unread = n.read_at === null;
  const content = (
    <>
      {n.actor ? <Avatar user={n.actor} size="sm" /> : <KindIcon kind={n.kind} />}
      <span className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="flex flex-wrap items-baseline justify-between gap-x-3">
          <span className="font-extrabold">{n.title}</span>
          <time dateTime={n.created_at} className="text-xs font-bold text-subtle">
            {timeAgo(n.created_at)}
          </time>
        </span>
        <span className="text-sm text-muted">{n.body}</span>
      </span>
      {unread && <span className="mt-2 size-2.5 shrink-0 rounded-full bg-pink" aria-label="Nova" />}
    </>
  );

  const box = `flex items-start gap-3 rounded-2xl border-[3px] p-3 text-chalk no-underline ${
    unread ? "border-ink bg-panel shadow-hard-sm" : "border-transparent bg-panel/50"
  }`;

  return n.url ? (
    <Link href={n.url} className={`${box} transition-transform hover:-translate-y-0.5`}>
      {content}
    </Link>
  ) : (
    <div className={box}>{content}</div>
  );
}

const kindStyle: Record<string, string> = {
  award: "bg-yellow",
  round_settled: "bg-lime",
};

/** Ícone quando a notificação não tem uma pessoa por trás (prêmio, rodada). */
function KindIcon({ kind }: { kind: string }) {
  return (
    <span
      aria-hidden="true"
      className={`inline-flex size-11 shrink-0 items-center justify-center rounded-full border-[3px] border-ink text-ink ${kindStyle[kind] ?? "bg-cyan"}`}
    >
      {kind === "award" ? (
        <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth={2.4} strokeLinecap="round" strokeLinejoin="round">
          <path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4ZM7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4" />
        </svg>
      ) : (
        <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth={2.4} strokeLinecap="round">
          <circle cx="12" cy="12" r="9" />
          <path d="M3 12h18M12 3v18M5.6 5.6c3.5 3.5 3.5 9.3 0 12.8M18.4 5.6c-3.5 3.5-3.5 9.3 0 12.8" />
        </svg>
      )}
    </span>
  );
}

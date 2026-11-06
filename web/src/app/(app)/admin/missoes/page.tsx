import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import { formatDay } from "@/lib/labels";
import type { Mission } from "@/lib/types";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";
import { TitleBadge } from "@/components/cosmetics/CosmeticPreview";
import { ActiveToggle } from "@/components/admin/ActiveToggle";
import { buttonClass } from "@/components/ui/button";

export const metadata = { title: "Moderação de missões" };

export default function AdminMissionsPage({ searchParams }: PageProps<"/admin/missoes">) {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="font-display text-3xl">MISSÕES</h1>
          <p className="text-muted">Cada missão junta uma condição, os números dela e os prêmios que libera.</p>
        </div>
        <Link href="/admin/missoes/nova" className={buttonClass("primary")}>
          Nova missão
        </Link>
      </header>
      <Suspense>
        <GrantedNotice searchParams={searchParams} />
      </Suspense>
      <Suspense fallback={<TabSkeleton />}>
        <Missions />
      </Suspense>
    </div>
  );
}

/** Missão salva que já premiou quem cumpria (ex.: contador de títulos). */
async function GrantedNotice({ searchParams }: { searchParams: PageProps<"/admin/missoes">["searchParams"] }) {
  const granted = Number((await searchParams).liberados ?? 0);
  if (!(granted > 0)) return null;

  return (
    <p role="status" className="rounded-2xl border-2 border-lime bg-panel p-4 font-bold">
      Missão salva. {granted === 1 ? "1 prêmio liberado" : `${granted} prêmios liberados`} agora para quem já cumpria a condição.
    </p>
  );
}

async function Missions() {
  const me = await requireUser();
  if (me.role !== "admin") notFound();

  const { data: missions } = await serverApi<{ data: Mission[] }>("/admin/missions");

  if (missions.length === 0) {
    return <EmptyState title="NENHUMA MISSÃO">Crie a primeira: escolha a condição e os prêmios que ela libera.</EmptyState>;
  }

  return (
    <ul className="grid gap-4 lg:grid-cols-2">
      {missions.map((m) => (
        <li key={m.id} className={`flex flex-col gap-3 rounded-2xl border-[3px] border-ink bg-panel p-5 ${m.active ? "" : "opacity-70"}`}>
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div className="flex min-w-0 flex-col">
              <span className="text-xl leading-snug font-extrabold">{m.name}</span>
              <span className="text-sm text-cyan">{m.condition_label}</span>
            </div>
            {m.secret && <span className="rounded-md border-2 border-ink bg-surface px-2 py-0.5 font-display text-[11px]">SECRETA</span>}
          </div>

          <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            {Object.entries(m.params_display).map(([label, value]) => (
              <div key={label} className="contents">
                <dt className="text-subtle">{label}</dt>
                <dd className="font-extrabold">{value}</dd>
              </div>
            ))}
            {m.secret && m.hint && (
              <>
                <dt className="text-subtle">Dica</dt>
                <dd className="font-marker">“{m.hint}”</dd>
              </>
            )}
            {(m.starts_at || m.ends_at) && (
              <>
                <dt className="text-subtle">Período</dt>
                <dd>
                  {m.starts_at ? formatDay(m.starts_at) : "já"} → {m.ends_at ? formatDay(m.ends_at) : "sem fim"}
                </dd>
              </>
            )}
          </dl>

          <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm text-subtle">Libera:</span>
            {m.rewards.length === 0 && <span className="text-sm font-bold text-danger">nenhum prêmio</span>}
            {m.rewards.map((r) =>
              r.slot === "TITLE" ? (
                <TitleBadge key={r.key} title={r} className={r.active ? "" : "opacity-50"} />
              ) : (
                <span key={r.key} className={`rounded-md border-2 border-surface px-2 py-0.5 text-xs font-extrabold ${r.active ? "" : "opacity-50"}`}>
                  {r.name}
                </span>
              ),
            )}
          </div>

          <div className="mt-auto flex items-center justify-between gap-2 pt-1">
            <ActiveToggle active={m.active} path={`/admin/missions/${m.id}/active`} method="PATCH" label={m.name} />
            <Link href={`/admin/missoes/${m.id}`} className={buttonClass("outline", "md", "h-10 px-4 text-sm")}>
              Editar
            </Link>
          </div>
        </li>
      ))}
    </ul>
  );
}

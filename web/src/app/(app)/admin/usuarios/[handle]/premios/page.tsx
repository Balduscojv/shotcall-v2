import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import { formatDay, slotLabel, slots, sourceLabel, triggerLabel } from "@/lib/labels";
import type { Cosmetic, User } from "@/lib/types";
import { Avatar } from "@/components/profile/Avatar";
import { TabSkeleton } from "@/components/profile/TabStates";
import { CosmeticCard } from "@/components/cosmetics/CosmeticCard";
import { TitleBadge } from "@/components/cosmetics/CosmeticPreview";
import { GrantControl } from "@/components/admin/GrantControl";

export const metadata = { title: "Prêmios do usuário" };

export default function AdminUserAwardsPage({ params }: PageProps<"/admin/usuarios/[handle]/premios">) {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <Link href="/admin/usuarios" className="self-start text-sm font-bold text-muted no-underline hover:text-chalk">
        ← Moderação de usuários
      </Link>
      <Suspense fallback={<TabSkeleton />}>
        <UserAwards params={params} />
      </Suspense>
    </div>
  );
}

async function UserAwards({ params }: Pick<PageProps<"/admin/usuarios/[handle]/premios">, "params">) {
  const me = await requireUser();
  // Quem não é admin nem descobre que a página existe. A API também barra (403).
  if (me.role !== "admin") notFound();

  const { handle } = await params;
  const { data: catalog, user } = await serverApi<{ data: Cosmetic[]; user: User }>(
    `/admin/users/${encodeURIComponent(handle)}/cosmetics`,
  );
  const ownedCount = catalog.filter((c) => c.owned).length;

  return (
    <div className="flex flex-col gap-8">
      <header className="flex flex-wrap items-center gap-4">
        <Avatar user={user} size="md" />
        <div className="flex min-w-0 flex-col gap-1">
          <h1 className="font-display text-2xl leading-tight sm:text-3xl">PRÊMIOS DE {user.name.toUpperCase()}</h1>
          <p className="flex flex-wrap items-center gap-2 text-muted">
            <Link href={`/u/${user.handle}`} className="font-extrabold text-cyan no-underline hover:underline">
              @{user.handle}
            </Link>
            {user.equipped?.title && <TitleBadge title={user.equipped.title} />}
            <span>
              · {ownedCount} de {catalog.length} liberados
            </span>
          </p>
        </div>
      </header>

      {slots.map((slot) => {
        const items = catalog.filter((c) => c.slot === slot);
        if (items.length === 0) return null;

        return (
          <section key={slot} aria-labelledby={`h-${slot}`} className="flex flex-col gap-3">
            <h2 id={`h-${slot}`} className="font-display text-lg">{slotLabel[slot].toUpperCase()}</h2>
            <ul className="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
              {items.map((item) => (
                <CosmeticCard
                  key={item.key}
                  cosmetic={item}
                  initials={user.name.slice(0, 2).toUpperCase()}
                  note={
                    <>
                      <span className="block">
                        {sourceLabel[item.source ?? ""] ?? item.source} · Gatilho: {triggerLabel(item.trigger)}
                      </span>
                      {item.owned && item.unlocked_at && (
                        <span className="block text-lime">
                          Liberado: {item.reason} · {formatDay(item.unlocked_at)}
                        </span>
                      )}
                    </>
                  }
                >
                  <GrantControl handle={user.handle!} cosmeticKey={item.key} cosmeticName={item.name} owned={!!item.owned} />
                </CosmeticCard>
              ))}
            </ul>
          </section>
        );
      })}
    </div>
  );
}

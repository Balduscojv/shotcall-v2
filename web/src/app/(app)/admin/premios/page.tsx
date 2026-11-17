import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import { rarityLabel, rarityStyle, slotLabel, slots, sourceLabel } from "@/lib/labels";
import type { AdminCosmetic } from "@/lib/types";
import { TabSkeleton } from "@/components/profile/TabStates";
import { CosmeticPreview } from "@/components/cosmetics/CosmeticPreview";
import { ActiveToggle } from "@/components/admin/ActiveToggle";
import { buttonClass } from "@/components/ui/button";

export const metadata = { title: "Moderação de prêmios" };

export default function AdminAwardsPage() {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="font-display text-3xl">PRÊMIOS</h1>
          <p className="text-muted">Crie banners, molduras e títulos, e ative ou desative. Desativado não sai mais para ninguém; quem tem continua usando.</p>
        </div>
        <Link href="/admin/premios/novo" className={buttonClass("primary")}>
          Novo prêmio
        </Link>
      </header>
      <Suspense fallback={<TabSkeleton />}>
        <Catalog />
      </Suspense>
    </div>
  );
}

async function Catalog() {
  const me = await requireUser();
  if (me.role !== "admin") notFound();

  const { data: catalog } = await serverApi<{ data: AdminCosmetic[] }>("/admin/cosmetics");

  return (
    <div className="flex flex-col gap-8">
      {slots.map((slot) => {
        const items = catalog.filter((c) => c.slot === slot);
        if (items.length === 0) return null;

        return (
          <section key={slot} aria-labelledby={`h-${slot}`} className="flex flex-col gap-3">
            <h2 id={`h-${slot}`} className="font-display text-lg">
              {slotLabel[slot].toUpperCase()} <span className="text-subtle">· {items.length}</span>
            </h2>
            <ul className="grid grid-cols-[repeat(auto-fill,minmax(min(100%,170px),1fr))] gap-4">
              {items.map((item) => (
                <li
                  key={item.key}
                  className={`flex flex-col gap-3 rounded-2xl border-[3px] border-ink bg-panel p-4 ${item.active ? "" : "opacity-70"}`}
                >
                  <CosmeticPreview cosmetic={item} />
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className={`rounded-md border-2 border-ink px-2 py-0.5 font-display text-[11px] ${rarityStyle[item.rarity]}`}>
                      {rarityLabel[item.rarity].toUpperCase()}
                    </span>
                    {item.secret && <span className="text-xs font-extrabold text-subtle">MISSÃO SECRETA</span>}
                  </div>
                  <div className="flex flex-col gap-0.5">
                    <span className="text-lg leading-snug font-extrabold">{item.name}</span>
                    <span className="text-sm text-muted">
                      {sourceLabel[item.source ?? ""] ?? item.source} · {item.owners_count} {item.owners_count === 1 ? "jogador tem" : "jogadores têm"}
                    </span>
                    {item.missions.length > 0 && (
                      <span className="text-sm text-subtle">
                        Recompensa de: {item.missions.map((m) => m.name).join(", ")}
                      </span>
                    )}
                  </div>
                  <div className="mt-auto flex flex-wrap items-center justify-between gap-2 pt-1">
                    <ActiveToggle active={item.active} path={`/admin/cosmetics/${encodeURIComponent(item.key)}`} method="POST" label={item.name} />
                    <Link href={`/admin/premios/${encodeURIComponent(item.key)}`} className={buttonClass("outline", "md", "h-10 px-4 text-sm")}>
                      Editar
                    </Link>
                  </div>
                </li>
              ))}
            </ul>
          </section>
        );
      })}
    </div>
  );
}

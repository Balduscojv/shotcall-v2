import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import { slotHint, slotLabel, slots } from "@/lib/labels";
import { getProfile } from "@/lib/profile";
import type { Cosmetic } from "@/lib/types";
import { CosmeticCard } from "@/components/cosmetics/CosmeticCard";
import { EquipButton } from "@/components/cosmetics/EquipButton";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Prêmios" };

// Aba "Prêmios". No próprio perfil é a coleção, com o botão de usar cada prêmio (um por tipo).
// Admin tem acesso a todos, então vê o catálogo inteiro.
export default function AwardsTab({ params }: PageProps<"/u/[handle]/premios">) {
  return (
    <Suspense fallback={<TabSkeleton />}>
      <Awards params={params} />
    </Suspense>
  );
}

const initialsOf = (name: string) =>
  name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");

async function Awards({ params }: Pick<PageProps<"/u/[handle]/premios">, "params">) {
  const { handle } = await params;
  const profile = await getProfile(handle);
  const own = profile.friendship === "SELF";

  if (!own) {
    const awards = await serverApi<{ data: Cosmetic[]; meta: { collection_total: number } }>(
      `/users/${encodeURIComponent(handle)}/awards`,
    );
    return (
      <section aria-labelledby="h-premios" className="flex flex-col gap-4">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h2 id="h-premios" className="font-display text-2xl">PRÊMIOS</h2>
          <span className="font-bold text-muted">
            {awards.data.length} de {awards.meta.collection_total} da coleção
          </span>
        </div>
        {awards.data.length === 0 ? (
          <EmptyState title="NENHUM PRÊMIO AINDA">Os prêmios conquistados aparecem aqui.</EmptyState>
        ) : (
          <ul className="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
            {awards.data.map((award) => (
              <CosmeticCard key={award.key} cosmetic={award} initials={initialsOf(profile.name)} />
            ))}
          </ul>
        )}
      </section>
    );
  }

  const [me, { data: collection }] = await Promise.all([requireUser(), serverApi<{ data: Cosmetic[] }>("/me/cosmetics")]);
  const isAdmin = me.role === "admin";
  const initials = initialsOf(profile.name);

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-col gap-1">
        <h2 className="font-display text-2xl">MEUS PRÊMIOS</h2>
        <p className="text-muted">
          {isAdmin
            ? "Como admin, você pode usar qualquer prêmio do catálogo."
            : "Escolha um de cada tipo para usar no seu perfil. Novos prêmios chegam jogando."}
        </p>
      </div>

      {slots.map((slot) => {
        const items = collection.filter((c) => c.slot === slot);
        const inUse = items.find((c) => c.equipped);

        return (
          <section key={slot} aria-labelledby={`h-${slot}`} className="flex flex-col gap-3">
            <div className="flex flex-wrap items-end justify-between gap-2">
              <div className="flex flex-col">
                <h3 id={`h-${slot}`} className="font-display text-lg">{slotLabel[slot].toUpperCase()}</h3>
                <span className="text-sm text-subtle">{slotHint[slot]}</span>
              </div>
              {inUse && <EquipButton slot={slot} cosmeticKey={null} label={`Não usar ${slotLabel[slot].toLowerCase()}`} />}
            </div>
            {items.length === 0 ? (
              <EmptyState title="NADA AQUI AINDA">Continue jogando para liberar prêmios deste tipo.</EmptyState>
            ) : (
              <ul className="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
                {items.map((item) => (
                  <CosmeticCard
                    key={item.key}
                    cosmetic={item}
                    initials={initials}
                    note={isAdmin && !item.owned ? "Acesso de admin" : undefined}
                  >
                    {!item.equipped && <EquipButton slot={slot} cosmeticKey={item.key} label="Usar" />}
                  </CosmeticCard>
                ))}
              </ul>
            )}
          </section>
        );
      })}
    </div>
  );
}

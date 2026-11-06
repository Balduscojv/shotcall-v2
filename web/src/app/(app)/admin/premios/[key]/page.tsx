import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import type { AdminCosmetic } from "@/lib/types";
import { TabSkeleton } from "@/components/profile/TabStates";
import { CosmeticForm } from "@/components/admin/CosmeticForm";

export const metadata = { title: "Editar prêmio" };

export default function EditAwardPage({ params }: PageProps<"/admin/premios/[key]">) {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <Link href="/admin/premios" className="self-start text-sm font-bold text-muted no-underline hover:text-chalk">
        ← Prêmios
      </Link>
      <Suspense fallback={<TabSkeleton />}>
        <EditAward params={params} />
      </Suspense>
    </div>
  );
}

async function EditAward({ params }: Pick<PageProps<"/admin/premios/[key]">, "params">) {
  const me = await requireUser();
  if (me.role !== "admin") notFound();

  const { key } = await params;
  const { data: catalog } = await serverApi<{ data: AdminCosmetic[] }>("/admin/cosmetics");
  const cosmetic = catalog.find((c) => c.key === decodeURIComponent(key));
  if (!cosmetic) notFound();

  return (
    <>
      <h1 className="font-display text-3xl">EDITAR {cosmetic.name.toUpperCase()}</h1>
      <CosmeticForm cosmetic={cosmetic} />
    </>
  );
}

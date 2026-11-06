import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import type { AdminCosmetic, Mission, MissionCondition } from "@/lib/types";
import { TabSkeleton } from "@/components/profile/TabStates";
import { MissionForm } from "@/components/admin/MissionForm";

export const metadata = { title: "Editar missão" };

export default function EditMissionPage({ params }: PageProps<"/admin/missoes/[id]">) {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <Link href="/admin/missoes" className="self-start text-sm font-bold text-muted no-underline hover:text-chalk">
        ← Missões
      </Link>
      <Suspense fallback={<TabSkeleton />}>
        <EditMission params={params} />
      </Suspense>
    </div>
  );
}

async function EditMission({ params }: Pick<PageProps<"/admin/missoes/[id]">, "params">) {
  const me = await requireUser();
  if (me.role !== "admin") notFound();

  const { id } = await params;
  if (!/^\d+$/.test(id)) notFound();

  const [{ data: mission }, { data: conditions }, { data: cosmetics }] = await Promise.all([
    serverApi<{ data: Mission }>(`/admin/missions/${id}`),
    serverApi<{ data: MissionCondition[] }>("/admin/missions/conditions"),
    serverApi<{ data: AdminCosmetic[] }>("/admin/cosmetics"),
  ]);

  return (
    <>
      <h1 className="font-display text-3xl">EDITAR {mission.name.toUpperCase()}</h1>
      <MissionForm mission={mission} conditions={conditions} cosmetics={cosmetics} />
    </>
  );
}

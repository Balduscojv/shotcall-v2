import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import type { AdminCosmetic, MissionCondition } from "@/lib/types";
import { TabSkeleton } from "@/components/profile/TabStates";
import { MissionForm } from "@/components/admin/MissionForm";

export const metadata = { title: "Nova missão" };

export default function NewMissionPage() {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <Link href="/admin/missoes" className="self-start text-sm font-bold text-muted no-underline hover:text-chalk">
        ← Missões
      </Link>
      <h1 className="font-display text-3xl">NOVA MISSÃO</h1>
      <Suspense fallback={<TabSkeleton />}>
        <NewMission />
      </Suspense>
    </div>
  );
}

async function NewMission() {
  const me = await requireUser();
  if (me.role !== "admin") notFound();

  const [{ data: conditions }, { data: cosmetics }] = await Promise.all([
    serverApi<{ data: MissionCondition[] }>("/admin/missions/conditions"),
    serverApi<{ data: AdminCosmetic[] }>("/admin/cosmetics"),
  ]);

  return <MissionForm conditions={conditions} cosmetics={cosmetics} />;
}

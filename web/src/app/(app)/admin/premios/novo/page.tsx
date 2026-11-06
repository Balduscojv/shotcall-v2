import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { TabSkeleton } from "@/components/profile/TabStates";
import { CosmeticForm } from "@/components/admin/CosmeticForm";

export const metadata = { title: "Novo prêmio" };

export default function NewAwardPage() {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <Link href="/admin/premios" className="self-start text-sm font-bold text-muted no-underline hover:text-chalk">
        ← Prêmios
      </Link>
      <h1 className="font-display text-3xl">NOVO PRÊMIO</h1>
      <Suspense fallback={<TabSkeleton />}>
        <AdminOnly />
      </Suspense>
    </div>
  );
}

async function AdminOnly() {
  const me = await requireUser();
  if (me.role !== "admin") notFound();

  return <CosmeticForm />;
}

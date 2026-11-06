import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import type { League } from "@/lib/types";
import { LeagueActionButton, LeagueForm } from "@/components/leagues/LeagueForms";
import { TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Editar liga" };

// Só o dono edita e exclui (LeaguePolicy). Os outros caem no "não encontrado".
export default function EditLeaguePage({ params }: PageProps<"/ligas/[slug]/editar">) {
  return (
    <div className="px-4 pt-6 sm:px-0">
      <Suspense fallback={<TabSkeleton />}>
        <EditLeague params={params} />
      </Suspense>
    </div>
  );
}

async function EditLeague({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const { data: league } = await serverApi<{ data: League }>(`/leagues/${encodeURIComponent(slug)}`);
  if (!league.can.update) notFound();

  return (
    <div className="flex flex-col gap-10">
      <div className="flex flex-col gap-6">
        <div className="flex flex-col gap-1">
          <Link href={`/ligas/${league.slug}`} className="font-bold">
            ← {league.name}
          </Link>
          <h1 className="font-display text-3xl">EDITAR LIGA</h1>
        </div>
        <LeagueForm league={league} />
      </div>

      <section aria-labelledby="h-excluir" className="flex flex-col items-start gap-3 rounded-3xl border-2 border-dashed border-danger/60 p-5">
        <h2 id="h-excluir" className="font-display text-xl">EXCLUIR LIGA</h2>
        <p className="max-w-xl text-muted">
          Some para todos os {league.members_count} membros, com o ranking e o histórico. Não dá para desfazer. Se só quer sair,
          passe a liga para outro membro na lista de membros.
        </p>
        <LeagueActionButton
          slug={league.slug}
          action="delete"
          label="Excluir liga"
          variant="outline"
          confirmText={`Excluir a ${league.name} para todos os membros? Não dá para desfazer.`}
        />
      </section>
    </div>
  );
}

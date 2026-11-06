import Link from "next/link";
import { LeagueForm } from "@/components/leagues/LeagueForms";

export const metadata = { title: "Criar liga" };

export default function NewLeaguePage() {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <div className="flex flex-col gap-1">
        <Link href="/ligas" className="font-bold">
          ← Ligas
        </Link>
        <h1 className="font-display text-3xl">CRIAR LIGA</h1>
        <p className="text-muted">Até 20 membros. Quem entrar depois do começo de uma rodada pontua a partir da seguinte.</p>
      </div>
      <LeagueForm />
    </div>
  );
}

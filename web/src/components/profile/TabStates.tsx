import type { ReactNode } from "react";
import { HoopLoader } from "@/components/ui/HoopLoader";

/** Aba sem conteúdo ainda: explica o que vai aparecer e, se fizer sentido, o que fazer. */
export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="flex flex-col items-start gap-2 rounded-3xl border-2 border-dashed border-surface p-8">
      <p className="font-display text-xl">{title}</p>
      {children && <div className="max-w-xl text-muted">{children}</div>}
    </div>
  );
}

/** Carregando: a bola girando no aro (HoopLoader). */
export function TabSkeleton() {
  return <HoopLoader />;
}

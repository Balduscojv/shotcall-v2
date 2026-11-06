import type { ReactNode } from "react";

/** Aba sem conteúdo ainda: explica o que vai aparecer e, se fizer sentido, o que fazer. */
export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="flex flex-col items-start gap-2 rounded-3xl border-2 border-dashed border-surface p-8">
      <p className="font-display text-xl">{title}</p>
      {children && <div className="max-w-xl text-muted">{children}</div>}
    </div>
  );
}

export function TabSkeleton() {
  return (
    <div className="flex flex-col gap-3" aria-hidden="true">
      {[0, 1, 2].map((i) => (
        <div key={i} className="h-20 animate-pulse rounded-2xl bg-panel" />
      ))}
    </div>
  );
}

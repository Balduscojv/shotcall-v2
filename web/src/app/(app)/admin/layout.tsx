import { Suspense } from "react";
import { AdminTabs } from "@/components/admin/AdminTabs";

/**
 * Área de moderação: abas no topo. Cada página confere se quem vê é admin (e a API também).
 * As abas leem a URL para marcar a ativa; o Suspense deixa o resto da página pré-renderizar.
 */
export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <Suspense fallback={<AdminTabs pathname={null} />}>
        <AdminTabs />
      </Suspense>
      {children}
    </>
  );
}

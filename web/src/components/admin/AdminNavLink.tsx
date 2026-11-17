import Link from "next/link";
import { requireUser } from "@/lib/auth";

/** Link da moderação no cabeçalho: só aparece para admin. Usar dentro de <Suspense>. */
export async function AdminNavLink() {
  const me = await requireUser();
  if (me.role !== "admin") return null;

  return (
    <Link href="/admin/usuarios" className="inline-flex h-11 items-center px-2 font-bold whitespace-nowrap text-chalk no-underline hover:text-lime sm:px-3">
      <span className="sm:hidden">Admin</span>
      <span className="hidden sm:inline">Moderação</span>
    </Link>
  );
}

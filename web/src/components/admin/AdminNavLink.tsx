import Link from "next/link";
import { requireUser } from "@/lib/auth";

/** Link da moderação no cabeçalho: só aparece para admin. Usar dentro de <Suspense>. */
export async function AdminNavLink() {
  const me = await requireUser();
  if (me.role !== "admin") return null;

  return (
    <Link href="/admin/usuarios" className="inline-flex h-11 items-center px-3 font-bold text-chalk no-underline hover:text-lime">
      Moderação
    </Link>
  );
}

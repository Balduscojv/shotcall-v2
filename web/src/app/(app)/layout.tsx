import Link from "next/link";
import { Suspense } from "react";
import { AdminNavLink } from "@/components/admin/AdminNavLink";
import { Logo } from "@/components/ui/Logo";
import { LogoutButton } from "@/components/auth/LogoutButton";

const navLink = "inline-flex h-11 items-center px-2 font-bold whitespace-nowrap text-chalk no-underline hover:text-lime sm:px-3";

/** Moldura da área logada. Não lê a sessão, então entra no shell estático e abre na hora. */
export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-dvh flex-col">
      <header className="fence border-b-[3px] border-ink">
        <div className="mx-auto flex max-w-[1240px] items-center justify-between gap-2 px-4 py-3 sm:gap-4 sm:px-6">
          <Logo href="/perfil" />
          <nav aria-label="Principal" className="flex items-center gap-0.5 sm:gap-3">
            <Suspense fallback={null}>
              <AdminNavLink />
            </Suspense>
            <Link href="/jogos" className={navLink}>
              Jogos
            </Link>
            <Link href="/perfil" className={navLink}>
              <span className="sm:hidden">Perfil</span>
              <span className="hidden sm:inline">Meu perfil</span>
            </Link>
            <LogoutButton />
          </nav>
        </div>
      </header>
      <main className="mx-auto w-full max-w-[1240px] flex-1 pb-16 sm:px-6 sm:pt-6">{children}</main>
    </div>
  );
}

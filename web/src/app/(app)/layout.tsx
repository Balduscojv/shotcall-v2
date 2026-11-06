import Link from "next/link";
import { Suspense } from "react";
import { AdminNavLink } from "@/components/admin/AdminNavLink";
import { Logo } from "@/components/ui/Logo";
import { LogoutButton } from "@/components/auth/LogoutButton";
import { NotificationBell, NotificationBellStatic } from "@/components/notifications/NotificationBell";
import { ProfileNavLink, ProfileNavLinkStatic } from "@/components/profile/ProfileNavLink";

const navLink = "inline-flex h-11 items-center px-2 font-bold whitespace-nowrap text-chalk no-underline hover:text-lime sm:px-3";

/** Moldura da área logada. Não lê a sessão, então entra no shell estático e abre na hora. */
export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-dvh flex-col">
      <header className="fence border-b-[3px] border-ink">
        {/* No celular: logo e Sair na primeira linha, o menu na segunda. No computador, tudo numa linha. */}
        <div className="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 sm:px-6">
          <Logo href="/perfil" />
          <div className="flex items-center gap-1 sm:order-last">
            <Suspense fallback={<NotificationBellStatic />}>
              <NotificationBell />
            </Suspense>
            <LogoutButton />
          </div>
          <nav aria-label="Principal" className="-mx-2 flex w-full items-center justify-between gap-0.5 sm:mx-0 sm:ml-auto sm:w-auto sm:justify-end sm:gap-3">
            <Suspense fallback={null}>
              <AdminNavLink />
            </Suspense>
            <Link href="/jogos" className={navLink}>
              Calendário
            </Link>
            <Link href="/time" className={navLink}>
              Escale seu time
            </Link>
            <Suspense fallback={<ProfileNavLinkStatic />}>
              <ProfileNavLink />
            </Suspense>
          </nav>
        </div>
      </header>
      <main className="mx-auto w-full max-w-[1240px] flex-1 pb-16 sm:px-6 sm:pt-6">{children}</main>
    </div>
  );
}

import Link from "next/link";
import { Suspense } from "react";
import { AdminNavLink } from "@/components/admin/AdminNavLink";
import { Logo } from "@/components/ui/Logo";
import { SiteFooter } from "@/components/ui/SiteFooter";
import { LogoutButton } from "@/components/auth/LogoutButton";
import { VerifyEmailBanner } from "@/components/auth/VerifyEmailBanner";
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
          <nav
            aria-label="Principal"
            className="-mx-2 flex w-[calc(100%+1rem)] items-center justify-between gap-0.5 overflow-x-auto sm:mx-0 sm:ml-auto sm:w-auto sm:justify-end sm:gap-3 sm:overflow-visible"
          >
            <Suspense fallback={null}>
              <AdminNavLink />
            </Suspense>
            {/* No celular, rótulos curtos para o menu caber numa linha sem empurrar a página. */}
            <Link href="/jogos" className={navLink}>
              <span className="sm:hidden">Jogos</span>
              <span className="hidden sm:inline">Calendário</span>
            </Link>
            <Link href="/time" className={navLink}>
              <span className="sm:hidden">Time</span>
              <span className="hidden sm:inline">Escale seu time</span>
            </Link>
            <Link href="/ligas" className={navLink}>
              Ligas
            </Link>
            <Suspense fallback={<ProfileNavLinkStatic />}>
              <ProfileNavLink />
            </Suspense>
          </nav>
        </div>
      </header>
      <Suspense fallback={null}>
        <VerifyEmailBanner />
      </Suspense>
      <main className="mx-auto w-full max-w-[1240px] flex-1 pb-16 sm:px-6 sm:pt-6">{children}</main>
      <SiteFooter />
    </div>
  );
}

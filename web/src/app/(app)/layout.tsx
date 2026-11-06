import Link from "next/link";
import { Logo } from "@/components/ui/Logo";
import { LogoutButton } from "@/components/auth/LogoutButton";

/** Moldura da área logada. Não lê a sessão, então entra no shell estático e abre na hora. */
export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-dvh flex-col">
      <header className="fence border-b-[3px] border-ink">
        <div className="mx-auto flex max-w-[1240px] items-center justify-between gap-4 px-4 py-3 sm:px-6">
          <Logo href="/perfil" />
          <nav aria-label="Principal" className="flex items-center gap-1 sm:gap-3">
            <Link href="/perfil" className="inline-flex h-11 items-center px-3 font-bold text-chalk no-underline hover:text-lime">
              Meu perfil
            </Link>
            <LogoutButton />
          </nav>
        </div>
      </header>
      <main className="mx-auto w-full max-w-[1240px] flex-1 pb-16 sm:px-6 sm:pt-6">{children}</main>
    </div>
  );
}

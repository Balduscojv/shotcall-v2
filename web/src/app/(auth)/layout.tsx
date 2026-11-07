import { Ball, Logo } from "@/components/ui/Logo";
import { SiteFooter } from "@/components/ui/SiteFooter";

/** Moldura das telas de entrar e criar conta: vitrine do jogo à esquerda, formulário à direita. */
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-dvh flex-col">
      <header className="fence border-b-[3px] border-ink">
        <div className="mx-auto flex max-w-[1240px] items-center px-4 py-4 sm:px-6">
          <Logo />
        </div>
      </header>

      <main className="mx-auto grid w-full max-w-[1240px] flex-1 items-center gap-12 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_minmax(0,480px)] lg:py-16">
        <aside className="relative hidden flex-col gap-6 lg:flex" aria-hidden="true">
          <div className="absolute top-0 right-[12%] animate-bounce-ball">
            <Ball size={64} stroke={4} />
          </div>
          <span className="inline-flex -rotate-2 items-center gap-2 self-start rounded-lg border-2 border-ink bg-pink px-3 py-1.5 font-display text-[13px] text-ink shadow-hard-sm">
            <span className="size-2 animate-pulse-dot rounded-full bg-ink" />
            TEMPORADA 2026-27 ROLANDO
          </span>
          <p className="font-display text-[clamp(44px,5.5vw,76px)] leading-[0.95]">
            CRAVA O<br />
            <span className="text-orange">PALPITE.</span>
          </p>
          <ul className="flex flex-col gap-3 text-lg font-semibold text-muted">
            <li className="flex items-center gap-3">
              <span className="size-3 rounded-full border-2 border-ink bg-orange" />
              Shot calls por jogo, semana e temporada
            </li>
            <li className="flex items-center gap-3">
              <span className="size-3 rounded-full border-2 border-ink bg-purple" />
              Fantasy com F$ 100 e Cestinha em dobro
            </li>
            <li className="flex items-center gap-3">
              <span className="size-3 rounded-full border-2 border-ink bg-cyan" />
              Ligas com até 20 amigos
            </li>
          </ul>
          <p className="origin-left -rotate-1 font-marker text-xl text-lime">
            Sem dinheiro em jogo. Só moral e cosmético raro.
          </p>
        </aside>

        <section className="w-full rounded-[26px] border-[3px] border-ink bg-panel p-6 shadow-hard-lg sm:p-8">
          {children}
        </section>
      </main>
      <SiteFooter />
    </div>
  );
}

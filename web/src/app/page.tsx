import Link from "next/link";
import { Ball, Logo } from "@/components/ui/Logo";
import { buttonClass } from "@/components/ui/button";

const marquee = ["SWISH!", "CLUTCH", "CESTINHA 2X", "SEMANA PERFEITA", "MVP DO JOGO", "LIGA DA RUA"];

const modes = [
  {
    title: "SHOT CALLS",
    color: "bg-orange",
    tilt: "-rotate-1",
    text: "Vencedor, margem e MVP de cada jogo, a cartela da semana e os palpites da temporada. Acertou 70% ou mais, desbloqueia cosmético.",
    icon: (
      <>
        <circle cx="12" cy="12" r="9" />
        <circle cx="12" cy="12" r="4.5" />
        <circle cx="12" cy="12" r="1.4" fill="currentColor" />
      </>
    ),
  },
  {
    title: "FANTASY",
    color: "bg-purple",
    tilt: "rotate-1",
    text: "F$ 100 para montar cinco titulares. Escolha seu Cestinha, que pontua em dobro, e veja o mercado valorizar quem joga bem.",
    icon: <path d="M8 3h8l1 3 4 2-2 4-2-1v10H7V11l-2 1-2-4 4-2z" strokeLinejoin="round" />,
  },
  {
    title: "LIGAS",
    color: "bg-cyan",
    tilt: "-rotate-[0.5deg]",
    text: "Crie a liga da sua quadra com até 20 amigos. Ranking por rodada, mês e temporada, com troféu para o campeão.",
    icon: (
      <path
        d="M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3M12 14v4M8 21h8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    ),
  },
];

export default function LandingPage() {
  return (
    <div className="overflow-hidden">
      <header className="fence border-b-[3px] border-ink">
        <div className="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
          <Logo />
          <div className="flex gap-2.5">
            <Link href="/entrar" className={buttonClass("outline")}>
              Entrar
            </Link>
            <Link href="/criar-conta" className={buttonClass("primary")}>
              Criar conta grátis
            </Link>
          </div>
        </div>
      </header>

      <main>
        <section className="mx-auto flex max-w-[1240px] flex-wrap items-center gap-12 px-4 pt-16 pb-14 sm:px-6 lg:pt-20">
          <div className="flex min-w-0 flex-[1_1_520px] flex-col gap-6">
            <span className="inline-flex -rotate-2 items-center gap-2 self-start rounded-lg border-2 border-ink bg-pink px-3 py-1.5 font-display text-[13px] text-ink shadow-hard-sm">
              <span className="size-2 animate-pulse-dot rounded-full bg-ink" aria-hidden="true" />
              TEMPORADA 2026-27 ROLANDO
            </span>
            <h1 className="font-display text-[clamp(44px,7vw,92px)] leading-[0.95]">
              CRAVA O<br />
              <span className="text-orange">PALPITE.</span>
            </h1>
            <p className="max-w-[540px] text-xl leading-relaxed text-muted">
              Acompanhe a NBA ao vivo, faça shot calls por jogo, semana e temporada, monte seu time da rodada e
              desafie a galera na sua liga.
            </p>
            <div className="flex flex-wrap items-center gap-3.5">
              <Link href="/criar-conta" className={buttonClass("hype", "lg", "h-15 px-8 text-lg")}>
                BORA JOGAR
              </Link>
              <Link href="/entrar" className={buttonClass("outline", "lg")}>
                Já tenho conta
              </Link>
            </div>
            <p className="origin-left -rotate-1 font-marker text-xl text-lime">
              Sem dinheiro em jogo. Só moral e cosmético raro.
            </p>
          </div>

          <div className="relative flex min-w-0 flex-[1_1_420px] justify-center pt-10">
            <div className="absolute -top-2.5 right-[8%] animate-bounce-ball" aria-hidden="true">
              <Ball size={72} stroke={4} />
            </div>
            <article
              aria-label="Placar ao vivo de exemplo"
              className="w-full max-w-[460px] overflow-hidden rounded-[26px] border-[3px] border-ink bg-panel shadow-hard-lg"
            >
              <div className="flex items-center justify-between bg-[linear-gradient(135deg,#8E0B21_0_50%,#004C82_50%_100%)] px-6 py-5">
                <div className="flex flex-col">
                  <span className="font-display text-lg">DET</span>
                  <span className="font-score text-7xl leading-none">86</span>
                </div>
                <div className="flex flex-col items-center gap-1.5">
                  <span className="font-score text-2xl">Q4 03:29</span>
                  <span className="animate-wobble rounded-md border-2 border-ink bg-yellow px-2 py-0.5 font-display text-xs text-ink">
                    EXEMPLO
                  </span>
                </div>
                <div className="flex flex-col items-end">
                  <span className="font-display text-lg">NYK</span>
                  <span className="font-score text-7xl leading-none text-yellow">103</span>
                </div>
              </div>
              <div className="flex flex-col gap-3 px-6 py-4">
                <div className="flex justify-between text-sm">
                  <span className="text-muted">Seu shot call</span>
                  <span className="font-extrabold text-lime">2 de 3 certos até aqui</span>
                </div>
                <div className="flex flex-wrap gap-2 text-[13px]">
                  <span className="rounded-full bg-lime px-3 py-1.5 font-extrabold text-ink">NYK vence</span>
                  <span className="rounded-full bg-lime px-3 py-1.5 font-extrabold text-ink">16+</span>
                  <span className="rounded-full bg-surface px-3 py-1.5 font-bold">MVP: em jogo</span>
                </div>
              </div>
            </article>
          </div>
        </section>

        <div className="overflow-hidden border-y-[3px] border-ink bg-yellow py-3.5 text-ink" aria-hidden="true">
          <div className="flex w-max animate-marquee gap-12 font-display text-[22px]">
            {[...marquee, ...marquee].map((word, i) => (
              <span key={i}>{word}</span>
            ))}
          </div>
        </div>

        <section aria-labelledby="h-modos" className="mx-auto flex max-w-[1240px] flex-col gap-8 px-4 py-18 sm:px-6">
          <h2 id="h-modos" className="font-display text-[clamp(28px,4vw,44px)]">
            TRÊS JEITOS DE DOMINAR
          </h2>
          <div className="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-6">
            {modes.map((mode) => (
              <article
                key={mode.title}
                className={`flex flex-col gap-3.5 rounded-3xl border-[3px] border-ink p-7 text-ink shadow-hard ${mode.color} ${mode.tilt}`}
              >
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.3" aria-hidden="true">
                  {mode.icon}
                </svg>
                <h3 className="font-display text-[26px]">{mode.title}</h3>
                <p className="leading-relaxed font-semibold">{mode.text}</p>
              </article>
            ))}
          </div>
        </section>

        <section aria-labelledby="h-cta" className="px-4 pb-18 sm:px-6">
          <div className="mx-auto flex max-w-[1192px] flex-wrap items-center justify-between gap-6 rounded-[30px] border-[3px] border-ink bg-hype px-8 py-14 text-ink shadow-hard-lg sm:px-10">
            <div className="flex min-w-0 flex-[1_1_420px] flex-col gap-2.5">
              <h2 id="h-cta" className="font-display text-[clamp(28px,4vw,48px)] leading-none">
                A RODADA COMEÇA SEGUNDA.
              </h2>
              <p className="text-lg font-bold">Monte o time antes do primeiro apito.</p>
            </div>
            <Link
              href="/criar-conta"
              className="inline-flex h-14 items-center rounded-2xl bg-ink px-6 font-extrabold text-chalk no-underline hover:-translate-y-0.5"
            >
              Criar conta grátis
            </Link>
          </div>
        </section>
      </main>

      <footer className="border-t-2 border-surface px-4 py-7 sm:px-6">
        <div className="mx-auto flex max-w-[1240px] flex-wrap justify-between gap-4 text-[13px] text-subtle">
          <span>ShotCall não é afiliado à NBA nem aos times. Fichas e cosméticos não têm valor em dinheiro.</span>
        </div>
      </footer>
    </div>
  );
}

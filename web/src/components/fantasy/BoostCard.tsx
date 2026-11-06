import Link from "next/link";
import { fpoints } from "@/lib/fantasy";
import type { ShotCallBoost } from "@/lib/types";

const pct = (v: number) => `${Math.round(v * 100)}%`;

/**
 * Boost de palpites: os acertos de shot call nos jogos da rodada multiplicam os pontos do time
 * (vencedor +1%, margem +2%, MVP do jogo +3%, até o teto).
 */
export function BoostCard({ boost, basePoints }: { boost: ShotCallBoost | null; basePoints?: number }) {
  const value = boost?.value ?? 0;
  const cap = boost?.cap ?? 0.3;
  const hits = boost?.hits;
  const capped = !!boost && boost.raw > boost.cap;

  return (
    <section aria-labelledby="h-boost" className="flex flex-col gap-2.5 rounded-2xl border-2 border-purple/60 bg-[#1C1730] px-4 py-3">
      <div className="flex items-baseline justify-between gap-2">
        <h2 id="h-boost" className="font-display text-sm">BOOST DE PALPITES</h2>
        <span className="font-score text-3xl leading-none text-[#C4B5FD]">+{pct(value)}</span>
      </div>
      <div className="h-2.5 overflow-hidden rounded-full bg-surface" aria-hidden="true">
        <div className="h-full rounded-full bg-gradient-to-r from-purple to-pink" style={{ width: `${Math.min(100, (value / cap) * 100)}%` }} />
      </div>
      <p className="text-sm text-muted">
        {boost && boost.calls > 0 ? (
          <>
            {hits!.winner} {hits!.winner === 1 ? "vencedor" : "vencedores"}, {hits!.margin} {hits!.margin === 1 ? "margem" : "margens"} e {hits!.mvp}{" "}
            {hits!.mvp === 1 ? "MVP" : "MVPs"} certos em {boost.calls} {boost.calls === 1 ? "palpite apurado" : "palpites apurados"}
            {capped ? ` · no teto de +${pct(cap)}` : ` · até +${pct(cap)}`}.
            {basePoints !== undefined && value > 0 && (
              <>
                {" "}
                Vale <strong className="text-chalk">+{fpoints(basePoints * value)}</strong> pontos agora.
              </>
            )}
          </>
        ) : (
          <>
            Acerte palpites nos jogos da rodada para multiplicar os pontos do time: vencedor <strong className="text-chalk">+1%</strong>, margem{" "}
            <strong className="text-chalk">+2%</strong>, MVP do jogo <strong className="text-chalk">+3%</strong>, até +{pct(cap)}.
          </>
        )}
      </p>
      <Link href="/jogos" className="self-start text-sm font-bold text-cyan no-underline hover:text-lime">
        Palpitar nos jogos →
      </Link>
    </section>
  );
}

/** Foguinho do cestinha (pontua em dobro). Cinza quando apagado, laranja quando aceso. */
export function Flame({ lit = true, size = 14, className = "" }: { lit?: boolean; size?: number; className?: string }) {
  return (
    <svg width={size} height={Math.round(size * 1.25)} viewBox="0 0 16 20" aria-hidden="true" className={className}>
      <path
        d="M8 1c1 4 6 6 6 11a6 6 0 0 1-12 0c0-3 2-4 3-7 1 2 2 3 3 3 0-3-1-5 0-7z"
        fill={lit ? "var(--color-orange)" : "var(--color-subtle)"}
        stroke="var(--color-ink)"
        strokeWidth="1.4"
        strokeLinejoin="round"
      />
      {lit && <path d="M8 9c.6 2 3 3 3 5.5a3 3 0 0 1-6 0c0-1.5 1.2-2.4 1.6-3.6.5.8 1 1.2 1.4 1.2 0-1.3-.5-2.2 0-3.1z" fill="var(--color-yellow)" />}
    </svg>
  );
}

/** Chamas sobre o círculo do jogador que é o cestinha: três labaredas tremulando fora de tempo. */
export function OnFire() {
  return (
    <span className="pointer-events-none absolute -top-7 left-1/2 flex -translate-x-1/2 items-end sm:-top-8" aria-hidden="true">
      <Flame size={15} className="-mr-1.5 origin-bottom animate-flame [animation-delay:-0.2s]" />
      <Flame size={22} className="z-10 origin-bottom animate-flame" />
      <Flame size={15} className="-ml-1.5 origin-bottom animate-flame [animation-delay:-0.35s]" />
    </span>
  );
}

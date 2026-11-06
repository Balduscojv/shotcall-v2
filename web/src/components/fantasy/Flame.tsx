import type { CSSProperties } from "react";

/** Foguinho do cestinha (pontua em dobro). Cinza quando apagado, laranja quando aceso. */
export function Flame({
  lit = true,
  size = 14,
  className = "",
  style,
}: {
  lit?: boolean;
  size?: number;
  className?: string;
  style?: CSSProperties;
}) {
  return (
    <svg width={size} height={Math.round(size * 1.25)} viewBox="0 0 16 20" aria-hidden="true" className={className} style={style}>
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

const FLAMES = 12;

/**
 * Anel de fogo em volta do círculo do cestinha: 12 labaredas apontando para fora, alternando
 * tamanho e tremulando fora de tempo, sobre um brilho de brasa que pulsa. Fica atrás do círculo
 * (o rosto com a sigla do time continua à vista) e o raio acompanha o tamanho do círculo.
 */
export function OnFire() {
  return (
    <span className="pointer-events-none absolute inset-0 -z-10 [--fire-r:28px] sm:[--fire-r:32px]" aria-hidden="true">
      <span className="absolute -inset-6 animate-fire-glow rounded-full bg-[radial-gradient(circle,rgba(255,200,61,.6)_38%,rgba(255,90,31,.45)_56%,transparent_72%)]" />
      {Array.from({ length: FLAMES }, (_, i) => {
        const big = i % 2 === 0;
        return (
          <span key={i} className="absolute top-1/2 left-1/2 size-0" style={{ transform: `rotate(${(360 / FLAMES) * i}deg)` }}>
            <Flame
              size={big ? 22 : 15}
              className="absolute left-0 origin-bottom -translate-x-1/2 animate-flame"
              style={{ bottom: big ? "calc(var(--fire-r) - 8px)" : "calc(var(--fire-r) - 6px)", animationDelay: `${-((i * 0.17) % 0.5).toFixed(2)}s` }}
            />
          </span>
        );
      })}
    </span>
  );
}

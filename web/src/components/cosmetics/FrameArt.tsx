import { useId, type ReactNode } from "react";

/**
 * Molduras com arte que sai do círculo da foto (capuz, foice...). O desenho é um SVG por cima
 * do avatar, posicionado em volta dele: viewBox em que a foto ocupa 0..100 nos dois eixos.
 *
 * Só aparece em avatar médio ou grande e na prévia: nos pequenos (listas), a arte invadiria a
 * linha de cima. Ali vale só o aro da moldura (a classe em lib/cosmetics.ts).
 */
type Art = {
  /** Caixa do SVG em volta da foto, em % do tamanho do avatar. */
  box: { left: number; top: number; width: number; height: number };
  draw: (id: (name: string) => string) => ReactNode;
};

const arts: Record<string, Art> = {
  "frame-reaper": { box: { left: -18, top: -32, width: 136, height: 144 }, draw: reaper },
};

export function hasFrameArt(assetKey: string | null | undefined): boolean {
  return !!assetKey && assetKey in arts;
}

/** Arte da moldura por cima do avatar. O pai precisa ser `relative`. */
export function FrameArt({ assetKey }: { assetKey: string | null | undefined }) {
  const uid = useId().replace(/:/g, "");
  const art = assetKey ? arts[assetKey] : undefined;
  if (!art) return null;

  const { left, top, width, height } = art.box;
  const id = (name: string) => `${uid}-${name}`;

  return (
    <svg
      aria-hidden="true"
      focusable="false"
      viewBox={`${left} ${top} ${width} ${height}`}
      className="pointer-events-none absolute"
      style={{ left: `${left}%`, top: `${top}%`, width: `${width}%`, height: `${height}%` }}
    >
      {art.draw(id)}
    </svg>
  );
}

const HOOD =
  "M -14 104 C -17 78, -12 44, 4 18 C 14 2, 28 -12, 44 -20 C 56 -26, 70 -30, 82 -27 C 74 -24, 70 -19, 72 -12 " +
  "C 86 2, 101 24, 107 52 C 111 72, 112 90, 114 104 C 100 109, 88 103, 76 108 C 64 103, 52 110, 40 105 " +
  "C 28 110, 14 103, -14 104 Z M 6 52 A 44 44 0 1 0 94 52 A 44 44 0 1 0 6 52 Z";

/**
 * Capuz do Ceifador (Exclusivo): capuz escuro com o rosto vazado e filete dourado na abertura,
 * brilho violeta em volta para separar do fundo escuro, e a foice do lado direito com a lâmina
 * passando por cima do capuz.
 */
function reaper(id: (name: string) => string) {
  return (
    <>
      <defs>
        <linearGradient id={id("cloth")} x1="0.2" y1="0" x2="0.8" y2="1">
          <stop offset="0" stopColor="#3a3148" />
          <stop offset="0.45" stopColor="#1d1826" />
          <stop offset="1" stopColor="#0c0a11" />
        </linearGradient>
        <linearGradient id={id("sheen")} x1="0" y1="0" x2="1" y2="0">
          <stop offset="0" stopColor="#ffffff" stopOpacity="0.12" />
          <stop offset="0.3" stopColor="#ffffff" stopOpacity="0" />
          <stop offset="0.85" stopColor="#000000" stopOpacity="0.3" />
        </linearGradient>
        <linearGradient id={id("blade")} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#f6f8fb" />
          <stop offset="0.55" stopColor="#bcc3ce" />
          <stop offset="1" stopColor="#6f7888" />
        </linearGradient>
        <linearGradient id={id("wood")} x1="0" y1="0" x2="1" y2="0">
          <stop offset="0" stopColor="#8a5c34" />
          <stop offset="0.5" stopColor="#5e3d20" />
          <stop offset="1" stopColor="#3a2411" />
        </linearGradient>
        <filter id={id("aura")} x="-20%" y="-20%" width="140%" height="140%">
          <feDropShadow dx="0" dy="0" stdDeviation="2.4" floodColor="#8b5cf6" floodOpacity="0.75" />
        </filter>
      </defs>

      {/* Capuz */}
      <g filter={`url(#${id("aura")})`}>
        <path fillRule="evenodd" fill={`url(#${id("cloth")})`} stroke="#0a0a0a" strokeWidth="2.6" strokeLinejoin="round" d={HOOD} />
      </g>
      <path fillRule="evenodd" fill={`url(#${id("sheen")})`} d={HOOD} />
      <g fill="none" stroke="#000" strokeOpacity="0.5" strokeWidth="2" strokeLinecap="round">
        <path d="M -4 82 C -5 58, 0 38, 12 20" />
        <path d="M 4 102 C 1 92, 0 84, 2 74" />
        <path d="M 104 84 C 104 62, 99 40, 88 22" />
        <path d="M 52 -20 C 58 -14, 62 -8, 64 0" />
      </g>
      <circle cx="50" cy="52" r="41" fill="none" stroke="#000" strokeOpacity="0.55" strokeWidth="6" />
      <circle cx="50" cy="52" r="44" fill="none" stroke="#e6b325" strokeWidth="2.4" />
      <circle cx="50" cy="52" r="45.8" fill="none" stroke="#0a0a0a" strokeWidth="1.3" />

      {/* Foice: cabo com anéis dourados, lâmina curva e encaixe dourado */}
      <line x1="103" y1="108" x2="95" y2="-16" stroke="#0a0a0a" strokeWidth="8.4" strokeLinecap="round" />
      <line x1="103" y1="108" x2="95" y2="-16" stroke={`url(#${id("wood")})`} strokeWidth="5" strokeLinecap="round" />
      <g stroke="#0a0a0a" strokeWidth="1.2" fill="#e6b325">
        <rect x="94.4" y="20" width="7.6" height="4" rx="1" transform="rotate(-3.7 98.2 22)" />
        <rect x="98" y="78" width="7.6" height="4" rx="1" transform="rotate(-3.7 101.8 80)" />
      </g>
      <path
        fill={`url(#${id("blade")})`}
        stroke="#0a0a0a"
        strokeWidth="2.2"
        strokeLinejoin="round"
        d="M 99 -22 C 76 -34, 40 -33, 14 -18 C 2 -11, -6 0, -9 12 C -1 -1, 12 -10, 28 -15 C 50 -21, 72 -18, 94 -8 Z"
      />
      <path fill="none" stroke="#ffffff" strokeOpacity="0.85" strokeWidth="1.3" strokeLinecap="round" d="M 91 -26 C 70 -32, 42 -30, 20 -19" />
      <rect x="90.5" y="-22" width="9" height="9" rx="1.6" fill="#e6b325" stroke="#0a0a0a" strokeWidth="1.4" />
    </>
  );
}

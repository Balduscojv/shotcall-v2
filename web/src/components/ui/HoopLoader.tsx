/**
 * Carregando: a bola gira em volta do aro, passando por trás e pela frente dele. A cesta é vista
 * de baixo e da esquerda: a tabela de vidro fica à direita, fugindo em perspectiva, o aro preso
 * nela pelo suporte laranja e a rede longa de losangos pendurada.
 *
 * A órbita é uma elipse feita com duas animações CSS (globals.css): uma move a bola na
 * horizontal e outra na vertical, defasadas em 1/4 de volta. Há duas cópias da bola: uma
 * desenhada antes da frente do aro (aparece quando a bola está atrás) e outra depois (quando
 * está na frente); cada uma fica visível em metade da volta.
 */

// Rede: 5 anéis cada vez menores, pontos ligados em X ao anel de baixo (gerado, não editar à mão).
const NET_BACK = "M137 100L133.8 116.3M137 100L133.8 125.7M35 100L44.2 125.7M35 100L44.2 116.3M40.1 88.7L44.2 116.3M40.1 88.7L53 107.9M54.2 79.7L53 107.9M54.2 79.7L69 102.1M74.7 74.7L69 102.1M74.7 74.7L89 100M97.3 74.7L89 100M97.3 74.7L109 102.1M117.8 79.7L109 102.1M117.8 79.7L125 107.9M131.9 88.7L125 107.9M131.9 88.7L133.8 116.3M44.2 116.3L51 142M44.2 116.3L55.1 135.1M53 107.9L55.1 135.1M53 107.9L66.4 129.5M69 102.1L66.4 129.5M69 102.1L82.9 126.4M89 100L82.9 126.4M89 100L101.1 126.4M109 102.1L101.1 126.4M109 102.1L117.6 129.5M125 107.9L117.6 129.5M125 107.9L128.9 135.1M133.8 116.3L128.9 135.1M133.8 116.3L133 142M133 142L130.1 159.3M133 142L130.1 164.7M51 142L59.9 164.7M51 142L59.9 159.3M55.1 135.1L59.9 159.3M55.1 135.1L66.9 154.5M66.4 129.5L66.9 154.5M66.4 129.5L79.4 151.2M82.9 126.4L79.4 151.2M82.9 126.4L95 150M101.1 126.4L95 150M101.1 126.4L110.6 151.2M117.6 129.5L110.6 151.2M117.6 129.5L123.1 154.5M128.9 135.1L123.1 154.5M128.9 135.1L130.1 159.3M59.9 159.3L67 182M59.9 159.3L70.1 178.1M66.9 154.5L70.1 178.1M66.9 154.5L78.7 175M79.4 151.2L78.7 175M79.4 151.2L91.1 173.2M95 150L91.1 173.2M95 150L104.9 173.2M110.6 151.2L104.9 173.2M110.6 151.2L117.3 175M123.1 154.5L117.3 175M123.1 154.5L125.9 178.1M130.1 159.3L125.9 178.1M130.1 159.3L129 182";
const NET_FRONT = "M131.9 111.3L133.8 125.7M131.9 111.3L125 134.1M117.8 120.3L125 134.1M117.8 120.3L109 139.9M97.3 125.3L109 139.9M97.3 125.3L89 142M74.7 125.3L89 142M74.7 125.3L69 139.9M54.2 120.3L69 139.9M54.2 120.3L53 134.1M40.1 111.3L53 134.1M40.1 111.3L44.2 125.7M133.8 125.7L133 142M133.8 125.7L128.9 148.9M125 134.1L128.9 148.9M125 134.1L117.6 154.5M109 139.9L117.6 154.5M109 139.9L101.1 157.6M89 142L101.1 157.6M89 142L82.9 157.6M69 139.9L82.9 157.6M69 139.9L66.4 154.5M53 134.1L66.4 154.5M53 134.1L55.1 148.9M44.2 125.7L55.1 148.9M44.2 125.7L51 142M128.9 148.9L130.1 164.7M128.9 148.9L123.1 169.5M117.6 154.5L123.1 169.5M117.6 154.5L110.6 172.8M101.1 157.6L110.6 172.8M101.1 157.6L95 174M82.9 157.6L95 174M82.9 157.6L79.4 172.8M66.4 154.5L79.4 172.8M66.4 154.5L66.9 169.5M55.1 148.9L66.9 169.5M55.1 148.9L59.9 164.7M130.1 164.7L129 182M130.1 164.7L125.9 185.9M123.1 169.5L125.9 185.9M123.1 169.5L117.3 189M110.6 172.8L117.3 189M110.6 172.8L104.9 190.8M95 174L104.9 190.8M95 174L91.1 190.8M79.4 172.8L91.1 190.8M79.4 172.8L78.7 189M66.9 169.5L78.7 189M66.9 169.5L70.1 185.9M59.9 164.7L70.1 185.9M59.9 164.7L67 182";
// Aro: elipse com centro (86, 100), rx 51, ry 26.
const RIM_BACK = "M35 100 A51 26 0 0 1 137 100";
const RIM_FRONT = "M35 100 A51 26 0 0 0 137 100";
// Tabela inteira vista de baixo, pela esquerda: lado esquerdo mais perto (maior), topo e base
// subindo para a direita. Quadrado e faixa calculados no plano da tabela (oficial 72 × 42 pol.).
const BOARD = "M40 58 L212 10 L212 132 L34 204 Z";
const SQUARE = "M95.7 97.2 L153.9 78 L153 133.9 L94 156.5 Z";
const PAD = "M34.6 189.4 L212 119.8 L212 132 L34 204 Z";

export function HoopLoader({ label = "CARREGANDO", size = 170 }: { label?: string | null; size?: number }) {
  return (
    <div role="status" className="flex flex-col items-center justify-center gap-3 py-10">
      <svg width={size} height={size * (210 / 215)} viewBox="0 0 215 210" aria-hidden="true" className="overflow-visible">
        {/* Tabela de vidro: moldura branca, quadrado branco e faixa preta na base */}
        <path d={BOARD} fill="#22D3EE" fillOpacity="0.16" stroke="#0A0A0A" strokeWidth="7" strokeLinejoin="round" />
        <path d={BOARD} fill="none" stroke="#F6F4EE" strokeWidth="3.5" strokeLinejoin="round" />
        <path d={SQUARE} fill="none" stroke="#F6F4EE" strokeWidth="4" strokeLinejoin="round" />
        <path d={PAD} fill="#0A0A0A" />

        {/* Suporte em caixa: do centro da base da tabela até o fundo do aro */}
        <path d="M112 104 L136 96 L138 134 L110 146 Z" fill="#FF5A1F" stroke="#0A0A0A" strokeWidth="3.5" strokeLinejoin="round" />
        <path d="M136 96 L146 100 L148 136 L138 134" fill="#B83E12" stroke="#0A0A0A" strokeWidth="3" strokeLinejoin="round" />

        {/* Fundo da rede e metade de trás do aro */}
        <path d={NET_BACK} fill="none" stroke="#F6F4EE" strokeWidth="2" strokeLinecap="round" opacity="0.45" />
        <path d={RIM_BACK} fill="none" stroke="#B83E12" strokeWidth="5" strokeLinecap="round" />

        <HoopBall className="hoop-ball-back" />

        {/* Frente da rede e do aro */}
        <path d={NET_FRONT} fill="none" stroke="#F6F4EE" strokeWidth="2.6" strokeLinecap="round" />
        <path d={RIM_FRONT} fill="none" stroke="#FF5A1F" strokeWidth="6" strokeLinecap="round" />
        <path d={RIM_FRONT} fill="none" stroke="#0A0A0A" strokeWidth="2" strokeLinecap="round" opacity="0.35" />

        <HoopBall className="hoop-ball-front" />
      </svg>
      {label && <span className="font-display text-sm tracking-widest text-muted">{label}</span>}
      <span className="sr-only">Carregando…</span>
    </div>
  );
}

/** Bola na órbita: x e y em grupos separados, giro no grupo de dentro. */
function HoopBall({ className }: { className: string }) {
  return (
    <g className="hoop-orbit-x">
      <g className={`hoop-orbit-y ${className}`}>
        <g className="hoop-spin">
          <circle cx="86" cy="86" r="23" fill="#FF5A1F" stroke="#0A0A0A" strokeWidth="3.5" />
          <path d="M86 63v46M63 86h46M70 70c8 7 8 25 0 32M102 70c-8 7-8 25 0 32" fill="none" stroke="#0A0A0A" strokeWidth="3" />
        </g>
      </g>
    </g>
  );
}

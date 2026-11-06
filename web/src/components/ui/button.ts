// Classes dos botões do canvas, para usar em <button> e <Link> sem duplicar estilo.
const base =
  "inline-flex items-center justify-center gap-2 rounded-full font-extrabold no-underline transition-transform " +
  "disabled:cursor-not-allowed disabled:opacity-60 enabled:active:translate-x-0.5 enabled:active:translate-y-0.5";

const variants = {
  /** Ação principal: limão com contorno e sombra chapada. */
  primary: "border-2 border-ink bg-lime text-ink shadow-hard-sm hover:-translate-y-0.5",
  /** Chamada de destaque: degradê laranja → rosa → roxo, fonte display. */
  hype: "border-2 border-ink bg-hype font-display font-normal text-ink shadow-hard hover:-translate-y-0.5",
  /** Secundária: só contorno claro. */
  outline: "border-2 border-chalk text-chalk hover:bg-chalk hover:text-ink",
  ghost: "text-muted hover:text-chalk",
} as const;

const sizes = {
  md: "h-11 px-5 text-[15px]",
  lg: "h-14 px-7 text-base",
} as const;

export function buttonClass(
  variant: keyof typeof variants = "primary",
  size: keyof typeof sizes = "md",
  extra = "",
) {
  return `${base} ${variants[variant]} ${sizes[size]} ${extra}`.trim();
}

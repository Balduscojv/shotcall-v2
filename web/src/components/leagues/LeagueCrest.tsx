import { crestInitials, crestInk } from "@/lib/leagues";

const sizes = {
  sm: "h-12 w-11 text-sm",
  md: "h-[72px] w-16 text-[22px]",
  lg: "h-24 w-[86px] text-3xl",
} as const;

/** Escudo da liga (canvas App-Liga): cor da liga com as iniciais do nome. */
export function LeagueCrest({ name, color, size = "md" }: { name: string; color: string; size?: keyof typeof sizes }) {
  return (
    <span
      aria-hidden="true"
      className={`flex shrink-0 items-center justify-center font-display ${sizes[size]}`}
      style={{
        clipPath: "polygon(50% 0, 100% 14%, 100% 62%, 50% 100%, 0 62%, 0 14%)",
        background: `linear-gradient(140deg, ${color}, color-mix(in srgb, ${color} 55%, #0a0a0a))`,
        color: crestInk(color),
      }}
    >
      {crestInitials(name)}
    </span>
  );
}

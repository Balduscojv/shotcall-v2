import { frameLook } from "@/lib/cosmetics";
import type { User } from "@/lib/types";
import { FrameArt, hasFrameArt } from "@/components/cosmetics/FrameArt";

const sizes = {
  xs: "size-9 text-sm border-[3px]",
  sm: "size-11 text-base border-[3px]",
  md: "size-16 text-xl border-4",
  lg: "size-24 text-3xl border-[6px] sm:size-36 sm:text-5xl sm:border-8",
} as const;

/** Tamanhos em que a arte da moldura (capuz, foice...) aparece. Nos pequenos fica só o aro. */
const withArt: (keyof typeof sizes)[] = ["md", "lg"];

type AvatarUser = Pick<User, "name" | "avatar_url"> & { equipped?: Pick<NonNullable<User["equipped"]>, "frame"> };

/** Foto de perfil com a moldura-prêmio em uso. Sem foto, mostra as iniciais. */
export function Avatar({ user, size = "md" }: { user: AvatarUser; size?: keyof typeof sizes }) {
  const frame = frameLook(user.equipped?.frame);
  const initials = user.name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");

  const art = withArt.includes(size) && hasFrameArt(user.equipped?.frame?.asset_key);
  // No médio (cards de lista), a arte passaria da borda de cima do card: um respiro só quando há arte.
  const room = art && size === "md" ? "mt-3 ml-1.5" : "";

  // A borda (moldura) fica no span de fora; o recorte redondo da foto, no de dentro. Assim a
  // arte da moldura pode sair do círculo sem ser cortada.
  return (
    <span
      className={`relative inline-flex shrink-0 rounded-full bg-surface font-display text-chalk ${frame.className} ${sizes[size]} ${room}`}
      style={frame.style}
    >
      <span className="flex size-full items-center justify-center overflow-hidden rounded-full">
        {user.avatar_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- imagem do usuário servida pelo Laravel
          <img src={user.avatar_url} alt="" className="size-full object-cover" />
        ) : (
          <span aria-hidden="true">{initials || "?"}</span>
        )}
      </span>
      {art && <FrameArt assetKey={user.equipped?.frame?.asset_key} />}
    </span>
  );
}

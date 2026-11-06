import { frameLook } from "@/lib/cosmetics";
import type { User } from "@/lib/types";

const sizes = {
  xs: "size-9 text-sm border-[3px]",
  sm: "size-11 text-base border-[3px]",
  md: "size-16 text-xl border-4",
  lg: "size-24 text-3xl border-[6px] sm:size-36 sm:text-5xl sm:border-8",
} as const;

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

  return (
    <span
      className={`relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface font-display text-chalk ${frame.className} ${sizes[size]}`}
      style={frame.style}
    >
      {user.avatar_url ? (
        // eslint-disable-next-line @next/next/no-img-element -- imagem do usuário servida pelo Laravel
        <img src={user.avatar_url} alt="" className="size-full object-cover" />
      ) : (
        <span aria-hidden="true">{initials || "?"}</span>
      )}
    </span>
  );
}

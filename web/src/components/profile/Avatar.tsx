import type { User } from "@/lib/types";

const sizes = {
  sm: "size-11 text-base border-[3px]",
  md: "size-16 text-xl border-4",
  lg: "size-24 text-3xl border-[6px] sm:size-36 sm:text-5xl sm:border-8",
} as const;

/** Foto de perfil com a moldura laranja. Sem foto, mostra as iniciais. */
export function Avatar({ user, size = "md" }: { user: Pick<User, "name" | "avatar_url">; size?: keyof typeof sizes }) {
  const initials = user.name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");

  return (
    <span
      className={`relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border-orange bg-surface font-display text-chalk ${sizes[size]}`}
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

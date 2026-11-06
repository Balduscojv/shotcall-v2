import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import type { FriendRequests } from "@/lib/types";

export const profileNavClass =
  "inline-flex h-11 items-center gap-1.5 px-2 font-bold whitespace-nowrap text-chalk no-underline hover:text-lime sm:px-3";

/** "Meu perfil" sem consultar nada: o fallback enquanto a contagem de pedidos carrega. */
export function ProfileNavLinkStatic() {
  return (
    <Link href="/perfil" className={profileNavClass}>
      <span className="sm:hidden">Perfil</span>
      <span className="hidden sm:inline">Meu perfil</span>
    </Link>
  );
}

/**
 * "Meu perfil" com a contagem de pedidos de amizade recebidos. Com pedido, leva direto para a
 * aba Amigos. Usar dentro de <Suspense>.
 */
export async function ProfileNavLink() {
  const me = await requireUser();
  const { data } = await serverApi<{ data: FriendRequests }>("/friends/requests");
  const count = data.received.length;
  if (count === 0) return <ProfileNavLinkStatic />;

  const label = count === 1 ? "1 pedido de amizade" : `${count} pedidos de amizade`;

  return (
    <Link href={`/u/${me.handle}/amigos`} className={profileNavClass}>
      <span className="sm:hidden">Perfil</span>
      <span className="hidden sm:inline">Meu perfil</span>
      <span
        className="inline-flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-ink bg-pink px-1 text-xs font-extrabold text-ink"
        aria-label={label}
        title={label}
      >
        {count > 9 ? "9+" : count}
      </span>
    </Link>
  );
}

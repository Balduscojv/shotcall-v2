import Link from "next/link";
import type { ReactNode } from "react";
import { Suspense } from "react";
import { serverApi } from "@/lib/server-api";
import { getProfile } from "@/lib/profile";
import type { FriendRequests, User } from "@/lib/types";
import { Avatar } from "@/components/profile/Avatar";
import { AddFriendForm, RequestActions } from "@/components/profile/FriendForms";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Amigos" };

// Aba "Amigos". No próprio perfil também mostra o campo de adicionar e os pedidos pendentes.
export default function FriendsTab({ params }: PageProps<"/u/[handle]/amigos">) {
  return (
    <Suspense fallback={<TabSkeleton />}>
      <Friends params={params} />
    </Suspense>
  );
}

async function Friends({ params }: Pick<PageProps<"/u/[handle]/amigos">, "params">) {
  const { handle } = await params;
  const profile = await getProfile(handle);
  const { data: friends } = await serverApi<{ data: User[] }>(`/users/${encodeURIComponent(handle)}/friends`);
  const own = profile.friendship === "SELF";
  const requests = own ? (await serverApi<{ data: FriendRequests }>("/friends/requests")).data : null;

  return (
    <div className="flex flex-col gap-8">
      {own && (
        <div className="grid gap-6 lg:grid-cols-2">
          <div className="rounded-2xl border-[3px] border-ink bg-panel p-5">
            <AddFriendForm />
          </div>
          {requests && (requests.received.length > 0 || requests.sent.length > 0) && (
            <section aria-labelledby="h-pedidos" className="flex flex-col gap-3 rounded-2xl border-[3px] border-ink bg-panel p-5">
              <h2 id="h-pedidos" className="font-display text-lg">PEDIDOS</h2>
              <ul className="flex flex-col gap-2">
                {requests.received.map((user) => (
                  <UserRow key={user.id} user={user} note="quer ser seu amigo">
                    <RequestActions handle={user.handle!} kind="received" />
                  </UserRow>
                ))}
                {requests.sent.map((user) => (
                  <UserRow key={user.id} user={user} note="aguardando resposta">
                    <RequestActions handle={user.handle!} kind="sent" />
                  </UserRow>
                ))}
              </ul>
            </section>
          )}
        </div>
      )}

      <section aria-labelledby="h-amigos" className="flex flex-col gap-4">
        <h2 id="h-amigos" className="font-display text-2xl">AMIGOS</h2>
        {friends.length === 0 ? (
          <EmptyState title="NINGUÉM NA QUADRA AINDA">
            {own ? "Adicione a galera pelo @ para comparar palpites e chamar para sua liga." : `${profile.name} ainda não adicionou amigos.`}
          </EmptyState>
        ) : (
          <ul className="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-3">
            {friends.map((friend) => (
              <li key={friend.id}>
                <Link
                  href={`/u/${friend.handle}`}
                  className="flex items-center gap-3 rounded-2xl border-[3px] border-ink bg-panel p-3 text-chalk no-underline transition-transform hover:-translate-y-0.5 hover:shadow-hard"
                >
                  <Avatar user={friend} size="md" />
                  <span className="flex min-w-0 flex-col">
                    <span className="truncate font-extrabold">{friend.name}</span>
                    <span className="truncate text-sm text-cyan">@{friend.handle}</span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}

function UserRow({ user, note, children }: { user: User; note: string; children: ReactNode }) {
  return (
    <li className="flex flex-wrap items-center justify-between gap-3">
      <Link href={`/u/${user.handle}`} className="flex min-w-0 items-center gap-3 text-chalk no-underline">
        <Avatar user={user} size="sm" />
        <span className="flex min-w-0 flex-col">
          <span className="truncate font-extrabold">{user.name}</span>
          <span className="truncate text-sm text-muted">
            <span className="text-cyan">@{user.handle}</span> · {note}
          </span>
        </span>
      </Link>
      {children}
    </li>
  );
}

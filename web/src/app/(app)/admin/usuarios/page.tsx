import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { requireUser } from "@/lib/auth";
import { serverApi } from "@/lib/server-api";
import { roleLabel, roles, roleStyle } from "@/lib/labels";
import type { Paginated, User, UserRole } from "@/lib/types";
import { Avatar } from "@/components/profile/Avatar";
import { EmptyState, TabSkeleton } from "@/components/profile/TabStates";
import { RoleSelect } from "@/components/admin/RoleSelect";
import { buttonClass } from "@/components/ui/button";

export const metadata = { title: "Moderação de usuários" };

type Search = { q?: string; papel?: string; pagina?: string };

export default function AdminUsersPage({ searchParams }: PageProps<"/admin/usuarios">) {
  return (
    <div className="flex flex-col gap-6 px-4 pt-6 sm:px-0">
      <header className="flex flex-col gap-1">
        <h1 className="font-display text-3xl">MODERAÇÃO DE USUÁRIOS</h1>
        <p className="text-muted">Defina quem é admin, moderador ou jogador. Toda conta nova começa como jogador.</p>
      </header>
      <Suspense fallback={<TabSkeleton />}>
        <UsersList searchParams={searchParams as Promise<Search>} />
      </Suspense>
    </div>
  );
}

async function UsersList({ searchParams }: { searchParams: Promise<Search> }) {
  const me = await requireUser();
  // Quem não é admin nem descobre que a página existe. A API também barra (403).
  if (me.role !== "admin") notFound();

  const { q = "", papel = "", pagina = "1" } = await searchParams;
  const role = roles.includes(papel as UserRole) ? (papel as UserRole) : undefined;
  const page = Math.max(1, Number.parseInt(pagina, 10) || 1);

  const query = new URLSearchParams({ page: String(page) });
  if (q.trim()) query.set("q", q.trim());
  if (role) query.set("role", role);

  const { data: users, meta } = await serverApi<Paginated<User>>(`/admin/users?${query}`);

  const pageHref = (n: number) => {
    const params = new URLSearchParams();
    if (q.trim()) params.set("q", q.trim());
    if (role) params.set("papel", role);
    if (n > 1) params.set("pagina", String(n));
    const s = params.toString();
    return `/admin/usuarios${s ? `?${s}` : ""}`;
  };

  return (
    <div className="flex flex-col gap-4">
      {/* GET simples: filtra até sem JavaScript e o filtro fica na URL. */}
      <form action="/admin/usuarios" className="flex flex-wrap items-end gap-3 rounded-2xl border-[3px] border-ink bg-panel p-4">
        <div className="flex min-w-0 flex-1 basis-60 flex-col gap-1.5">
          <label htmlFor="busca" className="text-sm font-bold text-muted">
            Buscar por nome ou @
          </label>
          <input
            id="busca"
            name="q"
            defaultValue={q}
            autoComplete="off"
            spellCheck={false}
            placeholder="crava_tudo"
            className="h-11 rounded-full border-2 border-ink bg-surface px-4 font-semibold text-chalk outline-none placeholder:text-subtle/70 focus:border-cyan"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor="papel" className="text-sm font-bold text-muted">
            Papel
          </label>
          <select
            id="papel"
            name="papel"
            defaultValue={role ?? ""}
            className="h-11 rounded-full border-2 border-ink bg-surface px-4 font-bold text-chalk outline-none focus:border-cyan"
          >
            <option value="">Todos</option>
            {roles.map((r) => (
              <option key={r} value={r}>
                {roleLabel[r]}
              </option>
            ))}
          </select>
        </div>
        <button type="submit" className={buttonClass("primary")}>
          Filtrar
        </button>
        {(q || role) && (
          <Link href="/admin/usuarios" className={buttonClass("ghost", "md", "px-2")}>
            Limpar
          </Link>
        )}
      </form>

      <p className="text-sm text-muted" aria-live="polite">
        {meta.total === 1 ? "1 usuário" : `${meta.total} usuários`}
      </p>

      {users.length === 0 ? (
        <EmptyState title="NINGUÉM POR AQUI">Nenhum usuário bate com esse filtro.</EmptyState>
      ) : (
        <ul className="flex flex-col gap-2">
          {users.map((user) => {
            const userRole = user.role ?? "player";
            const isMe = user.id === me.id;
            return (
              <li
                key={user.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border-[3px] border-ink bg-panel p-3"
              >
                <Link href={`/u/${user.handle}`} className="flex min-w-0 items-center gap-3 text-chalk no-underline">
                  <Avatar user={user} size="sm" />
                  <span className="flex min-w-0 flex-col">
                    <span className="flex items-center gap-2">
                      <span className="truncate font-extrabold">{user.name}</span>
                      <span className={`rounded-full border-2 border-ink px-2 text-xs font-extrabold ${roleStyle[userRole]}`}>
                        {roleLabel[userRole]}
                      </span>
                    </span>
                    <span className="truncate text-sm">
                      <span className="text-cyan">@{user.handle}</span>
                      {user.equipped?.title && <span className="text-subtle"> · {user.equipped.title.name}</span>}
                      {user.email && <span className="text-subtle"> · {user.email}</span>}
                    </span>
                  </span>
                </Link>
                <div className="flex flex-wrap items-start justify-end gap-2">
                  {user.handle && (
                    <Link href={`/admin/usuarios/${user.handle}/premios`} className={buttonClass("outline", "md", "px-4")}>
                      Prêmios
                    </Link>
                  )}
                  {isMe ? (
                    <span className="inline-flex h-11 items-center text-sm font-bold text-subtle">Você não pode mudar o próprio papel</span>
                  ) : (
                    user.handle && <RoleSelect key={userRole} handle={user.handle} name={user.name} current={userRole} />
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      )}

      {meta.last_page > 1 && (
        <nav aria-label="Páginas" className="flex items-center justify-between gap-3">
          {page > 1 ? (
            <Link href={pageHref(page - 1)} className={buttonClass("outline")}>
              Anterior
            </Link>
          ) : (
            <span />
          )}
          <span className="text-sm text-muted">
            Página {meta.current_page} de {meta.last_page}
          </span>
          {page < meta.last_page ? (
            <Link href={pageHref(page + 1)} className={buttonClass("outline")}>
              Próxima
            </Link>
          ) : (
            <span />
          )}
        </nav>
      )}
    </div>
  );
}

import "server-only";
import { cookies } from "next/headers";
import { notFound, redirect } from "next/navigation";

const API_URL = process.env.API_URL ?? "http://localhost:8000";
const APP_URL = process.env.APP_URL ?? "http://localhost:3000";
export const SESSION_COOKIE = "shotcall_session";

/**
 * Chamada ao Laravel a partir do servidor do Next, levando a sessão do navegador.
 * Lê a requisição: quem chama precisa estar dentro de um <Suspense>.
 * 401 manda para o login; 404 mostra a página de não encontrado.
 */
export async function serverApi<T>(path: string): Promise<T> {
  const jar = await cookies();
  // Sem sessão e sem o cookie de "Manter conectado" (remember_web_*), nem tenta.
  const remembered = jar.getAll().some((c) => c.name.startsWith("remember_web_"));
  if (!jar.has(SESSION_COOKIE) && !remembered) redirect("/entrar");

  const res = await fetch(`${API_URL}/api${path}`, {
    headers: {
      Accept: "application/json",
      Cookie: jar.toString(),
      // O Sanctum só liga a sessão para origens do front; o servidor do Next se apresenta como ele.
      Origin: APP_URL,
      Referer: `${APP_URL}/`,
    },
    cache: "no-store",
  });

  if (!res.ok) {
    // Lê o corpo antes de sair: resposta abandonada com "Connection: close" derruba o parser do fetch do Node.
    await res.text().catch(() => "");
    if (res.status === 401) redirect("/entrar");
    if (res.status === 404) notFound();
    throw new Error(`API ${path} respondeu ${res.status}`);
  }

  return (await res.json()) as T;
}

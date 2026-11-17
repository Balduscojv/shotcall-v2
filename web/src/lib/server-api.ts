import "server-only";
import { cookies } from "next/headers";
import { notFound, redirect } from "next/navigation";

const API_URL = process.env.API_URL ?? "http://localhost:8000";
const APP_URL = process.env.APP_URL ?? "http://localhost:3000";
export const SESSION_COOKIE = "shotcall_session";

// No Render Free a API dorme e, enquanto acorda (1–3 min), responde 502/503/504 ou derruba a
// conexão sem a requisição chegar ao Laravel. Nos dois casos repetir é seguro (só GET passa aqui).
const WAKING_STATUS = new Set([502, 503, 504]);
const WAKING_BUDGET_MS = 3.5 * 60_000;
const ATTEMPT_TIMEOUT_MS = 100_000;
const WAKING_DELAYS_MS = [2_000, 4_000, 8_000, 15_000];

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

/** fetch que espera a API acordar. Esgotado o tempo, devolve a última resposta ou lança o último erro. */
async function fetchWaking(url: string, init: RequestInit): Promise<Response> {
  const deadline = performance.now() + WAKING_BUDGET_MS;
  for (let attempt = 0; ; attempt++) {
    const delay = WAKING_DELAYS_MS[Math.min(attempt, WAKING_DELAYS_MS.length - 1)];
    const last = performance.now() + delay >= deadline;
    try {
      const timeout = Math.max(5_000, Math.min(ATTEMPT_TIMEOUT_MS, deadline - performance.now()));
      const res = await fetch(url, { ...init, signal: AbortSignal.timeout(timeout) });
      if (last || !WAKING_STATUS.has(res.status)) return res;
      // Lê o corpo antes de repetir: resposta abandonada com "Connection: close" derruba o parser do fetch do Node.
      await res.text().catch(() => "");
    } catch (e) {
      if (last || performance.now() + delay >= deadline) throw e;
    }
    await sleep(delay);
  }
}

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

  const init: RequestInit = {
    headers: {
      Accept: "application/json",
      Cookie: jar.toString(),
      // O Sanctum só liga a sessão para origens do front; o servidor do Next se apresenta como ele.
      Origin: APP_URL,
      Referer: `${APP_URL}/`,
    },
    cache: "no-store",
  };

  const res = await fetchWaking(`${API_URL}/api${path}`, init);

  if (!res.ok) {
    // Lê o corpo antes de sair: resposta abandonada com "Connection: close" derruba o parser do fetch do Node.
    await res.text().catch(() => "");
    if (res.status === 401) redirect("/entrar");
    if (res.status === 404) notFound();
    throw new Error(`API ${path} respondeu ${res.status}`);
  }

  return (await res.json()) as T;
}

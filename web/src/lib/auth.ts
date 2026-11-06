import "server-only";
import { serverApi } from "./server-api";
import type { User } from "./types";

/** Usuário logado. Sem sessão válida, vai para /entrar. Chamar dentro de um <Suspense>. */
export async function requireUser(): Promise<User> {
  const { data } = await serverApi<{ data: User }>("/me");
  return data;
}

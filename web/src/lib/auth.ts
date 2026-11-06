import "server-only";
import { cache } from "react";
import { serverApi } from "./server-api";
import type { User } from "./types";

/**
 * Usuário logado. Sem sessão válida, vai para /entrar. Chamar dentro de um <Suspense>.
 * O cache() faz o cabeçalho e a página dividirem a mesma chamada ao /me numa requisição.
 */
export const requireUser = cache(async (): Promise<User> => {
  const { data } = await serverApi<{ data: User }>("/me");
  return data;
});

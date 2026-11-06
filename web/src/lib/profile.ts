import "server-only";
import { cache } from "react";
import { serverApi } from "./server-api";
import type { Profile } from "./types";

/**
 * Perfil por @. O cache() do React faz o cabeçalho e a aba dividirem a mesma chamada
 * dentro de uma requisição. Buscar o perfil antes das listas também evita disparar
 * chamadas que ficariam órfãs quando o @ não existe (404).
 */
export const getProfile = cache(async (handle: string): Promise<Profile> => {
  const { data } = await serverApi<{ data: Profile }>(`/users/${encodeURIComponent(handle)}`);
  return data;
});

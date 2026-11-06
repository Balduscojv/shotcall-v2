/** Para onde voltar depois de entrar: só caminhos internos, nunca outro site. */
export function safeReturnPath(fallback = "/perfil"): string {
  const value = new URLSearchParams(window.location.search).get("voltar");
  return value && value.startsWith("/") && !value.startsWith("//") ? value : fallback;
}

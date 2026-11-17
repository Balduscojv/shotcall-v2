// Roda uma vez quando o servidor do Next liga. No Render Free o web e a API dormem sem uso; quando
// alguém acorda o web, já cutuca a API para os dois subirem em paralelo, e não um depois do outro.
export function register() {
  if (process.env.NEXT_RUNTIME !== "nodejs" || process.env.NODE_ENV !== "production") return;

  const apiUrl = process.env.API_URL;
  if (!apiUrl) return;

  void fetch(`${apiUrl}/up`, { cache: "no-store", signal: AbortSignal.timeout(180_000) })
    .then((res) => res.text())
    .catch(() => undefined);
}

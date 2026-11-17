import { connection } from "next/server";

const API_URL = process.env.API_URL ?? "http://localhost:8000";

/**
 * A API está de pé? Usado pela tela de erro para saber se a falha foi a API dormindo no Render
 * Free (aí mostra "acordando" e tenta sozinha) ou um erro de verdade. Cada chamada também
 * cutuca a API, que acorda com ela.
 */
export async function GET() {
  await connection();

  let up = false;
  try {
    const res = await fetch(`${API_URL}/up`, { cache: "no-store", signal: AbortSignal.timeout(8_000) });
    await res.text().catch(() => "");
    up = res.ok;
  } catch {
    up = false;
  }

  return Response.json({ up }, { status: up ? 200 : 503, headers: { "Cache-Control": "no-store" } });
}

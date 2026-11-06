import { NextResponse, type NextRequest } from "next/server";

// Checagem otimista: sem cookie de sessão nem tenta abrir área logada.
// A validação de verdade acontece no Laravel (lib/server-api.ts).
//
// A sessão morre ao fechar o navegador. Quem marcou "Manter conectado" volta só com o cookie
// de lembrar do Laravel (remember_web_<hash>), que reabre a sessão na primeira chamada.
export function proxy(request: NextRequest) {
  const hasSession = request.cookies.has("shotcall_session");
  const remembered = request.cookies.getAll().some((c) => c.name.startsWith("remember_web_"));

  if (!hasSession && !remembered) {
    const url = new URL("/entrar", request.url);
    url.searchParams.set("voltar", request.nextUrl.pathname);
    return NextResponse.redirect(url);
  }
  return NextResponse.next();
}

export const config = {
  matcher: ["/perfil/:path*", "/u/:path*", "/admin/:path*"],
};

import { NextResponse, type NextRequest } from "next/server";

// Checagem otimista: sem cookie de sessão nem tenta abrir área logada.
// A validação de verdade acontece no Laravel (lib/server-api.ts).
export function proxy(request: NextRequest) {
  if (!request.cookies.has("shotcall_session")) {
    const url = new URL("/entrar", request.url);
    url.searchParams.set("voltar", request.nextUrl.pathname);
    return NextResponse.redirect(url);
  }
  return NextResponse.next();
}

export const config = {
  matcher: ["/perfil/:path*", "/u/:path*"],
};

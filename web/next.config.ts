import type { NextConfig } from "next";

// O navegador só fala com o Next; /api e /sanctum são repassados ao Laravel.
// Mesma origem = cookie de sessão httpOnly, sem CORS e sem token no localStorage.
const API_URL = process.env.API_URL ?? "http://localhost:8000";

const nextConfig: NextConfig = {
  cacheComponents: true,
  experimental: {
    // Com proxy.ts, o Next guarda o corpo de toda requisição em memória e corta em 10 MB por
    // padrão. O banner da moderação aceita GIF de até 15 MB (CosmeticCatalogController), então
    // o envio chegava pela metade no Laravel. 20 MB dá folga para os outros campos do formulário.
    proxyClientMaxBodySize: "20mb",
  },
  partialPrefetching: true,
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
  async rewrites() {
    return [
      { source: "/api/:path*", destination: `${API_URL}/api/:path*` },
      { source: "/sanctum/:path*", destination: `${API_URL}/sanctum/:path*` },
      // Fotos de perfil e banners enviados (disco público do Laravel).
      { source: "/storage/:path*", destination: `${API_URL}/storage/:path*` },
    ];
  },
};

export default nextConfig;

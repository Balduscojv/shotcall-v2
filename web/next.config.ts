import type { NextConfig } from "next";

// O navegador só fala com o Next; /api e /sanctum são repassados ao Laravel.
// Mesma origem = cookie de sessão httpOnly, sem CORS e sem token no localStorage.
const API_URL = process.env.API_URL ?? "http://localhost:8000";

const nextConfig: NextConfig = {
  cacheComponents: true,
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

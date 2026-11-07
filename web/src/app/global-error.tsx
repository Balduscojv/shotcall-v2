"use client";

import "./globals.css";
import { ErrorScreen } from "@/components/ui/ErrorScreen";

// Erro no layout raiz: substitui o documento inteiro, então traz o próprio <html>.
export default function GlobalError({ error, retry }: { error: unknown; retry: () => void }) {
  return (
    <html lang="pt-BR">
      <body className="concrete min-h-dvh">
        <title>Erro · ShotCall</title>
        <ErrorScreen error={error} retry={retry} />
      </body>
    </html>
  );
}

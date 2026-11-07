import type { Metadata } from "next";
import { Suspense } from "react";
import { VerifyEmail } from "@/components/auth/PasswordResetForms";

export const metadata: Metadata = { title: "Confirmar e-mail" };

export default function VerifyEmailPage({ searchParams }: PageProps<"/verificar-email">) {
  return (
    <div className="flex flex-col gap-6">
      <h1 className="font-display text-4xl">CONFIRMAR E-MAIL</h1>
      <Suspense fallback={<p className="text-muted">Confirmando…</p>}>
        <Verify searchParams={searchParams} />
      </Suspense>
    </div>
  );
}

async function Verify({ searchParams }: Pick<PageProps<"/verificar-email">, "searchParams">) {
  const q = await searchParams;
  const params = Object.fromEntries(Object.entries(q).map(([k, v]) => [k, (Array.isArray(v) ? v[0] : v) ?? ""]));
  return <VerifyEmail params={params} />;
}

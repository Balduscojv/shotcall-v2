import type { Metadata } from "next";
import { VerifyEmail } from "@/components/auth/PasswordResetForms";

export const metadata: Metadata = { title: "Confirmar e-mail" };

export default async function VerifyEmailPage({ searchParams }: PageProps<"/verificar-email">) {
  const q = await searchParams;
  const params = Object.fromEntries(Object.entries(q).map(([k, v]) => [k, (Array.isArray(v) ? v[0] : v) ?? ""]));
  return (
    <div className="flex flex-col gap-6">
      <h1 className="font-display text-4xl">CONFIRMAR E-MAIL</h1>
      <VerifyEmail params={params} />
    </div>
  );
}

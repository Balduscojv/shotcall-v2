import type { Metadata } from "next";
import { ResetPasswordForm } from "@/components/auth/PasswordResetForms";

export const metadata: Metadata = { title: "Senha nova" };

const first = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) ?? "";

export default async function ResetPasswordPage({ searchParams }: PageProps<"/redefinir-senha">) {
  const q = await searchParams;
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <h1 className="font-display text-4xl">SENHA NOVA</h1>
        <p className="text-muted">Escolha a senha que você vai usar daqui pra frente.</p>
      </div>
      <ResetPasswordForm token={first(q.token)} email={first(q.email)} />
    </div>
  );
}

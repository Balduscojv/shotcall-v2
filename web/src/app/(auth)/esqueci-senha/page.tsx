import type { Metadata } from "next";
import { ForgotPasswordForm } from "@/components/auth/PasswordResetForms";

export const metadata: Metadata = { title: "Esqueci a senha" };

export default function ForgotPasswordPage() {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <h1 className="font-display text-4xl">ESQUECI A SENHA</h1>
        <p className="text-muted">Mandamos um link para o seu e-mail. Por ele você cria uma senha nova.</p>
      </div>
      <ForgotPasswordForm />
    </div>
  );
}

import type { Metadata } from "next";
import { RegisterForm } from "@/components/auth/RegisterForm";

export const metadata: Metadata = { title: "Criar conta" };

export default function RegisterPage() {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <h1 className="font-display text-4xl">CRIAR CONTA</h1>
        <p className="text-muted">Grátis. Você já entra na Liga Global e joga a rodada desta semana.</p>
      </div>
      <RegisterForm />
    </div>
  );
}

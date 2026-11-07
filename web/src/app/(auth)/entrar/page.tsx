import type { Metadata } from "next";
import { GoogleButton } from "@/components/auth/GoogleButton";
import { LoginForm } from "@/components/auth/LoginForm";

export const metadata: Metadata = { title: "Entrar" };

export default function LoginPage() {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <h1 className="font-display text-4xl">ENTRAR</h1>
        <p className="text-muted">Volta pra quadra. Seus palpites estão esperando.</p>
      </div>
      <GoogleButton label="Entrar com Google" />
      <LoginForm />
    </div>
  );
}

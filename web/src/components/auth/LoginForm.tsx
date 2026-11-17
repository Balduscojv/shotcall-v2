"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
import { api, ApiError, wakeApi } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";
import { Field, FormAlert } from "@/components/ui/Field";
import { safeReturnPath } from "./returnPath";

export function LoginForm() {
  const router = useRouter();
  const [error, setError] = useState<ApiError | null>(null);
  const [pending, setPending] = useState(false);

  // A API pode estar dormindo (Render Free): acorda enquanto a pessoa digita.
  useEffect(wakeApi, []);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setPending(true);
    setError(null);
    try {
      await api("/auth/login", {
        body: {
          email: form.get("email"),
          password: form.get("password"),
          remember: form.get("remember") === "on",
        },
      });
      router.replace(safeReturnPath());
      router.refresh();
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, "Algo deu errado."));
      setPending(false);
    }
  }

  const fieldErrors = error && Object.keys(error.errors).length > 0;

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {error && !fieldErrors && <FormAlert>{error.message}</FormAlert>}
      <Field
        label="E-mail"
        name="email"
        type="email"
        autoComplete="email"
        required
        placeholder="voce@email.com"
        error={error?.field("email")}
      />
      <Field
        label="Senha"
        name="password"
        type="password"
        autoComplete="current-password"
        required
        error={error?.field("password")}
      />
      <Link href="/esqueci-senha" className="-mt-3 self-end text-sm font-bold text-cyan underline-offset-4 hover:underline">
        Esqueci a senha
      </Link>
      <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold text-muted">
        <input type="checkbox" name="remember" className="size-5 accent-lime" />
        Manter conectado neste computador
      </label>
      <button type="submit" disabled={pending} className={buttonClass("hype", "lg", "w-full text-lg")}>
        {pending ? "ENTRANDO…" : "ENTRAR"}
      </button>
      <p className="text-center text-sm text-muted">
        Ainda não tem conta?{" "}
        <Link href="/criar-conta" className="font-extrabold text-lime underline-offset-4 hover:underline">
          Criar conta grátis
        </Link>
      </p>
    </form>
  );
}

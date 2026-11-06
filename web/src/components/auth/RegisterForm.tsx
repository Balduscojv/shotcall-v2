"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";
import { Field, FormAlert } from "@/components/ui/Field";

export function RegisterForm() {
  const router = useRouter();
  const [error, setError] = useState<ApiError | null>(null);
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setPending(true);
    setError(null);
    try {
      await api("/auth/register", {
        body: {
          name: form.get("name"),
          handle: form.get("handle"),
          email: form.get("email"),
          password: form.get("password"),
        },
      });
      router.replace("/perfil");
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
        label="Nome"
        name="name"
        autoComplete="name"
        required
        maxLength={60}
        placeholder="Como a galera te chama"
        error={error?.field("name")}
      />
      <Field
        label="Seu @"
        name="handle"
        prefix="@"
        autoComplete="username"
        autoCapitalize="none"
        spellCheck={false}
        required
        minLength={3}
        maxLength={24}
        placeholder="crava_tudo"
        hint="É assim que você aparece no ranking. Letras, números e _."
        error={error?.field("handle")}
      />
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
        autoComplete="new-password"
        required
        minLength={8}
        hint="Mínimo de 8 caracteres."
        error={error?.field("password")}
      />
      <button type="submit" disabled={pending} className={buttonClass("hype", "lg", "w-full text-lg")}>
        {pending ? "CRIANDO…" : "BORA JOGAR"}
      </button>
      <p className="text-center text-sm text-muted">
        Já tem conta?{" "}
        <Link href="/entrar" className="font-extrabold text-lime underline-offset-4 hover:underline">
          Entrar
        </Link>
      </p>
    </form>
  );
}

"use client";

import Link from "next/link";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";
import { Field, FormAlert } from "@/components/ui/Field";

const linkClass = "font-extrabold text-lime underline-offset-4 hover:underline";

function Done({ children }: { children: React.ReactNode }) {
  return (
    <p role="status" className="rounded-xl border-2 border-ink bg-lime px-4 py-3 text-sm font-bold text-ink shadow-hard-sm">
      {children}
    </p>
  );
}

/** Pede o link de senha nova por e-mail. */
export function ForgotPasswordForm() {
  const [error, setError] = useState<ApiError | null>(null);
  const [pending, setPending] = useState(false);
  const [sent, setSent] = useState<string | null>(null);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setPending(true);
    setError(null);
    try {
      const res = await api<{ message: string }>("/auth/forgot-password", { body: { email: form.get("email") } });
      setSent(res.message);
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, "Algo deu errado."));
    } finally {
      setPending(false);
    }
  }

  if (sent) {
    return (
      <div className="flex flex-col gap-5">
        <Done>{sent}</Done>
        <Link href="/entrar" className={linkClass}>
          ← Voltar para entrar
        </Link>
      </div>
    );
  }

  const fieldErrors = error && Object.keys(error.errors).length > 0;
  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {error && !fieldErrors && <FormAlert>{error.message}</FormAlert>}
      <Field label="E-mail da conta" name="email" type="email" autoComplete="email" required error={error?.field("email")} />
      <button type="submit" disabled={pending} className={buttonClass("hype", "lg", "w-full text-lg")}>
        {pending ? "ENVIANDO…" : "MANDAR LINK"}
      </button>
      <Link href="/entrar" className={`text-center text-sm ${linkClass}`}>
        Lembrei a senha
      </Link>
    </form>
  );
}

/** Senha nova a partir do link do e-mail. */
export function ResetPasswordForm({ token, email }: { token: string; email: string }) {
  const [error, setError] = useState<ApiError | null>(null);
  const [pending, setPending] = useState(false);
  const [done, setDone] = useState(false);

  if (!token || !email) {
    return (
      <div className="flex flex-col gap-5">
        <FormAlert>Esse link está incompleto. Peça um novo.</FormAlert>
        <Link href="/esqueci-senha" className={linkClass}>
          Pedir link novo
        </Link>
      </div>
    );
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setPending(true);
    setError(null);
    try {
      await api("/auth/reset-password", {
        body: { token, email, password: form.get("password"), password_confirmation: form.get("password_confirmation") },
      });
      setDone(true);
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, "Algo deu errado."));
    } finally {
      setPending(false);
    }
  }

  if (done) {
    return (
      <div className="flex flex-col gap-5">
        <Done>Senha nova salva. Agora é só entrar.</Done>
        <Link href="/entrar" className={buttonClass("hype", "lg", "w-full text-lg")}>
          ENTRAR
        </Link>
      </div>
    );
  }

  const tokenError = error?.field("token") ?? error?.field("email");
  const generic = error && Object.keys(error.errors).length === 0 ? error.message : null;
  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {(generic || tokenError) && (
        <FormAlert>
          {generic ?? tokenError}{" "}
          {tokenError && (
            <Link href="/esqueci-senha" className="underline">
              Pedir link novo
            </Link>
          )}
        </FormAlert>
      )}
      <p className="text-sm text-muted">
        Conta: <strong className="text-chalk">{email}</strong>
      </p>
      <Field
        label="Senha nova"
        name="password"
        type="password"
        autoComplete="new-password"
        required
        minLength={8}
        hint="Pelo menos 8 caracteres."
        error={error?.field("password")}
      />
      <Field label="Repita a senha nova" name="password_confirmation" type="password" autoComplete="new-password" required />
      <button type="submit" disabled={pending} className={buttonClass("hype", "lg", "w-full text-lg")}>
        {pending ? "SALVANDO…" : "SALVAR SENHA"}
      </button>
    </form>
  );
}

/** Abre o link de confirmação: repassa os parâmetros assinados para a API. */
export function VerifyEmail({ params }: { params: Record<string, string> }) {
  const [state, setState] = useState<"pending" | "ok" | "error">("pending");
  const [message, setMessage] = useState("");
  const started = useRef(false);

  const incomplete = !params.id || !params.hash || !params.signature;

  useEffect(() => {
    if (started.current || incomplete) return;
    started.current = true;
    const { id, hash, ...rest } = params;
    api(`/auth/verify-email/${encodeURIComponent(id)}/${encodeURIComponent(hash)}?${new URLSearchParams(rest)}`)
      .then(() => setState("ok"))
      .catch((e) => {
        setState("error");
        setMessage(e instanceof ApiError ? e.message : "Algo deu errado.");
      });
  }, [params, incomplete]);

  if (incomplete) {
    return (
      <div className="flex flex-col gap-5">
        <FormAlert>Esse link está incompleto.</FormAlert>
        <Link href="/perfil" className={linkClass}>
          Ir para o app
        </Link>
      </div>
    );
  }
  if (state === "pending") return <p className="text-muted">Confirmando…</p>;
  if (state === "ok") {
    return (
      <div className="flex flex-col gap-5">
        <Done>E-mail confirmado. Valeu!</Done>
        <Link href="/perfil" className={buttonClass("hype", "lg", "w-full text-lg")}>
          IR PARA O APP
        </Link>
      </div>
    );
  }
  return (
    <div className="flex flex-col gap-5">
      <FormAlert>{message}</FormAlert>
      <p className="text-sm text-muted">Entre na sua conta e peça um link novo no aviso do topo da página.</p>
      <Link href="/perfil" className={linkClass}>
        Ir para o app
      </Link>
    </div>
  );
}

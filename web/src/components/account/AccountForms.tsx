"use client";

import { useRouter } from "next/navigation";
import { useState, type FormEvent, type ReactNode } from "react";
import { api, ApiError } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";
import { Field, FormAlert } from "@/components/ui/Field";

/** Estado comum dos formulários: enviando, erro da API e aviso de sucesso. */
function useSubmit() {
  const [error, setError] = useState<ApiError | null>(null);
  const [pending, setPending] = useState(false);
  const [done, setDone] = useState(false);

  async function run(action: () => Promise<void>) {
    setPending(true);
    setError(null);
    setDone(false);
    try {
      await action();
      setDone(true);
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, "Algo deu errado."));
    } finally {
      setPending(false);
    }
  }

  const generic = error && Object.keys(error.errors).length === 0 ? error.message : null;
  return { error, pending, done, run, generic };
}

function Saved({ children }: { children: ReactNode }) {
  return (
    <p role="status" className="text-sm font-bold text-lime">
      {children}
    </p>
  );
}

export function AccountForm({ name, handle, email }: { name: string; handle: string; email: string }) {
  const router = useRouter();
  const { error, pending, done, run, generic } = useSubmit();

  function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    run(async () => {
      await api("/me", { method: "PATCH", body: { name: form.get("name"), handle: form.get("handle"), email: form.get("email") } });
      router.refresh();
    });
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
      {generic && <FormAlert>{generic}</FormAlert>}
      <Field label="Nome" name="name" defaultValue={name} autoComplete="name" required maxLength={60} error={error?.field("name")} />
      <Field
        label="Seu @"
        name="handle"
        prefix="@"
        defaultValue={handle}
        autoComplete="username"
        autoCapitalize="none"
        spellCheck={false}
        required
        minLength={3}
        maxLength={24}
        hint="Trocar o @ muda o endereço do seu perfil."
        error={error?.field("handle")}
      />
      <Field label="E-mail" name="email" type="email" defaultValue={email} autoComplete="email" required error={error?.field("email")} />
      <div className="flex items-center gap-4">
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Salvando…" : "Salvar"}
        </button>
        {done && <Saved>Dados salvos.</Saved>}
      </div>
    </form>
  );
}

export function PasswordForm() {
  const { error, pending, done, run, generic } = useSubmit();

  function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const el = event.currentTarget;
    const form = new FormData(el);
    run(async () => {
      await api("/me/password", {
        method: "PUT",
        body: {
          current_password: form.get("current_password"),
          password: form.get("password"),
          password_confirmation: form.get("password_confirmation"),
        },
      });
      el.reset();
    });
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
      {generic && <FormAlert>{generic}</FormAlert>}
      <Field
        label="Senha atual"
        name="current_password"
        type="password"
        autoComplete="current-password"
        required
        error={error?.field("current_password")}
      />
      <Field
        label="Nova senha"
        name="password"
        type="password"
        autoComplete="new-password"
        required
        minLength={8}
        hint="Pelo menos 8 caracteres."
        error={error?.field("password")}
      />
      <Field label="Repita a nova senha" name="password_confirmation" type="password" autoComplete="new-password" required />
      <div className="flex items-center gap-4">
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Trocando…" : "Trocar senha"}
        </button>
        {done && <Saved>Senha trocada. As outras sessões foram encerradas.</Saved>}
      </div>
    </form>
  );
}

export function DeleteAccount() {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const { error, pending, run, generic } = useSubmit();

  function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    run(async () => {
      await api("/me", { method: "DELETE", body: { password: form.get("password") } });
      router.replace("/");
      router.refresh();
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="text-muted">
        Apaga sua conta e tudo o que é seu: palpites, times, prêmios, amizades e fotos. Você sai dos rankings. As ligas que você criou
        passam para outro membro, e a liga que ficar sem ninguém é excluída. <strong className="text-chalk">Não dá para desfazer.</strong>
      </p>
      {!open ? (
        <button
          type="button"
          onClick={() => setOpen(true)}
          className={buttonClass("outline", "md", "self-start border-pink! text-pink! hover:bg-pink! hover:text-ink!")}
        >
          Quero excluir minha conta
        </button>
      ) : (
        <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
          {generic && <FormAlert>{generic}</FormAlert>}
          <Field
            label="Confirme com sua senha"
            name="password"
            type="password"
            autoComplete="current-password"
            required
            autoFocus
            error={error?.field("password") ?? error?.field("account")}
          />
          <div className="flex flex-wrap items-center gap-3">
            <button type="submit" disabled={pending} className={buttonClass("primary", "md", "bg-pink!")}>
              {pending ? "Excluindo…" : "Excluir para sempre"}
            </button>
            <button type="button" onClick={() => setOpen(false)} className={buttonClass("ghost")}>
              Cancelar
            </button>
          </div>
        </form>
      )}
    </div>
  );
}

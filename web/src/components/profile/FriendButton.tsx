"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";
import type { Friendship } from "@/lib/types";

/** Ação de amizade no perfil de outra pessoa, conforme a relação atual. */
export function FriendButton({ handle, status }: { handle: string; status: Friendship }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function run(action: () => Promise<unknown>) {
    setPending(true);
    setError(null);
    try {
      await action();
      router.refresh();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Não deu certo. Tente de novo.");
    } finally {
      setPending(false);
    }
  }

  const add = () => run(() => api("/friends", { body: { handle } }));
  const accept = () => run(() => api(`/friends/${handle}/accept`, { method: "POST" }));
  const remove = () => run(() => api(`/friends/${handle}`, { method: "DELETE" }));

  return (
    <div className="flex flex-col items-end gap-1.5">
      <div className="flex flex-wrap gap-2">
        {status === "NONE" && (
          <button type="button" onClick={add} disabled={pending} className={buttonClass("primary")}>
            Adicionar amigo
          </button>
        )}
        {status === "SENT" && (
          <button type="button" onClick={remove} disabled={pending} className={buttonClass("outline")}>
            Pedido enviado · cancelar
          </button>
        )}
        {status === "RECEIVED" && (
          <>
            <button type="button" onClick={accept} disabled={pending} className={buttonClass("primary")}>
              Aceitar pedido
            </button>
            <button type="button" onClick={remove} disabled={pending} className={buttonClass("outline")}>
              Recusar
            </button>
          </>
        )}
        {status === "FRIENDS" && (
          <button
            type="button"
            onClick={() => confirm("Desfazer a amizade?") && remove()}
            disabled={pending}
            className={buttonClass("outline")}
          >
            Amigos · desfazer
          </button>
        )}
      </div>
      {error && <p role="alert" className="text-sm font-bold text-danger">{error}</p>}
    </div>
  );
}

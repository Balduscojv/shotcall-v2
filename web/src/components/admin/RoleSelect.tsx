"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import { roleLabel, roles } from "@/lib/labels";
import type { UserRole } from "@/lib/types";
import { buttonClass } from "@/components/ui/button";

/**
 * Troca o papel de um usuário. A API é quem decide: só admin chega aqui e ninguém muda
 * o próprio papel; o erro dela aparece embaixo do campo.
 */
export function RoleSelect({ handle, name, current }: { handle: string; name: string; current: UserRole }) {
  const router = useRouter();
  const [role, setRole] = useState<UserRole>(current);
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const changed = role !== current;
  const selectId = `role-${handle}`;

  async function save() {
    // Dar ou tirar acesso total merece uma confirmação.
    if ((role === "admin" || current === "admin") && !window.confirm(`Mudar ${name} de ${roleLabel[current]} para ${roleLabel[role]}?`)) {
      return;
    }

    setPending(true);
    setMessage(null);
    try {
      await api(`/admin/users/${encodeURIComponent(handle)}/role`, { method: "PATCH", body: { role } });
      setMessage({ ok: true, text: "Papel atualizado." });
      router.refresh();
    } catch (e) {
      setRole(current);
      setMessage({ ok: false, text: e instanceof ApiError ? (e.field("role") ?? e.message) : "Não deu certo." });
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <div className="flex items-center gap-2">
        <label htmlFor={selectId} className="sr-only">
          Papel de {name}
        </label>
        <select
          id={selectId}
          value={role}
          disabled={pending}
          onChange={(e) => {
            setRole(e.target.value as UserRole);
            setMessage(null);
          }}
          className="h-11 rounded-full border-2 border-ink bg-surface px-4 font-bold text-chalk outline-none focus:border-cyan disabled:opacity-60"
        >
          {roles.map((r) => (
            <option key={r} value={r}>
              {roleLabel[r]}
            </option>
          ))}
        </select>
        {changed && (
          <>
            <button type="button" onClick={save} disabled={pending} className={buttonClass("primary", "md", "px-4")}>
              {pending ? "Salvando…" : "Salvar"}
            </button>
            <button type="button" onClick={() => setRole(current)} disabled={pending} className={buttonClass("ghost", "md", "px-2")}>
              Desfazer
            </button>
          </>
        )}
      </div>
      {message && (
        <p role="status" className={`text-sm font-bold ${message.ok ? "text-lime" : "text-danger"}`}>
          {message.text}
        </p>
      )}
    </div>
  );
}

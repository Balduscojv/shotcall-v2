"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "@/lib/api";
import { buttonClass } from "@/components/ui/button";

export function LogoutButton() {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  async function logout() {
    setPending(true);
    try {
      await api("/auth/logout", { method: "POST" });
    } finally {
      router.replace("/entrar");
      router.refresh();
    }
  }

  return (
    <button type="button" onClick={logout} disabled={pending} className={buttonClass("outline")}>
      {pending ? "Saindo…" : "Sair"}
    </button>
  );
}

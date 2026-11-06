"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { CosmeticSlot } from "@/lib/types";
import { buttonClass } from "@/components/ui/button";

/** Usar um prêmio (key) ou tirar o que está em uso naquele tipo (key = null). */
export function EquipButton({ slot, cosmeticKey, label }: { slot: CosmeticSlot; cosmeticKey: string | null; label: string }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function run() {
    setPending(true);
    setError(null);
    try {
      await api(`/me/cosmetics/${slot.toLowerCase()}`, { method: "PUT", body: { key: cosmeticKey } });
      router.refresh();
    } catch (e) {
      setError(e instanceof ApiError ? (e.field("key") ?? e.message) : "Não deu certo.");
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col gap-1">
      <button
        type="button"
        onClick={run}
        disabled={pending}
        className={cosmeticKey ? buttonClass("primary", "md", "w-full") : buttonClass("ghost", "md", "px-2")}
      >
        {pending ? "Salvando…" : label}
      </button>
      {error && (
        <p role="alert" className="text-sm font-bold text-danger">
          {error}
        </p>
      )}
    </div>
  );
}

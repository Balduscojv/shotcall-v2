"use client";

import { useEffect } from "react";
import { api } from "@/lib/api";
import { NOTIFICATIONS_CHANGED } from "@/lib/notifications";

/**
 * Abriu a lista: marca tudo como lido e avisa o sininho. A página continua destacando o que
 * chegou de novo, porque foi desenhada antes desta chamada.
 */
export function MarkAllRead({ enabled }: { enabled: boolean }) {
  useEffect(() => {
    if (!enabled) return;
    api("/notifications/read", { method: "POST" })
      .then(() => window.dispatchEvent(new Event(NOTIFICATIONS_CHANGED)))
      .catch(() => {});
  }, [enabled]);

  return null;
}

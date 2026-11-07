"use client";

import { useState } from "react";
import { api, ApiError } from "@/lib/api";

export function ResendVerification() {
  const [state, setState] = useState<"idle" | "pending" | "sent">("idle");
  const [message, setMessage] = useState("");

  async function resend() {
    setState("pending");
    try {
      const res = await api<{ message: string }>("/me/verification-email", { method: "POST" });
      setMessage(res.message);
      setState("sent");
    } catch (e) {
      setMessage(e instanceof ApiError ? e.message : "Algo deu errado.");
      setState("idle");
    }
  }

  if (state === "sent") return <span className="font-semibold">{message}</span>;
  return (
    <>
      <button type="button" onClick={resend} disabled={state === "pending"} className="min-h-8 underline underline-offset-4 disabled:opacity-60">
        {state === "pending" ? "Enviando…" : "Mandar de novo"}
      </button>
      {message && <span className="font-semibold">{message}</span>}
    </>
  );
}

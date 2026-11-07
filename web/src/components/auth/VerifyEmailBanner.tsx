import { requireUser } from "@/lib/auth";
import { ResendVerification } from "./ResendVerification";

/** Aviso no topo enquanto o e-mail não foi confirmado. */
export async function VerifyEmailBanner() {
  const user = await requireUser();
  if (user.email_verified !== false) return null;

  return (
    <div role="status" className="border-b-2 border-ink bg-yellow text-ink">
      <div className="mx-auto flex max-w-[1240px] flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-sm font-bold sm:px-6">
        <span>Confirme seu e-mail: mandamos um link para {user.email}.</span>
        <ResendVerification />
      </div>
    </div>
  );
}

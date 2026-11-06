import Link from "next/link";
import { Ball } from "@/components/ui/Logo";
import { buttonClass } from "@/components/ui/button";

export default function NotFound() {
  return (
    <div className="mx-auto flex max-w-xl flex-col items-start gap-5 px-4 py-20">
      <Ball size={56} stroke={4} />
      <h1 className="font-display text-[clamp(32px,6vw,56px)] leading-none">
        AIRBALL<span className="text-orange">.</span>
      </h1>
      <p className="text-lg text-muted">Não achamos essa página. O @ pode ter mudado ou o link está errado.</p>
      <Link href="/perfil" className={buttonClass("primary")}>
        Voltar para o meu perfil
      </Link>
    </div>
  );
}

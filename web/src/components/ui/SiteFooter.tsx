import Link from "next/link";

/** Rodapé de todas as áreas: aviso de não afiliação e links legais. */
export function SiteFooter() {
  return (
    <footer className="border-t-2 border-surface px-4 py-7 sm:px-6">
      <div className="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-x-6 gap-y-3 text-[13px] text-subtle">
        <span>ShotCall não é afiliado à NBA nem aos times. Fichas e cosméticos não têm valor em dinheiro.</span>
        <nav aria-label="Informações legais" className="flex gap-4">
          <Link href="/termos" className="text-subtle underline-offset-4 hover:text-chalk hover:underline">
            Termos de uso
          </Link>
          <Link href="/privacidade" className="text-subtle underline-offset-4 hover:text-chalk hover:underline">
            Privacidade
          </Link>
        </nav>
      </div>
    </footer>
  );
}

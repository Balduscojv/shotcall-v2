"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const tabs = [
  { href: "/admin/usuarios", label: "Usuários" },
  { href: "/admin/premios", label: "Prêmios" },
  { href: "/admin/missoes", label: "Missões" },
];

/** Abas da moderação. A aba fica ativa também nas páginas dentro dela (ex.: editar prêmio). */
export function AdminTabs({ pathname: fixed }: { pathname?: string | null }) {
  return fixed === undefined ? <LiveTabs /> : <Tabs pathname={fixed ?? ""} />;
}

/** Lê a URL atual. Fica dentro de <Suspense> no layout. */
function LiveTabs() {
  return <Tabs pathname={usePathname()} />;
}

function Tabs({ pathname }: { pathname: string }) {
  return (
    <nav aria-label="Moderação" className="flex flex-wrap gap-2 px-4 pt-6 sm:px-0">
      {tabs.map((tab) => {
        const active = pathname === tab.href || pathname.startsWith(`${tab.href}/`);
        return (
          <Link
            key={tab.href}
            href={tab.href}
            aria-current={active ? "page" : undefined}
            className={`inline-flex h-11 items-center rounded-full border-2 px-5 text-[15px] font-extrabold no-underline transition-colors ${
              active ? "border-ink bg-lime text-ink shadow-hard-sm" : "border-surface text-chalk hover:border-chalk"
            }`}
          >
            {tab.label}
          </Link>
        );
      })}
    </nav>
  );
}

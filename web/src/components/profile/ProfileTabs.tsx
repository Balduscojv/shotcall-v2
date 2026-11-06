"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { Profile } from "@/lib/types";

/** Abas do perfil. Cada aba é uma rota, então dá para compartilhar o link direto. */
export function ProfileTabs({ handle, counts }: { handle: string; counts: Profile["counts"] }) {
  const pathname = usePathname();
  const base = `/u/${handle}`;
  const tabs = [
    { href: base, label: "Palpites", count: counts.shot_calls },
    { href: `${base}/premios`, label: "Prêmios", count: counts.awards },
    { href: `${base}/ligas`, label: "Ligas", count: counts.leagues },
    { href: `${base}/amigos`, label: "Amigos", count: counts.friends },
  ];

  return (
    <nav aria-label="Seções do perfil" className="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
      <ul className="flex min-w-max gap-2">
        {tabs.map((tab) => {
          const active = pathname === tab.href;
          return (
            <li key={tab.href}>
              <Link
                href={tab.href}
                aria-current={active ? "page" : undefined}
                className={`inline-flex h-11 items-center gap-2 rounded-full border-2 px-5 text-[15px] font-extrabold no-underline transition-colors ${
                  active
                    ? "border-ink bg-lime text-ink shadow-hard-sm"
                    : "border-surface text-muted hover:border-chalk hover:text-chalk"
                }`}
              >
                {tab.label}
                <span
                  className={`rounded-full px-2 py-0.5 text-xs ${active ? "bg-ink text-lime" : "bg-surface text-chalk"}`}
                >
                  {tab.count}
                </span>
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

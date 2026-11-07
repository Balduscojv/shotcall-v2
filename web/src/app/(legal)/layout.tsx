import { Logo } from "@/components/ui/Logo";
import { SiteFooter } from "@/components/ui/SiteFooter";

/** Páginas públicas de texto (termos, privacidade): abrem sem login. */
export default function LegalLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-dvh flex-col">
      <header className="fence border-b-[3px] border-ink">
        <div className="mx-auto flex max-w-[1240px] items-center px-4 py-4 sm:px-6">
          <Logo />
        </div>
      </header>
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-10 sm:px-6">
        <article className="legal flex flex-col gap-4 leading-relaxed text-muted [&_h1]:font-display [&_h1]:text-4xl [&_h1]:text-chalk [&_h2]:mt-6 [&_h2]:font-display [&_h2]:text-xl [&_h2]:text-chalk [&_li]:ml-5 [&_li]:list-disc [&_strong]:text-chalk">
          {children}
        </article>
      </main>
      <SiteFooter />
    </div>
  );
}

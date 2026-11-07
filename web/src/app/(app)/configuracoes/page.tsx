import { Suspense, type ReactNode } from "react";
import { requireUser } from "@/lib/auth";
import { AccountForm, DeleteAccount, PasswordForm } from "@/components/account/AccountForms";
import { TabSkeleton } from "@/components/profile/TabStates";

export const metadata = { title: "Configurações" };

export default function SettingsPage() {
  return (
    <div className="flex max-w-2xl flex-col gap-8 px-4 pt-6 sm:px-0">
      <h1 className="font-display text-3xl">CONFIGURAÇÕES</h1>
      <Suspense fallback={<TabSkeleton />}>
        <Settings />
      </Suspense>
    </div>
  );
}

async function Settings() {
  const user = await requireUser();

  return (
    <>
      <Section id="h-conta" title="CONTA">
        <AccountForm name={user.name} handle={user.handle ?? ""} email={user.email ?? ""} />
      </Section>
      <Section id="h-senha" title="SENHA">
        <PasswordForm />
      </Section>
      <Section id="h-excluir" title="EXCLUIR CONTA" danger>
        <DeleteAccount />
      </Section>
    </>
  );
}

function Section({ id, title, danger, children }: { id: string; title: string; danger?: boolean; children: ReactNode }) {
  return (
    <section
      aria-labelledby={id}
      className={`flex flex-col gap-4 rounded-3xl border-2 bg-panel p-5 sm:p-6 ${danger ? "border-pink/60" : "border-surface"}`}
    >
      <h2 id={id} className={`font-display text-lg ${danger ? "text-pink" : ""}`}>
        {title}
      </h2>
      {children}
    </section>
  );
}

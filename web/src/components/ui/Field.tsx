import type { InputHTMLAttributes, ReactNode } from "react";

type Props = InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  name: string;
  error?: string;
  hint?: ReactNode;
  prefix?: string;
};

export function Field({ label, name, error, hint, prefix, id, ...input }: Props) {
  const inputId = id ?? `f-${name}`;
  const describedBy = error ? `${inputId}-erro` : hint ? `${inputId}-dica` : undefined;

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={inputId} className="text-sm font-bold text-muted">
        {label}
      </label>
      <div
        className={`flex h-12 items-center rounded-xl border-2 bg-surface transition-colors focus-within:border-cyan ${
          error ? "border-danger" : "border-ink"
        }`}
      >
        {prefix && <span className="pl-4 font-bold text-subtle">{prefix}</span>}
        <input
          id={inputId}
          name={name}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          className={`h-full w-full min-w-0 bg-transparent text-base font-semibold text-chalk outline-none placeholder:text-subtle/70 ${
            prefix ? "pr-4 pl-1" : "px-4"
          }`}
          {...input}
        />
      </div>
      {error ? (
        <p id={`${inputId}-erro`} className="text-sm font-bold text-danger">
          {error}
        </p>
      ) : hint ? (
        <p id={`${inputId}-dica`} className="text-sm text-subtle">
          {hint}
        </p>
      ) : null}
    </div>
  );
}

export function FormAlert({ children }: { children: ReactNode }) {
  return (
    <p role="alert" className="rounded-xl border-2 border-ink bg-pink px-4 py-3 text-sm font-bold text-ink shadow-hard-sm">
      {children}
    </p>
  );
}

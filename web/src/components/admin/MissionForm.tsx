"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { api, ApiError, type FieldErrors } from "@/lib/api";
import { rarityLabel, slotLabel, slots } from "@/lib/labels";
import type { AdminCosmetic, ConditionParam, Mission, MissionCondition } from "@/lib/types";
import { CosmeticPreview } from "@/components/cosmetics/CosmeticPreview";
import { Field } from "@/components/ui/Field";
import { LeagueParamPicker } from "./LeagueParamPicker";
import { buttonClass } from "@/components/ui/button";

/** ISO da API -> valor de <input type="datetime-local"> no fuso de quem está vendo. */
function toLocalInput(iso: string | null): string {
  if (!iso) return "";
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** Valor do input (hora local) -> ISO com fuso, para o servidor entender a mesma hora. */
const toIso = (local: string) => (local ? new Date(local).toISOString() : null);

/**
 * Criar ou editar missão: escolhe a condição do catálogo, ajusta os números, escolhe os
 * prêmios de recompensa, se é secreta (com dica), se está ativa e o período.
 */
export function MissionForm({
  mission,
  conditions,
  cosmetics,
}: {
  mission?: Mission;
  conditions: MissionCondition[];
  cosmetics: AdminCosmetic[];
}) {
  const router = useRouter();
  const editing = !!mission;
  const [name, setName] = useState(mission?.name ?? "");
  const [description, setDescription] = useState(mission?.description ?? "");
  const [conditionKey, setConditionKey] = useState(mission?.condition ?? conditions[0]?.key ?? "");
  const [params, setParams] = useState<Record<string, string>>(() =>
    Object.fromEntries(Object.entries(mission?.params ?? {}).map(([k, v]) => [k, String(v)])),
  );
  const [rewards, setRewards] = useState<string[]>(mission?.rewards.map((r) => r.key) ?? []);
  const [secret, setSecret] = useState(mission?.secret ?? true);
  const [hint, setHint] = useState(mission?.hint ?? "");
  const [active, setActive] = useState(mission?.active ?? true);
  const [startsAt, setStartsAt] = useState(toLocalInput(mission?.starts_at ?? null));
  const [endsAt, setEndsAt] = useState(toLocalInput(mission?.ends_at ?? null));
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const condition = conditions.find((c) => c.key === conditionKey);
  const paramValue = (name: string, fallback: number) => params[name] ?? String(fallback);

  // Prêmio desativado não é entregue: avisa e deixa ativar dali mesmo.
  const disabledRewards = cosmetics.filter((c) => rewards.includes(c.key) && !c.active);
  const [enabling, setEnabling] = useState(false);

  async function enableRewards() {
    setEnabling(true);
    try {
      await Promise.all(
        disabledRewards.map((c) => api(`/admin/cosmetics/${encodeURIComponent(c.key)}`, { method: "POST", body: { active: true } })),
      );
      router.refresh();
    } catch (e) {
      setMessage(e instanceof ApiError ? e.message : "Não deu para ativar os prêmios.");
    } finally {
      setEnabling(false);
    }
  }

  function toggleReward(key: string) {
    setRewards((list) => (list.includes(key) ? list.filter((k) => k !== key) : [...list, key]));
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    setMessage(null);

    const body = {
      name: name.trim(),
      description: description.trim() || null,
      condition: conditionKey,
      params: Object.fromEntries((condition?.params ?? []).map((p) => [p.name, Number(paramValue(p.name, p.default))])),
      rewards,
      secret,
      hint: hint.trim() || null,
      active,
      starts_at: toIso(startsAt),
      ends_at: toIso(endsAt),
    };

    try {
      const { meta } = await api<{ meta: { granted: number } }>(editing ? `/admin/missions/${mission.id}` : "/admin/missions", {
        method: editing ? "PUT" : "POST",
        body,
      });
      // Contador de títulos e afins premiam na hora quem já cumpria: a lista avisa quantos.
      router.push(meta.granted > 0 ? `/admin/missoes?liberados=${meta.granted}` : "/admin/missoes");
      router.refresh();
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage(Object.keys(e.errors).length ? "Confira os campos marcados." : e.message);
      } else {
        setMessage("Não deu certo. Tente de novo.");
      }
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    if (!mission || !confirm(`Apagar a missão ${mission.name}? Quem já cumpriu continua com o prêmio.`)) return;
    setPending(true);
    try {
      await api(`/admin/missions/${mission.id}`, { method: "DELETE" });
      router.push("/admin/missoes");
      router.refresh();
    } catch (e) {
      setMessage(e instanceof ApiError ? e.message : "Não deu certo.");
      setPending(false);
    }
  }

  const err = (field: string) => errors[field]?.[0];

  return (
    <form onSubmit={onSubmit} noValidate className="flex max-w-4xl flex-col gap-8">
      <section className="flex flex-col gap-5">
        <Field label="Nome" name="name" value={name} onChange={(e) => setName(e.target.value)} maxLength={60} required error={err("name")} />
        <Field
          label="Descrição (para a moderação)"
          name="description"
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          maxLength={200}
          error={err("description")}
        />
      </section>

      <section aria-labelledby="h-condicao" className="flex flex-col gap-4 rounded-2xl border-[3px] border-ink bg-panel p-5">
        <h2 id="h-condicao" className="font-display text-lg">CONDIÇÃO</h2>
        <div className="flex flex-col gap-1.5">
          <label htmlFor="f-condition" className="text-sm font-bold text-muted">
            O que o jogador precisa fazer
          </label>
          <select
            id="f-condition"
            value={conditionKey}
            onChange={(e) => {
              setConditionKey(e.target.value);
              setParams({});
            }}
            className="h-12 w-full rounded-xl border-2 border-ink bg-surface px-4 text-base font-semibold text-chalk outline-none focus:border-cyan"
          >
            {conditions.map((c) => (
              <option key={c.key} value={c.key}>
                {c.label}
              </option>
            ))}
          </select>
          {condition && <p className="text-sm text-muted">{condition.description}</p>}
          {err("condition") && <p className="text-sm font-bold text-danger">{err("condition")}</p>}
        </div>
        {condition && condition.params.length > 0 && (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {condition.params.map((p) => (
              <ParamInput
                key={p.name}
                param={p}
                value={paramValue(p.name, p.default)}
                onChange={(v) => setParams((list) => ({ ...list, [p.name]: v }))}
                error={err(`params.${p.name}`)}
              />
            ))}
          </div>
        )}
      </section>

      <section aria-labelledby="h-recompensas" className="flex flex-col gap-4">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div className="flex flex-col gap-1">
            <h2 id="h-recompensas" className="font-display text-lg">RECOMPENSAS</h2>
            <p className={`text-sm ${err("rewards") ? "font-bold text-danger" : "text-muted"}`}>
              {err("rewards") ?? "Marque os prêmios que a missão libera. Quem cumprir ganha todos."}
            </p>
          </div>
          <Link href="/admin/premios/novo" target="_blank" className="text-sm font-bold text-cyan underline">
            Criar prêmio novo ↗
          </Link>
        </div>
        {disabledRewards.length > 0 && (
          <div role="status" className="flex flex-wrap items-center justify-between gap-3 rounded-xl border-2 border-yellow bg-yellow/10 p-3">
            <p className="text-sm font-bold text-yellow">
              {disabledRewards.map((c) => c.name).join(", ")} {disabledRewards.length === 1 ? "está desativado e não será entregue" : "estão desativados e não serão entregues"}.
            </p>
            <button type="button" onClick={enableRewards} disabled={enabling} className={buttonClass("primary", "md", "h-10 px-4 text-sm")}>
              {enabling ? "Ativando…" : "Ativar agora"}
            </button>
          </div>
        )}
        {slots.map((slot) => {
          const items = cosmetics.filter((c) => c.slot === slot);
          if (items.length === 0) return null;
          return (
            <fieldset key={slot} className="flex flex-col gap-2">
              <legend className="mb-2 text-sm font-bold text-muted">{slotLabel[slot]}</legend>
              <div className="grid grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-3">
                {items.map((item) => {
                  const checked = rewards.includes(item.key);
                  return (
                    <label
                      key={item.key}
                      className={`flex cursor-pointer flex-col gap-2 rounded-xl border-[3px] bg-panel p-3 ${
                        checked ? "border-lime shadow-hard-sm" : "border-ink"
                      } ${item.active ? "" : "opacity-60"}`}
                    >
                      <CosmeticPreview cosmetic={item} />
                      <span className="flex items-center gap-2">
                        <input type="checkbox" checked={checked} onChange={() => toggleReward(item.key)} className="size-5 accent-lime" />
                        <span className="min-w-0 flex-1 truncate font-extrabold">{item.name}</span>
                      </span>
                      <span className="text-xs text-subtle">
                        {rarityLabel[item.rarity]}
                        {!item.active && " · desativado"}
                      </span>
                    </label>
                  );
                })}
              </div>
            </fieldset>
          );
        })}
      </section>

      <section aria-labelledby="h-regras" className="flex flex-col gap-4 rounded-2xl border-[3px] border-ink bg-panel p-5">
        <h2 id="h-regras" className="font-display text-lg">VISIBILIDADE E PERÍODO</h2>
        <label className="flex items-center gap-3 font-bold">
          <input type="checkbox" checked={secret} onChange={(e) => setSecret(e.target.checked)} className="size-5 accent-lime" />
          Missão secreta
          <span className="text-sm font-normal text-subtle">Antes de cumprir, o jogador só vê “???” e a dica.</span>
        </label>
        {secret && (
          <Field
            label="Dica"
            name="hint"
            value={hint}
            onChange={(e) => setHint(e.target.value)}
            maxLength={120}
            placeholder="Forme amizades consistentes."
            error={err("hint")}
            hint="Vaga de propósito: aponta o caminho sem entregar."
          />
        )}
        <label className="flex items-center gap-3 font-bold">
          <input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} className="size-5 accent-lime" />
          Ativa
        </label>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="Começa em (opcional)"
            name="starts_at"
            type="datetime-local"
            value={startsAt}
            onChange={(e) => setStartsAt(e.target.value)}
            error={err("starts_at")}
            hint="Vazio: já vale."
          />
          <Field
            label="Termina em (opcional)"
            name="ends_at"
            type="datetime-local"
            value={endsAt}
            onChange={(e) => setEndsAt(e.target.value)}
            error={err("ends_at")}
            hint="Vazio: não acaba."
          />
        </div>
        <p className="text-sm text-subtle">A missão vale dali para frente: é avaliada nos próximos jogos, rodadas, ligas e amizades.</p>
      </section>

      {message && (
        <p role="alert" className="font-bold text-danger">
          {message}
        </p>
      )}

      <div className="flex flex-wrap gap-3">
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Salvando…" : editing ? "Salvar missão" : "Criar missão"}
        </button>
        {editing && (
          <button type="button" onClick={remove} disabled={pending} className={buttonClass("ghost")}>
            Apagar missão
          </button>
        )}
      </div>
    </form>
  );
}

/** Um parâmetro da condição: número livre, uma das opções ou uma liga (0 = qualquer). */
function ParamInput({
  param: p,
  value,
  onChange,
  error,
}: {
  param: ConditionParam;
  value: string;
  onChange: (value: string) => void;
  error?: string;
}) {
  if (p.type === "league") {
    return (
      <LeagueParamPicker
        id={`param-${p.name}`}
        label={p.label}
        help={p.help}
        value={Number(value)}
        onChange={(v) => onChange(String(v))}
        error={error}
      />
    );
  }

  if (p.type === "choice") {
    const id = `param-${p.name}`;
    return (
      <div className="flex flex-col gap-1.5">
        <label htmlFor={id} className="text-sm font-bold text-muted">
          {p.label}
        </label>
        <select
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? `${id}-erro` : undefined}
          className="h-12 rounded-xl border-2 border-ink bg-surface px-3 font-semibold text-chalk focus:border-cyan"
        >
          {p.options?.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        {error ? (
          <p id={`${id}-erro`} className="text-sm font-bold text-danger">
            {error}
          </p>
        ) : (
          p.help && <p className="text-sm text-subtle">{p.help}</p>
        )}
      </div>
    );
  }

  return (
    <Field
      label={p.label}
      name={`param-${p.name}`}
      type="number"
      inputMode="numeric"
      min={p.min}
      max={p.max}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      error={error}
      hint={`${p.help ? `${p.help} ` : ""}De ${p.min} a ${p.max}.`}
    />
  );
}

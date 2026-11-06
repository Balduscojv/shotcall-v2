"use client";

import { useRouter } from "next/navigation";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { api, ApiError, type FieldErrors } from "@/lib/api";
import { rarityLabel, rarityStyle, slotLabel, slots } from "@/lib/labels";
import type { AdminCosmetic, CosmeticSlot, Rarity } from "@/lib/types";
import { CosmeticPreview } from "@/components/cosmetics/CosmeticPreview";
import { Field } from "@/components/ui/Field";
import { buttonClass } from "@/components/ui/button";

const rarities: Rarity[] = ["COMMON", "RARE", "EPIC", "LEGENDARY"];
const swatches = ["#c6ff3d", "#22d3ee", "#ff3d7f", "#ff5a1f", "#ffc83d", "#8b5cf6", "#f6f4ee", "#0a0a0a"];

/** O que cada tipo usa como visual. */
const slotOption: Record<CosmeticSlot, string> = {
  BANNER: "Fundo do perfil · imagem ou GIF",
  FRAME: "Contorno da foto · cor",
  TITLE: "Etiqueta ao lado do @ · cor",
};

/**
 * Criar ou editar um prêmio. Banner: imagem enviada. Moldura e título: uma cor.
 * Prêmios com arte própria (os de boas-vindas, Clutch...) mantêm a arte se a cor ficar vazia.
 */
export function CosmeticForm({ cosmetic }: { cosmetic?: AdminCosmetic }) {
  const router = useRouter();
  const editing = !!cosmetic;
  const [name, setName] = useState(cosmetic?.name ?? "");
  const [description, setDescription] = useState(cosmetic?.description ?? "");
  const [slot, setSlot] = useState<CosmeticSlot>(cosmetic?.slot ?? "TITLE");
  const [rarity, setRarity] = useState<Rarity>(cosmetic?.rarity ?? "RARE");
  const [color, setColor] = useState(cosmetic?.color ?? (editing ? "" : "#22d3ee"));
  const [active, setActive] = useState(cosmetic?.active ?? true);
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  // URL da prévia da imagem escolhida; a anterior é liberada ao trocar e ao sair da página.
  const previewUrl = useRef<string | null>(null);
  useEffect(() => () => {
    if (previewUrl.current) URL.revokeObjectURL(previewUrl.current);
  }, []);

  function pickFile(next: File | null) {
    if (previewUrl.current) URL.revokeObjectURL(previewUrl.current);
    previewUrl.current = next ? URL.createObjectURL(next) : null;
    setFile(next);
    setPreview(previewUrl.current);
  }

  const look = {
    name: name || "Nome do prêmio",
    slot,
    rarity,
    asset_key: cosmetic?.asset_key ?? null,
    color: slot === "BANNER" ? null : color || null,
    image_url: slot === "BANNER" ? (preview ?? cosmetic?.image_url ?? null) : null,
  };

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    setMessage(null);

    const body = new FormData();
    body.set("name", name.trim());
    body.set("description", description.trim());
    body.set("rarity", rarity);
    body.set("active", active ? "1" : "0");
    if (!editing) body.set("slot", slot);
    if (slot !== "BANNER") body.set("color", color);
    if (file) body.set("image", file);

    try {
      const path = editing ? `/admin/cosmetics/${encodeURIComponent(cosmetic.key)}` : "/admin/cosmetics";
      await api(path, { method: "POST", body });
      router.push("/admin/premios");
      router.refresh();
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage(Object.keys(e.errors).length ? null : e.message);
      } else {
        setMessage("Não deu certo. Tente de novo.");
      }
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    if (!cosmetic || !confirm(`Apagar ${cosmetic.name}? Não dá para desfazer.`)) return;
    setPending(true);
    try {
      await api(`/admin/cosmetics/${encodeURIComponent(cosmetic.key)}`, { method: "DELETE" });
      router.push("/admin/premios");
      router.refresh();
    } catch (e) {
      setMessage(e instanceof ApiError ? e.message : "Não deu certo.");
      setPending(false);
    }
  }

  const err = (field: string) => errors[field]?.[0];

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-8 lg:grid-cols-[1fr_380px]">
      <div className="flex flex-col gap-5">
        <Field label="Nome" name="name" value={name} onChange={(e) => setName(e.target.value)} maxLength={40} required error={err("name")} />

        <div className="flex flex-col gap-1.5">
          <label htmlFor="f-description" className="text-sm font-bold text-muted">
            Descrição <span className="font-normal text-subtle">(aparece no card do prêmio)</span>
          </label>
          <textarea
            id="f-description"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            maxLength={200}
            rows={2}
            className="rounded-xl border-2 border-ink bg-surface px-4 py-3 font-semibold text-chalk outline-none focus:border-cyan"
          />
        </div>

        <fieldset className="flex flex-col gap-2" disabled={editing}>
          <legend className="mb-2 text-sm font-bold text-muted">Tipo</legend>
          <div className="grid gap-2 sm:grid-cols-3">
            {slots.map((s) => (
              <label
                key={s}
                className={`flex cursor-pointer flex-col gap-0.5 rounded-xl border-[3px] p-3 has-disabled:cursor-not-allowed ${
                  slot === s ? "border-lime bg-panel shadow-hard-sm" : "border-ink bg-surface"
                } ${editing && slot !== s ? "opacity-50" : ""}`}
              >
                <span className="flex items-center gap-2 font-extrabold">
                  <input type="radio" name="slot" value={s} checked={slot === s} onChange={() => setSlot(s)} className="size-4 accent-lime" />
                  {slotLabel[s]}
                </span>
                <span className="text-xs text-subtle">{slotOption[s]}</span>
              </label>
            ))}
          </div>
          {editing && <p className="text-sm text-subtle">O tipo não muda depois de criado.</p>}
        </fieldset>

        <fieldset className="flex flex-col gap-2">
          <legend className="mb-2 text-sm font-bold text-muted">Raridade</legend>
          <div className="flex flex-wrap gap-2">
            {rarities.map((r) => (
              <label
                key={r}
                className={`inline-flex cursor-pointer items-center gap-2 rounded-full border-2 px-4 py-2 font-display text-xs ${
                  rarity === r ? `border-chalk ${rarityStyle[r]} ring-2 ring-cyan` : "border-ink bg-surface text-chalk"
                }`}
              >
                <input type="radio" name="rarity" value={r} checked={rarity === r} onChange={() => setRarity(r)} className="sr-only" />
                {rarityLabel[r].toUpperCase()}
              </label>
            ))}
          </div>
        </fieldset>

        {slot === "BANNER" ? (
          <div className="flex flex-col gap-1.5">
            <label htmlFor="f-image" className="text-sm font-bold text-muted">
              Imagem do banner {editing && <span className="font-normal text-subtle">(envie só se for trocar)</span>}
            </label>
            <input
              id="f-image"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              onChange={(e) => pickFile(e.target.files?.[0] ?? null)}
              className="text-sm font-semibold text-muted file:mr-3 file:h-11 file:rounded-full file:border-2 file:border-chalk file:bg-transparent file:px-4 file:font-extrabold file:text-chalk"
            />
            <p className={`text-sm ${err("image") ? "font-bold text-danger" : "text-subtle"}`}>
              {err("image") ??
                "Imagem (JPG, PNG, WebP) ou GIF animado, até 8 MB. Proporção 1200 × 280: a imagem é recortada; o GIF fica como veio e é ajustado na tela."}
            </p>
          </div>
        ) : (
          <fieldset className="flex flex-col gap-2">
            <legend className="text-sm font-bold text-muted">Cor {slot === "FRAME" ? "da moldura" : "da etiqueta"}</legend>
            <div className="flex flex-wrap items-center gap-2">
              {swatches.map((s) => (
                <button
                  key={s}
                  type="button"
                  onClick={() => setColor(s)}
                  aria-label={`Usar a cor ${s}`}
                  aria-pressed={color === s}
                  className={`size-9 rounded-full border-2 ${color === s ? "border-chalk ring-2 ring-cyan" : "border-ink"}`}
                  style={{ backgroundColor: s }}
                />
              ))}
              <input
                type="color"
                value={color || "#22d3ee"}
                onChange={(e) => setColor(e.target.value)}
                aria-label="Escolher outra cor"
                className="size-9 cursor-pointer rounded-full border-2 border-ink bg-transparent"
              />
              <code className="text-sm text-muted">{color || "arte própria"}</code>
              {editing && cosmetic?.asset_key && color && (
                <button type="button" onClick={() => setColor("")} className="text-sm font-bold text-cyan underline">
                  Voltar à arte própria
                </button>
              )}
            </div>
            {err("color") && <p className="text-sm font-bold text-danger">{err("color")}</p>}
          </fieldset>
        )}

        <label className="flex items-center gap-3 font-bold">
          <input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} className="size-5 accent-lime" />
          Ativo
          <span className="text-sm font-normal text-subtle">Desativado: ninguém mais ganha. Quem já tem continua usando.</span>
        </label>

        {message && (
          <p role="alert" className="font-bold text-danger">
            {message}
          </p>
        )}

        <div className="flex flex-wrap gap-3">
          <button type="submit" disabled={pending} className={buttonClass("primary")}>
            {pending ? "Salvando…" : editing ? "Salvar" : "Criar prêmio"}
          </button>
          {editing && cosmetic.owners_count === 0 && (
            <button type="button" onClick={remove} disabled={pending} className={buttonClass("ghost")}>
              Apagar prêmio
            </button>
          )}
        </div>
      </div>

      <aside className="flex flex-col gap-3 self-start rounded-2xl border-[3px] border-ink bg-panel p-4 lg:sticky lg:top-6">
        <span className="text-sm font-bold text-muted">Prévia</span>
        <CosmeticPreview cosmetic={look} />
        <span className="font-extrabold">{look.name}</span>
        <span className="text-sm text-subtle">
          {slotLabel[slot]} · {rarityLabel[rarity]}
        </span>
      </aside>
    </form>
  );
}

// Como cada prêmio é desenhado. A chave é o `asset_key` do catálogo (database/seeders/CosmeticSeeder.php).
// Prêmio criado pela moderação vem com imagem (banner) ou cor (moldura/título).
// Prêmio novo sem arte nem cor cai no visual padrão da raridade.
import type { CSSProperties } from "react";
import type { CosmeticLook, Rarity } from "./types";

type Asset = CosmeticLook | null | undefined;

/** Classes e estilo inline para aplicar no elemento. */
export type Look = { className: string; style?: CSSProperties };

/** Fundo do banner do perfil (classes de globals.css). */
const banners: Record<string, string> = {
  "welcome-banner": "banner-sunset",
  "welcome-banner-arena": "banner-arena",
  "welcome-banner-street": "banner-street",
};

/** Contorno da foto de perfil: uma cor de borda ou uma moldura desenhada de globals.css. */
const frames: Record<string, string> = {
  "welcome-frame": "frame-ball",
  "welcome-frame-parquet": "frame-parquet",
  "welcome-frame-paint": "frame-paint",
};

/** Etiqueta do título ao lado do @. */
const titles: Record<string, string> = {
  "welcome-title": "bg-lime text-ink",
  "welcome-title-ballhog": "bg-orange text-ink",
  "welcome-title-sixth": "bg-pink text-ink",
  // Dupla: as duas cores do arremesso de três.
  "mission-splash-brothers": "bg-linear-to-r from-cyan to-yellow text-ink",
};

// Visual padrão de quem não tem arte nem cor própria: a cor da raridade.
const bannerByRarity: Record<Rarity, string> = {
  COMMON: "banner-common",
  RARE: "banner-rare",
  EPIC: "banner-epic",
  LEGENDARY: "banner-legendary",
};

const frameByRarity: Record<Rarity, string> = {
  COMMON: "border-common",
  RARE: "border-rare",
  EPIC: "border-epic",
  LEGENDARY: "border-exclusive",
};

const titleByRarity: Record<Rarity, string> = {
  COMMON: "bg-common text-ink",
  RARE: "bg-rare text-ink",
  EPIC: "bg-epic text-ink",
  LEGENDARY: "bg-gold text-ink",
};

/** Sem moldura equipada: contorno discreto. */
export const NO_FRAME = "border-surface";

/** Texto escuro em cor clara, claro em cor escura (luminância relativa do WCAG). */
export function readableOn(hex: string): string {
  const [r, g, b] = [1, 3, 5].map((i) => {
    const c = Number.parseInt(hex.slice(i, i + 2), 16) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.179 ? "#0a0a0a" : "#f6f4ee";
}

/** Banner equipado; null = nenhum (o perfil mostra a imagem enviada ou a grade). */
export function bannerLook(item: Asset): Look | null {
  if (!item) return null;
  if (item.image_url) return { className: "bg-cover bg-center", style: { backgroundImage: `url("${item.image_url}")` } };
  return { className: (item.asset_key && banners[item.asset_key]) || bannerByRarity[item.rarity] };
}

export function frameLook(item: Asset): Look {
  if (!item) return { className: NO_FRAME };
  if (item.color) return { className: "", style: { borderColor: item.color } };
  return { className: (item.asset_key && frames[item.asset_key]) || frameByRarity[item.rarity] };
}

export function titleLook(item: Asset): Look {
  if (!item) return { className: "" };
  if (item.color) return { className: "", style: { backgroundColor: item.color, color: readableOn(item.color) } };
  return { className: (item.asset_key && titles[item.asset_key]) || titleByRarity[item.rarity] };
}

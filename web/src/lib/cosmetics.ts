// Como cada prêmio é desenhado. A chave é o `asset_key` do catálogo (database/seeders/CosmeticSeeder.php).
// Prêmio novo sem arte ainda cai no visual padrão da raridade.
import type { Rarity } from "./types";

type Asset = { asset_key: string | null; rarity: Rarity } | null | undefined;

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
};

const bannerByRarity: Record<Rarity, string> = {
  COMMON: "fence",
  RARE: "banner-rare",
  EPIC: "banner-epic",
  LEGENDARY: "banner-legendary",
};

const frameByRarity: Record<Rarity, string> = {
  COMMON: "border-chalk",
  RARE: "border-cyan",
  EPIC: "border-purple",
  LEGENDARY: "border-yellow",
};

const titleByRarity: Record<Rarity, string> = {
  COMMON: "bg-chalk text-ink",
  RARE: "bg-cyan text-ink",
  EPIC: "bg-purple text-ink",
  LEGENDARY: "bg-hype text-ink",
};

/** Sem moldura equipada: contorno discreto. */
export const NO_FRAME = "border-surface";

export function bannerClass(item: Asset): string | null {
  if (!item) return null;
  return (item.asset_key && banners[item.asset_key]) || bannerByRarity[item.rarity];
}

export function frameClass(item: Asset): string {
  if (!item) return NO_FRAME;
  return (item.asset_key && frames[item.asset_key]) || frameByRarity[item.rarity];
}

export function titleClass(item: Asset): string {
  if (!item) return "";
  return (item.asset_key && titles[item.asset_key]) || titleByRarity[item.rarity];
}

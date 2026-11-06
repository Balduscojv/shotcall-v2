import type { AccuracyMode, Rarity, Tier } from "./types";

export const tierLabel: Record<Tier, string> = {
  SILVER: "Prata",
  GOLD: "Ouro",
  DIAMOND: "Diamante",
  PERFECT: "Perfeito",
};

export const tierColor: Record<Tier, string> = {
  SILVER: "bg-muted",
  GOLD: "bg-yellow",
  DIAMOND: "bg-cyan",
  PERFECT: "bg-lime",
};

export const modeLabel: Record<AccuracyMode, string> = {
  GAME: "Jogos",
  WEEK: "Semana",
  SEASON: "Temporada",
  BRACKET: "Chave",
};

export const marginLabel: Record<string, string> = {
  M1_5: "por 1–5",
  M6_10: "por 6–10",
  M11_15: "por 11–15",
  M16_PLUS: "por 16+",
};

export const rarityLabel: Record<Rarity, string> = {
  COMMON: "Comum",
  RARE: "Raro",
  EPIC: "Épico",
  LEGENDARY: "Lendário",
};

export const rarityStyle: Record<Rarity, string> = {
  COMMON: "bg-surface text-chalk",
  RARE: "bg-cyan text-ink",
  EPIC: "bg-purple text-ink",
  LEGENDARY: "bg-hype text-ink",
};

export const slotLabel: Record<string, string> = {
  FRAME: "Moldura",
  BANNER: "Banner",
  TITLE: "Título",
  SHOWCASE: "Vitrine",
  BALL: "Bola",
  COURT: "Quadra",
  CELEBRATION: "Comemoração",
  CARD_FRAME: "Moldura de card",
  CESTINHA_FX: "Efeito do Cestinha",
  CREST_TAG: "Selo",
};

const dateFmt = new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "short", timeZone: "America/Sao_Paulo" });
const monthYearFmt = new Intl.DateTimeFormat("pt-BR", { month: "long", year: "numeric", timeZone: "America/Sao_Paulo" });

export const formatDay = (iso: string) => dateFmt.format(new Date(iso)).replace(".", "");
export const formatMonthYear = (iso: string) => monthYearFmt.format(new Date(iso));
export const formatRate = (rate: number) => `${Math.round(rate * 100)}%`;

import type { AccuracyMode, CosmeticSlot, Rarity, Tier, UserRole } from "./types";

export const roleLabel: Record<UserRole, string> = {
  admin: "Admin",
  moderator: "Moderador",
  player: "Jogador",
};

export const roleStyle: Record<UserRole, string> = {
  admin: "bg-orange text-ink",
  moderator: "bg-cyan text-ink",
  player: "bg-surface text-chalk",
};

export const roles = Object.keys(roleLabel) as UserRole[];

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

export const slotLabel: Record<CosmeticSlot, string> = {
  BANNER: "Banner",
  FRAME: "Moldura",
  TITLE: "Título",
};

export const slotHint: Record<CosmeticSlot, string> = {
  BANNER: "Fundo do topo do perfil",
  FRAME: "Contorno da foto de perfil",
  TITLE: "Aparece ao lado do seu @",
};

export const slots: CosmeticSlot[] = ["BANNER", "FRAME", "TITLE"];

/** De onde o prêmio veio (o `source` do catálogo). */
export const sourceLabel: Record<string, string> = {
  SIGNUP: "Boas-vindas",
  SPECIAL: "Exclusivo",
  GAME: "Palpites de jogo",
  WEEK: "Cartela da semana",
  SEASON: "Temporada",
  BRACKET: "Chave dos playoffs",
  LEAGUE: "Liga",
};

export const triggerLabel = (trigger: string | null | undefined) =>
  !trigger ? "Só pela moderação" : trigger === "SIGNUP" ? "Ao criar a conta" : trigger;

const dateFmt = new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "short", timeZone: "America/Sao_Paulo" });
const monthYearFmt = new Intl.DateTimeFormat("pt-BR", { month: "long", year: "numeric", timeZone: "America/Sao_Paulo" });

export const formatDay = (iso: string) => dateFmt.format(new Date(iso)).replace(".", "");
export const formatMonthYear = (iso: string) => monthYearFmt.format(new Date(iso));
export const formatRate = (rate: number) => `${Math.round(rate * 100)}%`;

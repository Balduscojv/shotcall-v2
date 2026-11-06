// Textos e regras de exibição do shot call de jogo.
import type { MarginBand, ShotCallMarket, Tier } from "./types";

export const bands: MarginBand[] = ["M1_5", "M6_10", "M11_15", "M16_PLUS"];

export const bandLabel: Record<MarginBand, string> = {
  M1_5: "1–5",
  M6_10: "6–10",
  M11_15: "11–15",
  M16_PLUS: "16+",
};

export const marketLabel: Record<ShotCallMarket, string> = { winner: "Vencedor", margin: "Margem", mvp: "MVP do jogo" };
export const marketPoints: Record<ShotCallMarket, number> = { winner: 1, margin: 2, mvp: 3 };

/** Níveis do modo jogo (app/Services/ShotCalls/TierPolicy.php). */
const tierSteps: { tier: Tier; rate: number }[] = [
  { tier: "SILVER", rate: 0.7 },
  { tier: "GOLD", rate: 0.8 },
  { tier: "DIAMOND", rate: 0.9 },
  { tier: "PERFECT", rate: 1 },
];

/** Próximo nível acima da taxa atual, e quantos pontos percentuais faltam. */
export function nextTier(rate: number): { tier: Tier; gap: number } | null {
  const next = tierSteps.find((s) => rate < s.rate);
  return next ? { tier: next.tier, gap: Math.ceil((next.rate - rate) * 100) } : null;
}

const avgFmt = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
export const formatAverage = (avg: number | null) => (avg === null ? "sem jogos ainda" : `${avgFmt.format(avg)} pts fantasy/jogo`);

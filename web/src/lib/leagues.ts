// Textos e regras de exibição das ligas (app/Services/Leagues no backend).
import type { Competition, LeagueRole, StandingsBoard, StandingsPeriod } from "./types";

export const roleLabel: Record<LeagueRole, string> = { OWNER: "Dono", ADMIN: "Admin", MEMBER: "Membro" };
export const competitionLabel: Record<Competition, string> = { FANTASY: "Fantasy", SHOTCALL: "Shot calls" };
export const boardLabel: Record<StandingsBoard, string> = { fantasy: "Fantasy", patrimony: "Patrimônio", shotcall: "Shot calls" };
export const periodLabel: Record<StandingsPeriod, string> = { round: "Rodada", month: "Mês", season: "Temporada" };

/** Cores do escudo: as da marca (globals.css). */
export const crestColors = ["#FF5A1F", "#FF3D7F", "#8B5CF6", "#22D3EE", "#C6FF3D", "#FFC83D", "#10B981", "#F6F4EE"];

/** "Liga do Rachão" → "LR". Ignora preposições curtas ("do", "da"). */
export function crestInitials(name: string): string {
  const words = name.split(/\s+/).filter((w) => w.length > 2 || /^[A-Z0-9]/.test(w));
  return (words.length ? words : name.split(/\s+/))
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? "")
    .join("");
}

/** Texto preto ou branco no escudo, o que tiver mais contraste com a cor. */
export function crestInk(hex: string): string {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
  const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
  return lum > 0.4 ? "#0A0A0A" : "#F6F4EE";
}

const one = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const money = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Pontuação na unidade do quadro: "212,4", "F$ 104,50" ou "38". */
export function formatScore(board: StandingsBoard, score: number | null): string {
  if (score === null) return "—";
  if (board === "patrimony") return `F$ ${money.format(score)}`;
  if (board === "shotcall") return String(Math.round(score));
  return one.format(score);
}

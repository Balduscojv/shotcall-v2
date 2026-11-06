// Formatação e regras de exibição do fantasy (config/fantasy.php no backend).
import type { FantasyPosition, FantasySlot, PlayerRole } from "./types";

/** Vagas na quadra: um jogador de cada posição (config/fantasy.php, lineup). */
export const slots: FantasySlot[] = ["PG", "SG", "SF", "PF", "C"];
export const positionLabel: Record<FantasyPosition, string> = { G: "Armador", F: "Ala", C: "Pivô" };
export const positionPlural: Record<FantasyPosition, string> = { G: "Armadores", F: "Alas", C: "Pivôs" };

export const roleLabel: Record<PlayerRole, string> = { PG: "Armador", SG: "Ala-armador", SF: "Ala", PF: "Ala-pivô", C: "Pivô" };
/** Sigla do jogador: a detalhada, ou a genérica se ainda não tiver. */
export const roleOf = (p: { role: PlayerRole | null; position: FantasyPosition }): PlayerRole =>
  p.role ?? (p.position === "G" ? "SG" : p.position === "F" ? "SF" : "C");

const money = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const one = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 });

/** "F$ 11,60" */
export const fmoney = (v: number) => `F$ ${money.format(v)}`;
/** "▲ 1,20" / "▼ 0,40" / "" */
export const fvariation = (v: number) => (v > 0 ? `▲ ${money.format(v)}` : v < 0 ? `▼ ${money.format(-v)}` : "");
/** "38,4" */
export const fpoints = (v: number) => one.format(v);

const dayFmt = new Intl.DateTimeFormat("pt-BR", { day: "numeric", month: "long", timeZone: "America/New_York" });
/** "9 a 15 de novembro" (dias da NBA, horário de Nova York) */
export function roundSpan(startsAt: string, endsAt: string): string {
  const [a, b] = [dayFmt.format(new Date(startsAt)), dayFmt.format(new Date(endsAt))];
  const [da, ma] = a.split(" de ");
  const [db, mb] = b.split(" de ");
  return ma === mb ? `${da} a ${db} de ${mb}` : `${a} a ${b}`;
}

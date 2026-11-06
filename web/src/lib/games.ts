// Formatação de jogos. O dia do calendário é o da NBA (Nova York); os horários aparecem em Brasília.
import type { Game, Team } from "./types";

const BR = "America/Sao_Paulo";

const timeFmt = new Intl.DateTimeFormat("pt-BR", { hour: "2-digit", minute: "2-digit", timeZone: BR });
const brDateFmt = new Intl.DateTimeFormat("en-CA", { timeZone: BR }); // AAAA-MM-DD
const weekdayFmt = new Intl.DateTimeFormat("pt-BR", { weekday: "short", timeZone: "UTC" });
const longDayFmt = new Intl.DateTimeFormat("pt-BR", { weekday: "long", day: "numeric", month: "long", timeZone: "UTC" });

/** "2026-11-06" vira uma data ao meio-dia UTC, para o dia da semana não escorregar de fuso. */
const asDate = (day: string) => new Date(`${day}T12:00:00Z`);

/** "SEX" */
export const weekdayShort = (day: string) => weekdayFmt.format(asDate(day)).replace(".", "").toUpperCase();

/** "06" */
export const dayOfMonth = (day: string) => day.slice(8, 10);

/** "sexta-feira, 6 de novembro" */
export const longDay = (day: string) => longDayFmt.format(asDate(day));

/**
 * Horário de início em Brasília. Se em Brasília já é o dia seguinte ao da noite da NBA
 * (jogo das 22h30 em Nova York), avisa: "00:30 · sáb".
 */
export function tipoff(game: Pick<Game, "start_time">, nbaDay?: string): string {
  const start = new Date(game.start_time);
  const time = timeFmt.format(start);
  const brDay = brDateFmt.format(start);
  if (nbaDay && brDay !== nbaDay) return `${time} · ${weekdayShort(brDay).toLowerCase()}`;
  return time;
}

/** Nome do período: 1º–4º quarto, depois prorrogações. */
export function periodLabel(period: number, short = true): string {
  if (period <= 4) return short ? `Q${period}` : `${period}º quarto`;
  const ot = period - 4;
  return short ? (ot === 1 ? "PR" : `PR${ot}`) : ot === 1 ? "Prorrogação" : `${ot}ª prorrogação`;
}

/** Linha de status do card: "Q4 03:29", "FINAL", "FINAL/PR", "21:00", "ADIADO". */
export function statusLine(game: Game, nbaDay?: string): string {
  switch (game.status) {
    case "LIVE":
      return game.clock ? `${periodLabel(game.period)} ${game.clock}` : periodLabel(game.period);
    case "FINAL":
      return game.period > 4 ? `FINAL/${periodLabel(game.period)}` : "FINAL";
    case "POSTPONED":
      return "ADIADO";
    default:
      return tipoff(game, nbaDay);
  }
}

export const hasScore = (game: Game) => game.status === "LIVE" || game.status === "FINAL";

/** Quem está na frente (ou venceu); null se empatado ou sem placar. */
export function leader(game: Game): "home" | "away" | null {
  if (!hasScore(game) || game.home_score === game.away_score) return null;
  return game.home_score > game.away_score ? "home" : "away";
}

/** Faixa com as cores dos dois times, como no canvas: visitante à esquerda, mandante à direita. */
export const matchupGradient = (game: Pick<Game, "home" | "away">) =>
  `linear-gradient(135deg, ${game.away.primary_color} 0 50%, ${game.home.primary_color} 50% 100%)`;

/** Luminância relativa (0 = preto, 1 = branco) de um "#RRGGBB". */
function luminance(hex: string): number {
  const [r, g, b] = [1, 3, 5].map((i) => {
    const c = Number.parseInt(hex.slice(i, i + 2), 16) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * Cor do time que aparece bem no fundo escuro: a principal, ou a secundária quando a principal
 * é escura demais (Nets preto, Wolves e Pelicans azul-marinho...).
 */
export function teamAccent(team: Pick<Team, "primary_color" | "secondary_color">): string {
  if (luminance(team.primary_color) >= 0.06) return team.primary_color;
  return luminance(team.secondary_color) >= 0.06 ? team.secondary_color : "#9AA0B2";
}

/** Cor do texto em cima de teamAccent: escuro nas cores claras (dourado do Warriors, prata do Spurs...). */
export const onTeamAccent = (team: Pick<Team, "primary_color" | "secondary_color">) =>
  luminance(teamAccent(team)) > 0.35 ? "var(--color-ink)" : "var(--color-chalk)";

/** "45%" ou "—" */
export const pct = (made: number, attempted: number) => (attempted > 0 ? `${Math.round((made / attempted) * 100)}%` : "—");

import Link from "next/link";
import { hasScore, leader, matchupGradient, statusLine } from "@/lib/games";
import type { Game } from "@/lib/types";
import { LiveBadge } from "./LiveBadge";

/** Situação do meu palpite no rodapé do card. */
function ShotCallTag({ game }: { game: Game }) {
  const call = game.my_shot_call;
  const base = "shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-extrabold";
  if (call?.status === "SETTLED") {
    return (
      <span className={`${base} bg-lime text-ink`}>
        {call.correct}/3 · {call.points} pts
      </span>
    );
  }
  if (call) return <span className={`${base} bg-cyan text-ink`}>{game.status === "SCHEDULED" ? "Palpite cravado" : "Palpite travado"}</span>;
  if (game.status === "SCHEDULED") return <span className={`${base} border-2 border-lime text-lime group-hover:bg-lime group-hover:text-ink`}>Palpitar →</span>;
  return <span className="shrink-0 text-cyan group-hover:text-lime">{game.status === "LIVE" ? "Acompanhar" : "Ver jogo"} →</span>;
}

/** Card do calendário: faixa com as cores dos dois times, placar (ou horário) e onde passa. */
export function GameCard({ game, nbaDay }: { game: Game; nbaDay: string }) {
  const live = game.status === "LIVE";
  const ahead = leader(game);
  const scored = hasScore(game);

  const side = (which: "away" | "home") => {
    const team = game[which];
    const score = which === "home" ? game.home_score : game.away_score;
    return (
      <div className={`flex min-w-0 flex-col gap-0.5 ${which === "home" ? "items-end text-right" : "items-start"}`}>
        <span className="font-display text-xl leading-none drop-shadow-[2px_2px_0_var(--color-ink)]">{team.tricode}</span>
        <span className="truncate text-xs font-bold text-chalk/85">{team.name}</span>
        {scored && (
          <span
            className={`font-score text-5xl leading-none tabular-nums drop-shadow-[3px_3px_0_var(--color-ink)] ${
              ahead === which ? "text-yellow" : "text-chalk"
            }`}
          >
            {score}
          </span>
        )}
      </div>
    );
  };

  const where = [game.arena, game.national_tv].filter(Boolean).join(" · ");
  const label = `${game.away.city} ${game.away.name} ${scored ? game.away_score : ""} x ${scored ? game.home_score : ""} ${game.home.city} ${game.home.name}, ${statusLine(game, nbaDay)}`;

  return (
    <li>
      <Link
        href={`/jogos/${game.id}`}
        aria-label={label.replace(/\s+/g, " ")}
        className={`group flex flex-col overflow-hidden rounded-2xl border-[3px] border-ink text-chalk no-underline transition-transform hover:-translate-y-0.5 ${
          live ? "shadow-hard-lg" : "shadow-hard"
        }`}
      >
        <div className="relative grid grid-cols-[1fr_auto_1fr] items-center gap-3 px-4 py-4" style={{ background: matchupGradient(game) }}>
          {/* Escurece um pouco: algumas cores de time são claras demais para texto claro. */}
          <span className="absolute inset-0 bg-ink/25" aria-hidden="true" />
          <div className="relative">{side("away")}</div>
          <div className="relative flex flex-col items-center gap-1.5">
            {live ? (
              <LiveBadge size="sm" />
            ) : (
              game.status !== "SCHEDULED" && (
                <span className="rounded-full bg-ink/60 px-2 py-0.5 text-[11px] font-extrabold tracking-wide">
                  {statusLine(game, nbaDay)}
                </span>
              )
            )}
            <span className="font-score text-xl tabular-nums">
              {live || game.status === "SCHEDULED" ? statusLine(game, nbaDay) : "×"}
            </span>
          </div>
          <div className="relative">{side("home")}</div>
        </div>
        <div className="flex items-center justify-between gap-2 bg-panel px-4 py-2 text-xs font-bold text-muted">
          <span className="truncate">{where || "Local a confirmar"}</span>
          <ShotCallTag game={game} />
        </div>
      </Link>
    </li>
  );
}

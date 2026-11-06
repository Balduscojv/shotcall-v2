import Link from "next/link";
import { boardLabel, formatScore, periodLabel } from "@/lib/leagues";
import type { StandingRow, Standings as StandingsData, StandingsBoard, StandingsPeriod } from "@/lib/types";
import { LiveBadge } from "@/components/games/LiveBadge";
import { Avatar } from "@/components/profile/Avatar";

/** A partir de quantas linhas a minha posição ganha a barra fixa embaixo. */
const STICKY_FROM = 8;

type Query = { board: StandingsBoard; period: StandingsPeriod; round?: number };

const href = (slug: string, q: Query) => {
  const params = new URLSearchParams({ board: q.board, period: q.period });
  if (q.round) params.set("rodada", String(q.round));
  return `/ligas/${slug}?${params}`;
};

/** Ranking da liga (canvas App-Liga): quadro, período, pódio, lista e a minha posição. */
export function Standings({ slug, standings }: { slug: string; standings: StandingsData }) {
  const { data: rows, meta } = standings;
  const { board, period, round } = meta;
  const showPeriods = board !== "patrimony";

  const podium = rows.length >= 3 && rows[0].score !== null ? rows.slice(0, 3) : [];
  const rest = rows.slice(podium.length);
  const meVisible = rows.some((r) => r.is_me);
  // A barra fixa com a minha posição só ajuda quando a lista é longa ou eu não estou nela; no
  // pódio eu já apareço em destaque.
  const onPodium = podium.some((r) => r.is_me);
  const showMeBar = meta.me !== null && !onPodium && (!meVisible || rows.length > STICKY_FROM);

  const scope =
    !round ? null
    : board === "patrimony" ? `Patrimônio na rodada ${round.number}`
    : period === "round" ? `Rodada ${round.number}`
    : period === "month" ? `Mês de ${round.month}, até a rodada ${round.number}`
    : `Temporada, até a rodada ${round.number}`;

  return (
    <section aria-labelledby="h-ranking" className="flex flex-col gap-4">
      <h2 id="h-ranking" className="sr-only">Ranking</h2>

      {meta.boards.length > 1 && (
        <nav aria-label="Ranking" className="grid gap-1.5 rounded-full border-2 border-surface bg-panel p-1.5" style={{ gridTemplateColumns: `repeat(${meta.boards.length}, minmax(0, 1fr))` }}>
          {meta.boards.map((b) => (
            <Link
              key={b}
              href={href(slug, { board: b, period })}
              aria-current={b === board ? "page" : undefined}
              className={`flex h-10 items-center justify-center rounded-full text-sm font-extrabold no-underline ${
                b === board ? "border-2 border-ink bg-purple text-ink" : "text-muted hover:text-chalk"
              }`}
            >
              {boardLabel[b]}
            </Link>
          ))}
        </nav>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        {showPeriods ? (
          <nav aria-label="Período" className="flex gap-2">
            {(["round", "month", "season"] as const).map((p) => (
              <Link
                key={p}
                href={href(slug, { board, period: p })}
                aria-current={p === period ? "page" : undefined}
                className={`inline-flex h-9 items-center rounded-full px-4 text-sm font-extrabold no-underline ${
                  p === period ? "border-2 border-chalk text-chalk" : "bg-surface text-muted hover:text-chalk"
                }`}
              >
                {periodLabel[p]}
              </Link>
            ))}
          </nav>
        ) : (
          <span />
        )}
        {round && period === "round" && (
          <RoundStepper slug={slug} board={board} period={period} number={round.number} rounds={meta.rounds} />
        )}
      </div>
      {scope && (
        <div className="-mt-1 flex flex-wrap items-center gap-2">
          <p className="text-sm text-subtle">{scope}</p>
          {round?.live ? (
            <LiveBadge size="sm" />
          ) : round?.provisional ? (
            <span className="rounded-lg bg-surface px-2 py-0.5 text-xs font-extrabold text-muted">PARCIAL</span>
          ) : null}
        </div>
      )}
      {round?.provisional && (
        <p className="-mt-2 text-xs text-subtle">
          {board === "shotcall"
            ? "A rodada ainda não foi apurada: conta os palpites já apurados e, nos jogos rolando, quanto valeriam se acabassem agora."
            : "A rodada ainda não foi apurada: conta os jogos encerrados e os que estão rolando, com o boost dos palpites já apurados."}
        </p>
      )}

      {rows.length === 0 ? (
        <p className="rounded-2xl border-2 border-dashed border-surface p-6 text-muted">Ninguém pontuou ainda.</p>
      ) : (
        <>
          {podium.length === 3 && <Podium rows={podium} board={board} />}
          {rest.length > 0 && (
            <ol aria-label="Classificação" className="flex flex-col gap-1.5" start={rest[0].rank}>
              {rest.map((row) => (
                <RankRow key={row.user.id} row={row} board={board} />
              ))}
            </ol>
          )}
          {meta.total > rows.length && (
            <p className="text-sm text-subtle">Mostrando os {rows.length} primeiros de {meta.total.toLocaleString("pt-BR")}.</p>
          )}
        </>
      )}

      {showMeBar && meta.me && (
        <div className="sticky bottom-4 z-10">
          <RankRow row={meta.me} board={board} highlight label={meVisible ? "Você" : `Você · ${meta.me.rank}º de ${meta.total}`} />
        </div>
      )}
    </section>
  );
}

function RoundStepper({ slug, board, period, number, rounds }: Query & { number: number; rounds: number[]; slug: string }) {
  const i = rounds.indexOf(number);
  const prev = rounds[i - 1];
  const next = rounds[i + 1];
  const arrow = "inline-flex size-9 items-center justify-center rounded-full bg-surface font-extrabold text-chalk no-underline hover:bg-chalk hover:text-ink";

  return (
    <div className="flex items-center gap-2">
      {prev ? (
        <Link href={href(slug, { board, period, round: prev })} className={arrow} aria-label={`Rodada ${prev}`}>
          ‹
        </Link>
      ) : (
        <span className={`${arrow} opacity-30`} aria-hidden="true">‹</span>
      )}
      <span className="min-w-20 text-center text-sm font-extrabold">Rodada {number}</span>
      {next ? (
        <Link href={href(slug, { board, period, round: next })} className={arrow} aria-label={`Rodada ${next}`}>
          ›
        </Link>
      ) : (
        <span className={`${arrow} opacity-30`} aria-hidden="true">›</span>
      )}
    </div>
  );
}

const podiumLook = {
  1: { block: "h-28 bg-[linear-gradient(160deg,var(--color-yellow),var(--color-orange))]", ring: "#FFC83D", num: "text-4xl" },
  2: { block: "h-20 bg-[#C9D1DC]", ring: "#C9D1DC", num: "text-3xl" },
  3: { block: "h-16 bg-orange", ring: "#FF5A1F", num: "text-2xl" },
} as const;

function Podium({ rows, board }: { rows: StandingRow[]; board: StandingsBoard }) {
  // No HTML, 1º, 2º e 3º (ordem de leitura); na tela, 2º, 1º e 3º da esquerda para a direita.
  const visualOrder = [1, 0, 2];

  return (
    <ol aria-label="Pódio" className="relative flex items-end justify-center gap-2 pt-6 sm:gap-4">
      {rows.map((row, i) => {
        const place = (i + 1) as 1 | 2 | 3;
        const look = podiumLook[place];
        return (
          <li key={row.user.id} className="flex w-28 flex-col items-center gap-1.5 sm:w-36" style={{ order: visualOrder[i] }} value={row.rank}>
            {place === 1 && (
              <svg width="30" height="22" viewBox="0 0 40 30" aria-hidden="true">
                <path d="M4 28h32M6 28 3 6l10 8 7-12 7 12 10-8-3 22" fill="#FFC83D" stroke="#0A0A0A" strokeWidth="2.5" strokeLinejoin="round" />
              </svg>
            )}
            <Link href={`/u/${row.user.handle}`} className="flex max-w-full flex-col items-center gap-1 text-chalk no-underline">
              <span className="rounded-full p-0.5" style={{ background: look.ring }}>
                <Avatar user={row.user} size={place === 1 ? "md" : "sm"} />
              </span>
              <strong className={`max-w-full truncate text-sm ${row.is_me ? "text-lime" : ""}`}>
                {row.is_me ? "Você" : `@${row.user.handle}`}
              </strong>
            </Link>
            <div className={`flex w-full flex-col items-center justify-center rounded-t-xl border-2 border-ink text-ink ${look.block}`}>
              <span className={`font-score leading-none ${look.num}`}>
                <span className="sr-only">{row.rank}º lugar, </span>
                <span aria-hidden="true">{row.rank}</span>
              </span>
              <span className="text-xs font-extrabold">{formatScore(board, row.score)}</span>
            </div>
          </li>
        );
      })}
    </ol>
  );
}

/** Subiu/caiu desde a rodada anterior. Na barra limão ("Você"), tudo em preto. */
function Move({ move, onLime }: { move: number | null; onLime: boolean }) {
  const base = "w-8 text-center text-xs font-extrabold";
  if (move === null) return <span className="w-8" />;
  if (move === 0) return <span className={`${base} ${onLime ? "" : "text-subtle"}`}>–<span className="sr-only"> manteve a posição</span></span>;
  return move > 0 ? (
    <span className={`${base} ${onLime ? "" : "text-lime"}`}>▲{move}<span className="sr-only"> subiu</span></span>
  ) : (
    <span className={`${base} ${onLime ? "" : "text-pink"}`}>▼{-move}<span className="sr-only"> caiu</span></span>
  );
}

function RankRow({ row, board, highlight = false, label }: { row: StandingRow; board: StandingsBoard; highlight?: boolean; label?: string }) {
  const Tag = highlight ? "div" : "li";
  return (
    <Tag
      className={`flex items-center gap-3 rounded-2xl px-3 py-2.5 ${
        highlight ? "border-2 border-ink bg-lime text-ink shadow-hard-sm" : row.is_me ? "bg-surface ring-2 ring-lime" : "bg-panel"
      }`}
    >
      <span className="w-8 font-score text-xl">{row.rank}</span>
      <Avatar user={row.user} size="xs" />
      <Link
        href={`/u/${row.user.handle}`}
        className={`min-w-0 flex-1 truncate text-sm font-extrabold no-underline ${highlight ? "text-ink hover:text-ink" : "text-chalk"}`}
      >
        {label ?? (row.is_me ? "Você" : <>{row.user.name} <span className={highlight ? "" : "text-cyan"}>@{row.user.handle}</span></>)}
      </Link>
      <Move move={row.move} onLime={highlight} />
      <span className="min-w-16 text-right font-score text-xl">{formatScore(board, row.score)}</span>
    </Tag>
  );
}

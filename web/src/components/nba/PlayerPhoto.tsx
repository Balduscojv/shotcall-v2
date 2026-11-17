"use client";

import { useState } from "react";
import { onTeamAccent, teamAccent } from "@/lib/games";
import { playerPhotoUrl } from "@/lib/nbaImages";
import type { Team } from "@/lib/types";

/**
 * Foto oficial do jogador num círculo com a cor do time. Sem foto (novato, ESPN fora do ar):
 * as iniciais no lugar.
 */
export function PlayerPhoto({
  id,
  name,
  team,
  size = 40,
  fill = false,
  className = "",
}: {
  id: number;
  name: string;
  team?: Team | null;
  /** Tamanho em px (também define a resolução pedida à ESPN). */
  size?: number;
  /** Ocupa o elemento de fora (que já tem borda e tamanho), sem borda própria. */
  fill?: boolean;
  className?: string;
}) {
  const [failed, setFailed] = useState(false);
  const initials = name
    .split(" ")
    .filter(Boolean)
    .map((w) => w[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <span
      className={`inline-flex shrink-0 items-end justify-center overflow-hidden rounded-full font-display ${fill ? "" : "border-2 border-ink"} ${className}`}
      style={{
        width: fill ? "100%" : size,
        height: fill ? "100%" : size,
        background: team ? teamAccent(team) : "var(--color-surface)",
        color: team ? onTeamAccent(team) : "var(--color-chalk)",
        fontSize: size * 0.36,
      }}
      aria-hidden="true"
    >
      {failed ? (
        <span className="self-center">{initials}</span>
      ) : (
        // eslint-disable-next-line @next/next/no-img-element -- imagem externa (CDN da ESPN), já redimensionada lá
        <img
          src={playerPhotoUrl(id, size)}
          alt=""
          loading="lazy"
          decoding="async"
          onError={() => setFailed(true)}
          className="-mb-[14%] h-[125%] w-auto max-w-none object-cover"
        />
      )}
    </span>
  );
}

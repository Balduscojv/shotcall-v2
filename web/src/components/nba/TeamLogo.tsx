"use client";

import { useState } from "react";
import { teamLogoUrl } from "@/lib/nbaImages";
import type { Team } from "@/lib/types";

/** Logo oficial do time. Se não carregar, a sigla no lugar. */
export function TeamLogo({
  team,
  size = 24,
  className = "",
}: {
  team: Pick<Team, "tricode">;
  size?: number;
  className?: string;
}) {
  const [failed, setFailed] = useState(false);

  if (failed) {
    return (
      <span
        className={`inline-flex shrink-0 items-center justify-center font-display ${className}`}
        style={{ width: size, height: size, fontSize: size * 0.34 }}
        aria-hidden="true"
      >
        {team.tricode}
      </span>
    );
  }

  return (
    // eslint-disable-next-line @next/next/no-img-element -- imagem externa (CDN da ESPN), já redimensionada lá
    <img
      src={teamLogoUrl(team.tricode, size)}
      alt=""
      width={size}
      height={size}
      loading="lazy"
      decoding="async"
      onError={() => setFailed(true)}
      className={`shrink-0 object-contain drop-shadow-[1px_1px_0_var(--color-ink)] ${className}`}
      aria-hidden="true"
    />
  );
}

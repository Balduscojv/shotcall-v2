"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

const two = (n: number) => String(n).padStart(2, "0");

/** Contagem até a trava ("TRAVA EM 02:24:12", "MERCADO FECHA EM 3d 11h"). No zero, recarrega a página. */
export function LockCountdown({ lockAt, label = "TRAVA EM" }: { lockAt: string; label?: string }) {
  const router = useRouter();
  const target = new Date(lockAt).getTime();
  const [now, setNow] = useState<number | null>(null);

  useEffect(() => {
    let id = 0;
    const tick = () => {
      const t = Date.now();
      setNow(t);
      if (t >= target) {
        window.clearInterval(id);
        router.refresh();
      }
    };
    const first = window.setTimeout(tick, 0);
    id = window.setInterval(tick, 1000);
    return () => {
      window.clearTimeout(first);
      window.clearInterval(id);
    };
  }, [target, router]);

  // Antes de montar no navegador não mostramos número (evita diferença entre servidor e cliente).
  const left = now === null ? null : Math.max(0, Math.floor((target - now) / 1000));
  const days = left === null ? 0 : Math.floor(left / 86400);
  const text =
    left === null
      ? "--:--:--"
      : days > 0
        ? `${days}d ${two(Math.floor((left % 86400) / 3600))}h`
        : `${two(Math.floor(left / 3600))}:${two(Math.floor((left % 3600) / 60))}:${two(left % 60)}`;

  return (
    <span className="flex flex-col items-center gap-0.5">
      <span className="text-[11px] font-extrabold tracking-wider">{label}</span>
      <span className="font-score text-3xl leading-none text-yellow tabular-nums" role="timer" aria-live="off">
        {text}
      </span>
    </span>
  );
}

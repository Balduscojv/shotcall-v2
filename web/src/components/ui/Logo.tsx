import Link from "next/link";

export function Ball({ size = 36, stroke = 5 }: { size?: number; stroke?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 84 84" aria-hidden="true">
      <circle cx="42" cy="42" r="38" fill="#FF5A1F" stroke="#0A0A0A" strokeWidth={stroke} />
      <path
        d="M42 4v76M4 42h76M15 15c14 12 14 42 0 54M69 15c-14 12-14 42 0 54"
        fill="none"
        stroke="#0A0A0A"
        strokeWidth={stroke - 0.5}
      />
    </svg>
  );
}

export function Logo({ href = "/" }: { href?: string }) {
  return (
    <Link href={href} className="flex items-center gap-2.5 text-chalk no-underline" aria-label="ShotCall, início">
      <Ball />
      <span className="font-display text-xl sm:text-2xl">
        SHOT<span className="text-orange">CALL</span>
      </span>
    </Link>
  );
}

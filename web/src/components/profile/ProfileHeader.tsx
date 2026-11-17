import Link from "next/link";
import { bannerLook } from "@/lib/cosmetics";
import { getProfile } from "@/lib/profile";
import { TrophyShelf } from "@/components/leagues/TrophyShelf";
import { TitleBadge } from "@/components/cosmetics/CosmeticPreview";
import { formatMonthYear, formatRate, tierColor, tierLabel } from "@/lib/labels";
import { Avatar } from "./Avatar";
import { FriendButton } from "./FriendButton";
import { ImageControl } from "./ImageControl";
import { ProfileTabs } from "./ProfileTabs";

/**
 * Cabeçalho do perfil, seguindo o gabarito web do canvas: banner 1200 × 280 e
 * avatar com moldura saindo pela borda de baixo, @ e título ao lado.
 */
export async function ProfileHeader({ params }: { params: Promise<{ handle: string }> }) {
  const { handle } = await params;
  const profile = await getProfile(handle);
  const own = profile.friendship === "SELF";
  const game = profile.accuracy.GAME;
  const banner = bannerLook(profile.equipped?.banner);
  const title = profile.equipped?.title;

  return (
    <section aria-label={`Perfil de ${profile.name}`} className="flex flex-col gap-6">
      <div className="relative">
        <div className="relative aspect-[1200/280] min-h-36 w-full overflow-hidden rounded-b-3xl border-x-[3px] border-b-[3px] border-ink bg-[#1C1730] sm:rounded-3xl sm:border-[3px] sm:shadow-hard-lg">
          {/* Banner é só o do jogo (prêmio em uso); sem nenhum, a grade padrão. */}
          {banner ? (
            <div className={`size-full ${banner.className}`} style={banner.style} aria-hidden="true" />
          ) : (
            <div className="fence size-full bg-[#1C1730]" aria-hidden="true" />
          )}
        </div>
        <div className="absolute -bottom-12 left-4 sm:-bottom-[72px] sm:left-10">
          <Avatar user={profile} size="lg" />
        </div>
      </div>

      {/* No celular o banner vai de ponta a ponta; o resto ganha a margem lateral da página. */}
      <div className="flex flex-wrap items-start justify-between gap-4 px-4 pt-12 sm:px-0 sm:pt-0 sm:pl-[200px]">
        <div className="flex min-w-0 flex-col gap-1">
          <h1 className="font-display text-[clamp(26px,3.5vw,40px)] leading-tight break-words">{profile.name}</h1>
          <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted">
            <span className="font-extrabold text-cyan">@{profile.handle}</span>
            {title && <TitleBadge title={title} />}
            {profile.member_since && <span>Na quadra desde {formatMonthYear(profile.member_since)}</span>}
          </p>
          {game?.tier && game.rate !== null && (
            <span
              className={`mt-2 inline-flex items-center gap-2 self-start rounded-lg border-2 border-ink px-3 py-1 font-display text-xs text-ink shadow-hard-sm ${tierColor[game.tier]}`}
            >
              {tierLabel[game.tier].toUpperCase()} NOS JOGOS · {formatRate(game.rate)}
            </span>
          )}
        </div>
        {own ? (
          <div className="flex flex-wrap items-center gap-2">
            <ImageControl kind="avatar" hasImage={!!profile.avatar_url} />
            <Link href="/configuracoes" className="text-sm font-bold text-cyan no-underline hover:text-lime">
              Configurações
            </Link>
          </div>
        ) : (
          <FriendButton handle={profile.handle!} status={profile.friendship} />
        )}
      </div>

      {profile.trophies.length > 0 && (
        <div className="px-4 sm:px-0">
          <TrophyShelf trophies={profile.trophies} />
        </div>
      )}

      <div className="px-4 sm:px-0">
        <ProfileTabs handle={profile.handle!} counts={profile.counts} />
      </div>
    </section>
  );
}

export function ProfileHeaderSkeleton() {
  return (
    <div className="flex flex-col gap-6" aria-hidden="true">
      <div className="aspect-[1200/280] min-h-36 w-full animate-pulse rounded-b-3xl bg-panel sm:rounded-3xl" />
      <div className="flex flex-col gap-3 px-4 pt-12 sm:px-0 sm:pt-0 sm:pl-[200px]">
        <div className="h-9 w-64 max-w-full animate-pulse rounded-lg bg-panel" />
        <div className="h-5 w-40 animate-pulse rounded-lg bg-panel" />
      </div>
      <div className="mx-4 h-11 max-w-xl animate-pulse rounded-full bg-panel sm:mx-0" />
    </div>
  );
}

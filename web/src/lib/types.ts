// Formatos das API Resources do Laravel (app/Http/Resources).

export type UserRole = "admin" | "moderator" | "player";

export type User = {
  id: number;
  name: string;
  handle: string | null;
  email?: string;
  avatar_url: string | null;
  banner_url: string | null;
  /** Só vem para o próprio usuário e para admins. */
  role?: UserRole;
  /** Prêmios em uso. Vem no /me, no perfil e nas listas de usuários. */
  equipped?: Equipped;
};

export type CosmeticSlot = "BANNER" | "FRAME" | "TITLE";

/** Resumo de um prêmio em uso, o suficiente para desenhar. */
export type EquippedItem = { key: string; name: string; asset_key: string | null; rarity: Rarity };

export type Equipped = { banner: EquippedItem | null; frame: EquippedItem | null; title: EquippedItem | null };

/** Prêmio do catálogo (app/Http/Resources/CosmeticResource.php). */
export type Cosmetic = {
  key: string;
  name: string;
  description: string | null;
  slot: CosmeticSlot;
  rarity: Rarity;
  source: string | null;
  /** Só para admin: o que libera o prêmio (SIGNUP, ...). Nulo = só pela moderação. */
  trigger?: string | null;
  asset_key: string | null;
  package_key: string | null;
  /** Em coleções de um usuário: se ele tem e se está usando. */
  owned?: boolean;
  equipped?: boolean;
  unlocked_at: string | null;
  reason: string | null;
};

/** Relação de quem vê com o dono do perfil. */
export type Friendship = "SELF" | "FRIENDS" | "SENT" | "RECEIVED" | "NONE";

export type Tier = "SILVER" | "GOLD" | "DIAMOND" | "PERFECT";
export type AccuracyMode = "GAME" | "WEEK" | "SEASON" | "BRACKET";

export type Profile = User & {
  member_since: string | null;
  friendship: Friendship;
  counts: { shot_calls: number; awards: number; leagues: number; friends: number };
  accuracy: Partial<Record<AccuracyMode, { rate: number | null; counted: number; tier: Tier | null }>>;
};

type TeamScore = { tricode: string; color: string; score: number };

export type ShotCallHistory = {
  id: number;
  status: "OPEN" | "LOCKED" | "SETTLED" | "VOID";
  correct: number | null;
  points: number | null;
  settled_at: string | null;
  pick: { winner: string | null; margin_band: string; mvp: string | null };
  game: { id: string; start_time: string; status: string; home: TeamScore; away: TeamScore };
};

export type Paginated<T> = {
  data: T[];
  meta: { current_page: number; last_page: number; total: number };
};

export type Rarity = "COMMON" | "RARE" | "EPIC" | "LEGENDARY";

export type ProfileLeague = {
  slug: string;
  name: string;
  color: string;
  is_global: boolean;
  privacy: "PUBLIC" | "PRIVATE";
  competitions: ("FANTASY" | "SHOTCALL")[];
  members_count: number;
  max_members: number;
  role: "OWNER" | "ADMIN" | "MEMBER" | null;
  rank: number | null;
};

export type FriendRequests = { received: User[]; sent: User[] };

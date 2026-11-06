// Formatos das API Resources do Laravel (app/Http/Resources).

export type User = {
  id: number;
  name: string;
  handle: string | null;
  email?: string;
  avatar_url: string | null;
  banner_url: string | null;
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

export type Award = {
  key: string;
  name: string;
  slot: string;
  rarity: Rarity;
  source: "GAME" | "WEEK" | "SEASON" | "BRACKET" | "LEAGUE";
  package_key: string | null;
  unlocked_at: string;
  reason: string;
  equipped: boolean;
};

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

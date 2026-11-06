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
  /** Missão secreta. Só aparece assim depois de liberada (ou para admin). */
  secret?: boolean;
  /** Em coleções de um usuário: se ele tem e se está usando. */
  owned?: boolean;
  equipped?: boolean;
  unlocked_at: string | null;
  reason: string | null;
};

/** Missão secreta ainda não liberada: só tipo, raridade e a dica vaga (meta.locked de /me/cosmetics). */
export type LockedMission = { id: number; slot: CosmeticSlot; rarity: Rarity; hint: string | null };

/** Time da NBA com as cores da paleta (app/Http/Resources/GameResource.php). */
export type Team = {
  id: number;
  tricode: string;
  city: string;
  name: string;
  primary_color: string;
  secondary_color: string;
};

export type GameStatus = "SCHEDULED" | "LIVE" | "FINAL" | "POSTPONED";

/** Jogo no calendário. `clock` só vem com o jogo ao vivo. */
export type Game = {
  id: string;
  phase: "PRESEASON" | "REGULAR" | "PLAYIN" | "PLAYOFFS";
  start_time: string;
  status: GameStatus;
  period: number;
  clock: string | null;
  home_score: number;
  away_score: number;
  arena: string | null;
  national_tv: string | null;
  round: number | null;
  /** Só na lista do calendário: o resumo do meu palpite neste jogo. */
  my_shot_call?: { status: ShotCallStatus; correct: number | null; points: number | null } | null;
  home: Team;
  away: Team;
};

/** GET /games: o dia da NBA (horário de Nova York), a semana em volta e os vizinhos com jogo. */
export type GamesDay = {
  data: Game[];
  meta: {
    date: string;
    today: string;
    week: { date: string; games: number; live: number }[];
    prev_date: string | null;
    next_date: string | null;
  };
};

export type BoxLine = {
  player_id: number;
  name: string | null;
  position: string | null;
  jersey: string | null;
  minutes: string | null;
  pts: number;
  reb: number;
  ast: number;
  stl: number;
  blk: number;
  tov: number;
  fgm: number;
  fga: number;
  fg3m: number;
  ftm: number;
  fta: number;
  plus_minus: number;
};

export type Leader = { player_id: number; name: string | null; value: number } | null;

export type TeamSheet = {
  totals: Record<"pts" | "reb" | "ast" | "stl" | "blk" | "tov" | "fgm" | "fga" | "fg3m" | "ftm" | "fta", number>;
  leaders: { pts: Leader; reb: Leader; ast: Leader };
  players: BoxLine[];
};

/** Arremesso de quadra, com a posição em % da quadra (comprimento × largura). */
export type Shot = { n: number; period: number; clock: string; team_id: number; player_id: number | null; three: boolean; made: boolean; x: number; y: number };

export type ShotCallStatus = "OPEN" | "LOCKED" | "SETTLED" | "VOID";
export type MarginBand = "M1_5" | "M6_10" | "M11_15" | "M16_PLUS";
export type ShotCallMarket = "winner" | "margin" | "mvp";

/** Jogador que pode ser MVP do jogo, com a média de pontos fantasy na temporada. */
export type MvpCandidate = {
  id: number;
  name: string;
  team_id: number;
  position: string | null;
  jersey: string | null;
  average: number | null;
  games: number;
};

/** Meu shot call no jogo (GameController::shotCallBlock). */
export type GameShotCall = {
  locked: boolean;
  lock_at: string;
  mine: {
    status: ShotCallStatus;
    winner_team_id: number;
    margin_band: MarginBand;
    mvp: { id: number; name: string; team_id: number } | null;
    /** Ao vivo: "se acabasse agora" (provisional). Encerrado: o resultado apurado. */
    correct: number | null;
    points: number | null;
    markets: Record<ShotCallMarket, boolean> | null;
    provisional: boolean;
  } | null;
  candidates: MvpCandidate[];
  /** Faixa da diferença atual no placar. */
  margin: MarginBand | null;
  mvp_leader: { id: number; name: string; team_id: number; points: number } | null;
  accuracy: { rate: number | null; counted: number; tier: Tier | null };
};

export type GameDetail = Game & {
  periods: { period: number; home_points: number; away_points: number }[];
  teams: { home: TeamSheet; away: TeamSheet };
  shots: Shot[];
  shot_call: GameShotCall;
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

/** Resultado da busca por @ (GET /friends/search), com a relação comigo. */
export type FriendSearchResult = User & { friendship: Friendship };

/** Rodada do fantasy (FantasyController::roundInfo). */
export type FantasyRound = {
  id: number;
  number: number;
  status: "UPCOMING" | "OPEN" | "LOCKED" | "SETTLING" | "SETTLED";
  starts_at: string;
  ends_at: string;
  /** Primeiro jogo da rodada: quando o mercado fecha. */
  lock_at: string | null;
};

/** Vaga na quadra: uma por posição detalhada. */
export type FantasySlot = PlayerRole;
export type FantasyPosition = "G" | "F" | "C";
/** Posição detalhada: PG armador, SG ala-armador, SF ala, PF ala-pivô, C pivô. */
export type PlayerRole = "PG" | "SG" | "SF" | "PF" | "C";

/** Jogador no mercado da rodada. */
export type MarketPlayer = {
  id: number;
  name: string;
  team_id: number;
  position: FantasyPosition;
  role: PlayerRole | null;
  jersey: string | null;
  price: number;
  variation: number;
  min_to_gain: number;
  average: number | null;
  games_played: number;
  games_in_round: number;
};

export type FantasyTeamPlayer = {
  slot: FantasySlot;
  id: number;
  name: string;
  position: FantasyPosition;
  role: PlayerRole | null;
  team: Team | null;
  price: number;
  bought_at_price: number;
  /** Pontos na rodada até agora (cestinha já multiplicado). */
  points: number;
  games_played: number;
  games_in_round: number;
  live: boolean;
  cestinha: boolean;
};

/** Boost dos palpites na rodada (app/Services/Fantasy/ShotCallBoost.php). */
export type ShotCallBoost = {
  /** Multiplicador aplicado: 0,04 = +4%. */
  value: number;
  /** Soma dos acertos antes do teto. */
  raw: number;
  cap: number;
  calls: number;
  hits: Record<ShotCallMarket, number>;
};

export type FantasyTeam = {
  cestinha_id: number | null;
  balance: number;
  patrimony: number;
  cost: number;
  /** Pontos dos 5 (cestinha em dobro), antes do boost. */
  base_points: number;
  /** Total da rodada: base × (1 + boost). */
  points: number;
  boost: ShotCallBoost;
  players: FantasyTeamPlayer[];
};

export type FantasyTeamResponse = {
  data: { round: FantasyRound; starting_balance: number; cestinha_multiplier: number; team: FantasyTeam | null };
};

export type MarketResponse = { data: MarketPlayer[]; meta: { round: FantasyRound; teams: Team[] } };


// Fotos e logos oficiais, direto do CDN da ESPN (a mesma fonte dos dados). Os ids dos jogadores
// já são os da ESPN. O "combiner" da ESPN redimensiona: a foto cai de ~200 KB para poucos KB.
// Marca e imagem são da NBA/ESPN; se a ESPN mudar as URLs, os componentes mostram as iniciais.

const CDN = "https://a.espncdn.com";

// Siglas da ESPN que diferem das nossas (o inverso de EspnClient::TRICODES no backend).
const espnAbbr: Record<string, string> = {
  GSW: "gs",
  NOP: "no",
  NYK: "ny",
  SAS: "sa",
  UTA: "utah",
  WAS: "wsh",
};

/** Foto do jogador recortada no rosto. A imagem original é 1040 × 760; pede o dobro para tela retina. */
export function playerPhotoUrl(playerId: number, px: number): string {
  const w = Math.round(px * 2 * (1040 / 760));
  return `${CDN}/combiner/i?img=/i/headshots/nba/players/full/${playerId}.png&w=${w}&h=${px * 2}&scale=crop`;
}

export function teamLogoUrl(tricode: string, px: number): string {
  const abbr = espnAbbr[tricode] ?? tricode.toLowerCase();
  return `${CDN}/combiner/i?img=/i/teamlogos/nba/500/${abbr}.png&w=${px * 2}&h=${px * 2}`;
}

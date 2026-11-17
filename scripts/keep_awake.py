"""Mantém a API e o site do ShotCall acordados no Render Free só quando há motivo.

O plano Free do Render dorme o serviço após ~15 min sem requisições, e com ele dormem a fila e
o agendador do Laravel (routes/console.php). Este script roda a cada 10 min pelo GitHub Actions
(.github/workflows/keep-awake.yml) e:

- no horário de uso (HORARIO_USO, hora de Brasília; padrão 18-24) acorda a API e o site, para
  quem entrar não esperar o Render subir os dois;
- fora dele, acorda só a API quando é horário de sincronização agendada (elencos 09:00 UTC,
  calendário 10:00 e 18:00 UTC) ou há jogo da NBA começando, rolando ou recém-terminado
  (placar ao vivo e apuração do fantasy).

No resto do tempo não faz nada. As 750 h/mês grátis do Render são somadas entre os serviços:
API e site ligados 24 h passariam de 1.400 h. Com 6 h de uso por dia (site + API), os jogos e as
sincronizações, a conta fica em torno de 550–650 h. Aumentar o HORARIO_USO gasta 2 h por hora.
Só usa a biblioteca padrão do Python.
"""

from __future__ import annotations

import json
import os
import sys
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, time, timedelta, timezone
from zoneinfo import ZoneInfo

API_URL = os.environ.get("API_URL") or "https://shotcall-api.onrender.com"
WEB_URL = os.environ.get("WEB_URL") or "https://shotcall-web.onrender.com"
# /up responde sem banco nem sessão. /entrar é estática e, ao acordar o site, ele também
# cutuca a API (web/src/instrumentation.ts).
API_PING = f"{API_URL.rstrip('/')}/up"
WEB_PING = f"{WEB_URL.rstrip('/')}/entrar"

BRASILIA = ZoneInfo("America/Sao_Paulo")
DEFAULT_USAGE_HOURS = "18-24"
SCOREBOARD = "https://site.api.espn.com/apis/site/v2/sports/basketball/nba/scoreboard?dates={}"

# Janelas (UTC) em volta das tarefas agendadas no Laravel.
SYNC_WINDOWS = [
    (time(8, 45), time(10, 20)),  # elencos 09:00 e calendário 10:00
    (time(17, 45), time(18, 20)),  # calendário 18:00
]

BEFORE_TIP_OFF = timedelta(minutes=15)
# A ESPN não informa o fim do jogo. Um jogo dura ~2h30; com prorrogação e folga para a
# apuração da rodada do fantasy, 4h depois do início cobre o "recém-terminado".
AFTER_TIP_OFF = timedelta(hours=4)


def fetch_json(url: str, timeout: int = 20) -> dict:
    req = urllib.request.Request(url, headers={"User-Agent": "shotcall-keep-awake"})
    with urllib.request.urlopen(req, timeout=timeout) as res:
        return json.load(res)


def usage_hours() -> tuple[int, int]:
    """HORARIO_USO no formato "INICIO-FIM" em horas cheias de Brasília (ex.: 18-24, 22-2)."""
    raw = (os.environ.get("HORARIO_USO") or DEFAULT_USAGE_HOURS).strip()
    try:
        start, end = (int(part) for part in raw.split("-"))
        if not (0 <= start <= 23 and 0 <= end <= 24):
            raise ValueError
    except ValueError:
        print(f"aviso: HORARIO_USO inválido ({raw!r}), usando {DEFAULT_USAGE_HOURS}")
        start, end = (int(part) for part in DEFAULT_USAGE_HOURS.split("-"))
    return start, end % 24


def in_usage_window(now: datetime) -> bool:
    start, end = usage_hours()
    hour = now.astimezone(BRASILIA).hour
    if start == end:  # 0-24: o dia todo
        return True
    if start < end:
        return start <= hour < end
    return hour >= start or hour < end  # atravessa a meia-noite (ex.: 22-2)


def in_sync_window(now: datetime) -> bool:
    return any(start <= now.time() <= end for start, end in SYNC_WINDOWS)


def game_reason(now: datetime) -> str | None:
    """Motivo para ficar acordado por causa de um jogo, ou None."""
    # Uma noite de jogos nos EUA atravessa a meia-noite UTC: olha ontem, hoje e amanhã.
    for offset in (-1, 0, 1):
        day = (now + timedelta(days=offset)).strftime("%Y%m%d")
        try:
            events = fetch_json(SCOREBOARD.format(day)).get("events", [])
        except Exception as exc:  # ESPN fora do ar não pode derrubar o bot
            print(f"aviso: placar da ESPN de {day} indisponível ({exc})")
            continue

        for event in events:
            start = datetime.fromisoformat(event["date"].replace("Z", "+00:00"))
            state = event["status"]["type"]["state"]  # pre, in, post
            name = event.get("shortName", event.get("name", "jogo"))

            if state == "in":
                return f"{name} ao vivo"
            if start - BEFORE_TIP_OFF <= now <= start + AFTER_TIP_OFF:
                return f"{name} ({state}, início {start:%H:%M} UTC)"
    return None


def ping(url: str) -> bool:
    # A primeira chamada acorda o serviço (pode levar 1–3 min).
    for attempt in range(1, 4):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "shotcall-keep-awake"})
            with urllib.request.urlopen(req, timeout=100) as res:
                print(f"ping {url} -> {res.status}")
                return res.status == 200
        except Exception as exc:
            print(f"ping {url} tentativa {attempt} falhou: {exc}")
    return False


def main() -> int:
    now = datetime.now(timezone.utc)
    force = os.environ.get("FORCE_PING") == "true"

    if force or in_usage_window(now):
        reason = "execução manual" if force else "horário de uso"
        urls = [API_PING, WEB_PING]
    else:
        reason = "janela de sincronização" if in_sync_window(now) else game_reason(now)
        urls = [API_PING]

    if not reason:
        print(f"{now:%Y-%m-%d %H:%M} UTC: nada agendado, deixando API e site dormirem")
        return 0

    print(f"{now:%Y-%m-%d %H:%M} UTC: acordando {', '.join(urls)} ({reason})")
    # Em paralelo: o site e a API sobem ao mesmo tempo, e o job cabe no timeout do workflow.
    with ThreadPoolExecutor(max_workers=len(urls)) as pool:
        results = list(pool.map(ping, urls))
    return 0 if all(results) else 1


if __name__ == "__main__":
    sys.exit(main())

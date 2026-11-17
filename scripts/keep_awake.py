"""Mantém a API do ShotCall acordada no Render Free só quando ela tem trabalho a fazer.

O plano Free do Render dorme o serviço após ~15 min sem requisições, e com ele dormem a fila e
o agendador do Laravel (routes/console.php). Este script roda a cada 10 min pelo GitHub Actions
(.github/workflows/keep-awake.yml) e só faz uma requisição à API quando:

- é horário de sincronização agendada (elencos 09:00 UTC, calendário 10:00 e 18:00 UTC), ou
- há jogo da NBA começando, rolando ou recém-terminado (placar ao vivo e apuração do fantasy).

Fora disso não faz nada, para não gastar as horas grátis do Render.
Só usa a biblioteca padrão do Python.
"""

from __future__ import annotations

import json
import os
import sys
import urllib.request
from datetime import datetime, time, timedelta, timezone

API_URL = os.environ.get("API_URL", "https://shotcall-api.onrender.com").rstrip("/")
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


def ping() -> bool:
    # /up responde sem banco nem sessão; a primeira chamada acorda o serviço (pode levar ~1 min).
    for attempt in range(1, 4):
        try:
            with urllib.request.urlopen(f"{API_URL}/up", timeout=90) as res:
                print(f"ping {API_URL}/up -> {res.status}")
                return res.status == 200
        except Exception as exc:
            print(f"ping tentativa {attempt} falhou: {exc}")
    return False


def main() -> int:
    now = datetime.now(timezone.utc)
    reason = "janela de sincronização" if in_sync_window(now) else game_reason(now)

    if os.environ.get("FORCE_PING") == "true":
        reason = reason or "execução manual"

    if not reason:
        print(f"{now:%Y-%m-%d %H:%M} UTC: nada agendado, deixando a API dormir")
        return 0

    print(f"{now:%Y-%m-%d %H:%M} UTC: acordando a API ({reason})")
    return 0 if ping() else 1


if __name__ == "__main__":
    sys.exit(main())

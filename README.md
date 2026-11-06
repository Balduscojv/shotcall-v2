# ShotCall

Fantasy de NBA com palpites (shot calls) que viram boost na pontuação.

- `backend/`: API em Laravel (MVC), com PostgreSQL e Redis
- `web/`: front em Next.js/React

## Rodando localmente

Precisa de Docker Desktop e Node.js.

```sh
docker compose up -d   # Postgres, Redis e o Laravel: API, fila e agendador
cd web
npm install            # só na primeira vez
npm run dev            # http://localhost:3000
```

Abra sempre pelo **localhost:3000**: o Next repassa `/api`, `/sanctum` e `/storage` para o
Laravel na porta 8000 (`API_URL` em `web/.env.local`).

Na primeira subida, o container `api` instala as dependências do Composer, cria o
`backend/.env` a partir do `.env.example`, gera a `APP_KEY` e roda as migrations.

### Primeira vez com o banco vazio

```sh
docker compose exec api php artisan db:seed     # times, temporada, cosméticos, missões, Liga Global
docker compose exec api php artisan nba:sync    # elencos, calendário e jogos (ESPN)
```

Depois de criar sua conta pelo site, para virar admin e acessar a moderação:

```sh
docker compose exec api php artisan user:role <seu_handle> admin
```

### Comandos úteis

| Comando | O que faz |
| --- | --- |
| `docker compose exec api php artisan <comando>` | roda qualquer comando do artisan |
| `docker compose logs -f api queue scheduler` | acompanha os logs do backend |
| `docker compose restart api queue scheduler` | aplica mudanças no `backend/.env` |
| `docker compose exec api php artisan fantasy:open <rodada>` | abre o mercado de uma rodada na mão |
| `docker compose exec api php artisan games:finish` | pontua e apura jogos encerrados |

O código é montado da pasta `backend/`, então mudanças em PHP valem na hora, sem rebuild.

### Testes

```sh
docker compose exec api php artisan test
```

Os testes usam SQLite em memória (`backend/phpunit.xml`), sem tocar no banco de desenvolvimento.

## Rodando o backend fora do Docker

Também funciona com PHP 8.4 instalado no Windows: suba só `docker compose up -d postgres postgres-test redis`
e, em `backend/`, rode `php artisan serve`, `php artisan queue:work` e `php artisan schedule:work`
em terminais separados.

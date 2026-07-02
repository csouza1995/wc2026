# Copa do Mundo 2026

Acompanhamento em tempo real da Copa do Mundo de 2026: jogos, placares ao vivo, classificação dos grupos e chaveamento mata-mata — construído com Laravel + Livewire.

## Créditos

O design circular do chaveamento mata-mata (fase eliminatória) é inspirado no trabalho de **[@emiliosansolini](https://www.instagram.com/emiliosansolini/)**. Todo o crédito da peça visual original é dele.

## Funcionalidades

- **Jogos**: lista de partidas agrupadas por dia, com filtro por seleção (até 3), por jogos ao vivo e por "hoje". Placar e minuto atualizam sozinhos via polling enquanto houver jogo ao vivo.
- **Classificação**: tabela dos grupos calculada a partir dos resultados (pontos, saldo de gols, critérios de desempate).
- **Chaveamento**: bracket circular da fase eliminatória (32 → 16 → 8 → 4 → 2 → campeão), com cores por seleção e indicação de times eliminados.
- Datas e horários são armazenados em UTC no banco e convertidos para o horário de São Paulo (UTC-3) em toda a interface e nas consultas de "jogos de hoje".
- Dados importados e sincronizados a partir de [football-data.org](https://www.football-data.org/) (fonte principal) e [API-Football](https://www.api-football.com/) (reforço em janelas críticas de jogos ao vivo, respeitando uma cota diária configurável).

## Requisitos

- PHP 8.4+
- Composer
- Node.js + npm
- SQLite (padrão) ou outro banco suportado pelo Laravel
- [Laravel Sail](https://laravel.com/docs/sail) (opcional, requer Docker) para rodar em container

## Instalação e setup

Clone o repositório e instale as dependências:

```bash
composer setup
```

Esse comando faz tudo de uma vez: instala dependências PHP e JS, cria o `.env`, gera a `APP_KEY`, roda as migrations e builda os assets. Equivalente a rodar manualmente:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### Chaves de API

Para importar e sincronizar os jogos, configure no `.env`:

```env
API_FOOTBALL_URL=https://v3.football.api-sports.io
API_FOOTBALL_API_KEY=
API_FOOTBALL_DAILY_QUOTA=100

FOOTBALL_DATA_ORG_URL=https://api.football-data.org/v4
FOOTBALL_DATA_ORG_API_KEY=
FOOTBALL_DATA_ORG_COMPETITION_CODE=WC
```

### Importar os dados do torneio

```bash
php artisan fixtures:import              # importa seleções, grupos e tabela de jogos
php artisan fixtures:link-api-football   # cruza os IDs do API-Football com os jogos importados
```

## Rodando o projeto

Com Sail:

```bash
sail up -d
sail composer dev
```

Sem Sail:

```bash
composer dev   # sobe servidor, queue e Vite juntos (via `php artisan dev`)
```

A aplicação fica disponível em `http://localhost` (ou na porta configurada em `APP_URL`).

### Acompanhar jogos ao vivo

Para manter os placares atualizados, rode o comando de polling (a cada ~10s) enquanto houver partidas em andamento:

```bash
php artisan matches:watch-live
```

## Testes e qualidade

```bash
composer test          # config:clear + lint:check + types:check + testes
php artisan test --compact
composer lint           # Pint (corrige estilo)
composer types:check    # PHPStan (Larastan, nível 7)
```

## Stack

- Laravel 13 + Livewire 4
- Tailwind CSS v4 (via Vite)
- SQLite
- Pest 4 para testes
- Larastan (PHPStan nível 7) para análise estática

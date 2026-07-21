# Changelog

All notable changes to `laravel-trail` will be documented in this file.

## v1.2.2 - 2026-07-21

v1.2.2 - 2026-07-21

Corrige o bug que fazia as telas do dashboard mostrarem só uma fração dos eventos quando havia qualquer filtro ativo.

🐛 Correções

- Filtros do dashboard passam a enxergar a janela inteira by @aqu1les in https://github.com/aqu1les/laravel-trail/commit/8bf147b
  As telas cortavam em 200 linhas (300 na timeline) e só depois filtravam em PHP, então o período selecionado definia apenas o piso da janela: o teto continuava sendo "as linhas mais recentes". Num app com volume essas linhas são quase todas page.viewed, e um ?q=user.registered&since=30d sobre mais de 100 registros retornava 5. Junto vão o painel de detalhes que esvaziava ao filtrar, os menus de "Evento" e "Todos os atores" que ofereciam opções incompletas, as estatísticas da Subject Timeline que saturavam no limite de linhas ("Primeira vez visto" mostrava a data do 300º evento mais recente), e a busca que diferenciava maiúsculas no PostgreSQL.

🔧 Por dentro

- Objetos de consulta para as leituras do dashboard by @aqu1les in https://github.com/aqu1les/laravel-trail/commit/bfc7999
  EventStreamQuery, SubjectActivityQuery e SubjectIndexQuery, mais SubjectKey, SubjectSearch e SubjectIdentity. Elimina ~40 linhas duplicadas de busca de atores entre telas e cinco montagens diferentes da identidade de exibição. Todas @internal: a API pública de leitura segue sendo Trail::events(). Events caiu de 396 para 259 linhas e SubjectTimeline de 630 para 366.
- Ignora o sqlite gerado pelo workbench by @aqu1les in https://github.com/aqu1les/laravel-trail/commit/96cd077

✅ Testes e CI

- Suíte rodando contra PostgreSQL 17 e MySQL 8 by @aqu1les in https://github.com/aqu1les/laravel-trail/commit/b9ff8c4
  O bug de maiúsculas acima existia com a suíte inteira verde, porque o LIKE do SQLite é insensível a caixa. Localmente nada muda: o padrão continua SQLite em memória, e TRAIL_TEST_DB=pgsql|mysql troca a conexão.
- Cobertura da série do gráfico da Overview by @aqu1les in https://github.com/aqu1les/laravel-trail/commit/127a12f
- Limpeza da suíte: menos testes, mais cobertura by @aqu1les in https://github.com/aqu1les/laravel-trail/commit/28d3b79
- Ordem de chaves JSON não é mais asserida by @aqu1les in https://github.com/aqu1les/laravel-trail/commit/9e71a6b

De 186 para 221 testes.

📦 Dependências

- chore(deps-dev): update laravel/mcp requirement from ^0.8.1 to ^0.9.0 by @dependabot[bot] in https://github.com/aqu1les/laravel-trail/pull/9
- chore(deps): bump stefanzweifel/git-auto-commit-action from 7.1.0 to 7.2.0 by @dependabot[bot] in https://github.com/aqu1les/laravel-trail/pull/8

📖 Documentação

- chore: bump wiki to document the shipped dashboard screens and URL filters by @aqu1les in https://github.com/aqu1les/laravel-trail/commit/dde50c4

Atualizando

Nada a fazer. Não há mudança de configuração, de banco ou de API pública.

Full Changelog: https://github.com/aqu1les/laravel-trail/compare/v1.2.1...v1.2.2

## v1.2.1 - 2026-07-10

### What's Changed

* Corrige filtro de período da tela de Events e leva os filtros do dashboard para a URL by @aqu1les in https://github.com/aqu1les/laravel-trail/pull/7

**Full Changelog**: https://github.com/aqu1les/laravel-trail/compare/v1.2.0...v1.2.1

## v1.2.0 - 2026-06-29

### What's Changed

* chore(deps): bump stefanzweifel/git-auto-commit-action from 7 to 7.1.0 by @dependabot[bot] in https://github.com/aqu1les/laravel-trail/pull/6
* chore(deps): bump actions/checkout from 6 to 7 by @dependabot[bot] in https://github.com/aqu1les/laravel-trail/pull/5
* Dashboard MCP: análise read-only dos dados do Trail via MCP by @aqu1les in https://github.com/aqu1les/laravel-trail/pull/4

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/aqu1les/laravel-trail/pull/6

**Full Changelog**: https://github.com/aqu1les/laravel-trail/compare/v1.1.0...v1.2.0

## v1.1.0 - 2026-06-14

### What's Changed

* Captura de eventos do navegador (estilo Amplitude) by @aqu1les in https://github.com/aqu1les/laravel-trail/pull/3

**Full Changelog**: https://github.com/aqu1les/laravel-trail/compare/v1.0.3...v1.1.0

## Unreleased

### Added

- Browser / SPA event capture. A plain `composer require` now ships a batch ingestion endpoint
  (`POST /<path>/api/ingest`), and `php artisan vendor:publish --tag=trail-js` publishes a batched,
  queue-safe TypeScript client (`createTrail` / `useTrail`).
- Separate view and write gates: `Trail::auth` guards the dashboard and read API, `Trail::ingestUsing`
  guards the browser write endpoint (default: allow, including anonymous).
- `Trail::routes()` plus the `register_routes`, `api`, and `browser` config keys for taking over route
  registration and tuning ingestion (recorder, batch size, rate limit, event allowlist).
- `PendingEvent::at()` and `PendingEvent::usingRecorder()` for setting a custom `occurred_at` and
  selecting a recorder by name.

## v1.0.3 - 2026-06-12

**Full Changelog**: https://github.com/aqu1les/laravel-trail/compare/v1.0.2...v1.0.3

## v1.0.2 - 2026-06-11

### What's Changed

* Footer dinamico e customizavel na sidebar do dashboard by @aqu1les in https://github.com/aqu1les/laravel-trail/pull/2
* Page views: captura de UTM e toggle para esconder by @aqu1les in https://github.com/aqu1les/laravel-trail/pull/1

### New Contributors

* @aqu1les made their first contribution in https://github.com/aqu1les/laravel-trail/pull/2

**Full Changelog**: https://github.com/aqu1les/laravel-trail/compare/v1.0.1...v1.0.2

## v1.0.1 - 2026-06-11

**Full Changelog**: https://github.com/aqu1les/laravel-trail/compare/v1.0.0...v1.0.1

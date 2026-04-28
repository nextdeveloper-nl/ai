# AI Services Aggregator

This is a Laravel module that aggregate AI services, to increase the speed of AI development. For this purpose this library only contains an interface to connect to AI services.

## Supported AI Engines;

- OpenAI

## Commercial Support

Please let us know if you need any commercial support. We dont have such a business plan yet but we will be happy to help you on your project and/or applying this library in your project. Please visit https://nextdeveloper.com for more information.

## Want to contribute?

You are very welcome to contribute of course. Please send us an email so that we can get in touch and talk about details; codewithus@nextdeveloper.com

---

---

## Requirements

- PHP `>=8.2`
- Laravel 10 (Illuminate components `>=8.0`)
- `nextdeveloper/commons` (filterable, UuidId, HasStates, HasObject, Taggable, RunAsAdministrator, CleanCache traits)
- `nextdeveloper/iam` (account/user scoping via `UserHelper`)
- `nextdeveloper/events`

## Installation

```bash
composer require nextdeveloper/ai
```

The service provider `NextDeveloper\AI\AIServiceProvider` is auto-discovered via `extra.laravel.providers`. Migrations live in `src/Database/Migrations` (currently managed at platform level, not per-package).

---

## REST API

All endpoints are mounted under the `ai` prefix and consume the standard NextDeveloper controller surface (index / show / store / update / destroy + actions, tags, addresses, related objects).

### Agents — `/ai/agents`

| Method   | Path                                  | Description                         |
| -------- | ------------------------------------- | ----------------------------------- |
| GET      | `/ai/agents`                          | List agents (filterable, paginated) |
| GET      | `/ai/agents/actions`                  | List available actions for the type |
| GET      | `/ai/agents/{ai_agents}`              | Show one agent (UUID)               |
| POST     | `/ai/agents`                          | Create agent                        |
| PATCH    | `/ai/agents/{ai_agents}`              | Update agent                        |
| DELETE   | `/ai/agents/{ai_agents}`              | Soft-delete agent                   |
| POST     | `/ai/agents/{ai_agents}/do/{action}`  | Invoke a registered action          |
| GET      | `/ai/agents/{ai_agents}/{subObjects}` | Related-object lookup               |
| GET/POST | `/ai/agents/{ai_agents}/tags`         | Read/write tags                     |
| GET/POST | `/ai/agents/{ai_agents}/addresses`    | Read/write addresses                |

### Runs — `/ai/runs`

Same surface as Agents, with `{ai_runs}` as the route binding:

| Method | Path                             | Description                                |
| ------ | -------------------------------- | ------------------------------------------ |
| GET    | `/ai/runs`                       | List runs (filter by agent, status, dates) |
| GET    | `/ai/runs/{ai_runs}`             | Show one run                               |
| POST   | `/ai/runs`                       | Create run record                          |
| PATCH  | `/ai/runs/{ai_runs}`             | Update run (status transitions, output)    |
| DELETE | `/ai/runs/{ai_runs}`             | Soft-delete run                            |
| POST   | `/ai/runs/{ai_runs}/do/{action}` | Invoke action                              |

---

## Service layer

Prefer the service classes over raw model queries — they apply IAM scoping, observers, and event dispatch.

```php
use NextDeveloper\AI\Services\AgentsService;
use NextDeveloper\AI\Services\RunsService;

// Lookup
$agent = AgentsService::getByRef('content-summarizer');
$agents = AgentsService::get($filter, ['paginate' => true, 'per_page' => 50]);

// Create
$agent = AgentsService::create([
    'name'            => 'Content Summarizer',
    'ref_name'        => 'content-summarizer',
    'system_prompt'   => 'You summarize blog posts in 3 bullet points.',
    'response_format' => 'json',
    'response_schema' => ['type' => 'object', 'properties' => ['bullets' => ['type' => 'array']]],
    'temperature'     => 0.2,
    'max_tokens'      => 512,
    'is_active'       => true,
]);

// Record a run
$run = RunsService::create([
    'ai_agent_id'   => $agent->id,
    'model'         => 'claude-opus-4-7',
    'input'         => ['post_id' => 1765, 'body' => $body],
    'status'        => 'running',
]);

// Update on completion
RunsService::update($run->id, [
    'output'        => $rawText,
    'parsed_output' => $json,
    'status'        => 'succeeded',
    'status_code'   => 200,
    'input_tokens'  => 1240,
    'output_tokens' => 187,
    'duration_ms'   => 3421,
    'cost'          => 0.0091,
]);
```

Available service methods (both `AgentsService` and `RunsService`):

- `get($filter = null, array $params = [])` — list, with optional pagination
- `getByRef(string $ref)` — lookup by UUID, slug, or ref_name
- `create(array $data)`
- `update($id, array $data)`
- `updateRaw(array $data)` — bypass observers (use sparingly)
- `delete($id)` — soft delete
- `doAction($objectId, $action, ...$params)` — dispatch a named action

---

## Filtering

Both models use `NextDeveloper\Commons\Database\Traits\Filterable`. Standard query string filters apply:

```
GET /ai/runs?ai_agent_id=42&status=succeeded&created_at_start=2026-04-01
GET /ai/agents?is_active=1&name=content
```

Filter classes live in `src/Database/Filters/`.

---

## Scopes

Global and per-model scopes can be registered via config:

```php
// config/ai.php
return [
    'scopes' => [
        'global'    => [/* applied to every model in the package */],
        'ai_agents' => [/* extra scopes for Agents */],
        'ai_runs'   => [/* extra scopes for Runs */],
    ],
];
```

Both models call `registerScopes()` in `boot()` and merge global + model-specific entries.

---

## Events

Each resource has its own event namespace under `src/Events/`:

- `NextDeveloper\AI\Events\Agents\*` — `AgentsCreatedEvent`, `AgentsUpdatedEvent`, `AgentsDeletedEvent`
- `NextDeveloper\AI\Events\Runs\*` — `RunsCreatedEvent`, `RunsUpdatedEvent`, `RunsDeletedEvent`

Dispatched by the abstract services through `NextDeveloper\Events\Services\Events`.

---

## Authoring custom logic

Generated abstract layers are regenerated on schema changes — **do not edit above the `EDIT AFTER HERE` marker** in any model, service, or controller. Place overrides below the marker, or in:

- `src/Services/AgentsService.php` / `RunsService.php` — business logic
- `src/Database/Observers/AgentsObserver.php` / `RunsObserver.php` — lifecycle hooks
- `src/Http/Controllers/Agents/AgentsController.php` / `Runs/RunsController.php` — request handling
- `src/Policies/` — authorization

---

## Testing

```bash
# or, from the host application:
php artisan test --compact --filter=Agents
php artisan test --compact --filter=Runs
```

Test stubs for the generated models live at the application level under `tests/Unit/GeneratedModelAi*Test.php`.

---

## License

MIT — see `composer.json` for authors.

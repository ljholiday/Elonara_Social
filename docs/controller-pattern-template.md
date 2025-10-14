Controller Pattern Template
===========================

This template describes the shape of modern HTTP controllers in the PSR-4 `src/Http` namespace. Follow it when migrating features from the legacy stack.

Controller Goals
----------------
- Thin orchestration layer: pull request data, invoke services, return view models.
- Pure methods where possible; no direct database access or session superglobals.
- Deterministic outputs that progress parity tests between modern and legacy routes.

Checklist Before Creating a Controller
--------------------------------------
- [ ] Service logic lives under `App\Services\…` (or `App\Domain\…`) and is registered in `src/bootstrap.php`.
- [ ] Request validation handled in controller or dedicated validator class; no templates reading raw `$_POST`.
- [ ] Templates receive scalar/array data (no service objects) and are stored under `templates/`.
- [ ] Routes wired in `public/index.php` (temporary until router abstraction lands).
- [ ] Tests cover controller behaviour (e.g., dedicated script in `tests/` or assertions in feature smoke).

Skeleton
--------

```php
<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\FeatureService;
use App\Services\AuthService;
use App\Services\CircleService; // optional, example shared service

final class FeatureController
{
    public function __construct(
        private FeatureService $features,
        private AuthService $auth,
        private CircleService $circles,
    ) {
    }

    /**
     * Example index action returning data for a listing template.
     *
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   filters: array<string, mixed>
     * }
     */
    public function index(): array
    {
        $request = $this->request();
        $viewerId = $this->auth->currentUserId() ?? 0;
        $filter = $this->normalizeFilter($request->query('filter'));

        $items = $this->features->listForViewer($viewerId, $filter);

        return [
            'items' => $items,
            'filters' => [
                'active' => $filter,
                'available' => FeatureService::FILTERS,
            ],
        ];
    }

    /**
     * Example show action for detail views.
     *
     * @return array{
     *   item: array<string, mixed>|null,
     *   status: int
     * }
     */
    public function show(string $slugOrId): array
    {
        $viewerId = $this->auth->currentUserId() ?? 0;
        $item = $this->features->getBySlugOrId($slugOrId);

        if ($item === null || !$this->features->viewerCanView($item, $viewerId)) {
            return [
                'item' => null,
                'status' => 404,
            ];
        }

        return [
            'item' => $item,
            'status' => 200,
        ];
    }

    /**
     * Example store action for POST handlers.
     *
     * @return array{
     *   redirect?: string,
     *   errors?: array<string,string>,
     *   input?: array<string,string>
     * }
     */
    public function store(): array
    {
        $request = $this->request();
        $validated = $this->validateInput($request);

        if ($validated['errors']) {
            return [
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $slug = $this->features->create($validated['input']);

        return [
            'redirect' => '/features/' . $slug,
        ];
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = vt_service('http.request');
        return $request;
    }

    private function normalizeFilter(?string $filter): string
    {
        $filter = strtolower((string) $filter);
        return in_array($filter, FeatureService::FILTERS, true) ? $filter : FeatureService::DEFAULT_FILTER;
    }

    /**
     * @return array{
     *   input: array<string,string>,
     *   errors: array<string,string>
     * }
     */
    private function validateInput(Request $request): array
    {
        $input = [
            'title' => trim((string) $request->input('title', '')),
            'description' => trim((string) $request->input('description', '')),
        ];

        $errors = [];
        if ($input['title'] === '') {
            $errors['title'] = 'Title is required.';
        }

        return [
            'input' => $input,
            'errors' => $errors,
        ];
    }
}
```

Registration Steps
------------------
1. Add the controller class under `src/Http/Controller/`.
2. Register it in `src/bootstrap.php`:
   ```php
   $container->register('controller.features', static function (VTContainer $c): FeatureController {
       return new FeatureController(
           $c->get('feature.service'),
           $c->get('auth.service'),
           $c->get('circle.service'),
       );
   }, false);
   ```
3. Wire the route in `public/index.php` (temporary router):
   ```php
   if ($path === '/features') {
       $view = vt_service('controller.features')->index();
       $items = $view['items'];
       require __DIR__ . '/../templates/features-list.php';
       return;
   }
   ```
4. Create or update templates to match the returned view model contract.
5. Add or update tests (e.g., `tests/features-controller-test.php`) to mirror controller outcomes.

Testing Guidance
----------------
- Use deterministic fixtures (similar to `tests/database-test.php`) to seed data needed by the controller.
- Exercise both success and failure paths: invalid input, missing entities, unauthorized access.
- When scripts mutate data, clean up or isolate using dedicated fixtures per run.

Future Router Migration Notes
-----------------------------
- Once a dedicated router is in place, the public index wiring can collapse into route definitions.
- Controller signatures should remain the same, making it simple to plug into upcoming middleware request/response handling.

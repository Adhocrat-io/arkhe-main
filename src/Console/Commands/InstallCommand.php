<?php

declare(strict_types=1);

namespace Arkhe\Main\Console\Commands;

use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'arkhe:main:install
                            {--y|yes : Accept all default confirmations without prompting}
                            {--with-test-users : Create test users (ignored in production)}
                            {--force : Force overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Arkhe Main package';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        app()->setLocale(config('app.locale'));

        if ($this->isNonInteractive()) {
            $this->info(__('Running in non-interactive mode...'));
        }

        $this->info(__('Installing Arkhe Main package...'));

        if ($this->shouldProceed(__('Do you want to publish the configuration?'))) {
            if (! $this->configExists('arkhe.php')) {
                $this->publishConfiguration();
                $this->info(__('Arkhe Main configuration published successfully.'));
            } else {
                if ($this->shouldOverwriteConfig()) {
                    $this->info(__('Overwriting existing configuration...'));
                    $this->publishConfiguration(force: true);
                } else {
                    $this->info(__('Existing configuration was not overwritten.'));
                }
            }
        }

        $this->modifyWelcomeBlade();
        $this->replaceDashboardRoutes();

        if ($this->shouldProceed(__('Do you want to publish the migrations?'))) {
            $this->publishMigrations();
            $this->info(__('Arkhe Main migrations published successfully.'));
        }

        if ($this->shouldProceed(__('Do you want to modify the User model to add HasRoles trait and fillable fields?'))) {
            $this->modifyUserModel();
        }

        $this->publishSeoAssets();
        $this->info(__('SEO package assets published successfully.'));

        if ($this->shouldProceed(__('Do you want to publish the roles and permissions seeder?'))) {
            $this->publishRolesAndPermissionsSeeder();
            $this->info(__('Arkhe Main roles and permissions seeder published successfully.'));
        }

        if ($this->shouldProceed(__('Do you want to run the migrations?'))) {
            $this->call('migrate');
            $this->info(__('Arkhe Main migrations run successfully.'));
        }

        if ($this->shouldProceed(__('Do you want to publish the lang files?'))) {
            $this->publishLangFiles();
            $this->info(__('Arkhe Main lang files published successfully.'));
        }

        if ($this->shouldPublishModifiedFiles()) {
            $this->publishFiles();
            $this->info(__('Arkhe Main files published successfully.'));
        } else {
            $this->info(__('You will have to manually modify your files to work with Arhkè. See documentation for more information.'));
        }

        if ($this->shouldProceed(__('Do you want to run the roles and permissions seeder?'))) {
            $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class]);
            $this->info(__('Arkhe Main roles and permissions seeder run successfully.'));
        }

        if ($this->shouldCreateTestUsers()) {
            $this->call('db:seed', ['--class' => TestUsersSeeder::class]);
            $this->info(__('Arkhe Main test users created successfully.'));
        }

        $this->info(__('Arkhe Main package installed successfully.'));
    }

    private function shouldProceed(string $question, bool $default = true): bool
    {
        if ($this->option('yes')) {
            return $default;
        }

        return confirm($question, $default);
    }

    private function isNonInteractive(): bool
    {
        return (bool) $this->option('yes');
    }

    private function configExists(string $fileName): bool
    {
        return File::exists(config_path($fileName));
    }

    private function publishConfiguration(bool $force = false): void
    {
        $params = [
            '--tag' => 'arkhe-main-config',
        ];

        if ($force) {
            $params['--force'] = true;
        }

        $this->call(command: 'vendor:publish', arguments: $params);
    }

    private function shouldOverwriteConfig(): bool
    {
        if ($this->isNonInteractive()) {
            return (bool) $this->option('force');
        }

        return confirm(label: __('Config file already exists. Do you want to overwrite it?'), default: false);
    }

    private function shouldPublishModifiedFiles(): bool
    {
        if ($this->isNonInteractive()) {
            return (bool) $this->option('force');
        }

        return confirm(__('Do you want to publish the modified files?'), true);
    }

    private function shouldCreateTestUsers(): bool
    {
        if ($this->isNonInteractive()) {
            return (bool) $this->option('with-test-users') && ! app()->isProduction();
        }

        return confirm(__("Do you want to create test users (don't do this on production)?"), false);
    }

    private function publishMigrations(): void
    {
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'arkhe-main-migrations', '--force' => true]);
    }

    private function publishRolesAndPermissionsSeeder(): void
    {
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'arkhe-main-roles-seeder', '--force' => true]);
    }

    private function publishLangFiles(): void
    {
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'arkhe-main-lang', '--force' => true]);
    }

    private function publishSeoAssets(): void
    {
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'seo-migrations']);
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'seo-config']);
    }

    private function publishFiles(): void
    {
        $shouldPublish = $this->isNonInteractive()
            ? true
            : confirm(label: __('This will overwrite the existing files. Are you sure?'), default: true);

        if ($shouldPublish) {
            $this->call(command: 'vendor:publish', arguments: ['--tag' => 'arkhe-main-files', '--force' => true]);
        }
    }

    private function modifyWelcomeBlade(): void
    {
        $welcomeBladePath = resource_path('views/welcome.blade.php');

        if (File::exists($welcomeBladePath)) {
            $content = File::get($welcomeBladePath);

            $patterns = [
                'href="{{ url(\'/dashboard\') }}"',
                'href="{{ url("/dashboard") }}"',
                'href="{{url(\'/dashboard\')}}"',
                'href="{{url("/dashboard")}}"',
            ];

            $replacement = 'href="{{ route(\'admin.dashboard\') }}"';
            $modified = false;

            foreach ($patterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    $content = str_replace($pattern, $replacement, $content);
                    $modified = true;
                    break;
                }
            }

            if ($modified) {
                File::put($welcomeBladePath, $content);
                $this->info(__('Welcome blade file updated: dashboard route set to /administration/dashboard.'));
            }
        }
    }

    private function replaceDashboardRoutes(): void
    {
        $viewsPath = resource_path('views');

        if (! File::isDirectory($viewsPath)) {
            return;
        }

        $patterns = [
            "route('dashboard')" => "route('admin.dashboard')",
            'route("dashboard")' => 'route("admin.dashboard")',
            "routeIs('dashboard')" => "routeIs('admin.dashboard')",
            'routeIs("dashboard")' => 'routeIs("admin.dashboard")',
            "route('profile.edit')" => "route('admin.settings.profile')",
            'route("profile.edit")' => 'route("admin.settings.profile")',
            "routeIs('profile.edit')" => "routeIs('admin.settings.profile')",
            'routeIs("profile.edit")' => 'routeIs("admin.settings.profile")',
        ];

        $files = File::allFiles($viewsPath);
        $modifiedCount = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = File::get($file->getPathname());
            $originalContent = $content;

            foreach ($patterns as $search => $replace) {
                $content = str_replace($search, $replace, $content);
            }

            if ($content !== $originalContent) {
                File::put($file->getPathname(), $content);
                $modifiedCount++;
                $this->line(__('  Updated: :file', ['file' => $file->getRelativePathname()]));
            }
        }

        if ($modifiedCount > 0) {
            $this->info(__(':count Blade file(s) updated: dashboard routes replaced with admin.dashboard.', ['count' => $modifiedCount]));
        }
    }

    /**
     * Modify the User model to add HasRoles trait and fillable fields.
     */
    private function modifyUserModel(): void
    {
        $userModelPath = app_path('Models/User.php');

        if (! File::exists($userModelPath)) {
            $this->warn(__('User model not found at :path', ['path' => $userModelPath]));

            return;
        }

        $code = File::get($userModelPath);

        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\PhpParser\Error $e) {
            $this->error(__('Failed to parse User model: :error', ['error' => $e->getMessage()]));

            return;
        }

        $traverser = new NodeTraverser;
        $visitor = new UserModelVisitor;
        $traverser->addVisitor($visitor);
        $ast = $traverser->traverse($ast);

        if ($visitor->wasModified()) {
            $printer = new Standard(['shortArraySyntax' => true]);
            $newCode = $printer->prettyPrintFile($ast);

            File::put($userModelPath, $newCode);
            $this->info(__('User model modified successfully:'));

            if ($visitor->hasRolesAdded()) {
                $this->line(__('  - Added HasRoles trait'));
            }
            if ($visitor->fillableFieldsAdded()) {
                $this->line(__('  - Added fillable fields: date_of_birth, civility, profession'));
            }
            if ($visitor->castsAdded()) {
                $this->line(__('  - Added casts: date_of_birth => date'));
            }
        } else {
            $this->info(__('User model already has all required modifications.'));
        }
    }
}

/**
 * PHP Parser visitor to modify the User model.
 */
class UserModelVisitor extends NodeVisitorAbstract
{
    private const REQUIRED_FILLABLE_FIELDS = ['date_of_birth', 'civility', 'profession'];

    private const REQUIRED_CASTS = ['date_of_birth' => 'date'];

    private const HAS_ROLES_TRAIT = 'Spatie\\Permission\\Traits\\HasRoles';

    private bool $hasRolesTraitExists = false;

    private bool $hasRolesAdded = false;

    private bool $fillableFieldsAdded = false;

    private bool $castsAdded = false;

    private bool $useStatementExists = false;

    public function enterNode(Node $node): ?Node
    {
        // Check if use statement for HasRoles already exists
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                if ($use->name->toString() === self::HAS_ROLES_TRAIT) {
                    $this->useStatementExists = true;
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node): Node|array|null
    {
        // Add use statement for HasRoles after namespace
        if ($node instanceof Node\Stmt\Namespace_) {
            if (! $this->useStatementExists) {
                $hasRolesUse = new Node\Stmt\Use_([
                    new Node\UseItem(new Node\Name(self::HAS_ROLES_TRAIT)),
                ]);

                // Find the position to insert (after existing use statements)
                $insertPosition = 0;
                foreach ($node->stmts as $index => $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_ || $stmt instanceof Node\Stmt\GroupUse) {
                        $insertPosition = $index + 1;
                    }
                }

                array_splice($node->stmts, $insertPosition, 0, [$hasRolesUse]);
                $this->hasRolesAdded = true;
            }

            return $node;
        }

        // Modify the User class
        if ($node instanceof Node\Stmt\Class_) {
            $this->addHasRolesTrait($node);
            $this->addFillableFields($node);
            $this->addCasts($node);

            return $node;
        }

        return null;
    }

    private function addHasRolesTrait(Node\Stmt\Class_ $class): void
    {
        // Check if HasRoles trait is already used
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\TraitUse) {
                foreach ($stmt->traits as $trait) {
                    if ($trait->toString() === 'HasRoles' || str_ends_with($trait->toString(), '\\HasRoles')) {
                        $this->hasRolesTraitExists = true;

                        return;
                    }
                }
            }
        }

        if ($this->hasRolesTraitExists) {
            return;
        }

        // Find existing trait use statement or create new one
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\TraitUse) {
                // Add HasRoles to existing trait use
                $stmt->traits[] = new Node\Name('HasRoles');
                $this->hasRolesAdded = true;

                return;
            }
        }

        // No existing trait use, create new one after class opening
        $traitUse = new Node\Stmt\TraitUse([new Node\Name('HasRoles')]);
        array_unshift($class->stmts, $traitUse);
        $this->hasRolesAdded = true;
    }

    private function addFillableFields(Node\Stmt\Class_ $class): void
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Property) {
                continue;
            }

            foreach ($stmt->props as $prop) {
                if ($prop->name->toString() !== 'fillable') {
                    continue;
                }

                // Found $fillable property
                if (! $prop->default instanceof Node\Expr\Array_) {
                    continue;
                }

                $existingFields = [];
                foreach ($prop->default->items as $item) {
                    if ($item instanceof Node\ArrayItem && $item->value instanceof Node\Scalar\String_) {
                        $existingFields[] = $item->value->value;
                    }
                }

                // Add missing fields
                $fieldsToAdd = array_diff(self::REQUIRED_FILLABLE_FIELDS, $existingFields);
                foreach ($fieldsToAdd as $field) {
                    $prop->default->items[] = new Node\ArrayItem(
                        new Node\Scalar\String_($field)
                    );
                    $this->fillableFieldsAdded = true;
                }

                return;
            }
        }
    }

    private function addCasts(Node\Stmt\Class_ $class): void
    {
        // First, check for casts() method (Laravel 11+ style)
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === 'casts') {
                $this->addCastsToMethod($stmt);

                return;
            }
        }

        // Then, check for $casts property (older Laravel style)
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Property) {
                continue;
            }

            foreach ($stmt->props as $prop) {
                if ($prop->name->toString() === 'casts') {
                    $this->addCastsToProperty($prop);

                    return;
                }
            }
        }
    }

    private function addCastsToProperty(Node\PropertyItem $prop): void
    {
        if (! $prop->default instanceof Node\Expr\Array_) {
            return;
        }

        $existingCasts = [];
        foreach ($prop->default->items as $item) {
            if ($item instanceof Node\ArrayItem && $item->key instanceof Node\Scalar\String_) {
                $existingCasts[] = $item->key->value;
            }
        }

        foreach (self::REQUIRED_CASTS as $field => $type) {
            if (! in_array($field, $existingCasts, true)) {
                $prop->default->items[] = new Node\ArrayItem(
                    new Node\Scalar\String_($type),
                    new Node\Scalar\String_($field)
                );
                $this->castsAdded = true;
            }
        }
    }

    private function addCastsToMethod(Node\Stmt\ClassMethod $method): void
    {
        // Look for return statement with array
        foreach ($method->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Return_) {
                continue;
            }

            if (! $stmt->expr instanceof Node\Expr\Array_) {
                continue;
            }

            $existingCasts = [];
            foreach ($stmt->expr->items as $item) {
                if ($item instanceof Node\ArrayItem && $item->key instanceof Node\Scalar\String_) {
                    $existingCasts[] = $item->key->value;
                }
            }

            foreach (self::REQUIRED_CASTS as $field => $type) {
                if (! in_array($field, $existingCasts, true)) {
                    $stmt->expr->items[] = new Node\ArrayItem(
                        new Node\Scalar\String_($type),
                        new Node\Scalar\String_($field)
                    );
                    $this->castsAdded = true;
                }
            }

            return;
        }
    }

    public function wasModified(): bool
    {
        return $this->hasRolesAdded || $this->fillableFieldsAdded || $this->castsAdded;
    }

    public function hasRolesAdded(): bool
    {
        return $this->hasRolesAdded;
    }

    public function fillableFieldsAdded(): bool
    {
        return $this->fillableFieldsAdded;
    }

    public function castsAdded(): bool
    {
        return $this->castsAdded;
    }
}

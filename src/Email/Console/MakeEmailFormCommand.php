<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * FR-AI-05: scaffolds a form already wired to the email module — the
 * generated file IS the documentation the assistant imitates, which beats
 * any instruction telling it what to write.
 */
class MakeEmailFormCommand extends Command
{
    protected $signature = 'arkhe:make:email-form
                            {name : Class name (ex: ContactRequest, RegisterForm)}
                            {--livewire : Generate an ArkheForm + Livewire component + view instead of a FormRequest}
                            {--names : Include first/last name fields (feeds the local-part comparison)}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate a form with a verified email field (FormRequest, or Livewire with --livewire)';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly((string) $this->argument('name'));

        return $this->option('livewire')
            ? $this->makeLivewire($files, $name)
            : $this->makeFormRequest($files, $name);
    }

    private function makeFormRequest(Filesystem $files, string $name): int
    {
        $target = app_path('Http/Requests/'.$name.'.php');

        $nameRules = $this->option('names')
            ? "            'first_name' => ['required', 'string', 'max:255'],\n            'last_name' => ['required', 'string', 'max:255'],\n"
            : '';

        return $this->write($files, $target, $this->stub('email-form-request.stub', [
            '{{ class }}' => $name,
            '{{ nameRules }}' => $nameRules,
        ]));
    }

    private function makeLivewire(Filesystem $files, string $name): int
    {
        $formClass = str_ends_with($name, 'Form') ? $name : $name.'Form';
        $componentClass = Str::beforeLast($formClass, 'Form');
        $view = Str::kebab($componentClass);

        $withNames = (bool) $this->option('names');

        $status = $this->write($files, app_path('Livewire/Forms/'.$formClass.'.php'), $this->stub('email-livewire-form.stub', [
            '{{ class }}' => $formClass,
            '{{ nameProperties }}' => $withNames
                ? "    public string \$first_name = '';\n\n    public string \$last_name = '';\n\n"
                : '',
            '{{ nameRules }}' => $withNames
                ? "            'first_name' => ['required', 'string', 'max:255'],\n            'last_name' => ['required', 'string', 'max:255'],\n"
                : '',
        ]));

        if ($status !== self::SUCCESS) {
            return $status;
        }

        $status = $this->write($files, app_path('Livewire/'.$componentClass.'.php'), $this->stub('email-livewire-component.stub', [
            '{{ class }}' => $componentClass,
            '{{ formClass }}' => $formClass,
            '{{ view }}' => $view,
            '{{ verifyArguments }}' => $withNames
                ? "\$this->form->first_name,\n            \$this->form->last_name,"
                : 'null,
            null,',
        ]));

        if ($status !== self::SUCCESS) {
            return $status;
        }

        return $this->write($files, resource_path('views/livewire/'.$view.'.blade.php'), $this->stub('email-livewire-view.stub', [
            '{{ nameInputs }}' => $withNames
                ? "    <flux:input wire:model=\"form.first_name\" :label=\"__('Prénom')\" />\n    <flux:input wire:model=\"form.last_name\" :label=\"__('Nom')\" />\n\n"
                : '',
        ]));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function stub(string $name, array $replacements): string
    {
        return strtr((string) file_get_contents(__DIR__.'/stubs/'.$name), $replacements);
    }

    private function write(Filesystem $files, string $target, string $contents): int
    {
        if ($files->exists($target) && ! $this->option('force')) {
            $this->components->error(str_replace(base_path().'/', '', $target).' existe déjà (utilisez --force pour écraser).');

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($target));
        $files->put($target, $contents);

        $this->components->info(str_replace(base_path().'/', '', $target).' généré.');

        return self::SUCCESS;
    }
}

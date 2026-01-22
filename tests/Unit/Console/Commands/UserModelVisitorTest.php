<?php

declare(strict_types=1);

use Arkhe\Main\Console\Commands\UserModelVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

describe('UserModelVisitor', function () {
    it('adds HasRoles trait to User model', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
PHP;

        $result = transformCode($code);

        expect($result)->toContain('use Spatie\Permission\Traits\HasRoles;');
        expect($result)->toContain('use HasRoles;');
    });

    it('adds fillable fields to User model', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

class User
{
    protected $fillable = [
        'name',
        'email',
    ];
}
PHP;

        $result = transformCode($code);

        expect($result)->toContain("'date_of_birth'");
        expect($result)->toContain("'civility'");
        expect($result)->toContain("'profession'");
    });

    it('does not duplicate HasRoles trait if already exists', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;

class User
{
    use HasRoles;

    protected $fillable = ['name'];
}
PHP;

        $result = transformCode($code);

        // Count occurrences of HasRoles - should only appear in existing locations
        $useStatementCount = substr_count($result, 'use Spatie\Permission\Traits\HasRoles;');
        $traitUseCount = substr_count($result, 'use HasRoles;');

        expect($useStatementCount)->toBe(1);
        expect($traitUseCount)->toBe(1);
    });

    it('does not duplicate fillable fields if already exist', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

class User
{
    protected $fillable = [
        'name',
        'email',
        'date_of_birth',
        'civility',
        'profession',
    ];
}
PHP;

        $result = transformCode($code);

        // Count occurrences - should only appear once each
        expect(substr_count($result, "'date_of_birth'"))->toBe(1);
        expect(substr_count($result, "'civility'"))->toBe(1);
        expect(substr_count($result, "'profession'"))->toBe(1);
    });

    it('adds HasRoles to existing trait use statement', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;

class User
{
    use Notifiable;

    protected $fillable = ['name'];
}
PHP;

        $result = transformCode($code);

        // Should add HasRoles to the existing trait use
        expect($result)->toContain('use Spatie\Permission\Traits\HasRoles;');
        expect($result)->toContain('HasRoles');
    });

    it('reports modifications correctly', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

class User
{
    protected $fillable = ['name'];
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser;
        $visitor = new UserModelVisitor;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        expect($visitor->wasModified())->toBeTrue();
        expect($visitor->hasRolesAdded())->toBeTrue();
        expect($visitor->fillableFieldsAdded())->toBeTrue();
    });

    it('reports no modifications when model is complete', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;

class User
{
    use HasRoles;

    protected $fillable = [
        'name',
        'date_of_birth',
        'civility',
        'profession',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser;
        $visitor = new UserModelVisitor;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        expect($visitor->wasModified())->toBeFalse();
    });

    it('adds date_of_birth cast to $casts property', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

class User
{
    protected $fillable = ['name'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
PHP;

        $result = transformCode($code);

        expect($result)->toContain("'date_of_birth' => 'date'");
    });

    it('adds date_of_birth cast to casts() method', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

class User
{
    protected $fillable = ['name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
PHP;

        $result = transformCode($code);

        expect($result)->toContain("'date_of_birth' => 'date'");
    });

    it('does not duplicate date_of_birth cast in $casts property', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

class User
{
    protected $fillable = ['name'];

    protected $casts = [
        'date_of_birth' => 'date',
    ];
}
PHP;

        $result = transformCode($code);

        expect(substr_count($result, "'date_of_birth' => 'date'"))->toBe(1);
    });

    it('does not duplicate date_of_birth cast in casts() method', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

class User
{
    protected $fillable = ['name'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }
}
PHP;

        $result = transformCode($code);

        expect(substr_count($result, "'date_of_birth' => 'date'"))->toBe(1);
    });

    it('reports castsAdded correctly', function () {
        $code = <<<'PHP'
<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;

class User
{
    use HasRoles;

    protected $fillable = [
        'name',
        'date_of_birth',
        'civility',
        'profession',
    ];

    protected $casts = [];
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser;
        $visitor = new UserModelVisitor;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        expect($visitor->wasModified())->toBeTrue();
        expect($visitor->hasRolesAdded())->toBeFalse();
        expect($visitor->fillableFieldsAdded())->toBeFalse();
        expect($visitor->castsAdded())->toBeTrue();
    });
});

function transformCode(string $code): string
{
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $ast = $parser->parse($code);

    $traverser = new NodeTraverser;
    $visitor = new UserModelVisitor;
    $traverser->addVisitor($visitor);
    $ast = $traverser->traverse($ast);

    $printer = new Standard(['shortArraySyntax' => true]);

    return $printer->prettyPrintFile($ast);
}

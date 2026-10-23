<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Erp\Application\ImportResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.old_shop_migration_step')]
interface MigrationStepInterface
{
    public function name(): string;

    public function run(bool $dryRun): ImportResult;
}

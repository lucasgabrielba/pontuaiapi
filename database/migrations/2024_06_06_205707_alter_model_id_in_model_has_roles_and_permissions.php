<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlterModelIdInModelHasRolesAndPermissions extends Migration
{
    public function up()
    {
        // Altera a coluna model_id para char(26) no padrão ULID
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN model_id TYPE text USING model_id::text');
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN model_id TYPE char(26) USING model_id::text');

        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN model_id TYPE text USING model_id::text');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN model_id TYPE char(26) USING model_id::text');
    }

    public function down()
    {
        // Reverte a coluna model_id para bigint caso necessário
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN model_id TYPE bigint USING model_id::bigint');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN model_id TYPE bigint USING model_id::bigint');
    }
}

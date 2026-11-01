<?php

namespace App\Models;

use Database\Factories\TipoExameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoExame extends Model
{
    /** @use HasFactory<TipoExameFactory> */
    use HasFactory;

    public const EXAME_SANGUE = 'exame_sangue';

    public const CNS = 'cns';

    protected $table = 'tipos_exame';

    protected function casts(): array
    {
        return ['validade_dias' => 'integer', 'exige_numero' => 'boolean', 'ativo' => 'boolean', 'ordem' => 'integer'];
    }
}

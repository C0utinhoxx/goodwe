<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AtendimentoHumano extends Model
{
    use HasFactory;

    protected $table = 'atendimento_humanos';

    protected $fillable = [
        'user_id',
        'mensagem',
        'motivo',
        'status',
    ];
}
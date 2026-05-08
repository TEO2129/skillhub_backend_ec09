<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle FormationVue.
 * Trace les vues uniques par utilisateur par formation.
 */
class FormationVue extends Model
{
    protected $fillable = [
        'formation_id',
        'user_id',
        'ip',
    ];

    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }

    public function utilisateur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

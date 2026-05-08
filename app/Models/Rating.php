<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Rating (notation d'une formation par un apprenant).
 */
class Rating extends Model
{
    protected $fillable = [
        'user_id',
        'formation_id',
        'note',
        'commentaire'
    ];

    protected $casts = [
        'note' => 'integer',
    ];

    /**
     * Relation : une note appartient à un utilisateur (apprenant).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation : une note appartient à une formation.
     */
    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }
}
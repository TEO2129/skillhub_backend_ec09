<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function utilisateur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }
}
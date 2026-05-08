<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Formation.
 */
class Formation extends Model
{
    protected $fillable = [
        'titre',
        'description',
        'categorie',
        'niveau',
        'prix',
        'duree_heures',
        'nombre_de_vues',
        'formateur_id',
    ];

    public function formateur()
    {
        return $this->belongsTo(User::class, 'formateur_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class, 'formation_id')->orderBy('ordre');
    }

    public function inscriptions()
    {
        return $this->hasMany(Inscription::class, 'formation_id');
    }

    public function vues()
    {
        return $this->hasMany(FormationVue::class, 'formation_id');
    }

        /**
     * Relation : une formation a plusieurs notes (ratings).
     */
    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Accesseur : note moyenne arrondie à 1 décimale.
     */
    public function getNoteMoyenneAttribute()
    {
        return round($this->ratings()->avg('note'), 1);
    }

    /**
     * Accesseur : nombre d'avis.
     */
    public function getNombreAvisAttribute()
    {
        return $this->ratings()->count();
    }
}

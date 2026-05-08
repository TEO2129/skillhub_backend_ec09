<?php

namespace Tests\Feature;

use App\Models\Formation;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    protected $apprenant;
    protected $formateur;
    protected $formation;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Création du formateur
        $this->formateur = User::create([
            'nom' => 'Formateur Test',
            'email' => 'formateur@test.com',
            'password' => bcrypt('password123'),
            'role' => 'formateur'
        ]);

        // Création de la formation
        $this->formation = Formation::create([
            'titre' => 'Formation test',
            'description' => 'Description test',
            'categorie' => 'developpement_web',
            'niveau' => 'debutant',
            'prix' => 0,
            'duree_heures' => 10,
            'nombre_de_vues' => 0,
            'formateur_id' => $this->formateur->id
        ]);

        // Création de l'apprenant
        $this->apprenant = User::create([
            'nom' => 'Apprenant Test',
            'email' => 'apprenant@test.com',
            'password' => bcrypt('password123'),
            'role' => 'apprenant'
        ]);
        $this->token = JWTAuth::fromUser($this->apprenant);

        // Inscription de l'apprenant à la formation (avec le bon nom de colonne)
        // La colonne s'appelle 'user_id' (pas 'user_id')
        \DB::table('inscriptions')->insert([
            'formation_id' => $this->formation->id,
            'user_id' => $this->apprenant->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /** @test */
    public function un_apprenant_inscrit_peut_noter_une_formation()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/formations/{$this->formation->id}/noter", [
                'note' => 5,
                'commentaire' => 'Très bonne formation'
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'user_id', 'formation_id', 'note', 'commentaire']);

        $this->assertDatabaseHas('ratings', [
            'user_id' => $this->apprenant->id,
            'formation_id' => $this->formation->id,
            'note' => 5,
            'commentaire' => 'Très bonne formation'
        ]);
    }

    /** @test */
    public function un_apprenant_ne_peut_pas_noter_deux_fois()
    {
        Rating::create([
            'user_id' => $this->apprenant->id,
            'formation_id' => $this->formation->id,
            'note' => 4,
            'commentaire' => 'Bien'
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/formations/{$this->formation->id}/noter", [
                'note' => 5,
                'commentaire' => 'Encore mieux'
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Vous avez déjà noté cette formation']);
    }

    /** @test */
    public function une_note_hors_intervalle_retourne_erreur()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/formations/{$this->formation->id}/noter", [
                'note' => 6,
                'commentaire' => 'Note invalide'
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function un_apprenant_non_inscrit_ne_peut_pas_noter()
    {
        $autreApprenant = User::create([
            'nom' => 'Autre Apprenant',
            'email' => 'autre@test.com',
            'password' => bcrypt('password123'),
            'role' => 'apprenant'
        ]);
        $autreToken = JWTAuth::fromUser($autreApprenant);

        $response = $this->withHeader('Authorization', "Bearer {$autreToken}")
            ->postJson("/api/formations/{$this->formation->id}/noter", [
                'note' => 4,
                'commentaire' => 'Je ne suis pas inscrit'
            ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Vous devez être inscrit à cette formation pour la noter']);
    }

    /** @test */
    public function requete_sans_token_retourne_401()
    {
        $response = $this->postJson("/api/formations/{$this->formation->id}/noter", [
            'note' => 4,
            'commentaire' => 'Pas de token'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function la_formation_inclut_note_moyenne_et_nombre_avis()
    {
        Rating::create([
            'user_id' => $this->apprenant->id,
            'formation_id' => $this->formation->id,
            'note' => 4,
            'commentaire' => 'Bien'
        ]);

        $autreApprenant = User::create([
            'nom' => 'Deuxieme Apprenant',
            'email' => 'deuxieme@test.com',
            'password' => bcrypt('password123'),
            'role' => 'apprenant'
        ]);

        // Inscription du deuxième apprenant
        \DB::table('inscriptions')->insert([
            'formation_id' => $this->formation->id,
            'user_id' => $autreApprenant->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        Rating::create([
            'user_id' => $autreApprenant->id,
            'formation_id' => $this->formation->id,
            'note' => 5,
            'commentaire' => 'Excellent'
        ]);

        $response = $this->getJson("/api/formations/{$this->formation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->formation->id,
                'note_moyenne' => 4.5,
                'nombre_avis' => 2
            ]);
    }
}
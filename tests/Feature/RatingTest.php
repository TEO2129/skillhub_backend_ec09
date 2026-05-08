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

        // Création formateur
        $this->formateur = User::factory()->create([
            'role' => 'formateur'
        ]);

        // Création formation
        $this->formation = Formation::factory()->create([
            'formateur_id' => $this->formateur->id,
            'titre' => 'Formation test',
            'description' => 'Description test',
            'categorie' => 'developpement_web',
            'niveau' => 'debutant'
        ]);

        // Création apprenant
        $this->apprenant = User::factory()->create([
            'role' => 'apprenant'
        ]);
        $this->token = JWTAuth::fromUser($this->apprenant);

        // Inscription de l'apprenant à la formation
        $this->formation->inscriptions()->create([
            'user_id' => $this->apprenant->id,
            'formation_id' => $this->formation->id
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
        $autreApprenant = User::factory()->create(['role' => 'apprenant']);
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

        $autreApprenant = User::factory()->create(['role' => 'apprenant']);
        $this->formation->inscriptions()->create([
            'user_id' => $autreApprenant->id,
            'formation_id' => $this->formation->id
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
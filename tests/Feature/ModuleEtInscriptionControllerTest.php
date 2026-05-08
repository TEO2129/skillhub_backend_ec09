<?php

namespace Tests\Feature;

use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Tests fonctionnels des contrôleurs Modules et Inscriptions.
 * Couvre : CRUD modules, inscription/désinscription, progression, mesFormations.
 */
class ModuleEtInscriptionControllerTest extends TestCase
{
    use RefreshDatabase;


    // Helpers


    private function creerUser(string $role): array
    {
        $user  = User::create([
            'nom'      => ucfirst($role) . ' Test',
            'email'    => $role . uniqid() . '@test.com',
            'password' => bcrypt('password123'),
            'role'     => $role,
        ]);
        $token = JWTAuth::fromUser($user);

        return ['user' => $user, 'token' => $token];
    }

    private function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function creerFormation(User $formateur, array $overrides = []): Formation
    {
        return Formation::create(array_merge([
            'titre'          => 'Formation Test',
            'description'    => 'Description test',
            'categorie'      => 'developpement_web',
            'niveau'         => 'debutant',
            'nombre_de_vues' => 0,
            'formateur_id'   => $formateur->id,
        ], $overrides));
    }

    private function creerModule(Formation $formation, int $ordre = 1): Module
    {
        return Module::create([
            'titre'        => 'Module ' . $ordre,
            'contenu'      => 'Contenu ' . $ordre,
            'ordre'        => $ordre,
            'formation_id' => $formation->id,
        ]);
    }

    private function inscrire(User $apprenant, Formation $formation): Inscription
    {
        return Inscription::create([
            'user_id' => $apprenant->id,
            'formation_id'   => $formation->id,
            'progression'    => 0,
        ]);
    }


    // MODULE CONTROLLER



    // GET /formations/{id}/modules (index)


    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_index_retourne_liste_triee_par_ordre(): void
    {
        ['user' => $formateur] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);
        $this->creerModule($formation, 2);
        $this->creerModule($formation, 1);

        $response = $this->getJson('/api/formations/' . $formation->id . '/modules');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(2, $data);
        $this->assertEquals(1, $data[0]['ordre']);
        $this->assertEquals(2, $data[1]['ordre']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_index_retourne_tableau_vide_si_aucun_module(): void
    {
        ['user' => $formateur] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->getJson('/api/formations/' . $formation->id . '/modules');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json());
    }


    // POST /formations/{id}/modules (store)


    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_store_cree_un_module_pour_formateur_proprietaire(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson(
            '/api/formations/' . $formation->id . '/modules',
            ['titre' => 'Intro PHP', 'contenu' => 'Variables et types', 'ordre' => 1],
            $this->authHeaders($token)
        );

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'module']);

        $this->assertDatabaseHas('modules', ['titre' => 'Intro PHP']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_store_echoue_pour_apprenant(): void
    {
        ['user' => $formateur]  = $this->creerUser('formateur');
        ['token' => $token]     = $this->creerUser('apprenant');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson(
            '/api/formations/' . $formation->id . '/modules',
            ['titre' => 'Module', 'contenu' => 'Contenu', 'ordre' => 1],
            $this->authHeaders($token)
        );

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_store_echoue_pour_autre_formateur(): void
    {
        ['user' => $formateur1]  = $this->creerUser('formateur');
        ['token' => $token2]     = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur1);

        $response = $this->postJson(
            '/api/formations/' . $formation->id . '/modules',
            ['titre' => 'Module', 'contenu' => 'Contenu', 'ordre' => 1],
            $this->authHeaders($token2)
        );

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_store_echoue_sans_token(): void
    {
        ['user' => $formateur] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson(
            '/api/formations/' . $formation->id . '/modules',
            ['titre' => 'Module', 'contenu' => 'Contenu', 'ordre' => 1]
        );

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_store_echoue_si_formation_inexistante(): void
    {
        ['token' => $token] = $this->creerUser('formateur');

        $response = $this->postJson(
            '/api/formations/99999/modules',
            ['titre' => 'Module', 'contenu' => 'Contenu', 'ordre' => 1],
            $this->authHeaders($token)
        );

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_store_echoue_si_champs_manquants(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson(
            '/api/formations/' . $formation->id . '/modules',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['titre', 'contenu', 'ordre']);
    }


    // PUT /modules/{id} (update)


    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_update_modifie_module_par_formateur_proprietaire(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        $response = $this->putJson(
            '/api/modules/' . $module->id,
            ['titre' => 'Titre Modifié', 'contenu' => 'Nouveau contenu', 'ordre' => 1],
            $this->authHeaders($token)
        );

        $response->assertStatus(200)
            ->assertJsonPath('module.titre', 'Titre Modifié');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_update_echoue_pour_autre_formateur(): void
    {
        ['user' => $formateur1]  = $this->creerUser('formateur');
        ['token' => $token2]     = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur1);
        $module    = $this->creerModule($formation);

        $response = $this->putJson(
            '/api/modules/' . $module->id,
            ['titre' => 'Modifié', 'contenu' => 'Contenu', 'ordre' => 1],
            $this->authHeaders($token2)
        );

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_update_echoue_si_module_inexistant(): void
    {
        ['token' => $token] = $this->creerUser('formateur');

        $response = $this->putJson(
            '/api/modules/99999',
            ['titre' => 'Modifié', 'contenu' => 'Contenu', 'ordre' => 1],
            $this->authHeaders($token)
        );

        $response->assertStatus(404);
    }


    // DELETE /modules/{id} (destroy)


    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_destroy_supprime_module_par_formateur(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        $response = $this->deleteJson(
            '/api/modules/' . $module->id,
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('modules', ['id' => $module->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_destroy_echoue_pour_apprenant(): void
    {
        ['user' => $formateur]  = $this->creerUser('formateur');
        ['token' => $token]     = $this->creerUser('apprenant');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        $response = $this->deleteJson(
            '/api/modules/' . $module->id,
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(403);
    }


    // POST /modules/{id}/terminer


    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_terminer_met_a_jour_la_progression(): void
    {
        ['user' => $formateur]                     = $this->creerUser('formateur');
        ['user' => $apprenant, 'token' => $token]  = $this->creerUser('apprenant');
        $formation  = $this->creerFormation($formateur);
        $module     = $this->creerModule($formation, 1);
        $this->creerModule($formation, 2);
        $this->inscrire($apprenant, $formation);

        $response = $this->postJson(
            '/api/modules/' . $module->id . '/terminer',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(200)
            ->assertJsonFragment(['progression' => 50]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_terminer_echoue_si_non_inscrit(): void
    {
        ['user' => $formateur]                     = $this->creerUser('formateur');
        ['user' => $apprenant, 'token' => $token]  = $this->creerUser('apprenant');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        $response = $this->postJson(
            '/api/modules/' . $module->id . '/terminer',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_terminer_echoue_si_module_inexistant(): void
    {
        ['token' => $token] = $this->creerUser('apprenant');

        $response = $this->postJson(
            '/api/modules/99999/terminer',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_terminer_retourne_message_si_deja_termine(): void
    {
        ['user' => $formateur]                     = $this->creerUser('formateur');
        ['user' => $apprenant, 'token' => $token]  = $this->creerUser('apprenant');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);
        $this->inscrire($apprenant, $formation);

        $this->postJson('/api/modules/' . $module->id . '/terminer', [], $this->authHeaders($token));
        $response = $this->postJson('/api/modules/' . $module->id . '/terminer', [], $this->authHeaders($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Ce module est déjà terminé']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modules_terminer_echoue_pour_formateur(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        $response = $this->postJson(
            '/api/modules/' . $module->id . '/terminer',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(403);
    }


    // INSCRIPTION CONTROLLER



    // POST /formations/{id}/inscription (store)


    #[\PHPUnit\Framework\Attributes\Test]
    public function inscription_store_inscrit_apprenant_a_formation(): void
    {
        ['user' => $formateur]                     = $this->creerUser('formateur');
        ['user' => $apprenant, 'token' => $token]  = $this->creerUser('apprenant');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson(
            '/api/formations/' . $formation->id . '/inscription',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'inscription']);

        $this->assertDatabaseHas('inscriptions', [
            'user_id' => $apprenant->id,
            'formation_id'   => $formation->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function inscription_store_echoue_pour_formateur(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson(
            '/api/formations/' . $formation->id . '/inscription',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function inscription_store_echoue_si_deja_inscrit(): void
    {
        ['user' => $formateur]                     = $this->creerUser('formateur');
        ['user' => $apprenant, 'token' => $token]  = $this->creerUser('apprenant');
        $formation = $this->creerFormation($formateur);
        $this->inscrire($apprenant, $formation);

        $response = $this->postJson(
            '/api/formations/' . $formation->id . '/inscription',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(409);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function inscription_store_echoue_si_formation_inexistante(): void
    {
        ['token' => $token] = $this->creerUser('apprenant');

        $response = $this->postJson('/api/formations/99999/inscription', [], $this->authHeaders($token));

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function inscription_store_echoue_sans_token(): void
    {
        ['user' => $formateur] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson('/api/formations/' . $formation->id . '/inscription');

        $response->assertStatus(401);
    }


    // DELETE /formations/{id}/inscription (destroy)


    #[\PHPUnit\Framework\Attributes\Test]
    public function inscription_destroy_desincrit_apprenant(): void
    {
        ['user' => $formateur]                     = $this->creerUser('formateur');
        ['user' => $apprenant, 'token' => $token]  = $this->creerUser('apprenant');
        $formation = $this->creerFormation($formateur);
        $this->inscrire($apprenant, $formation);

        $response = $this->deleteJson(
            '/api/formations/' . $formation->id . '/inscription',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Désinscription réussie']);

        $this->assertDatabaseMissing('inscriptions', [
            'user_id' => $apprenant->id,
            'formation_id'   => $formation->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function inscription_destroy_echoue_si_non_inscrit(): void
    {
        ['user' => $formateur]                     = $this->creerUser('formateur');
        ['token' => $token]                        = $this->creerUser('apprenant');
        $formation = $this->creerFormation($formateur);

        $response = $this->deleteJson(
            '/api/formations/' . $formation->id . '/inscription',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function inscription_destroy_echoue_pour_formateur(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUser('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->deleteJson(
            '/api/formations/' . $formation->id . '/inscription',
            [],
            $this->authHeaders($token)
        );

        $response->assertStatus(403);
    }


    // GET /apprenant/formations (mesFormations)


    #[\PHPUnit\Framework\Attributes\Test]
    public function mes_formations_retourne_inscriptions_apprenant(): void
    {
        ['user' => $formateur]                     = $this->creerUser('formateur');
        ['user' => $apprenant, 'token' => $token]  = $this->creerUser('apprenant');
        $formation = $this->creerFormation($formateur);
        $this->inscrire($apprenant, $formation);

        $response = $this->getJson('/api/apprenant/formations', $this->authHeaders($token));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mes_formations_echoue_pour_formateur(): void
    {
        ['token' => $token] = $this->creerUser('formateur');

        $response = $this->getJson('/api/apprenant/formations', $this->authHeaders($token));

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mes_formations_echoue_sans_token(): void
    {
        $response = $this->getJson('/api/apprenant/formations');

        $response->assertStatus(401);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\FormationVue;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Rating;

/**
 * Controleur de gestion des formations.
 */
class FormationController extends Controller
{
    private const MSG_TOKEN_INVALIDE   = 'Token invalide ou absent';
    private const MSG_USER_NON_TROUVE  = 'Utilisateur non trouvé';
    private const MSG_FORMATION_INTRO  = 'Formation introuvable';

    /**
     * Liste des formations avec filtres optionnels.
     * Route : GET /formations
     */
    public function index(Request $request): JsonResponse
    {
        $query = Formation::with('formateur:id,nom,email')
            ->withCount('inscriptions');

        if ($request->filled('recherche')) {
            $motCle = $request->input('recherche');
            $query->where(function ($q) use ($motCle) {
                $q->where('titre', 'like', '%' . $motCle . '%')
                    ->orWhere('description', 'like', '%' . $motCle . '%');
            });
        }

        if ($request->filled('categorie')) {
            $query->where('categorie', $request->input('categorie'));
        }

        if ($request->filled('niveau')) {
            $query->where('niveau', $request->input('niveau'));
        }

        return response()->json($query->get());
    }

        /**
     * Afficher une formation et incrementer ses vues de facon unique.
     * Route : GET /formations/{id}
     */
    public function show(Request $request, $id): JsonResponse
    {
        $formation = Formation::with('formateur:id,nom,email')
            ->withCount('inscriptions')
            ->find($id);

        if (! $formation) {
            return response()->json(['message' => self::MSG_FORMATION_INTRO], 404);
        }

        $user_id = null;
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if ($user) {
                $user_id = $user->id;
            }
        } catch (JWTException $e) {
            // Utilisateur non connecte
        }

        if ($user_id) {
            $dejaVue = FormationVue::where('formation_id', $formation->id)
                ->where('user_id', $user_id)
                ->exists();

            if (! $dejaVue) {
                FormationVue::create([
                    'formation_id'   => $formation->id,
                    'user_id' => $user_id,
                    'ip'             => $request->ip(),
                ]);
                $formation->increment('nombre_de_vues');
            }
        } else {
            $dejaVueIp = FormationVue::where('formation_id', $formation->id)
                ->whereNull('user_id')
                ->where('ip', $request->ip())
                ->exists();

            if (! $dejaVueIp) {
                FormationVue::create([
                    'formation_id'   => $formation->id,
                    'user_id' => null,
                    'ip'             => $request->ip(),
                ]);
                $formation->increment('nombre_de_vues');
            }
        }

        ActivityLogService::consultationFormation($formation->id, $user_id);

        // Récupérer la formation avec les attributs calculés
        $formation = $formation->fresh(['formateur:id,nom,email']);
        
        // Ajouter les attributs de notation
        $formation->note_moyenne = $formation->note_moyenne;
        $formation->nombre_avis = $formation->nombre_avis;

        return response()->json($formation);
    }

    /**
     * Creer une nouvelle formation.
     * Route : POST /formations
     */
    public function store(Request $request): JsonResponse
    {
        $reponse = response()->json(['message' => self::MSG_TOKEN_INVALIDE], 401);

        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                $reponse = response()->json(['message' => self::MSG_USER_NON_TROUVE], 404);
            } elseif ($user->role !== 'formateur') {
                $reponse = response()->json(['message' => 'Seul un formateur peut créer une formation'], 403);
            } else {
                $request->validate([
                    'titre'        => 'required|string|max:255',
                    'description'  => 'required|string',
                    'categorie'    => 'required|in:developpement_web,data,design,marketing,devops,autre',
                    'niveau'       => 'required|in:debutant,intermediaire,avance',
                    'prix'         => 'nullable|numeric|min:0',
                    'duree_heures' => 'nullable|integer|min:0',
                ]);

                $formation = Formation::create([
                    'titre'          => $request->titre,
                    'description'    => $request->description,
                    'categorie'      => $request->categorie,
                    'niveau'         => $request->niveau,
                    'prix'           => $request->prix ?? 0,
                    'duree_heures'   => $request->duree_heures ?? 0,
                    'nombre_de_vues' => 0,
                    'formateur_id'   => $user->id,
                ]);

                ActivityLogService::creationFormation($formation->id, $user->id);

                $reponse = response()->json([
                    'message'   => 'Formation créée avec succès',
                    'formation' => $formation,
                ], 201);
            }
        } catch (JWTException $e) {
            // reponse 401 deja definie
        }

        return $reponse;
    }

    /**
     * Mettre a jour une formation.
     * Route : PUT /formations/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $reponse = response()->json(['message' => self::MSG_TOKEN_INVALIDE], 401);

        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                $reponse = response()->json(['message' => self::MSG_USER_NON_TROUVE], 404);
            } else {
                $formation = Formation::find($id);

                if (! $formation) {
                    $reponse = response()->json(['message' => self::MSG_FORMATION_INTRO], 404);
                } elseif ($user->role !== 'formateur' || $formation->formateur_id !== $user->id) {
                    $reponse = response()->json(['message' => 'Action non autorisée'], 403);
                } else {
                    $oldValues = [
                        'titre'       => $formation->titre,
                        'description' => $formation->description,
                        'categorie'   => $formation->categorie,
                        'niveau'      => $formation->niveau,
                    ];

                    $request->validate([
                        'titre'        => 'required|string|max:255',
                        'description'  => 'required|string',
                        'categorie'    => 'required|in:developpement_web,data,design,marketing,devops,autre',
                        'niveau'       => 'required|in:debutant,intermediaire,avance',
                        'prix'         => 'nullable|numeric|min:0',
                        'duree_heures' => 'nullable|integer|min:0',
                    ]);

                    $formation->update([
                        'titre'        => $request->titre,
                        'description'  => $request->description,
                        'categorie'    => $request->categorie,
                        'niveau'       => $request->niveau,
                        'prix'         => $request->prix ?? $formation->prix,
                        'duree_heures' => $request->duree_heures ?? $formation->duree_heures,
                    ]);

                    ActivityLogService::modificationFormation(
                        $formation->id,
                        $user->id,
                        $oldValues,
                        [
                            'titre'       => $request->titre,
                            'description' => $request->description,
                            'categorie'   => $request->categorie,
                            'niveau'      => $request->niveau,
                        ]
                    );

                    $reponse = response()->json([
                        'message'   => 'Formation mise à jour avec succès',
                        'formation' => $formation,
                    ]);
                }
            }
        } catch (JWTException $e) {
            // reponse 401 deja definie
        }

        return $reponse;
    }

    /**
     * Supprimer une formation.
     * Route : DELETE /formations/{id}
     */
    public function destroy($id): JsonResponse
    {
        $reponse = response()->json(['message' => self::MSG_TOKEN_INVALIDE], 401);

        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                $reponse = response()->json(['message' => self::MSG_USER_NON_TROUVE], 404);
            } else {
                $formation = Formation::find($id);

                if (! $formation) {
                    $reponse = response()->json(['message' => self::MSG_FORMATION_INTRO], 404);
                } elseif ($user->role !== 'formateur' || $formation->formateur_id !== $user->id) {
                    $reponse = response()->json(['message' => 'Action non autorisée'], 403);
                } else {
                    ActivityLogService::suppressionFormation($formation->id, $user->id);
                    $formation->delete();
                    $reponse = response()->json(['message' => 'Formation supprimée avec succès']);
                }
            }
        } catch (JWTException $e) {
            // reponse 401 deja definie
        }

        return $reponse;
    }

        /**
     * Noter une formation (apprenant authentifié et inscrit uniquement).
     * Route : POST /api/formations/{id}/noter
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function noter(Request $request, $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json(['message' => self::MSG_USER_NON_TROUVE], 404);
            }

            if ($user->role !== 'apprenant') {
                return response()->json(['error' => 'Seuls les apprenants peuvent noter'], 403);
            }

            $formation = Formation::find($id);
            if (!$formation) {
                return response()->json(['message' => self::MSG_FORMATION_INTRO], 404);
            }

            // Vérifier que l'apprenant est inscrit à la formation
            $estInscrit = $formation->inscriptions()
                ->where('user_id', $user->id)
                ->exists();

            if (!$estInscrit) {
                return response()->json(['error' => 'Vous devez être inscrit à cette formation pour la noter'], 403);
            }

            // Validation des données
            $request->validate([
                'note' => 'required|integer|min:1|max:5',
                'commentaire' => 'nullable|string|max:1000'
            ]);

            // Vérifier qu'il n'a pas déjà noté
            $dejaNote = Rating::where('user_id', $user->id)
                ->where('formation_id', $formation->id)
                ->exists();

            if ($dejaNote) {
                return response()->json(['error' => 'Vous avez déjà noté cette formation'], 400);
            }

            // Création de la note
            $rating = Rating::create([
                'user_id' => $user->id,
                'formation_id' => $formation->id,
                'note' => $request->note,
                'commentaire' => $request->commentaire
            ]);

            return response()->json($rating, 201);

        } catch (JWTException $e) {
            return response()->json(['message' => self::MSG_TOKEN_INVALIDE], 401);
        }
    }
}

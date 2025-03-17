<?php
// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: index.php?page=login');
    exit;
}

// Récupérer les informations de l'utilisateur connecté
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Initialisation des variables
$enfants = [];
$groupesAge = [];
$message = '';
$errors = [];
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$enfantId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupérer la liste des groupes d'âge
try {
    global $db;
    $stmt = $db->query("SELECT id, nom, age_min, age_max, capacite FROM groupes_age ORDER BY age_min");
    $groupesAge = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des groupes d'âge: " . $e->getMessage());
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $formAction = $_POST['action'];
        
        // Ajout d'un enfant
        if ($formAction === 'add') {
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $dateNaissance = $_POST['date_naissance'] ?? '';
            $sexe = $_POST['sexe'] ?? '';
            $infosSante = trim($_POST['infos_sante'] ?? '');
            
            // Validation
            if (empty($nom) || empty($prenom) || empty($dateNaissance) || empty($sexe)) {
                $errors[] = "Tous les champs marqués d'un astérisque sont obligatoires.";
            }
            
            if (empty($errors)) {
                try {
                    // Insertion de l'enfant
                    $query = "INSERT INTO enfants (nom, prenom, date_naissance, sexe, infos_sante, created_at)
                              VALUES (:nom, :prenom, :date_naissance, :sexe, :infos_sante, NOW())";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'date_naissance' => $dateNaissance,
                        'sexe' => $sexe,
                        'infos_sante' => $infosSante
                    ]);
                    
                    if ($result) {
                        // Récupérer l'ID de l'enfant nouvellement créé
                        $enfantId = $db->lastInsertId();
                        
                        // Associer l'enfant au parent
                        if ($userRole === 'parent') {
                            $query = "INSERT INTO parents_enfants (parent_id, enfant_id) VALUES (:parent_id, :enfant_id)";
                            $stmt = $db->prepare($query);
                            $stmt->execute([
                                'parent_id' => $userId,
                                'enfant_id' => $enfantId
                            ]);
                        }
                        
                        setFlashMessage('success', 'L\'enfant a été ajouté avec succès.');
                        header('Location: index.php?page=enfants');
                        exit;
                    } else {
                        $errors[] = "Une erreur est survenue lors de l'ajout de l'enfant.";
                    }
                } catch (PDOException $e) {
                    $errors[] = "Erreur de base de données : " . $e->getMessage();
                }
            }
        }
        // Modification d'un enfant
        elseif ($formAction === 'edit' && $enfantId > 0) {
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $dateNaissance = $_POST['date_naissance'] ?? '';
            $sexe = $_POST['sexe'] ?? '';
            $infosSante = trim($_POST['infos_sante'] ?? '');
            
            // Validation
            if (empty($nom) || empty($prenom) || empty($dateNaissance) || empty($sexe)) {
                $errors[] = "Tous les champs marqués d'un astérisque sont obligatoires.";
            }
            
            if (empty($errors)) {
                try {
                    // Vérifier que l'utilisateur a les droits sur cet enfant
                    if ($userRole === 'parent') {
                        $query = "SELECT COUNT(*) FROM parents_enfants 
                                  WHERE parent_id = :parent_id AND enfant_id = :enfant_id";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            'parent_id' => $userId,
                            'enfant_id' => $enfantId
                        ]);
                        
                        if ($stmt->fetchColumn() === 0) {
                            setFlashMessage('danger', 'Vous n\'avez pas les droits pour modifier cet enfant.');
                            header('Location: index.php?page=enfants');
                            exit;
                        }
                    }
                    
                    // Mise à jour de l'enfant
                    $query = "UPDATE enfants 
                              SET nom = :nom, prenom = :prenom, date_naissance = :date_naissance, 
                                  sexe = :sexe, infos_sante = :infos_sante, updated_at = NOW()
                              WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'date_naissance' => $dateNaissance,
                        'sexe' => $sexe,
                        'infos_sante' => $infosSante,
                        'id' => $enfantId
                    ]);
                    
                    if ($result) {
                        setFlashMessage('success', 'L\'enfant a été modifié avec succès.');
                        header('Location: index.php?page=enfants');
                        exit;
                    } else {
                        $errors[] = "Une erreur est survenue lors de la modification de l'enfant.";
                    }
                } catch (PDOException $e) {
                    $errors[] = "Erreur de base de données : " . $e->getMessage();
                }
            }
        }
        // Suppression d'un enfant
        elseif ($formAction === 'delete' && $enfantId > 0) {
            try {
                // Vérifier que l'utilisateur a les droits sur cet enfant
                if ($userRole === 'parent') {
                    $query = "SELECT COUNT(*) FROM parents_enfants 
                              WHERE parent_id = :parent_id AND enfant_id = :enfant_id";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'parent_id' => $userId,
                        'enfant_id' => $enfantId
                    ]);
                    
                    if ($stmt->fetchColumn() === 0) {
                        setFlashMessage('danger', 'Vous n\'avez pas les droits pour supprimer cet enfant.');
                        header('Location: index.php?page=enfants');
                        exit;
                    }
                }
                
                // Vérifier si l'enfant a des inscriptions
                $query = "SELECT COUNT(*) FROM inscriptions_occasionnelles WHERE enfant_id = :enfant_id";
                $stmt = $db->prepare($query);
                $stmt->execute(['enfant_id' => $enfantId]);
                
                if ($stmt->fetchColumn() > 0) {
                    setFlashMessage('warning', 'Impossible de supprimer cet enfant car il a des inscriptions associées.');
                    header('Location: index.php?page=enfants');
                    exit;
                }
                
                // Supprimer les relations parent-enfant
                $query = "DELETE FROM parents_enfants WHERE enfant_id = :enfant_id";
                $stmt = $db->prepare($query);
                $stmt->execute(['enfant_id' => $enfantId]);
                
                // Supprimer l'enfant
                $query = "DELETE FROM enfants WHERE id = :id";
                $stmt = $db->prepare($query);
                $result = $stmt->execute(['id' => $enfantId]);
                
                if ($result) {
                    setFlashMessage('success', 'L\'enfant a été supprimé avec succès.');
                    header('Location: index.php?page=enfants');
                    exit;
                } else {
                    $errors[] = "Une erreur est survenue lors de la suppression de l'enfant.";
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur de base de données : " . $e->getMessage();
            }
        }
    }
}

// Récupérer les enfants selon le rôle de l'utilisateur
try {
    if ($userRole === 'parent') {
        // Pour les parents, récupérer uniquement leurs enfants
        $query = "SELECT e.*, 
                  TIMESTAMPDIFF(MONTH, e.date_naissance, CURDATE()) as age_mois
                  FROM enfants e
                  JOIN parents_enfants pe ON e.id = pe.enfant_id
                  WHERE pe.parent_id = :parent_id
                  ORDER BY e.prenom, e.nom";
        $stmt = $db->prepare($query);
        $stmt->execute(['parent_id' => $userId]);
    } else {
        // Pour le personnel/admin, récupérer tous les enfants
        $query = "SELECT e.*, 
                  TIMESTAMPDIFF(MONTH, e.date_naissance, CURDATE()) as age_mois
                  FROM enfants e
                  ORDER BY e.prenom, e.nom";
        $stmt = $db->query($query);
    }
    
    $enfants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des enfants: " . $e->getMessage());
}

// Pour l'édition, récupérer les détails de l'enfant
$enfant = [];
if ($action === 'edit' && $enfantId > 0) {
    try {
        $query = "SELECT * FROM enfants WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $enfantId]);
        $enfant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enfant) {
            setFlashMessage('danger', 'Enfant non trouvé.');
            header('Location: index.php?page=enfants');
            exit;
        }
        
        // Vérifier que l'utilisateur a les droits sur cet enfant si c'est un parent
        if ($userRole === 'parent') {
            $query = "SELECT COUNT(*) FROM parents_enfants 
                      WHERE parent_id = :parent_id AND enfant_id = :enfant_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'parent_id' => $userId,
                'enfant_id' => $enfantId
            ]);
            
            if ($stmt->fetchColumn() === 0) {
                setFlashMessage('danger', 'Vous n\'avez pas les droits pour modifier cet enfant.');
                header('Location: index.php?page=enfants');
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des détails de l'enfant: " . $e->getMessage());
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="mb-3">Gestion des enfants</h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="index.php?page=enfants&action=add" class="btn btn-primary">
            <i class="fas fa-plus-circle me-2"></i>Ajouter un enfant
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo escape($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Liste des enfants -->
    <?php if (empty($enfants)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <?php if ($userRole === 'parent'): ?>
                Vous n'avez pas encore ajouté d'enfant. Utilisez le bouton "Ajouter un enfant" pour commencer.
            <?php else: ?>
                Aucun enfant n'est encore enregistré dans le système.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Prénom</th>
                                <th>Nom</th>
                                <th>Date de naissance</th>
                                <th>Âge</th>
                                <th>Sexe</th>
                                <?php if ($userRole !== 'parent'): ?>
                                    <th>Parent(s)</th>
                                <?php endif; ?>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enfants as $enfant): ?>
                                <tr>
                                    <td><?php echo escape($enfant['prenom']); ?></td>
                                    <td><?php echo escape($enfant['nom']); ?></td>
                                    <td>
                                        <?php 
                                        $date = new DateTime($enfant['date_naissance']);
                                        echo $date->format('d/m/Y'); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $ageMois = $enfant['age_mois'];
                                        if ($ageMois < 12) {
                                            echo $ageMois . ' mois';
                                        } else {
                                            $annees = floor($ageMois / 12);
                                            $mois = $ageMois % 12;
                                            echo $annees . ' an' . ($annees > 1 ? 's' : '');
                                            if ($mois > 0) {
                                                echo ' et ' . $mois . ' mois';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $enfant['sexe'] === 'M' ? 'Garçon' : 'Fille'; ?></td>
                                    
                                    <?php if ($userRole !== 'parent'): ?>
                                        <td>
                                            <?php
                                            try {
                                                $query = "SELECT u.prenom, u.nom
                                                          FROM users u
                                                          JOIN parents_enfants pe ON u.id = pe.parent_id
                                                          WHERE pe.enfant_id = :enfant_id";
                                                $stmt = $db->prepare($query);
                                                $stmt->execute(['enfant_id' => $enfant['id']]);
                                                $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                if (!empty($parents)) {
                                                    foreach ($parents as $key => $parent) {
                                                        echo escape($parent['prenom'] . ' ' . $parent['nom']);
                                                        if ($key < count($parents) - 1) {
                                                            echo ', ';
                                                        }
                                                    }
                                                } else {
                                                    echo '<span class="text-muted">Non assigné</span>';
                                                }
                                            } catch (PDOException $e) {
                                                echo '<span class="text-danger">Erreur</span>';
                                                error_log("Erreur lors de la récupération des parents: " . $e->getMessage());
                                            }
                                            ?>
                                        </td>
                                    <?php endif; ?>
                                    
                                    <td class="text-end">
                                        <a href="index.php?page=enfants&action=edit&id=<?php echo $enfant['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteModal"
                                                data-enfant-id="<?php echo $enfant['id']; ?>"
                                                data-enfant-nom="<?php echo escape($enfant['prenom'] . ' ' . $enfant['nom']); ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Modal de confirmation de suppression -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Êtes-vous sûr de vouloir supprimer l'enfant <span id="enfantNom"></span> ?</p>
                        <p class="text-danger"><strong>Attention :</strong> Cette action est irréversible.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <form method="post" action="index.php?page=enfants">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" id="deleteEnfantId" value="">
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            // Script pour remplir la modal de suppression
            document.addEventListener('DOMContentLoaded', function() {
                var deleteModal = document.getElementById('deleteModal');
                if (deleteModal) {
                    deleteModal.addEventListener('show.bs.modal', function(event) {
                        var button = event.relatedTarget;
                        var enfantId = button.getAttribute('data-enfant-id');
                        var enfantNom = button.getAttribute('data-enfant-nom');
                        
                        var modalEnfantNom = document.getElementById('enfantNom');
                        var modalEnfantId = document.getElementById('deleteEnfantId');
                        
                        modalEnfantNom.textContent = enfantNom;
                        modalEnfantId.value = enfantId;
                    });
                }
            });
        </script>
    <?php endif; ?>
    
<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Formulaire d'ajout/modification d'un enfant -->
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <?php echo $action === 'add' ? 'Ajouter un enfant' : 'Modifier un enfant'; ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="post" action="index.php?page=enfants<?php echo $action === 'edit' ? '&action=edit&id=' . $enfantId : ''; ?>">
                <input type="hidden" name="action" value="<?php echo $action; ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $enfantId; ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="prenom" class="form-label">Prénom *</label>
                        <input type="text" class="form-control" id="prenom" name="prenom" 
                               value="<?php echo $action === 'edit' ? escape($enfant['prenom']) : ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="nom" name="nom" 
                               value="<?php echo $action === 'edit' ? escape($enfant['nom']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="date_naissance" class="form-label">Date de naissance *</label>
                        <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                               value="<?php echo $action === 'edit' ? $enfant['date_naissance'] : ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="sexe" class="form-label">Sexe *</label>
                        <select class="form-select" id="sexe" name="sexe" required>
                            <option value="">Sélectionnez...</option>
                            <option value="M" <?php echo ($action === 'edit' && $enfant['sexe'] === 'M') ? 'selected' : ''; ?>>Garçon</option>
                            <option value="F" <?php echo ($action === 'edit' && $enfant['sexe'] === 'F') ? 'selected' : ''; ?>>Fille</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="infos_sante" class="form-label">Informations de santé</label>
                    <textarea class="form-control" id="infos_sante" name="infos_sante" rows="3"><?php echo $action === 'edit' ? escape($enfant['infos_sante']) : ''; ?></textarea>
                    <div class="form-text">Allergies, traitements médicaux, ou autres informations importantes.</div>
                </div>
                
                <div class="form-text mb-3">
                    * Champs obligatoires
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="index.php?page=enfants" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retour
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'add' ? 'Ajouter' : 'Enregistrer les modifications'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

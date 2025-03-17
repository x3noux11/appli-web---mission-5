<?php
// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    addFlashMessage('error', 'Vous devez être connecté pour accéder à cette page.');
    redirect('index.php?page=login');
}

// Récupérer les enfants de l'utilisateur si c'est un parent
$enfants = [];
if (isParent()) {
    $stmt = $db->prepare("
        SELECT e.id, e.nom, e.prenom, e.date_naissance
        FROM enfants e
        JOIN parents_enfants pe ON e.id = pe.enfant_id
        WHERE pe.parent_id = :parent_id
    ");
    $stmt->execute(['parent_id' => $_SESSION['user_id']]);
    $enfants = $stmt->fetchAll();
} else {
    // Si c'est un administrateur ou membre du personnel, afficher tous les enfants
    $stmt = $db->query("
        SELECT e.id, e.nom, e.prenom, e.date_naissance
        FROM enfants e
        ORDER BY e.nom, e.prenom
    ");
    $enfants = $stmt->fetchAll();
}

// Récupérer les groupes d'âge
$stmtGroupes = $db->query("SELECT id, nom, age_min, age_max FROM groupes_age ORDER BY age_min");
$groupesAge = $stmtGroupes->fetchAll();

// Traitement du formulaire d'inscription occasionnelle
if (isFormSubmitted() && isset($_POST['action']) && $_POST['action'] === 'inscription_occasionnelle') {
    $enfantId = $_POST['enfant_id'] ?? null;
    $dateGarde = $_POST['date_garde'] ?? null;
    $heureDebut = $_POST['heure_debut'] ?? null;
    $heureFin = $_POST['heure_fin'] ?? null;
    $groupeAgeId = $_POST['groupe_age_id'] ?? null;
    $errors = [];
    
    // Validation des champs
    if (empty($enfantId)) {
        $errors[] = "Veuillez sélectionner un enfant.";
    }
    
    if (empty($dateGarde)) {
        $errors[] = "La date de garde est obligatoire.";
    } else {
        // Vérifier que la date est dans le futur
        $dateGardeObj = new DateTime($dateGarde);
        $now = new DateTime();
        $now->modify('+24 hours'); // Au moins 24h à l'avance
        
        if ($dateGardeObj < $now) {
            $errors[] = "La date de garde doit être au moins 24h dans le futur.";
        }
    }
    
    if (empty($heureDebut)) {
        $errors[] = "L'heure de début est obligatoire.";
    }
    
    if (empty($heureFin)) {
        $errors[] = "L'heure de fin est obligatoire.";
    } elseif ($heureDebut >= $heureFin) {
        $errors[] = "L'heure de fin doit être postérieure à l'heure de début.";
    }
    
    if (empty($groupeAgeId)) {
        $errors[] = "Veuillez sélectionner un groupe d'âge.";
    } else {
        // Vérifier que l'enfant a l'âge approprié pour le groupe
        $stmtEnfant = $db->prepare("SELECT date_naissance FROM enfants WHERE id = :id");
        $stmtEnfant->execute(['id' => $enfantId]);
        $dateNaissance = $stmtEnfant->fetchColumn();
        
        if ($dateNaissance) {
            $ageMois = calculerAgeMois($dateNaissance);
            
            $stmtGroupe = $db->prepare("SELECT age_min, age_max FROM groupes_age WHERE id = :id");
            $stmtGroupe->execute(['id' => $groupeAgeId]);
            $groupe = $stmtGroupe->fetch();
            
            if ($groupe && ($ageMois < $groupe['age_min'] || $ageMois > $groupe['age_max'])) {
                $errors[] = "L'âge de l'enfant ne correspond pas au groupe sélectionné.";
            }
        }
    }
    
    // Vérifier la disponibilité du créneau
    if (empty($errors)) {
        if (!verifierDisponibiliteCreneau($db, $dateGarde, $heureDebut, $heureFin, $groupeAgeId)) {
            $errors[] = "Ce créneau n'est pas disponible pour ce groupe d'âge.";
        }
    }
    
    // Si pas d'erreurs, enregistrer l'inscription occasionnelle
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO inscriptions_occasionnelles 
                (enfant_id, date_garde, heure_debut, heure_fin, groupe_age_id, statut)
                VALUES (:enfant_id, :date_garde, :heure_debut, :heure_fin, :groupe_age_id, :statut)
            ");
            
            $stmt->execute([
                'enfant_id' => $enfantId,
                'date_garde' => $dateGarde,
                'heure_debut' => $heureDebut,
                'heure_fin' => $heureFin,
                'groupe_age_id' => $groupeAgeId,
                'statut' => (isAdmin() || isPersonnel()) ? 'confirmee' : 'demande'
            ]);
            
            $message = (isAdmin() || isPersonnel()) 
                ? "L'inscription occasionnelle a été créée avec succès." 
                : "Votre demande d'inscription occasionnelle a été enregistrée et est en attente de confirmation.";
            
            addFlashMessage('success', $message);
            redirect('index.php?page=inscriptions');
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'enregistrement de l'inscription : " . $e->getMessage();
        }
    }
}

// Obtenir les inscriptions régulières de l'utilisateur
$inscriptionsRegulieres = [];
if (isParent()) {
    $stmt = $db->prepare("
        SELECT 
            ir.id,
            e.prenom AS enfant_prenom,
            e.nom AS enfant_nom,
            c.jour_semaine,
            c.heure_debut,
            c.heure_fin,
            ga.nom AS groupe_nom,
            ir.date_debut,
            ir.date_fin,
            ir.statut
        FROM inscriptions_regulieres ir
        JOIN enfants e ON ir.enfant_id = e.id
        JOIN creneaux c ON ir.creneau_id = c.id
        JOIN groupes_age ga ON c.groupe_age_id = ga.id
        JOIN parents_enfants pe ON e.id = pe.enfant_id
        WHERE pe.parent_id = :parent_id
        ORDER BY ir.date_debut DESC
    ");
    $stmt->execute(['parent_id' => $_SESSION['user_id']]);
    $inscriptionsRegulieres = $stmt->fetchAll();
} else {
    $stmt = $db->query("
        SELECT 
            ir.id,
            e.prenom AS enfant_prenom,
            e.nom AS enfant_nom,
            c.jour_semaine,
            c.heure_debut,
            c.heure_fin,
            ga.nom AS groupe_nom,
            ir.date_debut,
            ir.date_fin,
            ir.statut
        FROM inscriptions_regulieres ir
        JOIN enfants e ON ir.enfant_id = e.id
        JOIN creneaux c ON ir.creneau_id = c.id
        JOIN groupes_age ga ON c.groupe_age_id = ga.id
        ORDER BY ir.date_debut DESC
        LIMIT 50
    ");
    $inscriptionsRegulieres = $stmt->fetchAll();
}

// Obtenir les inscriptions occasionnelles de l'utilisateur
$inscriptionsOccasionnelles = [];
if (isParent()) {
    $stmt = $db->prepare("
        SELECT 
            io.id,
            e.prenom AS enfant_prenom,
            e.nom AS enfant_nom,
            io.date_garde,
            io.heure_debut,
            io.heure_fin,
            ga.nom AS groupe_nom,
            io.statut,
            io.created_at
        FROM inscriptions_occasionnelles io
        JOIN enfants e ON io.enfant_id = e.id
        JOIN groupes_age ga ON io.groupe_age_id = ga.id
        JOIN parents_enfants pe ON e.id = pe.enfant_id
        WHERE pe.parent_id = :parent_id
        ORDER BY io.date_garde DESC
    ");
    $stmt->execute(['parent_id' => $_SESSION['user_id']]);
    $inscriptionsOccasionnelles = $stmt->fetchAll();
} else {
    $stmt = $db->query("
        SELECT 
            io.id,
            e.prenom AS enfant_prenom,
            e.nom AS enfant_nom,
            io.date_garde,
            io.heure_debut,
            io.heure_fin,
            ga.nom AS groupe_nom,
            io.statut,
            io.created_at
        FROM inscriptions_occasionnelles io
        JOIN enfants e ON io.enfant_id = e.id
        JOIN groupes_age ga ON io.groupe_age_id = ga.id
        ORDER BY io.date_garde DESC
        LIMIT 50
    ");
    $inscriptionsOccasionnelles = $stmt->fetchAll();
}

// Fonction pour traduire le jour de la semaine
function traduireJour($jour) {
    $traduction = [
        'lundi' => 'Lundi',
        'mardi' => 'Mardi',
        'mercredi' => 'Mercredi',
        'jeudi' => 'Jeudi',
        'vendredi' => 'Vendredi',
        'samedi' => 'Samedi',
        'dimanche' => 'Dimanche'
    ];
    
    return $traduction[$jour] ?? $jour;
}

// Fonction pour traduire le statut
function traduireStatut($statut) {
    $traduction = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'terminee' => 'Terminée',
        'demande' => 'En attente',
        'confirmee' => 'Confirmée',
        'refusee' => 'Refusée'
    ];
    
    return $traduction[$statut] ?? $statut;
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>Gestion des inscriptions</h1>
        <p>Gérez les inscriptions régulières et occasionnelles des enfants à la crèche.</p>
    </div>
    <div class="col-md-4 text-end">
        <?php if (isAdmin() || isPersonnel()): ?>
            <a href="index.php?page=creer-inscription-reguliere" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nouvelle inscription régulière
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($errors) && !empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo escape($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Formulaire d'inscription occasionnelle -->
<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Nouvelle inscription occasionnelle</h5>
    </div>
    <div class="card-body">
        <?php if (empty($enfants)): ?>
            <div class="alert alert-warning">
                Vous n'avez pas encore d'enfant enregistré. 
                <a href="index.php?page=enfants">Ajouter un enfant</a> pour pouvoir faire une inscription.
            </div>
        <?php else: ?>
            <form method="POST" action="index.php?page=inscriptions">
                <input type="hidden" name="action" value="inscription_occasionnelle">
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="enfant_id" class="form-label">Enfant</label>
                        <select class="form-select" id="enfant_id" name="enfant_id" required>
                            <option value="">Sélectionner un enfant</option>
                            <?php foreach ($enfants as $enfant): ?>
                                <option value="<?php echo $enfant['id']; ?>" <?php echo (isset($_POST['enfant_id']) && $_POST['enfant_id'] == $enfant['id']) ? 'selected' : ''; ?>>
                                    <?php echo escape($enfant['prenom'] . ' ' . $enfant['nom']); ?> 
                                    (<?php echo date('d/m/Y', strtotime($enfant['date_naissance'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="groupe_age_id" class="form-label">Groupe d'âge</label>
                        <select class="form-select" id="groupe_age_id" name="groupe_age_id" required>
                            <option value="">Sélectionner un groupe</option>
                            <?php foreach ($groupesAge as $groupe): ?>
                                <option value="<?php echo $groupe['id']; ?>" <?php echo (isset($_POST['groupe_age_id']) && $_POST['groupe_age_id'] == $groupe['id']) ? 'selected' : ''; ?>>
                                    <?php echo escape($groupe['nom']); ?> 
                                    (<?php echo $groupe['age_min']; ?>-<?php echo $groupe['age_max']; ?> mois)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="date_garde" class="form-label">Date de garde</label>
                        <input type="date" class="form-control" id="date_garde" name="date_garde" 
                            min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                            value="<?php echo isset($_POST['date_garde']) ? $_POST['date_garde'] : ''; ?>"
                            required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="heure_debut" class="form-label">Heure d'arrivée</label>
                        <input type="time" class="form-control" id="heure_debut" name="heure_debut" 
                            min="08:00" max="18:00"
                            value="<?php echo isset($_POST['heure_debut']) ? $_POST['heure_debut'] : '08:00'; ?>"
                            required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="heure_fin" class="form-label">Heure de départ</label>
                        <input type="time" class="form-control" id="heure_fin" name="heure_fin" 
                            min="08:30" max="18:30"
                            value="<?php echo isset($_POST['heure_fin']) ? $_POST['heure_fin'] : '18:30'; ?>"
                            required>
                    </div>
                </div>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Demander une inscription occasionnelle
                    </button>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> Les inscriptions occasionnelles doivent être faites au moins 24 heures à l'avance
                    <?php if (!isAdmin() && !isPersonnel()): ?>
                        et sont soumises à validation par l'équipe de la crèche.
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Liste des inscriptions occasionnelles -->
<div class="card shadow mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Inscriptions occasionnelles</h5>
    </div>
    <div class="card-body">
        <?php if (empty($inscriptionsOccasionnelles)): ?>
            <div class="alert alert-info">
                Aucune inscription occasionnelle trouvée.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Enfant</th>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Groupe</th>
                            <th>Statut</th>
                            <th>Demande le</th>
                            <?php if (isAdmin() || isPersonnel()): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inscriptionsOccasionnelles as $inscription): ?>
                            <tr>
                                <td><?php echo escape($inscription['enfant_prenom'] . ' ' . $inscription['enfant_nom']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($inscription['date_garde'])); ?></td>
                                <td><?php echo $inscription['heure_debut'] . ' - ' . $inscription['heure_fin']; ?></td>
                                <td><?php echo escape($inscription['groupe_nom']); ?></td>
                                <td>
                                    <?php
                                    $statusClass = 'bg-secondary';
                                    switch ($inscription['statut']) {
                                        case 'demande':
                                            $statusClass = 'bg-warning';
                                            break;
                                        case 'confirmee':
                                            $statusClass = 'bg-success';
                                            break;
                                        case 'refusee':
                                            $statusClass = 'bg-danger';
                                            break;
                                        case 'terminee':
                                            $statusClass = 'bg-secondary';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo traduireStatut($inscription['statut']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($inscription['created_at'])); ?></td>
                                <?php if (isAdmin() || isPersonnel()): ?>
                                    <td>
                                        <?php if ($inscription['statut'] === 'demande'): ?>
                                            <div class="btn-group btn-group-sm">
                                                <a href="index.php?page=confirmer-inscription&id=<?php echo $inscription['id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="index.php?page=refuser-inscription&id=<?php echo $inscription['id']; ?>" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </div>
                                        <?php elseif ($inscription['statut'] === 'confirmee' && strtotime($inscription['date_garde']) > time()): ?>
                                            <a href="index.php?page=annuler-inscription&id=<?php echo $inscription['id']; ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-ban"></i> Annuler
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Liste des inscriptions régulières -->
<div class="card shadow">
    <div class="card-header bg-white">
        <h5 class="mb-0">Inscriptions régulières</h5>
    </div>
    <div class="card-body">
        <?php if (empty($inscriptionsRegulieres)): ?>
            <div class="alert alert-info">
                Aucune inscription régulière trouvée.
                <?php if (!isAdmin() && !isPersonnel()): ?>
                    Les inscriptions régulières sont gérées par l'équipe de la crèche.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Enfant</th>
                            <th>Jour</th>
                            <th>Horaire</th>
                            <th>Groupe</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th>Statut</th>
                            <?php if (isAdmin() || isPersonnel()): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inscriptionsRegulieres as $inscription): ?>
                            <tr>
                                <td><?php echo escape($inscription['enfant_prenom'] . ' ' . $inscription['enfant_nom']); ?></td>
                                <td><?php echo traduireJour($inscription['jour_semaine']); ?></td>
                                <td><?php echo $inscription['heure_debut'] . ' - ' . $inscription['heure_fin']; ?></td>
                                <td><?php echo escape($inscription['groupe_nom']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($inscription['date_debut'])); ?></td>
                                <td>
                                    <?php 
                                    echo $inscription['date_fin'] 
                                        ? date('d/m/Y', strtotime($inscription['date_fin'])) 
                                        : 'Indéfinie';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'bg-secondary';
                                    switch ($inscription['statut']) {
                                        case 'active':
                                            $statusClass = 'bg-success';
                                            break;
                                        case 'inactive':
                                            $statusClass = 'bg-warning';
                                            break;
                                        case 'terminee':
                                            $statusClass = 'bg-secondary';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo traduireStatut($inscription['statut']); ?>
                                    </span>
                                </td>
                                <?php if (isAdmin() || isPersonnel()): ?>
                                    <td>
                                        <?php if ($inscription['statut'] !== 'terminee'): ?>
                                            <div class="btn-group btn-group-sm">
                                                <a href="index.php?page=edit-inscription-reguliere&id=<?php echo $inscription['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($inscription['statut'] === 'active'): ?>
                                                    <a href="index.php?page=desactiver-inscription&id=<?php echo $inscription['id']; ?>" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-pause"></i>
                                                    </a>
                                                <?php elseif ($inscription['statut'] === 'inactive'): ?>
                                                    <a href="index.php?page=activer-inscription&id=<?php echo $inscription['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="index.php?page=terminer-inscription&id=<?php echo $inscription['id']; ?>" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

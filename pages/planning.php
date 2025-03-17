<?php
// Vérifier que l'utilisateur est connecté et a les droits d'accès
if (!isAdmin() && !isPersonnel()) {
    addFlashMessage('error', 'Vous n\'avez pas les droits pour accéder à cette page.');
    redirect('index.php');
}

// Récupérer les groupes d'âge
$stmtGroupes = $db->query("SELECT id, nom FROM groupes_age ORDER BY age_min");
$groupesAge = $stmtGroupes->fetchAll();

// Filtres du planning
$semaine = isset($_GET['semaine']) ? $_GET['semaine'] : date('W');
$annee = isset($_GET['annee']) ? $_GET['annee'] : date('Y');
$groupeId = isset($_GET['groupe']) ? $_GET['groupe'] : ($groupesAge[0]['id'] ?? null);

// Obtenir la date de début et de fin de la semaine
$dateDebut = new DateTime();
$dateDebut->setISODate($annee, $semaine, 1); // 1 = lundi
$dateFin = clone $dateDebut;
$dateFin->modify('+6 days'); // jusqu'à dimanche

// Format pour l'affichage
$dateDebutStr = $dateDebut->format('d/m/Y');
$dateFinStr = $dateFin->format('d/m/Y');

// Fonction pour récupérer les inscriptions pour une journée et un groupe d'âge
function getInscriptionsJour($db, $date, $groupeId) {
    $jourSemaine = strtolower(date('l', strtotime($date)));
    $jourSemaineFr = [
        'monday' => 'lundi',
        'tuesday' => 'mardi',
        'wednesday' => 'mercredi',
        'thursday' => 'jeudi',
        'friday' => 'vendredi',
        'saturday' => 'samedi',
        'sunday' => 'dimanche'
    ];
    
    $jourSemaineFr = $jourSemaineFr[$jourSemaine];
    
    // Inscriptions régulières
    $stmtRegulier = $db->prepare("
        SELECT 
            e.id AS enfant_id,
            e.prenom AS enfant_prenom,
            e.nom AS enfant_nom,
            c.heure_debut,
            c.heure_fin,
            'regulier' AS type
        FROM inscriptions_regulieres ir
        JOIN enfants e ON ir.enfant_id = e.id
        JOIN creneaux c ON ir.creneau_id = c.id
        WHERE c.jour_semaine = :jour_semaine
        AND c.groupe_age_id = :groupe_id
        AND ir.date_debut <= :date
        AND (ir.date_fin >= :date OR ir.date_fin IS NULL)
        AND ir.statut = 'active'
    ");
    
    $stmtRegulier->execute([
        'jour_semaine' => $jourSemaineFr,
        'groupe_id' => $groupeId,
        'date' => $date
    ]);
    
    $inscriptionsRegulier = $stmtRegulier->fetchAll();
    
    // Inscriptions occasionnelles
    $stmtOccasionnel = $db->prepare("
        SELECT 
            e.id AS enfant_id,
            e.prenom AS enfant_prenom,
            e.nom AS enfant_nom,
            io.heure_debut,
            io.heure_fin,
            'occasionnel' AS type
        FROM inscriptions_occasionnelles io
        JOIN enfants e ON io.enfant_id = e.id
        WHERE io.date_garde = :date
        AND io.groupe_age_id = :groupe_id
        AND io.statut = 'confirmee'
    ");
    
    $stmtOccasionnel->execute([
        'date' => $date,
        'groupe_id' => $groupeId
    ]);
    
    $inscriptionsOccasionnel = $stmtOccasionnel->fetchAll();
    
    // Combiner les deux types d'inscriptions
    return array_merge($inscriptionsRegulier, $inscriptionsOccasionnel);
}

// Générer les dates de la semaine (du lundi au dimanche)
$datesCourantes = [];
$joursCourrants = [];
$currentDate = clone $dateDebut;

for ($i = 0; $i < 7; $i++) {
    $datesCourantes[] = $currentDate->format('Y-m-d');
    $joursCourrants[] = [
        'numero' => $currentDate->format('d'),
        'nom' => ucfirst(strftime('%A', $currentDate->getTimestamp())),
        'complet' => $currentDate->format('Y-m-d')
    ];
    $currentDate->modify('+1 day');
}

// Récupérer le nom du groupe sélectionné
$nomGroupe = '';
if ($groupeId) {
    $stmtNomGroupe = $db->prepare("SELECT nom FROM groupes_age WHERE id = :id");
    $stmtNomGroupe->execute(['id' => $groupeId]);
    $nomGroupe = $stmtNomGroupe->fetchColumn();
}

// Récupérer les créneaux horaires pour ce groupe d'âge
$stmtCreneaux = $db->prepare("
    SELECT id, jour_semaine, heure_debut, heure_fin, places_disponibles
    FROM creneaux
    WHERE groupe_age_id = :groupe_id
    ORDER BY jour_semaine, heure_debut
");

$stmtCreneaux->execute(['groupe_id' => $groupeId]);
$creneaux = $stmtCreneaux->fetchAll();

// Organiser les créneaux par jour
$creneauxParJour = [];
foreach ($creneaux as $creneau) {
    $jour = $creneau['jour_semaine'];
    if (!isset($creneauxParJour[$jour])) {
        $creneauxParJour[$jour] = [];
    }
    $creneauxParJour[$jour][] = $creneau;
}

// Heures d'ouverture et de fermeture
$heureDebut = "08:00";
$heureFin = "18:30";
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>Planning des inscriptions</h1>
        <p>Consultez et gérez les inscriptions régulières et occasionnelles des enfants.</p>
    </div>
    <div class="col-md-4 text-end">
        <?php if (isAdmin()): ?>
            <a href="index.php?page=creer-creneaux" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Définir les créneaux
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header bg-white">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0">Planning du <?php echo $dateDebutStr; ?> au <?php echo $dateFinStr; ?></h5>
                <p class="mb-0 text-muted">Groupe : <?php echo escape($nomGroupe); ?></p>
            </div>
            <div class="col-md-6">
                <form action="index.php" method="GET" class="row g-2">
                    <input type="hidden" name="page" value="planning">
                    <div class="col-md-4">
                        <select name="semaine" class="form-select">
                            <?php for ($i = 1; $i <= 52; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $semaine == $i ? 'selected' : ''; ?>>
                                    Semaine <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="annee" class="form-select">
                            <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $annee == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="groupe" class="form-select">
                            <?php foreach ($groupesAge as $groupe): ?>
                                <option value="<?php echo $groupe['id']; ?>" <?php echo $groupeId == $groupe['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($groupe['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered planning-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 100px;">Horaire</th>
                        <?php foreach ($joursCourrants as $jour): ?>
                            <th>
                                <?php echo escape($jour['nom']); ?> <?php echo escape($jour['numero']); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Définir les intervalles de temps (par exemple, toutes les 30 minutes)
                    $interval = 30; // minutes
                    $time = strtotime($heureDebut);
                    $endTime = strtotime($heureFin);
                    
                    while ($time < $endTime) {
                        $timeStr = date('H:i', $time);
                        $nextTimeStr = date('H:i', $time + $interval * 60);
                        echo "<tr>";
                        echo "<td class='text-center'>{$timeStr}<br>-<br>{$nextTimeStr}</td>";
                        
                        foreach ($datesCourantes as $index => $dateCourante) {
                            $inscriptions = getInscriptionsJour($db, $dateCourante, $groupeId);
                            $cellContent = "";
                            
                            // Filtrer les inscriptions pour cet intervalle de temps
                            $inscriptionsIntervalle = array_filter($inscriptions, function($inscription) use ($timeStr, $nextTimeStr) {
                                return ($inscription['heure_debut'] <= $nextTimeStr && $inscription['heure_fin'] >= $timeStr);
                            });
                            
                            if (!empty($inscriptionsIntervalle)) {
                                foreach ($inscriptionsIntervalle as $inscription) {
                                    $typeClass = $inscription['type'] === 'regulier' ? 'bg-success' : 'bg-warning';
                                    $typeLabel = $inscription['type'] === 'regulier' ? 'R' : 'O';
                                    
                                    $cellContent .= "<div class='inscription-item {$typeClass} text-white mb-1 p-1 rounded'>";
                                    $cellContent .= "<span class='badge bg-dark me-1'>{$typeLabel}</span>";
                                    $cellContent .= escape($inscription['enfant_prenom']) . ' ' . substr(escape($inscription['enfant_nom']), 0, 1) . '.';
                                    $cellContent .= "</div>";
                                }
                            }
                            
                            echo "<td>{$cellContent}</td>";
                        }
                        
                        echo "</tr>";
                        $time += $interval * 60;
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            <div class="d-flex">
                <div class="me-3">
                    <span class="badge bg-success">&nbsp;</span> Inscription régulière
                </div>
                <div>
                    <span class="badge bg-warning">&nbsp;</span> Inscription occasionnelle
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white">
        <div class="row">
            <div class="col-md-6">
                <button class="btn btn-outline-primary me-2" onclick="window.location.href='index.php?page=planning&semaine=<?php echo $semaine-1; ?>&annee=<?php echo $annee; ?>&groupe=<?php echo $groupeId; ?>'">
                    <i class="fas fa-chevron-left"></i> Semaine précédente
                </button>
                <button class="btn btn-outline-secondary" onclick="window.location.href='index.php?page=planning&semaine=<?php echo date('W'); ?>&annee=<?php echo date('Y'); ?>&groupe=<?php echo $groupeId; ?>'">
                    Semaine actuelle
                </button>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-outline-primary" onclick="window.location.href='index.php?page=planning&semaine=<?php echo $semaine+1; ?>&annee=<?php echo $annee; ?>&groupe=<?php echo $groupeId; ?>'">
                    Semaine suivante <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Gestion des inscriptions occasionnelles en attente</h5>
    </div>
    <div class="card-body">
        <?php
        // Récupérer les demandes d'inscriptions occasionnelles en attente
        $stmtDemandes = $db->prepare("
            SELECT 
                io.id,
                e.prenom AS enfant_prenom,
                e.nom AS enfant_nom,
                io.date_garde,
                io.heure_debut,
                io.heure_fin,
                ga.nom AS groupe_nom,
                u.prenom AS parent_prenom,
                u.nom AS parent_nom,
                u.email AS parent_email,
                io.created_at
            FROM inscriptions_occasionnelles io
            JOIN enfants e ON io.enfant_id = e.id
            JOIN groupes_age ga ON io.groupe_age_id = ga.id
            JOIN parents_enfants pe ON e.id = pe.enfant_id
            JOIN users u ON pe.parent_id = u.id
            WHERE io.statut = 'demande'
            ORDER BY io.date_garde ASC, io.heure_debut ASC
        ");
        $stmtDemandes->execute();
        $demandes = $stmtDemandes->fetchAll();
        
        if (empty($demandes)) {
            echo '<div class="alert alert-info">Aucune demande d\'inscription occasionnelle en attente.</div>';
        } else {
        ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Enfant</th>
                        <th>Date</th>
                        <th>Horaire</th>
                        <th>Groupe</th>
                        <th>Parent</th>
                        <th>Demande le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($demandes as $demande): ?>
                    <tr>
                        <td><?php echo escape($demande['enfant_prenom']) . ' ' . escape($demande['enfant_nom']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($demande['date_garde'])); ?></td>
                        <td><?php echo $demande['heure_debut'] . ' - ' . $demande['heure_fin']; ?></td>
                        <td><?php echo escape($demande['groupe_nom']); ?></td>
                        <td>
                            <?php echo escape($demande['parent_prenom']) . ' ' . escape($demande['parent_nom']); ?><br>
                            <small><?php echo escape($demande['parent_email']); ?></small>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($demande['created_at'])); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="index.php?page=confirmer-inscription&id=<?php echo $demande['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i> Accepter
                                </a>
                                <a href="index.php?page=refuser-inscription&id=<?php echo $demande['id']; ?>" class="btn btn-sm btn-danger">
                                    <i class="fas fa-times"></i> Refuser
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>
</div>
<?php endif; ?>

<style>
.planning-table th, .planning-table td {
    text-align: center;
    vertical-align: middle;
    font-size: 0.85rem;
}
.planning-table td {
    height: 80px;
    padding: 5px;
}
.inscription-item {
    font-size: 0.8rem;
    text-align: left;
}
</style>

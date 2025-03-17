<?php
// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: index.php?page=login');
    exit;
}

// Récupérer les informations de l'utilisateur connecté
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Statistiques à afficher sur le tableau de bord
$stats = [
    'nb_enfants' => 0,
    'inscriptions_today' => 0,
    'inscriptions_week' => 0,
    'nb_places_dispo' => 0
];

try {
    global $db;
    
    // Pour les parents: nombre d'enfants enregistrés
    if ($userRole === 'parent') {
        $query = "SELECT COUNT(*) FROM enfants e 
                  JOIN parents_enfants pe ON e.id = pe.enfant_id
                  WHERE pe.parent_id = :parent_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['parent_id' => $userId]);
        $stats['nb_enfants'] = $stmt->fetchColumn();
        
        // Inscriptions du jour pour les enfants du parent
        $query = "SELECT COUNT(*) FROM inscriptions_occasionnelles io
                  JOIN enfants e ON io.enfant_id = e.id
                  JOIN parents_enfants pe ON e.id = pe.enfant_id
                  WHERE pe.parent_id = :parent_id AND DATE(io.date_garde) = CURDATE()";
        $stmt = $db->prepare($query);
        $stmt->execute(['parent_id' => $userId]);
        $stats['inscriptions_today'] = $stmt->fetchColumn();
        
        // Inscriptions de la semaine pour les enfants du parent
        $query = "SELECT COUNT(*) FROM inscriptions_occasionnelles io
                  JOIN enfants e ON io.enfant_id = e.id
                  JOIN parents_enfants pe ON e.id = pe.enfant_id
                  WHERE pe.parent_id = :parent_id 
                  AND DATE(io.date_garde) BETWEEN DATE(NOW()) AND DATE(NOW() + INTERVAL 7 DAY)";
        $stmt = $db->prepare($query);
        $stmt->execute(['parent_id' => $userId]);
        $stats['inscriptions_week'] = $stmt->fetchColumn();
        
        // Places disponibles par groupe d'âge (pour la semaine à venir)
        $query = "SELECT SUM(ga.capacite - IFNULL(io_count.nb_inscrits, 0)) as total_places
                  FROM groupes_age ga
                  LEFT JOIN (
                      SELECT io.groupe_age_id, COUNT(*) as nb_inscrits
                      FROM inscriptions_occasionnelles io
                      WHERE DATE(io.date_garde) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                      GROUP BY io.groupe_age_id, DATE(io.date_garde)
                  ) io_count ON ga.id = io_count.groupe_age_id";
        $stmt = $db->query($query);
        $stats['nb_places_dispo'] = $stmt->fetchColumn() ?: 0;
    } 
    // Pour le personnel/admin: statistiques globales
    else {
        // Nombre total d'enfants
        $query = "SELECT COUNT(*) FROM enfants";
        $stmt = $db->query($query);
        $stats['nb_enfants'] = $stmt->fetchColumn();
        
        // Inscriptions du jour
        $query = "SELECT COUNT(*) FROM inscriptions_occasionnelles 
                  WHERE DATE(date_garde) = CURDATE()";
        $stmt = $db->query($query);
        $stats['inscriptions_today'] = $stmt->fetchColumn();
        
        // Inscriptions de la semaine
        $query = "SELECT COUNT(*) FROM inscriptions_occasionnelles 
                  WHERE DATE(date_garde) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $stmt = $db->query($query);
        $stats['inscriptions_week'] = $stmt->fetchColumn();
        
        // Places disponibles totales
        $query = "SELECT SUM(capacite) FROM groupes_age";
        $stmt = $db->query($query);
        $total_capacity = $stmt->fetchColumn() ?: 0;
        
        $query = "SELECT COUNT(*) FROM inscriptions_occasionnelles 
                  WHERE DATE(date_garde) = CURDATE()";
        $stmt = $db->query($query);
        $occupied_today = $stmt->fetchColumn() ?: 0;
        
        $stats['nb_places_dispo'] = $total_capacity - $occupied_today;
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
}

// Liste des prochaines inscriptions pour les parents
$upcomingInscriptions = [];
if ($userRole === 'parent') {
    try {
        $query = "SELECT io.id, e.prenom, e.nom, io.date_garde, io.heure_debut, io.heure_fin, ga.nom as groupe_nom
                  FROM inscriptions_occasionnelles io
                  JOIN enfants e ON io.enfant_id = e.id
                  JOIN groupes_age ga ON io.groupe_age_id = ga.id
                  JOIN parents_enfants pe ON e.id = pe.enfant_id
                  WHERE pe.parent_id = :parent_id
                  AND io.date_garde >= CURDATE()
                  ORDER BY io.date_garde, io.heure_debut
                  LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute(['parent_id' => $userId]);
        $upcomingInscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des inscriptions: " . $e->getMessage());
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="mb-3">Tableau de bord</h1>
        <p class="lead">Bienvenue, <?php echo escape($_SESSION['user_name']); ?> !</p>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card bg-primary text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stats-icon bg-white text-primary">
                    <i class="fas fa-child fa-2x"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo $stats['nb_enfants']; ?></h3>
                    <p class="mb-0">Enfants</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card bg-success text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stats-icon bg-white text-success">
                    <i class="fas fa-calendar-day fa-2x"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo $stats['inscriptions_today']; ?></h3>
                    <p class="mb-0">Inscriptions aujourd'hui</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card bg-info text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stats-icon bg-white text-info">
                    <i class="fas fa-calendar-week fa-2x"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo $stats['inscriptions_week']; ?></h3>
                    <p class="mb-0">Inscriptions cette semaine</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card bg-warning text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stats-icon bg-white text-warning">
                    <i class="fas fa-door-open fa-2x"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo $stats['nb_places_dispo']; ?></h3>
                    <p class="mb-0">Places disponibles</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Actions rapides</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-3">
                    <?php if ($userRole === 'parent'): ?>
                        <a href="index.php?page=inscriptions" class="btn btn-primary">
                            <i class="fas fa-calendar-plus me-2"></i>Nouvelle inscription
                        </a>
                        <a href="index.php?page=enfants" class="btn btn-outline-primary">
                            <i class="fas fa-baby me-2"></i>Gérer mes enfants
                        </a>
                    <?php else: ?>
                        <a href="index.php?page=planning" class="btn btn-primary">
                            <i class="fas fa-calendar-alt me-2"></i>Voir le planning
                        </a>
                        <a href="index.php?page=enfants" class="btn btn-outline-primary">
                            <i class="fas fa-users me-2"></i>Gérer les enfants
                        </a>
                    <?php endif; ?>
                    <a href="index.php?page=profil" class="btn btn-outline-secondary">
                        <i class="fas fa-user-cog me-2"></i>Mon profil
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($userRole === 'parent' && !empty($upcomingInscriptions)): ?>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Prochaines inscriptions</h5>
                <a href="index.php?page=inscriptions" class="btn btn-sm btn-outline-primary">Tout voir</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($upcomingInscriptions as $inscription): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo escape($inscription['prenom']); ?></h6>
                                <small>
                                    <?php 
                                    $date = new DateTime($inscription['date_garde']);
                                    echo $date->format('d/m/Y'); 
                                    ?>
                                </small>
                            </div>
                            <p class="mb-1">
                                <small>
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo substr($inscription['heure_debut'], 0, 5) . ' - ' . substr($inscription['heure_fin'], 0, 5); ?>
                                </small>
                            </p>
                            <small class="text-muted">Groupe: <?php echo escape($inscription['groupe_nom']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($userRole !== 'parent'): ?>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Activité récente</h5>
            </div>
            <div class="card-body">
                <!-- Ici, on pourrait afficher les dernières inscriptions, modifications, etc. -->
                <p class="text-center text-muted my-5">
                    <i class="fas fa-info-circle me-2"></i>
                    Les activités récentes seront disponibles prochainement.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($userRole === 'admin' || $userRole === 'personnel'): ?>
<!-- Graphique d'occupation (pour le personnel et les admins seulement) -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Taux d'occupation par semaine</h5>
            </div>
            <div class="card-body">
                <!-- Ici, on pourrait intégrer un graphique avec Chart.js -->
                <p class="text-center text-muted my-5">
                    <i class="fas fa-chart-bar me-2"></i>
                    Les statistiques d'occupation seront disponibles prochainement.
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

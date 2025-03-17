<?php
/**
 * Fichier contenant les fonctions utilitaires de l'application
 */

/**
 * Échappe les données pour éviter les injections XSS
 * @param string $data Données à nettoyer
 * @return string Données nettoyées
 */
function escape($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Vérifie si le formulaire a été soumis
 * @param string $method Méthode HTTP (POST, GET)
 * @return bool True si le formulaire a été soumis, false sinon
 */
function isFormSubmitted($method = 'POST') {
    return $_SERVER['REQUEST_METHOD'] === $method;
}

/**
 * Redirige vers une URL
 * @param string $url URL de redirection
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Ajoute un message flash dans la session
 * @param string $type Type de message (success, error, warning, info)
 * @param string $message Contenu du message
 */
function addFlashMessage($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Ajoute un message flash dans la session (alias de addFlashMessage pour la compatibilité)
 * @param string $type Type de message (success, error, warning, info)
 * @param string $message Contenu du message
 */
function setFlashMessage($type, $message) {
    addFlashMessage($type, $message);
}

/**
 * Affiche les messages flash et les supprime de la session
 */
function displayFlashMessages() {
    if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $flash) {
            echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">';
            echo $flash['message'];
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        // Supprimer les messages après les avoir affichés
        unset($_SESSION['flash_messages']);
    }
}

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool True si l'utilisateur est connecté, false sinon
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur a le rôle spécifié
 * @param string $role Rôle à vérifier
 * @return bool True si l'utilisateur a le rôle, false sinon
 */
function hasRole($role) {
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

/**
 * Vérifie si l'utilisateur est un administrateur
 * @return bool True si l'utilisateur est un administrateur, false sinon
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Vérifie si l'utilisateur est un membre du personnel
 * @return bool True si l'utilisateur est un membre du personnel, false sinon
 */
function isPersonnel() {
    return hasRole('personnel');
}

/**
 * Vérifie si l'utilisateur est un parent
 * @return bool True si l'utilisateur est un parent, false sinon
 */
function isParent() {
    return hasRole('parent');
}

/**
 * Calcule l'âge en mois d'un enfant à partir de sa date de naissance
 * @param string $dateNaissance Date de naissance au format YYYY-MM-DD
 * @return int Âge en mois
 */
function calculerAgeMois($dateNaissance) {
    $naissance = new DateTime($dateNaissance);
    $aujourdhui = new DateTime();
    $diff = $naissance->diff($aujourdhui);
    return ($diff->y * 12) + $diff->m;
}

/**
 * Vérifie la disponibilité d'un créneau pour une inscription occasionnelle
 * @param PDO $db Instance de connexion à la base de données
 * @param string $date Date du créneau au format YYYY-MM-DD
 * @param string $heureDebut Heure de début au format HH:MM
 * @param string $heureFin Heure de fin au format HH:MM
 * @param int $groupeAgeId ID du groupe d'âge
 * @return bool True si le créneau est disponible, false sinon
 */
function verifierDisponibiliteCreneau($db, $date, $heureDebut, $heureFin, $groupeAgeId) {
    // Récupérer la capacité maximale du groupe d'âge
    $stmtCapacite = $db->prepare("SELECT capacite FROM groupes_age WHERE id = :id");
    $stmtCapacite->execute(['id' => $groupeAgeId]);
    $capacite = $stmtCapacite->fetchColumn();
    
    if (!$capacite) {
        return false; // Groupe d'âge non trouvé
    }
    
    // Compter le nombre d'inscriptions régulières pour ce créneau et ce jour de la semaine
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
    
    $stmtRegulier = $db->prepare("
        SELECT COUNT(*) FROM inscriptions_regulieres ir
        JOIN creneaux c ON ir.creneau_id = c.id
        WHERE c.jour_semaine = :jour_semaine
        AND c.groupe_age_id = :groupe_age_id
        AND c.heure_debut <= :heure_fin
        AND c.heure_fin >= :heure_debut
        AND ir.date_debut <= :date
        AND (ir.date_fin >= :date OR ir.date_fin IS NULL)
        AND ir.statut = 'active'
    ");
    
    $stmtRegulier->execute([
        'jour_semaine' => $jourSemaineFr,
        'groupe_age_id' => $groupeAgeId,
        'heure_debut' => $heureDebut,
        'heure_fin' => $heureFin,
        'date' => $date
    ]);
    
    $nbRegulier = $stmtRegulier->fetchColumn();
    
    // Compter le nombre d'inscriptions occasionnelles pour ce créneau et cette date
    $stmtOccasionnel = $db->prepare("
        SELECT COUNT(*) FROM inscriptions_occasionnelles
        WHERE date_garde = :date
        AND groupe_age_id = :groupe_age_id
        AND heure_debut <= :heure_fin
        AND heure_fin >= :heure_debut
        AND statut = 'confirmee'
    ");
    
    $stmtOccasionnel->execute([
        'date' => $date,
        'groupe_age_id' => $groupeAgeId,
        'heure_debut' => $heureDebut,
        'heure_fin' => $heureFin
    ]);
    
    $nbOccasionnel = $stmtOccasionnel->fetchColumn();
    
    // Vérifier si le nombre total d'inscriptions est inférieur à la capacité
    return ($nbRegulier + $nbOccasionnel) < $capacite;
}

/**
 * Obtient la liste des créneaux disponibles pour une date et un groupe d'âge donnés
 * @param PDO $db Instance de connexion à la base de données
 * @param string $date Date au format YYYY-MM-DD
 * @param int $groupeAgeId ID du groupe d'âge
 * @return array Liste des créneaux disponibles
 */
function getCreneauxDisponibles($db, $date, $groupeAgeId) {
    // Déterminer le jour de la semaine
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
    
    // Récupérer les créneaux pour ce jour de la semaine et ce groupe d'âge
    $stmt = $db->prepare("
        SELECT id, heure_debut, heure_fin, places_disponibles
        FROM creneaux
        WHERE jour_semaine = :jour_semaine
        AND groupe_age_id = :groupe_age_id
    ");
    
    $stmt->execute([
        'jour_semaine' => $jourSemaineFr,
        'groupe_age_id' => $groupeAgeId
    ]);
    
    $creneaux = $stmt->fetchAll();
    $creneauxDisponibles = [];
    
    // Pour chaque créneau, vérifier la disponibilité
    foreach ($creneaux as $creneau) {
        if (verifierDisponibiliteCreneau($db, $date, $creneau['heure_debut'], $creneau['heure_fin'], $groupeAgeId)) {
            $creneauxDisponibles[] = $creneau;
        }
    }
    
    return $creneauxDisponibles;
}
?>
